<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MLCSSP_CSS_Engine {
    private MLCSSP_Settings $settings_manager;
    private MLCSSP_License $license_manager;
    private ?bool $nicepage_detected = null;

    public function __construct(MLCSSP_Settings $settings_manager, MLCSSP_License $license_manager) {
        $this->settings_manager = $settings_manager;
        $this->license_manager = $license_manager;
    }

    public static function sanitize_css(string $css): string {
        $css = str_replace(["\r\n", "\r"], "\n", $css);
        $css = preg_replace('#<\s*/?\s*(script|iframe|object|embed|link|meta|style)[^>]*>#i', '', $css) ?? $css;
        $css = preg_replace('#<\?(php)?|\?>#i', '', $css) ?? $css;
        $css = preg_replace('/expression\s*\(/i', 'blocked-expression(', $css) ?? $css;
        $css = preg_replace('/javascript\s*:/i', 'blocked-javascript:', $css) ?? $css;
        $css = preg_replace('/data\s*:\s*text\/html/i', 'blocked-data-html', $css) ?? $css;
        return trim($css);
    }

    public function enqueue_frontend_css(): void {
        $settings = $this->settings_manager->get_settings();
        if (($settings['css_position'] ?? 'head') !== 'head') {
            return;
        }
        $asset = $this->get_compiled_asset();
        if ($asset['exists']) {
            $bust = (string) filemtime($asset['path']) ?: (string) get_option(MLCSSP_CACHE_VERSION_KEY, MLCSSP_VERSION);
            wp_enqueue_style('mlcssp-control-css', $asset['url'], [], $bust);
            return;
        }
        $css = $this->build_frontend_css($settings, true);
        if ($css === '') {
            return;
        }
        wp_register_style('mlcssp-control-css', false, [], MLCSSP_VERSION);
        wp_enqueue_style('mlcssp-control-css');
        wp_add_inline_style('mlcssp-control-css', $css);
    }

    public function print_footer_css(): void {
        $settings = $this->settings_manager->get_settings();
        if (($settings['css_position'] ?? 'head') !== 'footer') {
            return;
        }
        $css = $this->build_frontend_css($settings, true);
        if ($css === '') {
            return;
        }
        echo "\n<style id=\"mlcssp-control-css\">" . wp_strip_all_tags($css) . "</style>\n";
    }

    /**
     * Emergency inline fallback: prints override CSS directly into <head> at
     * very late priority (99999). Guarantees overrides apply even when the
     * compiled file is cached, CDN-delayed, or blocked by a caching plugin.
     * Only emits the override portion, not the full visual CSS.
     */
    public function print_override_inline_fallback(): void {
        $settings = $this->settings_manager->get_settings();
        if (($settings['css_enabled'] ?? '1') !== '1') {
            return;
        }
        $override_css = MLCSSP_Scanner::build_override_css();
        if ($override_css === '') {
            return;
        }
        $override_css = wp_strip_all_tags($override_css);
        static $printed = 0;
        $printed++;
        $id = $printed === 1 ? 'mlcssp-override-inline' : 'mlcssp-override-inline-footer';
        echo "\n<style id=\"" . $id . "\">" . $override_css . "</style>\n";
    }

    public function filter_body_classes(array $classes): array {
        if ($this->is_nicepage_context()) {
            $classes[] = 'mlcssp-nicepage-page';
        }
        return array_values(array_unique($classes));
    }

    public function get_report(): array {
        $settings = $this->settings_manager->get_settings();
        $global_css = (string) ($settings['global_css'] ?? '');
        $nicepage_css = (string) ($settings['nicepage_css'] ?? '');
        return [
            'enabled' => ($settings['css_enabled'] ?? '1') === '1',
            'visual_enabled' => ($settings['visual_enabled'] ?? '1') === '1',
            'visual_rules' => $this->count_visual_rules($settings),
            'position' => (string) ($settings['css_position'] ?? 'head'),
            'priority' => (string) ($settings['css_priority'] ?? '9999'),
            'minify' => ($settings['css_minify'] ?? '1') === '1',
            'safe_mode' => ($settings['css_safe_mode'] ?? '1') === '1',
            'nicepage_mode' => (string) ($settings['nicepage_mode'] ?? 'detected'),
            'nicepage_scope' => (string) ($settings['nicepage_scope'] ?? 'body.mlcssp-nicepage-page'),
            'global_chars' => strlen($global_css),
            'nicepage_chars' => strlen($nicepage_css),
            'global_size' => MLCSSP_Helpers::format_bytes(strlen($global_css)),
            'nicepage_size' => MLCSSP_Helpers::format_bytes(strlen($nicepage_css)),
            'nicepage_detected_now' => $this->is_nicepage_context(),
            // 1.6.0: a licença não altera o CSS publicado; só libera a edição no painel.
            'license_global_allowed' => true,
            'license_nicepage_allowed' => true,
            'license_advanced_allowed' => true,
            'license_can_edit' => MLCSSP_License::is_active(),
            'detected_targets' => $this->get_detected_targets(),
            'cache' => $this->get_compiled_asset(),
            'theme' => $this->get_theme_label(),
        ];
    }

    public function compile_cache(): bool {
        $settings = $this->settings_manager->get_settings();
        $css = $this->build_frontend_css($settings, false);
        $asset = $this->get_compiled_asset();
        if (!wp_mkdir_p($asset['dir'])) {
            return false;
        }

        $header = "/* ML CSS Pro v" . MLCSSP_VERSION . " - compilado em " . current_time('mysql') . " */\n";
        $payload = $header . $css;

        $written = $this->write_file($asset['path'], $payload);
        if (!$written) {
            return false;
        }
        update_option(MLCSSP_CACHE_VERSION_KEY, (string) time(), false);
        update_option(MLCSSP_COMPILED_AT_KEY, current_time('mysql'), false);
        return true;
    }

    public function clear_cache(): bool {
        $asset = $this->get_compiled_asset();
        $deleted = true;
        if (file_exists($asset['path'])) {
            $deleted = $this->delete_file($asset['path']);
        }
        if ($deleted) {
            delete_option(MLCSSP_COMPILED_AT_KEY);
        }
        return $deleted;
    }

    /**
     * Write a file using the WordPress filesystem API when available, falling
     * back to file_put_contents. Writes are confined to uploads/ml-css-pro.
     */
    private function write_file(string $path, string $contents): bool {
        if (!$this->path_is_allowed($path)) {
            return false;
        }
        if (function_exists('WP_Filesystem')) {
            global $wp_filesystem;
            if (!$wp_filesystem) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ($wp_filesystem && method_exists($wp_filesystem, 'put_contents')) {
                $ok = $wp_filesystem->put_contents($path, $contents, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);
                if ($ok) {
                    return true;
                }
            }
        }
        return file_put_contents($path, $contents) !== false;
    }

    private function delete_file(string $path): bool {
        if (!$this->path_is_allowed($path)) {
            return false;
        }
        if (function_exists('WP_Filesystem')) {
            global $wp_filesystem;
            if (!$wp_filesystem) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ($wp_filesystem && method_exists($wp_filesystem, 'delete')) {
                $ok = $wp_filesystem->delete($path);
                if ($ok) {
                    return true;
                }
            }
        }
        return @unlink($path);
    }

    /**
     * Confine all file operations to uploads/ml-css-pro. No arbitrary paths.
     */
    private function path_is_allowed(string $path): bool {
        $upload = wp_upload_dir();
        $base = trailingslashit((string) ($upload['basedir'] ?? '')) . 'ml-css-pro';
        $normalized_base = wp_normalize_path($base);
        $normalized_path = wp_normalize_path($path);
        return str_starts_with($normalized_path, $normalized_base . '/');
    }

    public function get_compiled_asset(): array {
        $upload = wp_upload_dir();
        $base_dir = trailingslashit((string) ($upload['basedir'] ?? '')) . 'ml-css-pro';
        $base_url = trailingslashit((string) ($upload['baseurl'] ?? '')) . 'ml-css-pro';
        $path = trailingslashit($base_dir) . 'compiled.css';
        $exists = file_exists($path);
        $mtime = $exists ? (int) filesize($path) : 0;
        $last_compiled = (string) get_option(MLCSSP_COMPILED_AT_KEY, '');
        if ($last_compiled === '' && $exists) {
            $last_compiled = gmdate('Y-m-d H:i:s', (int) filemtime($path));
        }
        return [
            'dir' => $base_dir,
            'path' => $path,
            'url' => set_url_scheme(trailingslashit($base_url) . 'compiled.css'),
            'exists' => $exists,
            'writable' => (is_dir($base_dir) && is_writable($base_dir)) || is_writable(dirname($base_dir)),
            'size' => $exists ? MLCSSP_Helpers::format_bytes((int) filesize($path)) : '0 B',
            'bytes' => $exists ? (int) filesize($path) : 0,
            'last_compiled' => $last_compiled,
            'position' => '',
        ];
    }

    /**
     * Public list of common target selectors with friendly labels.
     * Used by the selector helper (inspector foundation) in the admin UI.
     *
     * @return array<string,string>
     */
    public function get_target_examples(): array {
        return $this->get_detected_targets();
    }

    /**
     * Best-effort, read-only detection of Nicepage section selectors present in
     * recent site content. Safe foundation for the selector helper: it only
     * reads published content and never modifies frontend rendering.
     *
     * @return array<int,string>
     */
    public function get_detected_nicepage_selectors(int $limit = 30): array {
        $found = [];
        $query = new WP_Query([
            'post_type' => ['page', 'post'],
            'post_status' => 'publish',
            'posts_per_page' => 15,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'fields' => 'ids',
        ]);

        if (!empty($query->posts)) {
            foreach ($query->posts as $post_id) {
                $content = (string) get_post_field('post_content', (int) $post_id);
                if ($content === '') {
                    continue;
                }
                if (preg_match_all('/u-section-\d+/', $content, $matches)) {
                    foreach ($matches[0] as $match) {
                        $found['.' . $match] = true;
                    }
                }
                foreach (['u-sheet', 'u-container-style', 'u-group', 'u-btn', 'u-text', 'u-image'] as $token) {
                    if (str_contains($content, $token)) {
                        $found['.' . $token] = true;
                    }
                }
                if (count($found) >= $limit) {
                    break;
                }
            }
        }
        wp_reset_postdata();

        return array_slice(array_keys($found), 0, $limit);
    }

    public function get_preview_url(): string {
        return home_url('/');
    }

    private function build_frontend_css(array $settings, bool $front_context): string {
        if (($front_context && (is_admin() || wp_doing_ajax())) || ($settings['css_enabled'] ?? '1') !== '1') {
            return '';
        }

        $parts = [];
        $visual_css = $this->build_visual_css($settings);
        if ($visual_css !== '') {
            $parts[] = "/* ML CSS Pro: visual */\n" . $visual_css;
        }

        $global_css = self::sanitize_css((string) ($settings['global_css'] ?? ''));
        if ($global_css !== '') {
            $parts[] = "/* ML CSS Pro: global */\n" . $global_css;
        }

        $nicepage_css = self::sanitize_css((string) ($settings['nicepage_css'] ?? ''));
        $nicepage_mode = (string) ($settings['nicepage_mode'] ?? 'detected');
        $should_load_nicepage = $nicepage_mode === 'all' || ($nicepage_mode === 'detected' && $this->is_nicepage_context());
        if ($nicepage_css !== '' && $should_load_nicepage) {
            if (($settings['css_safe_mode'] ?? '1') === '1') {
                $scope = trim((string) ($settings['nicepage_scope'] ?? 'body.mlcssp-nicepage-page'));
                if ($scope !== '') {
                    $nicepage_css = $this->prefix_css_selectors($nicepage_css, $scope);
                }
            }
            $parts[] = "/* ML CSS Pro: nicepage */\n" . $nicepage_css;
        }

        // Scanner overrides (detected styles edited by admin).
        $override_css = MLCSSP_Scanner::build_override_css();
        if ($override_css !== '') {
            $parts[] = $override_css;
        }

        $css = trim(implode("\n\n", array_filter($parts)));
        if ($css === '') {
            return '';
        }
        if (($settings['css_minify'] ?? '1') === '1') {
            $css = $this->minify_css($css);
        }
        return $css;
    }

    private function is_nicepage_context(): bool {
        if ($this->nicepage_detected !== null) {
            return $this->nicepage_detected;
        }

        $detected = false;
        $post = get_post();
        if ($post && isset($post->post_content)) {
            $content = strtolower((string) $post->post_content);
            $detected = str_contains($content, 'u-section-')
                || str_contains($content, 'u-sheet')
                || str_contains($content, 'nicepage');
        }

        if (!$detected) {
            $theme = wp_get_theme();
            $theme_name = strtolower($theme->get('Name') . ' ' . $theme->get('Template') . ' ' . $theme->get('TextDomain'));
            $detected = str_contains($theme_name, 'nicepage');
        }

        if (!$detected) {
            $active_plugins = (array) get_option('active_plugins', []);
            foreach ($active_plugins as $plugin) {
                if (str_contains(strtolower((string) $plugin), 'nicepage')) {
                    $detected = true;
                    break;
                }
            }
        }

        $this->nicepage_detected = $detected;
        return $detected;
    }

    private function prefix_css_selectors(string $css, string $scope): string {
        return preg_replace_callback('/(^|[{}])\s*([^@{}][^{]+)\s*\{/', function (array $matches) use ($scope): string {
            $prefix = $matches[1];
            $selector_group = trim($matches[2]);
            if ($selector_group === '' || preg_match('/^(from|to|\d+%)/i', $selector_group)) {
                return $matches[0];
            }

            $selectors = array_map('trim', explode(',', $selector_group));
            $selectors = array_map(function (string $selector) use ($scope): string {
                if ($selector === '' || str_starts_with($selector, $scope)) {
                    return $selector;
                }
                if ($selector === 'body') {
                    return $scope;
                }
                if (str_starts_with($selector, 'body.')) {
                    return preg_replace('/^body/', $scope, $selector, 1) ?? ($scope . ' ' . $selector);
                }
                return $scope . ' ' . $selector;
            }, $selectors);

            return $prefix . ' ' . implode(', ', array_filter($selectors)) . ' {';
        }, $css) ?? $css;
    }

    private function minify_css(string $css): string {
        $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;
        $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css) ?? $css;
        return trim($css);
    }

    private function build_visual_css(array $settings): string {
        if (($settings['visual_enabled'] ?? '1') !== '1') {
            return '';
        }

        $scope = $this->get_visual_scope($settings);
        $rules = [];
        $this->add_rule($rules, $scope, [
            'background-color' => (string) ($settings['site_bg_color'] ?? ''),
            'color' => (string) ($settings['site_text_color'] ?? ''),
            'font-family' => $this->font_stack((string) ($settings['body_font'] ?? '')),
            'font-size' => (string) ($settings['body_size'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors($scope, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', '.u-title', '.u-heading-font', '.h-title', '.hero-title', '.hero h1']), [
            'color' => (string) ($settings['heading_color'] ?? ''),
            'font-family' => $this->font_stack((string) ($settings['heading_font'] ?? '')),
            'font-size' => (string) ($settings['heading_size'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors($scope, ['a', '.u-link']), [
            'color' => (string) ($settings['link_color'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors($scope, ['a:hover', '.u-link:hover']), [
            'color' => (string) ($settings['link_hover_color'] ?? ''),
        ]);
        $button_selectors = [
            'button',
            'input[type="button"]',
            'input[type="submit"]',
            '.button',
            'a.button',
            '.btn',
            'a.btn',
            '.wp-block-button > a',
            '.wp-block-button__link',
            '.u-btn',
            '.h-button',
            '.colibri-button',
            'a.h-button',
            'a.colibri-button',
            '.hero a.h-button',
            '.hero a.button',
            '.hero a.btn',
            '.hero .wp-block-button__link',
        ];
        $this->add_rule($rules, $this->scoped_selectors($scope, $button_selectors), [
            'background-color' => (string) ($settings['button_bg_color'] ?? ''),
            'color' => (string) ($settings['button_text_color'] ?? ''),
            'border-color' => (string) ($settings['button_border_color'] ?? ''),
            'border-width' => (string) ($settings['button_border_width'] ?? ''),
            'border-style' => !empty($settings['button_border_color']) || !empty($settings['button_border_width']) ? 'solid' : '',
            'border-radius' => (string) ($settings['button_radius'] ?? ''),
            'padding-top' => (string) ($settings['button_padding_y'] ?? ''),
            'padding-bottom' => (string) ($settings['button_padding_y'] ?? ''),
            'padding-left' => (string) ($settings['button_padding_x'] ?? ''),
            'padding-right' => (string) ($settings['button_padding_x'] ?? ''),
            'box-shadow' => (($settings['button_shadow'] ?? '0') === '1') ? '0 12px 28px rgba(15, 23, 42, .18)' : '',
            'text-transform' => (string) ($settings['button_text_transform'] ?? ''),
            'transition' => (($settings['button_hover_animation'] ?? '0') === '1') ? 'all .2s ease' : '',
        ]);
        $this->add_rule($rules, $this->scoped_selectors($scope, ['button:hover', 'input[type="button"]:hover', 'input[type="submit"]:hover', '.button:hover', 'a.button:hover', '.btn:hover', 'a.btn:hover', '.wp-block-button > a:hover', '.wp-block-button__link:hover', '.u-btn:hover', '.h-button:hover', '.colibri-button:hover', 'a.h-button:hover', 'a.colibri-button:hover', '.hero a.h-button:hover', '.hero a.button:hover', '.hero a.btn:hover', '.hero .wp-block-button__link:hover']), [
            'background-color' => (string) ($settings['button_hover_bg_color'] ?? ''),
            'color' => (string) ($settings['button_hover_text_color'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors($scope, ['section', '.hero', '.h-section', '.content-section', '.u-section-1', '[class*="u-section-"]']), [
            'background-color' => (string) ($settings['section_bg_color'] ?? ''),
            'padding-top' => (string) ($settings['section_padding_y'] ?? ''),
            'padding-bottom' => (string) ($settings['section_padding_y'] ?? ''),
        ]);
        $card_shadow = (($settings['card_shadow'] ?? '0') === '1') ? '0 18px 42px rgba(15, 23, 42, .16)' : '';
        $this->add_rule($rules, $this->scoped_selectors($scope, ['.card', '.u-container-style', '.u-group', '.u-list-item']), [
            'background-color' => (string) ($settings['card_bg_color'] ?? ''),
            'border-radius' => (string) ($settings['card_radius'] ?? ''),
            'box-shadow' => $card_shadow,
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['header', '.site-header', '.u-header', '.header', '.h-navigation', '.navigation-bar', '[class*="header"]']), [
            'background-color' => (string) ($settings['header_bg_color'] ?? ''),
            'min-height' => (string) ($settings['header_height'] ?? ''),
            'border-bottom-color' => (string) ($settings['header_border_color'] ?? ''),
            'border-bottom-style' => !empty($settings['header_border_color']) ? 'solid' : '',
            'border-bottom-width' => !empty($settings['header_border_color']) ? '1px' : '',
            'box-shadow' => (($settings['header_shadow'] ?? '0') === '1') ? '0 8px 28px rgba(15, 23, 42, .12)' : '',
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['header a', '.site-header a', '.u-header a', '.menu a', '.h-navigation a', '.navigation-bar a']), [
            'color' => (string) ($settings['header_text_color'] ?? ''),
            'font-size' => (string) ($settings['header_font_size'] ?? ''),
            'font-weight' => (string) ($settings['header_font_weight'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['header a:hover', '.site-header a:hover', '.u-header a:hover', '.menu a:hover', '.h-navigation a:hover', '.navigation-bar a:hover']), [
            'color' => (string) ($settings['header_hover_color'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['.menu', '.u-nav']), [
            'gap' => (string) ($settings['header_item_gap'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['.is-sticky header', 'header.is-sticky', '.u-sticky-fixed']), [
            'background-color' => (string) ($settings['header_sticky_bg_color'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['footer', '.site-footer', '.u-footer']), [
            'background-color' => (string) ($settings['footer_bg_color'] ?? ''),
            'color' => (string) ($settings['footer_text_color'] ?? ''),
            'padding-top' => (string) ($settings['footer_padding_y'] ?? ''),
            'padding-bottom' => (string) ($settings['footer_padding_y'] ?? ''),
            'font-size' => (string) ($settings['footer_font_size'] ?? ''),
            'border-top-color' => (string) ($settings['footer_divider_color'] ?? ''),
            'border-top-style' => !empty($settings['footer_divider_color']) ? 'solid' : '',
            'border-top-width' => !empty($settings['footer_divider_color']) ? '1px' : '',
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['footer a', '.site-footer a', '.u-footer a']), [
            'color' => (string) ($settings['footer_link_color'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['footer a:hover', '.site-footer a:hover', '.u-footer a:hover']), [
            'color' => (string) ($settings['footer_link_hover_color'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['footer h1', 'footer h2', 'footer h3', 'footer h4', '.u-footer h1', '.u-footer h2', '.u-footer h3', '.u-footer h4']), [
            'color' => (string) ($settings['footer_title_color'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['.container', '.site-content', '.entry-content', '.u-sheet']), [
            'max-width' => (string) ($settings['container_max_width'] ?? ''),
            'padding-left' => (string) ($settings['container_padding_x'] ?? ''),
            'padding-right' => (string) ($settings['container_padding_x'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['.entry-content > * + *', '.u-section-1 + .u-section-2', '[class*="u-section-"] + [class*="u-section-"]']), [
            'margin-top' => (string) ($settings['block_spacing_y'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['input', 'textarea', 'select', '.u-input']), [
            'background-color' => (string) ($settings['form_field_bg_color'] ?? ''),
            'color' => (string) ($settings['form_field_text_color'] ?? ''),
            'border-color' => (string) ($settings['form_border_color'] ?? ''),
            'border-radius' => (string) ($settings['form_radius'] ?? ''),
            'min-height' => (string) ($settings['form_field_height'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['input::placeholder', 'textarea::placeholder']), [
            'color' => (string) ($settings['form_placeholder_color'] ?? ''),
        ]);
        $this->add_rule($rules, $this->scoped_selectors('body', ['input:focus', 'textarea:focus', 'select:focus', '.u-input:focus']), [
            'border-color' => (string) ($settings['form_focus_border_color'] ?? ''),
            'outline-color' => (string) ($settings['form_focus_border_color'] ?? ''),
        ]);
        $responsive = $this->build_responsive_css($settings);
        if ($responsive !== '') {
            $rules[] = $responsive;
        }

        $custom_selector = trim((string) ($settings['custom_selector'] ?? ''));
        if ($custom_selector !== '') {
            $custom_scope = $scope;
            if (($settings['page_override_enabled'] ?? '0') === '1' && !empty($settings['page_override_id'])) {
                $custom_scope = 'body.page-id-' . absint($settings['page_override_id']);
            }
            $this->add_rule($rules, $this->scoped_selectors($custom_scope, [$custom_selector]), [
                'color' => (string) ($settings['custom_color'] ?? ''),
                'background-color' => (string) ($settings['custom_bg_color'] ?? ''),
                'font-family' => $this->font_stack((string) ($settings['custom_font'] ?? '')),
                'font-size' => (string) ($settings['custom_size'] ?? ''),
            ]);
        }

        return implode("\n", $rules);
    }

    private function build_responsive_css(array $settings): string {
        $blocks = [];
        $tablet = [];
        $this->add_rule($tablet, 'body', ['font-size' => (string) ($settings['tablet_body_size'] ?? '')]);
        $this->add_rule($tablet, 'body h1, body h2, body h3, body .u-title', ['font-size' => (string) ($settings['tablet_heading_size'] ?? '')]);
        $this->add_rule($tablet, 'body section, body [class*="u-section-"]', ['padding-top' => (string) ($settings['tablet_section_padding_y'] ?? ''), 'padding-bottom' => (string) ($settings['tablet_section_padding_y'] ?? '')]);
        $this->add_rule($tablet, 'body .container, body .site-content, body .entry-content, body .u-sheet', ['max-width' => (string) ($settings['tablet_container_width'] ?? '')]);
        if ($tablet !== []) {
            $blocks[] = "@media (max-width: 1024px) {\n" . implode("\n", $tablet) . "\n}";
        }

        $mobile = [];
        $this->add_rule($mobile, 'body', ['font-size' => (string) ($settings['mobile_body_size'] ?? '')]);
        $this->add_rule($mobile, 'body h1, body h2, body h3, body .u-title', ['font-size' => (string) ($settings['mobile_heading_size'] ?? '')]);
        $this->add_rule($mobile, 'body section, body [class*="u-section-"]', ['padding-top' => (string) ($settings['mobile_section_padding_y'] ?? ''), 'padding-bottom' => (string) ($settings['mobile_section_padding_y'] ?? '')]);
        $this->add_rule($mobile, 'body .container, body .site-content, body .entry-content, body .u-sheet', ['max-width' => (string) ($settings['mobile_container_width'] ?? '')]);
        if (($settings['mobile_stack_blocks'] ?? '0') === '1') {
            $this->add_rule($mobile, 'body .u-layout-row, body .wp-block-columns', ['display' => 'block']);
        }
        if ($mobile !== []) {
            $blocks[] = "@media (max-width: 767px) {\n" . implode("\n", $mobile) . "\n}";
        }
        return implode("\n", $blocks);
    }

    private function get_visual_scope(array $settings): string {
        $target = (string) ($settings['visual_target'] ?? 'site');
        if ($target === 'nicepage') {
            if (!$this->is_nicepage_context()) {
                return 'body';
            }
            return trim((string) ($settings['nicepage_scope'] ?? 'body.mlcssp-nicepage-page')) ?: 'body.mlcssp-nicepage-page';
        }
        if ($target === 'site') {
            return 'body';
        }
        return 'body';
    }

    private function scoped_selectors(string $scope, array $selectors): string {
        $items = [];
        foreach ($selectors as $selector) {
            $selector = trim($selector);
            if ($selector === '') {
                continue;
            }
            if ($scope === 'body' && str_starts_with($selector, 'body')) {
                $items[] = $selector;
            } elseif ($scope === 'body') {
                $items[] = 'body ' . $selector;
            } elseif (str_starts_with($selector, 'body')) {
                $items[] = preg_replace('/^body/', $scope, $selector, 1) ?? ($scope . ' ' . $selector);
            } else {
                $items[] = $scope . ' ' . $selector;
            }
        }
        return implode(', ', array_unique($items));
    }

    private function add_rule(array &$rules, string $selector, array $properties): void {
        $clean = [];
        foreach ($properties as $property => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                if (!str_contains($value, '!important')) {
                    $value .= ' !important';
                }
                $clean[] = $property . ': ' . $value . ';';
            }
        }
        if ($selector !== '' && $clean !== []) {
            $rules[] = $selector . " {\n  " . implode("\n  ", $clean) . "\n}";
        }
    }

    private function font_stack(string $font): string {
        $font = trim($font);
        if ($font === '') {
            return '';
        }
        if ($font === 'system-ui') {
            return 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        }
        return '"' . str_replace('"', '', $font) . '", sans-serif';
    }

    private function count_visual_rules(array $settings): int {
        $keys = ['site_bg_color', 'site_text_color', 'heading_color', 'heading_font', 'heading_size', 'body_font', 'body_size', 'link_color', 'link_hover_color', 'button_bg_color', 'button_text_color', 'button_radius', 'button_padding_y', 'button_padding_x', 'header_bg_color', 'header_text_color', 'footer_bg_color', 'footer_text_color', 'section_bg_color', 'section_padding_y', 'card_bg_color', 'card_radius', 'form_field_bg_color', 'form_border_color', 'tablet_body_size', 'mobile_body_size', 'custom_color', 'custom_bg_color', 'custom_font', 'custom_size'];
        $count = 0;
        foreach ($keys as $key) {
            if (!empty($settings[$key])) {
                $count++;
            }
        }
        if (($settings['card_shadow'] ?? '0') === '1') {
            $count++;
        }
        return $count;
    }

    private function get_detected_targets(): array {
        return [
            'body' => 'Site inteiro',
            'h1, h2, h3' => 'Titulos',
            'a, .u-link' => 'Links',
            'button, .button, .btn, .h-button, .u-btn' => 'Botoes',
            '.h-navigation, .site-header, .u-header' => 'Header e menu',
            '.hero, .h-section' => 'Hero e secoes do tema',
            '[class*="u-section-"]' => 'Secoes Nicepage',
            '.u-container-style, .u-group' => 'Cards e blocos Nicepage',
        ];
    }

    private function get_theme_label(): string {
        $theme = wp_get_theme();
        return $theme->exists() ? (string) $theme->get('Name') : 'Tema nao detectado';
    }
}
