<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend CSS Scanner — fetches rendered HTML from the public site, parses
 * linked stylesheets and inline <style> blocks, extracts real selectors and
 * property values, then groups them into editable categories.
 *
 * This engine never scans admin pages, never fetches external domains (except
 * same-host), and stores results in a WP option with autoload=no.
 */
final class MLCSSP_Scanner {

    /** Option key for persisted scan results. */
    public const OPTION_KEY = 'mlcsspro_scan_results';

    /** Option key for scan metadata (time, counts). */
    public const META_KEY = 'mlcsspro_scan_meta';

    /** Option key for user-applied overrides from detected values. */
    public const OVERRIDES_KEY = 'mlcsspro_overrides';

    /** Maximum number of CSS files to fetch per scan. */
    private const MAX_FILES = 30;

    /** Maximum CSS text length per file (512 KB). */
    private const MAX_CSS_LENGTH = 524288;

    /** Maximum stored results (selectors × properties). */
    private const MAX_RESULTS = 600;

    /** Editable CSS properties to extract. */
    private const PROPERTIES = [
        'color', 'background-color', 'background', 'background-image',
        'background-size', 'background-position', 'background-repeat', 'background-attachment',
        'font-family', 'font-size', 'font-weight', 'font-style', 'line-height',
        'text-transform', 'text-align', 'text-decoration', 'text-shadow',
        'letter-spacing', 'word-spacing',
        'border-radius', 'border-color', 'border', 'border-width', 'border-style',
        'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
        'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'width', 'max-width', 'min-width', 'height', 'max-height', 'min-height',
        'position', 'top', 'right', 'bottom', 'left', 'z-index',
        'box-shadow', 'transform', 'filter',
        'opacity', 'display', 'visibility', 'overflow', 'cursor', 'pointer-events',
    ];

    /** Category → selector patterns (substring match against the selector). */
    private const CATEGORY_MAP = [
        'body'      => ['body', 'html'],
        'headings'  => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', '.u-title', '.u-heading'],
        'paragraphs'=> ['p ', 'p,', 'p{', 'p.', '.u-text'],
        'links'     => [' a ', ' a,', ' a{', ' a:', ' a.', '.u-link', 'a:hover', 'a:focus'],
        'buttons'   => ['button', '.btn', '.u-btn', 'input[type="submit"]', 'input[type="button"]', '.wp-block-button', '.h-button'],
        'menus'     => ['nav', '.menu', '.u-menu', '.u-nav', '.navigation', '.h-navigation'],
        'header'    => ['header', '.u-header', '.site-header', '.h-header'],
        'footer'    => ['footer', '.u-footer', '.site-footer'],
        'sections'  => ['section', '.u-section', 'u-section-', '.hero', '.h-section'],
        'cards'     => ['.card', '.u-container-style', '.u-container-layout', '.u-group', '.u-list-item'],
        'forms'     => ['form', 'input', 'textarea', 'select', '.u-input', '.u-form'],
        'nicepage'  => ['.u-sheet', '.u-layout', '.u-image', '.u-block', '.u-body'],
    ];

    /** Human labels for each category. */
    public const CATEGORY_LABELS = [
        'body'       => 'Corpo / body',
        'headings'   => 'Titulos',
        'paragraphs' => 'Paragrafos',
        'links'      => 'Links',
        'buttons'    => 'Botoes',
        'menus'      => 'Menus / nav',
        'header'     => 'Header',
        'footer'     => 'Rodape',
        'sections'   => 'Secoes',
        'cards'      => 'Cards / containers',
        'forms'      => 'Formularios',
        'nicepage'   => 'Nicepage',
    ];

    /**
     * Run a full scan of the given URL (defaults to site home).
     *
     * @return array{ok:bool,message:string,meta:array}
     */
    public function scan(string $url = ''): array {
        $url = $this->resolve_url($url);
        if ($url === '') {
            return ['ok' => false, 'message' => 'URL invalida ou fora do dominio.', 'meta' => []];
        }

        $html = $this->fetch($url);
        if ($html === '') {
            return ['ok' => false, 'message' => 'Nao foi possivel obter o HTML da pagina.', 'meta' => []];
        }

        $css_sources = $this->extract_css_sources($html, $url);
        $all_rules   = [];
        $file_count  = 0;

        foreach ($css_sources as $source) {
            $css_text = '';
            if ($source['type'] === 'inline') {
                $css_text = $source['content'];
            } else {
                $css_text = $this->fetch($source['url']);
            }
            if ($css_text === '') {
                continue;
            }
            $css_text = $this->safe_truncate($css_text, self::MAX_CSS_LENGTH);
            $rules = $this->parse_css($css_text, $source['label']);
            $all_rules = array_merge($all_rules, $rules);
            $file_count++;
        }

        // Deduplicate + categorize.
        $results = $this->categorize($all_rules);

        // Limit size.
        $results = array_slice($results, 0, self::MAX_RESULTS);

        $meta = [
            'scanned_at'   => current_time('mysql'),
            'url'          => $url,
            'files'        => $file_count,
            'selectors'    => count(array_unique(array_column($results, 'selector'))),
            'properties'   => count($results),
        ];

        update_option(self::OPTION_KEY, $results, false);
        update_option(self::META_KEY, $meta, false);

        return [
            'ok'      => true,
            'message' => sprintf('Varredura concluida: %d arquivos, %d seletores, %d propriedades.', $meta['files'], $meta['selectors'], $meta['properties']),
            'meta'    => $meta,
        ];
    }

    /** Retrieve stored scan results. */
    public function get_results(): array {
        $data = get_option(self::OPTION_KEY, []);
        return is_array($data) ? $data : [];
    }

    /** Retrieve stored scan metadata. */
    public function get_meta(): array {
        $data = get_option(self::META_KEY, []);
        return is_array($data) ? $data : [];
    }

    /** Filter results by category. */
    public function get_by_category(string $category): array {
        return array_values(array_filter($this->get_results(), fn(array $r) => ($r['group'] ?? '') === $category));
    }

    /** Clear stored scan data. */
    public function clear(): void {
        delete_option(self::OPTION_KEY);
        delete_option(self::META_KEY);
    }

    // --- Overrides -----------------------------------------------------------

    /** Get saved overrides (selector → property → value). */
    public static function get_overrides(): array {
        $data = get_option(self::OVERRIDES_KEY, []);
        return is_array($data) ? $data : [];
    }

    /** Save a single override. Returns ['saved'=>bool, 'overrides'=>array]. */
    public static function save_override(string $selector, string $property, string $value): array {
        $overrides = self::get_overrides();
        $selector  = self::sanitize_selector($selector);
        $property  = self::sanitize_property($property);
        $value     = self::normalize_css_value($value);
        $value     = MLCSSP_CSS_Engine::sanitize_css($value);
        if ($selector === '' || $property === '' || $value === '') {
            return ['saved' => false, 'overrides' => $overrides, 'reason' => $selector === '' ? 'selector vazio' : ($property === '' ? 'propriedade nao permitida' : 'valor vazio/inseguro')];
        }
        if (!isset($overrides[$selector])) {
            $overrides[$selector] = [];
        }
        $overrides[$selector][$property] = $value;
        update_option(self::OVERRIDES_KEY, $overrides, false);
        return ['saved' => true, 'overrides' => $overrides, 'reason' => ''];
    }

    /** Normalize CSS value: fix decimal comma, trim, strip dangerous content. */
    private static function normalize_css_value(string $val): string {
        $val = trim($val);
        // Fix decimal comma from certain locales: 27,9px → 27.9px
        $val = preg_replace('/(\d),(\d)/', '$1.$2', $val) ?? $val;
        // Remove double semicolons or trailing semicolons.
        $val = rtrim($val, ';');
        return $val;
    }

    /** Remove a single override. */
    public static function remove_override(string $selector, string $property): array {
        $overrides = self::get_overrides();
        $selector  = self::sanitize_selector($selector);
        $property  = self::sanitize_property($property);
        unset($overrides[$selector][$property]);
        if (isset($overrides[$selector]) && $overrides[$selector] === []) {
            unset($overrides[$selector]);
        }
        update_option(self::OVERRIDES_KEY, $overrides, false);
        return $overrides;
    }

    /** Clear all overrides. */
    public static function clear_overrides(): void {
        delete_option(self::OVERRIDES_KEY);
    }

    /** Build override CSS text ready for compilation. */
    public static function build_override_css(): string {
        $overrides = self::get_overrides();
        $blocks = [];
        foreach ($overrides as $selector => $props) {
            $declarations = [];
            foreach ($props as $property => $value) {
                $v = trim((string) $value);
                if ($v === '') {
                    continue;
                }
                if (!str_contains($v, '!important')) {
                    $v .= ' !important';
                }
                $declarations[] = '  ' . $property . ': ' . $v . ';';
            }
            if ($declarations !== []) {
                // Wrap with `body` prefix for stronger specificity unless
                // the selector already starts with body or html.
                $safe_sel = self::public_safe_selector($selector);
                $blocks[] = $safe_sel . " {\n" . implode("\n", $declarations) . "\n}";
            }
        }
        $desktop = $blocks !== [] ? "/* ML CSS Pro: overrides */\n" . implode("\n\n", $blocks) : '';

        // Append responsive (tablet/mobile) media queries — additive, never
        // alters the desktop output above.
        $responsive = self::build_responsive_css();

        // Append page-specific overrides (global + responsive per page) — additive.
        $page_specific = self::build_page_css();

        $out = trim($desktop . "\n\n" . $responsive . "\n\n" . $page_specific);
        return $out;
    }

    // --- Responsive overrides (v1.5.0) --------------------------------------

    /** Option key for device-specific (tablet/mobile) overrides. */
    public const RESPONSIVE_KEY = 'mlcsspro_overrides_responsive';

    /** Media query breakpoints per device. */
    public const BREAKPOINTS = [
        'tablet' => '(max-width:1024px)',
        'mobile' => '(max-width:767px)',
    ];

    /** Get responsive overrides: ['tablet'=>[sel=>[prop=>val]], 'mobile'=>[...]]. */
    public static function get_responsive_overrides(): array {
        $data = get_option(self::RESPONSIVE_KEY, []);
        if (!is_array($data)) {
            $data = [];
        }
        return [
            'tablet' => isset($data['tablet']) && is_array($data['tablet']) ? $data['tablet'] : [],
            'mobile' => isset($data['mobile']) && is_array($data['mobile']) ? $data['mobile'] : [],
        ];
    }

    /** Save a device-specific override. Device: desktop|tablet|mobile. */
    public static function save_override_device(string $device, string $selector, string $property, string $value): array {
        $device = in_array($device, ['desktop', 'tablet', 'mobile'], true) ? $device : 'desktop';
        if ($device === 'desktop') {
            return self::save_override($selector, $property, $value);
        }
        $all = self::get_responsive_overrides();
        $selector = self::sanitize_selector($selector);
        $property = self::sanitize_property($property);
        $value    = self::normalize_css_value($value);
        $value    = MLCSSP_CSS_Engine::sanitize_css($value);
        if ($selector === '' || $property === '' || $value === '') {
            return ['saved' => false, 'reason' => $selector === '' ? 'selector vazio' : ($property === '' ? 'propriedade nao permitida' : 'valor vazio/inseguro')];
        }
        if (!isset($all[$device][$selector])) {
            $all[$device][$selector] = [];
        }
        $all[$device][$selector][$property] = $value;
        update_option(self::RESPONSIVE_KEY, $all, false);
        return ['saved' => true, 'reason' => ''];
    }

    /** Remove a device-specific override (or all for a device with property='__all__'). */
    public static function remove_override_device(string $device, string $selector = '', string $property = ''): void {
        if ($device === 'desktop') {
            if ($property === '__all__') {
                $o = self::get_overrides();
                unset($o[$selector], $o[self::public_safe_selector($selector)]);
                update_option(self::OVERRIDES_KEY, $o, false);
            } elseif ($selector !== '' && $property !== '') {
                self::remove_override($selector, $property);
            } else {
                self::clear_overrides();
            }
            return;
        }
        $all = self::get_responsive_overrides();
        if ($selector === '') {
            // Reset entire device.
            $all[$device] = [];
        } elseif ($property === '__all__') {
            unset($all[$device][$selector]);
        } else {
            unset($all[$device][$selector][$property]);
            if (isset($all[$device][$selector]) && $all[$device][$selector] === []) {
                unset($all[$device][$selector]);
            }
        }
        update_option(self::RESPONSIVE_KEY, $all, false);
    }

    /** Count overrides per device. */
    public static function count_overrides_by_device(): array {
        $count = static function (array $sels): int {
            $n = 0;
            foreach ($sels as $props) { $n += is_array($props) ? count($props) : 0; }
            return $n;
        };
        $resp = self::get_responsive_overrides();
        return [
            'desktop' => $count(self::get_overrides()),
            'tablet'  => $count($resp['tablet']),
            'mobile'  => $count($resp['mobile']),
        ];
    }

    /** Build the tablet/mobile media-query CSS. */
    public static function build_responsive_css(): string {
        $resp = self::get_responsive_overrides();
        $out = [];
        foreach (['tablet', 'mobile'] as $device) {
            $sels = $resp[$device];
            if ($sels === []) {
                continue;
            }
            $blocks = [];
            foreach ($sels as $selector => $props) {
                $declarations = [];
                foreach ($props as $property => $value) {
                    $v = trim((string) $value);
                    if ($v === '') {
                        continue;
                    }
                    if (!str_contains($v, '!important')) {
                        $v .= ' !important';
                    }
                    $declarations[] = '    ' . $property . ': ' . $v . ';';
                }
                if ($declarations !== []) {
                    $safe_sel = self::public_safe_selector($selector);
                    $blocks[] = '  ' . $safe_sel . " {\n" . implode("\n", $declarations) . "\n  }";
                }
            }
            if ($blocks !== []) {
                $mq = self::BREAKPOINTS[$device];
                $out[] = "/* ML CSS Pro: " . $device . " */\n@media " . $mq . " {\n" . implode("\n\n", $blocks) . "\n}";
            }
        }
        return implode("\n\n", $out);
    }

    /** Clear all responsive overrides. */
    public static function clear_responsive_overrides(): void {
        delete_option(self::RESPONSIVE_KEY);
    }

    // --- Page-specific overrides (v1.5.1) -----------------------------------

    /** Option key for page-specific overrides. */
    public const PAGE_KEY = 'mlcsspro_page_overrides';

    /**
     * Structure: [ page_key => [
     *   'url'=>..., 'title'=>..., 'body_class'=>...,
     *   'overrides' => [ device => [ selector => [ prop => val ] ] ]
     * ] ]
     */
    public static function get_page_overrides(): array {
        $data = get_option(self::PAGE_KEY, []);
        return is_array($data) ? $data : [];
    }

    /** Build the page body-class selector prefix, e.g. "body.page-id-123". */
    public static function page_body_selector(string $body_class): string {
        $body_class = trim($body_class);
        if ($body_class === '') {
            return '';
        }
        // Accept only safe class tokens like page-id-123 / postid-456.
        if (!preg_match('/^[a-z0-9_-]+$/i', $body_class)) {
            return '';
        }
        return 'body.' . $body_class;
    }

    /**
     * Save a page-specific override.
     *
     * @param string $page_key   Stable key (e.g. "page-id-123" or url hash).
     * @param array  $meta       ['url'=>, 'title'=>, 'body_class'=>]
     * @param string $device     desktop|tablet|mobile
     */
    public static function save_page_override(string $page_key, array $meta, string $device, string $selector, string $property, string $value): array {
        $page_key = sanitize_key($page_key);
        $device   = in_array($device, ['desktop', 'tablet', 'mobile'], true) ? $device : 'desktop';
        $selector = self::sanitize_selector($selector);
        $property = self::sanitize_property($property);
        $value    = self::normalize_css_value($value);
        $value    = MLCSSP_CSS_Engine::sanitize_css($value);
        if ($page_key === '' || $selector === '' || $property === '' || $value === '') {
            return ['saved' => false, 'reason' => 'dados invalidos'];
        }
        $all = self::get_page_overrides();
        if (!isset($all[$page_key])) {
            $all[$page_key] = ['url' => '', 'title' => '', 'body_class' => '', 'overrides' => []];
        }
        $all[$page_key]['url']        = esc_url_raw((string) ($meta['url'] ?? $all[$page_key]['url']));
        $all[$page_key]['title']      = sanitize_text_field((string) ($meta['title'] ?? $all[$page_key]['title']));
        $body_class = sanitize_html_class((string) ($meta['body_class'] ?? $all[$page_key]['body_class']));
        $all[$page_key]['body_class'] = $body_class;
        if (!isset($all[$page_key]['overrides'][$device])) {
            $all[$page_key]['overrides'][$device] = [];
        }
        if (!isset($all[$page_key]['overrides'][$device][$selector])) {
            $all[$page_key]['overrides'][$device][$selector] = [];
        }
        $all[$page_key]['overrides'][$device][$selector][$property] = $value;
        update_option(self::PAGE_KEY, $all, false);
        return ['saved' => true, 'reason' => ''];
    }

    /** Remove page-specific overrides. */
    public static function remove_page_override(string $page_key, string $device = '', string $selector = '', string $property = ''): void {
        $page_key = sanitize_key($page_key);
        $all = self::get_page_overrides();
        if (!isset($all[$page_key])) {
            return;
        }
        if ($selector === '') {
            // Reset entire page (all devices, all selectors).
            unset($all[$page_key]);
        } else {
            foreach (['desktop', 'tablet', 'mobile'] as $dev) {
                if ($device !== '' && $dev !== $device) {
                    continue;
                }
                if ($property === '' || $property === '__all__') {
                    unset($all[$page_key]['overrides'][$dev][$selector]);
                } else {
                    unset($all[$page_key]['overrides'][$dev][$selector][$property]);
                }
            }
        }
        update_option(self::PAGE_KEY, $all, false);
    }

    /** Count page-specific override properties (optionally for one page). */
    public static function count_page_overrides(string $page_key = ''): int {
        $all = self::get_page_overrides();
        $n = 0;
        foreach ($all as $key => $page) {
            if ($page_key !== '' && $key !== $page_key) {
                continue;
            }
            foreach (($page['overrides'] ?? []) as $sels) {
                foreach ($sels as $props) {
                    $n += is_array($props) ? count($props) : 0;
                }
            }
        }
        return $n;
    }

    /** Build page-specific CSS (desktop + tablet/mobile media queries). */
    public static function build_page_css(): string {
        $all = self::get_page_overrides();
        if ($all === []) {
            return '';
        }
        $out = [];
        foreach ($all as $page) {
            $body_class = (string) ($page['body_class'] ?? '');
            $prefix = self::page_body_selector($body_class);
            if ($prefix === '') {
                continue; // No safe page body class — skip (avoid global leak).
            }
            $title = (string) ($page['title'] ?? '');
            $overrides = (array) ($page['overrides'] ?? []);

            // Desktop page-specific.
            $desktop_blocks = self::page_blocks($overrides['desktop'] ?? [], $prefix, false);
            if ($desktop_blocks !== '') {
                $out[] = "/* ML CSS Pro: pagina " . $title . " (desktop) */\n" . $desktop_blocks;
            }
            // Tablet/mobile page-specific.
            foreach (['tablet', 'mobile'] as $device) {
                $blocks = self::page_blocks($overrides[$device] ?? [], $prefix, true);
                if ($blocks !== '') {
                    $mq = self::BREAKPOINTS[$device];
                    $out[] = "/* ML CSS Pro: pagina " . $title . " (" . $device . ") */\n@media " . $mq . " {\n" . $blocks . "\n}";
                }
            }
        }
        return implode("\n\n", $out);
    }

    /** Build CSS blocks for a set of page selectors, prefixed by page body class. */
    private static function page_blocks(array $sels, string $prefix, bool $indent): string {
        $blocks = [];
        $pad = $indent ? '    ' : '  ';
        $selpad = $indent ? '  ' : '';
        foreach ($sels as $selector => $props) {
            $declarations = [];
            foreach ($props as $property => $value) {
                $v = trim((string) $value);
                if ($v === '') {
                    continue;
                }
                if (!str_contains($v, '!important')) {
                    $v .= ' !important';
                }
                $declarations[] = $pad . $property . ': ' . $v . ';';
            }
            if ($declarations !== []) {
                // Combine page prefix with the public-safe selector.
                $safe = self::public_safe_selector($selector);
                // Strip a leading "body " from safe selector to merge with prefix.
                $safe_tail = preg_replace('/^body\s+/', '', $safe) ?? $safe;
                if ($safe_tail === 'body' || $safe === 'body') {
                    $full = $prefix;
                } else {
                    $full = $prefix . ' ' . $safe_tail;
                }
                $blocks[] = $selpad . $full . " {\n" . implode("\n", $declarations) . "\n" . $selpad . "}";
            }
        }
        return implode("\n\n", $blocks);
    }

    /** Clear all page-specific overrides. */
    public static function clear_page_overrides(): void {
        delete_option(self::PAGE_KEY);
    }

    /**
     * Ensure a selector is strong enough for public frontend and contains no
     * editor-only artifacts. Prepends `body` when needed for specificity.
     */
    public static function public_safe_selector(string $sel): string {
        $sel = trim($sel);
        // Strip known editor overlay classes/attributes.
        $sel = preg_replace('/\.mlcssp-ve-[a-z-]+/', '', $sel) ?? $sel;
        $sel = preg_replace('/\[data-mlcssp[^\]]*\]/', '', $sel) ?? $sel;
        $sel = preg_replace('/\.mlcssp-visual-selected/', '', $sel) ?? $sel;
        $sel = trim(preg_replace('/\s+/', ' ', $sel) ?? $sel);
        if ($sel === '') {
            return 'body';
        }
        // Already starts with body or html — strong enough.
        if (preg_match('/^(body|html)\b/i', $sel)) {
            return $sel;
        }
        // Already has an ID selector — strong enough.
        if (str_starts_with($sel, '#')) {
            return $sel;
        }
        return 'body ' . $sel;
    }

    // --- Internal methods ----------------------------------------------------

    private function resolve_url(string $url): string {
        if ($url === '') {
            $url = home_url('/');
        }
        $url = esc_url_raw($url);
        $site_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        $target_host = (string) wp_parse_url($url, PHP_URL_HOST);
        if ($site_host === '' || $target_host === '' || strtolower($site_host) !== strtolower($target_host)) {
            return '';
        }
        if (str_contains($url, '/wp-admin')) {
            return '';
        }
        return $url;
    }

    private function fetch(string $url): string {
        if ($url === '') {
            return '';
        }
        $response = wp_remote_get($url, [
            'timeout'    => 15,
            'user-agent' => 'ML-CSS-Pro-Scanner/' . MLCSSP_VERSION,
            'sslverify'  => false,
            'redirection' => 3,
        ]);
        if (is_wp_error($response)) {
            return '';
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 400) {
            return '';
        }
        return (string) wp_remote_retrieve_body($response);
    }

    /**
     * Extract CSS sources from HTML: linked stylesheets + inline <style> blocks.
     *
     * @return array<int,array{type:string,url:string,label:string,content:string}>
     */
    private function extract_css_sources(string $html, string $page_url): array {
        $sources    = [];
        $site_host  = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $count      = 0;

        // Linked stylesheets.
        if (preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[0] as $tag) {
                if ($count >= self::MAX_FILES) {
                    break;
                }
                if (preg_match('/href=["\']([^"\']+)["\']/', $tag, $m)) {
                    $href = $this->absolute_url($m[1], $page_url);
                    $href_host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
                    if ($href !== '' && $href_host === $site_host) {
                        $label = basename((string) wp_parse_url($href, PHP_URL_PATH));
                        $sources[] = ['type' => 'link', 'url' => $href, 'label' => $label, 'content' => ''];
                        $count++;
                    }
                }
            }
        }

        // Also try href before rel (some themes emit it reversed).
        if (preg_match_all('/<link[^>]+href=["\']([^"\']+\.css[^"\']*)["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                if ($count >= self::MAX_FILES) {
                    break;
                }
                $href = $this->absolute_url($href, $page_url);
                $href_host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
                if ($href !== '' && $href_host === $site_host) {
                    // Avoid duplication.
                    $clean = strtok($href, '?');
                    $already = false;
                    foreach ($sources as $s) {
                        if (strtok($s['url'], '?') === $clean) {
                            $already = true;
                            break;
                        }
                    }
                    if (!$already) {
                        $label = basename((string) wp_parse_url($href, PHP_URL_PATH));
                        $sources[] = ['type' => 'link', 'url' => $href, 'label' => $label, 'content' => ''];
                        $count++;
                    }
                }
            }
        }

        // Inline <style> blocks (not admin-bar or wp-emoji).
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/si', $html, $matches)) {
            foreach ($matches[1] as $idx => $block) {
                if ($count >= self::MAX_FILES) {
                    break;
                }
                $block = trim($block);
                if ($block === '' || strlen($block) < 20) {
                    continue;
                }
                // Skip admin-bar / emoji inline CSS.
                if (str_contains($block, '#wpadminbar') || str_contains($block, 'wp-emoji')) {
                    continue;
                }
                $sources[] = ['type' => 'inline', 'url' => '', 'label' => 'inline-style-' . ($idx + 1), 'content' => $block];
                $count++;
            }
        }

        return $sources;
    }

    /**
     * Lightweight CSS parser: extract selector → property → value triples.
     *
     * @return array<int,array{selector:string,property:string,value:string,source:string}>
     */
    private function parse_css(string $css, string $source_label): array {
        // Strip comments.
        $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;

        // Strip @import, @charset, @font-face (keep @media content).
        $css = preg_replace('/@(import|charset|font-face)[^;{]*;/i', '', $css) ?? $css;

        // Flatten @media by removing the outer wrapper (keeps inner rules).
        $css = preg_replace('/@media[^{]*\{/i', '', $css) ?? $css;

        // Remove extra closing braces from flattened @media.
        // Count and balance: after flattening, there may be stray `}`.
        // Simple approach: extract rule blocks.
        $results = [];
        $relevant_properties = array_flip(self::PROPERTIES);

        // Match: selector { ... }
        if (preg_match_all('/([^{}@]+?)\s*\{([^}]*)\}/s', $css, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $raw_selector = trim($match[1]);
                $raw_body     = trim($match[2]);
                if ($raw_selector === '' || $raw_body === '') {
                    continue;
                }
                // Skip keyframe steps.
                if (preg_match('/^(from|to|\d+%)/i', $raw_selector)) {
                    continue;
                }
                // Handle comma-separated selectors: take each one.
                $selectors = array_map('trim', explode(',', $raw_selector));
                // Parse declarations.
                $declarations = $this->parse_declarations($raw_body, $relevant_properties);
                foreach ($selectors as $sel) {
                    $sel = $this->clean_selector($sel);
                    if ($sel === '' || strlen($sel) > 200) {
                        continue;
                    }
                    foreach ($declarations as $prop => $val) {
                        $results[] = [
                            'selector' => $sel,
                            'property' => $prop,
                            'value'    => $val,
                            'source'   => $source_label,
                        ];
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Parse CSS declarations from a rule body, returning only relevant properties.
     *
     * @return array<string,string>
     */
    private function parse_declarations(string $body, array $relevant): array {
        $out = [];
        $parts = explode(';', $body);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || !str_contains($part, ':')) {
                continue;
            }
            $colon = strpos($part, ':');
            $prop  = strtolower(trim(substr($part, 0, $colon)));
            $val   = trim(substr($part, $colon + 1));
            // Remove !important for storage (we re-add on override output).
            $val = str_ireplace('!important', '', $val);
            $val = trim($val);
            if ($prop === '' || $val === '' || !isset($relevant[$prop])) {
                continue;
            }
            // Last wins (matches CSS cascade).
            $out[$prop] = $val;
        }
        return $out;
    }

    /** Categorize raw results into groups. */
    private function categorize(array $rules): array {
        foreach ($rules as &$rule) {
            $rule['group'] = $this->detect_group($rule['selector']);
        }
        unset($rule);

        // Deduplicate: keep last occurrence per selector+property.
        $seen = [];
        $deduped = [];
        foreach ($rules as $rule) {
            $key = $rule['selector'] . '|' . $rule['property'];
            $seen[$key] = $rule;
        }
        return array_values($seen);
    }

    private function detect_group(string $selector): string {
        $lower = strtolower($selector);
        foreach (self::CATEGORY_MAP as $group => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($lower, strtolower($pattern))) {
                    return $group;
                }
            }
        }
        return 'other';
    }

    private function clean_selector(string $sel): string {
        $sel = trim($sel);
        // Remove newlines and collapse whitespace.
        $sel = preg_replace('/\s+/', ' ', $sel) ?? $sel;
        $sel = trim($sel);
        // Security: strip anything with < > or PHP tags.
        if (preg_match('/[<>]/', $sel)) {
            return '';
        }
        return $sel;
    }

    private function absolute_url(string $href, string $base): string {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, 'data:')) {
            return '';
        }
        if (str_starts_with($href, '//')) {
            $scheme = (string) wp_parse_url($base, PHP_URL_SCHEME);
            return ($scheme ?: 'https') . ':' . $href;
        }
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        // Relative URL.
        $parts = wp_parse_url($base);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host   = (string) ($parts['host'] ?? '');
        if ($host === '') {
            return '';
        }
        if (str_starts_with($href, '/')) {
            return $scheme . '://' . $host . $href;
        }
        $path = (string) ($parts['path'] ?? '/');
        $dir  = substr($path, 0, (int) strrpos($path, '/'));
        return $scheme . '://' . $host . $dir . '/' . $href;
    }

    private function safe_truncate(string $text, int $max): string {
        if (strlen($text) <= $max) {
            return $text;
        }
        return substr($text, 0, $max);
    }

    private static function sanitize_selector(string $sel): string {
        $sel = trim(wp_strip_all_tags($sel));
        if (strlen($sel) > 200 || preg_match('/[{}<>;]/', $sel)) {
            return '';
        }
        return $sel;
    }

    private static function sanitize_property(string $prop): string {
        $prop = strtolower(trim($prop));
        $allowed = array_flip(self::PROPERTIES);
        return isset($allowed[$prop]) ? $prop : '';
    }
}
