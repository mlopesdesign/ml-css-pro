<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MLCSSP_Plugin {
    private MLCSSP_Settings $settings_manager;
    private MLCSSP_License $license_manager;
    private MLCSSP_CSS_Engine $css_engine;
    private MLCSSP_Admin_UI $admin_ui;

    public function __construct() {
        $this->settings_manager = new MLCSSP_Settings();
        $this->license_manager = new MLCSSP_License();
        $this->css_engine = new MLCSSP_CSS_Engine($this->settings_manager, $this->license_manager);
        $this->admin_ui = new MLCSSP_Admin_UI();

        $updater = new MLCSSP_Updater(
            MLCSSP_BASENAME,
            'ml-css-pro',
            'mlopesdesign/ml-css-pro-releases',
            MLCSSP_VERSION
        );
        $updater->hooks();

        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this->settings_manager, 'register']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        $settings = $this->settings_manager->get_settings();
        $css_priority = max(1, min(99999, absint($settings['css_priority'] ?? 99990)));
        add_action('wp_enqueue_scripts', [$this->css_engine, 'enqueue_frontend_css'], $css_priority);
        add_action('wp_footer', [$this->css_engine, 'print_footer_css'], 9999);
        add_action('wp_head', [$this->css_engine, 'print_override_inline_fallback'], 99999);
        add_action('wp_footer', [$this->css_engine, 'print_override_inline_fallback'], 1);
        add_filter('body_class', [$this->css_engine, 'filter_body_classes']);
        add_action('admin_post_mlcssp_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_mlcssp_save_css', [$this, 'handle_save_css']);
        add_action('admin_post_mlcssp_tools', [$this, 'handle_tools']);
        add_action('admin_post_mlcssp_scan', [$this, 'handle_scan']);
        add_action('admin_post_mlcssp_override', [$this, 'handle_override']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_visual_editor'], 99999);
        add_action('wp_ajax_mlcssp_visual_save', [$this, 'handle_visual_save']);
        add_action('wp_ajax_mlcssp_visual_reset', [$this, 'handle_visual_reset']);
        add_action('wp_ajax_mlcssp_page_search', [$this, 'handle_page_search']);
    }

    public function register_admin_menu(): void {
        $settings = $this->settings_manager->get_settings();
        add_menu_page(
            $settings['plugin_title'] ?? 'ML CSS Pro',
            $settings['menu_label'] ?? 'ML CSS Pro',
            'manage_options',
            'ml-css-pro',
            [$this, 'render_admin_page'],
            'dashicons-art',
            58
        );
    }

    public function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'toplevel_page_ml-css-pro') {
            return;
        }
        wp_enqueue_style('mlcssp-admin', MLCSSP_URL . 'assets/admin.css', [], MLCSSP_VERSION);

        // CSS editor engine: WordPress core CodeMirror (syntax highlight, line
        // numbers, CSSLint validation). No third-party library is bundled.
        $editor_settings = false;
        if (function_exists('wp_enqueue_code_editor')) {
            $editor_settings = wp_enqueue_code_editor(['type' => 'text/css']);
            if ($editor_settings !== false) {
                wp_enqueue_script('wp-theme-plugin-editor');
                wp_enqueue_style('wp-codemirror');
            }
        }

        wp_enqueue_script('mlcssp-admin', MLCSSP_URL . 'assets/admin.js', [], MLCSSP_VERSION, true);
        wp_enqueue_script('mlcssp-editor', MLCSSP_URL . 'assets/editor.js', ['mlcssp-admin'], MLCSSP_VERSION, true);
        wp_localize_script('mlcssp-editor', 'mlcsspEditor', [
            'codeEditor' => $editor_settings,
            'hasCodeEditor' => $editor_settings !== false,
            'i18n' => [
                'expand' => MLCSSP_Helpers::t('Expandir', 'Expand', 'Expandir'),
                'collapse' => MLCSSP_Helpers::t('Recolher', 'Collapse', 'Contraer'),
                'inserted' => MLCSSP_Helpers::t('Snippet inserido.', 'Snippet inserted.', 'Snippet insertado.'),
                'copied' => MLCSSP_Helpers::t('Copiado.', 'Copied.', 'Copiado.'),
            ],
        ]);
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = $this->settings_manager->get_settings();
        $license = $this->license_manager->get_license_summary();
        $css_report = $this->css_engine->get_report();
        $css_report['detected_nicepage'] = $this->css_engine->get_detected_nicepage_selectors();
        $css_report['target_examples'] = $this->css_engine->get_target_examples();
        $css_report['preview_url'] = $this->css_engine->get_preview_url();
        $css_report['lint_global'] = MLCSSP_Linter::analyze((string) ($settings['global_css'] ?? ''));
        $css_report['lint_nicepage'] = MLCSSP_Linter::analyze((string) ($settings['nicepage_css'] ?? ''));

        // Scanner data.
        $scanner = new MLCSSP_Scanner();
        $css_report['scan_meta'] = $scanner->get_meta();
        $css_report['scan_results'] = $scanner->get_results();
        $css_report['scan_overrides'] = MLCSSP_Scanner::get_overrides();

        $active_tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'dashboard';
        $toast_message = isset($_GET['mlcssp_toast']) ? sanitize_text_field(wp_unslash((string) $_GET['mlcssp_toast'])) : '';
        $toast_type = isset($_GET['mlcssp_toast_type']) ? sanitize_key((string) $_GET['mlcssp_toast_type']) : 'success';

        $this->admin_ui->render(
            $settings,
            $license,
            $css_report,
            $active_tab,
            $toast_message,
            $toast_type,
            $this->license_manager
        );
    }

    public function handle_save_settings(): void {
        MLCSSP_License::guard_write();
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'ml-css-pro'));
        }
        check_admin_referer('mlcssp_save_settings');
        $raw = isset($_POST['mlcssp_settings']) ? wp_unslash($_POST['mlcssp_settings']) : [];
        $sanitized = $this->settings_manager->sanitize_settings($raw);
        $this->settings_manager->save($sanitized);
        $this->redirect_with_toast('settings', 'Configuracoes salvas.', 'success');
    }

    public function handle_save_css(): void {
        MLCSSP_License::guard_write();
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'ml-css-pro'));
        }
        check_admin_referer('mlcssp_save_css');
        $raw = isset($_POST['mlcssp_css']) ? wp_unslash($_POST['mlcssp_css']) : [];
        $this->save_history('Alteracao visual salva');
        $sanitized = $this->settings_manager->sanitize_css_settings($raw);
        $this->settings_manager->save($sanitized);
        $compiled = $this->css_engine->compile_cache();
        $this->redirect_with_toast($this->posted_tab('global'), $compiled ? 'Ajustes salvos e CSS compilado.' : 'Ajustes salvos. Nao foi possivel gravar o CSS em uploads; o fallback inline continua disponivel.', $compiled ? 'success' : 'warning');
    }

    public function handle_tools(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'ml-css-pro'));
        }
        check_admin_referer('mlcssp_tools');
        $tool_action = isset($_POST['mlcssp_tool_action']) ? sanitize_key((string) wp_unslash($_POST['mlcssp_tool_action'])) : '';
        // Sem licença só a exportação (leitura) continua; o resto grava e fica travado.
        if ($tool_action !== 'export') {
            MLCSSP_License::guard_write();
        }

        if ($tool_action === 'export') {
            $filename = 'ml-css-pro-config-' . gmdate('Ymd-His') . '.json';
            nocache_headers();
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo wp_json_encode([
                'plugin' => 'ML CSS Pro',
                'version' => MLCSSP_VERSION,
                'exported_at' => current_time('mysql'),
                'settings' => $this->settings_manager->get_settings(),
                'overrides' => MLCSSP_Scanner::get_overrides(),
                'responsive' => MLCSSP_Scanner::get_responsive_overrides(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($tool_action === 'import') {
            $raw = isset($_POST['mlcssp_import_json']) ? (string) wp_unslash($_POST['mlcssp_import_json']) : '';
            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['settings']) || !is_array($data['settings'])) {
                $this->redirect_with_toast('tools', 'Arquivo de importacao invalido.', 'error');
            }
            $this->save_history('Snapshot antes da importacao');
            $settings = $this->settings_manager->sanitize_css_settings($data['settings']);
            $this->settings_manager->save($settings);
            // Import overrides (desktop + responsive) when present.
            if (isset($data['overrides']) && is_array($data['overrides'])) {
                update_option(MLCSSP_Scanner::OVERRIDES_KEY, $data['overrides'], false);
            }
            if (isset($data['responsive']) && is_array($data['responsive'])) {
                update_option(MLCSSP_Scanner::RESPONSIVE_KEY, [
                    'tablet' => isset($data['responsive']['tablet']) && is_array($data['responsive']['tablet']) ? $data['responsive']['tablet'] : [],
                    'mobile' => isset($data['responsive']['mobile']) && is_array($data['responsive']['mobile']) ? $data['responsive']['mobile'] : [],
                ], false);
            }
            $this->css_engine->compile_cache();
            $this->redirect_with_toast('tools', 'Configuracoes e overrides (todos os dispositivos) importados e CSS recompilado.', 'success');
        }

        if ($tool_action === 'clear_cache') {
            $ok = $this->css_engine->clear_cache();
            $this->redirect_with_toast('tools', $ok ? 'Cache CSS limpo.' : 'Nao foi possivel limpar o cache CSS.', $ok ? 'success' : 'error');
        }

        if ($tool_action === 'recompile') {
            $ok = $this->css_engine->compile_cache();
            $this->redirect_with_toast('tools', $ok ? 'CSS recompilado.' : 'Falha ao recompilar CSS.', $ok ? 'success' : 'error');
        }

        if ($tool_action === 'reset') {
            $this->save_history('Snapshot antes do reset');
            $this->settings_manager->save($this->settings_manager->get_defaults());
            $this->css_engine->compile_cache();
            $this->redirect_with_toast('tools', 'Configuracoes restauradas para o padrao.', 'success');
        }

        if ($tool_action === 'apply_preset') {
            $preset_key = isset($_POST['mlcssp_preset']) ? sanitize_key((string) wp_unslash($_POST['mlcssp_preset'])) : '';
            $presets = $this->settings_manager->get_builtin_presets();
            if (!isset($presets[$preset_key])) {
                $this->redirect_with_toast('presets', 'Preset nao encontrado.', 'error');
            }
            $this->save_history('Snapshot antes de aplicar preset');
            $settings = wp_parse_args($presets[$preset_key]['settings'], $this->settings_manager->get_settings());
            $settings['active_preset'] = sanitize_text_field((string) $presets[$preset_key]['label']);
            $this->settings_manager->save($this->settings_manager->sanitize_css_settings($settings));
            $this->css_engine->compile_cache();
            $this->redirect_with_toast('presets', 'Preset aplicado e CSS recompilado.', 'success');
        }

        if ($tool_action === 'restore_history') {
            $index = isset($_POST['mlcssp_history_index']) ? absint($_POST['mlcssp_history_index']) : -1;
            $history = get_option(MLCSSP_HISTORY_KEY, []);
            $history = is_array($history) ? $history : [];
            if (!isset($history[$index]) || !is_array($history[$index]) || empty($history[$index]['settings']) || !is_array($history[$index]['settings'])) {
                $this->redirect_with_toast('history', 'Revisao nao encontrada.', 'error');
            }
            $this->save_history('Snapshot antes de restaurar revisao');
            $restored = $this->settings_manager->sanitize_css_settings($history[$index]['settings']);
            $this->settings_manager->save($restored);
            $this->css_engine->compile_cache();
            $this->redirect_with_toast('history', 'Revisao restaurada e CSS recompilado.', 'success');
        }

        if ($tool_action === 'delete_history') {
            $index = isset($_POST['mlcssp_history_index']) ? absint($_POST['mlcssp_history_index']) : -1;
            $history = get_option(MLCSSP_HISTORY_KEY, []);
            $history = is_array($history) ? $history : [];
            if (isset($history[$index])) {
                array_splice($history, $index, 1);
                update_option(MLCSSP_HISTORY_KEY, $history, false);
                $this->redirect_with_toast('history', 'Revisao removida.', 'success');
            }
            $this->redirect_with_toast('history', 'Revisao nao encontrada.', 'error');
        }

        if ($tool_action === 'clear_history') {
            update_option(MLCSSP_HISTORY_KEY, [], false);
            $this->redirect_with_toast('history', 'Historico limpo.', 'success');
        }

        if ($tool_action === 'test_color_h1') {
            $test_sel = 'body h1';
            $test_prop = 'color';
            $test_val = '#ff0000';
            $result = MLCSSP_Scanner::save_override($test_sel, $test_prop, $test_val);
            if (empty($result['saved'])) {
                $this->redirect_with_toast('tools', 'Teste FALHOU: override rejeitado — ' . ($result['reason'] ?? ''), 'error');
            }
            $compiled = $this->css_engine->compile_cache();
            $asset = $this->css_engine->get_compiled_asset();
            $override_css = MLCSSP_Scanner::build_override_css();
            $in_compiled = false;
            if (!empty($asset['exists']) && !empty($asset['path'])) {
                $fc = (string) @file_get_contents((string) $asset['path']);
                $in_compiled = str_contains($fc, $test_sel) && str_contains($fc, $test_prop) && str_contains($fc, $test_val);
            }
            $in_inline = str_contains($override_css, $test_sel) && str_contains($override_css, $test_val);
            $this->store_last_save_diagnostic($test_sel, MLCSSP_Scanner::public_safe_selector($test_sel), $test_prop, $test_val, $compiled, $in_compiled);
            if ($in_compiled && $in_inline) {
                $this->redirect_with_toast('tools', 'Teste OK: body h1 color #ff0000 salvo, compilado e no fallback inline. Remova o teste manualmente se nao quiser manter.', 'success');
            } else {
                $this->redirect_with_toast('tools', 'Teste parcial: stored=' . (!empty($result['saved']) ? 'sim' : 'nao') . ' compiled=' . ($in_compiled ? 'sim' : 'nao') . ' inline=' . ($in_inline ? 'sim' : 'nao'), 'error');
            }
        }

        if ($tool_action === 'remove_test_color_h1') {
            MLCSSP_Scanner::remove_override('body h1', 'color');
            $this->css_engine->compile_cache();
            $this->redirect_with_toast('tools', 'Teste de cor removido e CSS recompilado.', 'success');
        }

        $this->redirect_with_toast('tools', 'Acao nao reconhecida.', 'error');
    }

    public function handle_scan(): void {
        MLCSSP_License::guard_write();
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'ml-css-pro'));
        }
        check_admin_referer('mlcssp_scan');
        $action = isset($_POST['mlcssp_scan_action']) ? sanitize_key((string) wp_unslash($_POST['mlcssp_scan_action'])) : '';

        $scanner = new MLCSSP_Scanner();

        if ($action === 'scan') {
            $url = isset($_POST['mlcssp_scan_url']) ? esc_url_raw((string) wp_unslash($_POST['mlcssp_scan_url'])) : '';
            $result = $scanner->scan($url);
            $this->redirect_with_toast('dashboard', $result['message'], $result['ok'] ? 'success' : 'error');
        }

        if ($action === 'clear_scan') {
            $scanner->clear();
            $this->redirect_with_toast('dashboard', 'Cache de varredura limpo.', 'success');
        }

        $this->redirect_with_toast('dashboard', 'Acao de varredura nao reconhecida.', 'error');
    }

    public function handle_override(): void {
        MLCSSP_License::guard_write();
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'ml-css-pro'));
        }
        check_admin_referer('mlcssp_override');
        $action   = isset($_POST['mlcssp_override_action']) ? sanitize_key((string) wp_unslash($_POST['mlcssp_override_action'])) : '';
        $selector = isset($_POST['mlcssp_selector']) ? sanitize_text_field((string) wp_unslash($_POST['mlcssp_selector'])) : '';
        $property = isset($_POST['mlcssp_property']) ? sanitize_text_field((string) wp_unslash($_POST['mlcssp_property'])) : '';
        $value    = isset($_POST['mlcssp_value']) ? sanitize_text_field((string) wp_unslash($_POST['mlcssp_value'])) : '';
        $tab      = isset($_POST['mlcssp_tab']) ? sanitize_key((string) wp_unslash($_POST['mlcssp_tab'])) : 'dashboard';

        if ($action === 'apply') {
            $result = MLCSSP_Scanner::save_override($selector, $property, $value);
            if (empty($result['saved'])) {
                $this->redirect_with_toast($tab, 'Override rejeitado: ' . ($result['reason'] ?? 'desconhecido'), 'error');
            }
            $this->css_engine->compile_cache();
            $this->redirect_with_toast($tab, 'Override aplicado e CSS recompilado.', 'success');
        }

        if ($action === 'remove') {
            MLCSSP_Scanner::remove_override($selector, $property);
            $this->css_engine->compile_cache();
            $this->redirect_with_toast($tab, 'Override removido e CSS recompilado.', 'success');
        }

        if ($action === 'clear_all') {
            MLCSSP_Scanner::clear_overrides();
            $this->css_engine->compile_cache();
            $this->redirect_with_toast($tab, 'Todos os overrides removidos e CSS recompilado.', 'success');
        }

        $this->redirect_with_toast($tab, 'Acao de override nao reconhecida.', 'error');
    }

    // ── Visual editor (frontend overlay) ──────────────────────────────────────

    public function maybe_enqueue_visual_editor(): void {
        if (!isset($_GET['mlcssp_visual']) || $_GET['mlcssp_visual'] !== '1') {
            return;
        }
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }
        if (is_admin()) {
            return;
        }
        // Detect current page context for page-specific editing.
        $page_ctx = $this->detect_page_context();

        wp_enqueue_style('mlcssp-visual', MLCSSP_URL . 'assets/visual.css', [], MLCSSP_VERSION);
        wp_enqueue_script('mlcssp-visual', MLCSSP_URL . 'assets/visual.js', [], MLCSSP_VERSION, true);
        wp_localize_script('mlcssp-visual', 'mlcsspVisual', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('mlcssp_visual_save'),
            'overrides'   => MLCSSP_Scanner::get_overrides(),
            'responsive'  => MLCSSP_Scanner::get_responsive_overrides(),
            'breakpoints' => MLCSSP_Scanner::BREAKPOINTS,
            'pageContext' => $page_ctx,
        ]);
        // Admin bar indicator.
        add_action('wp_footer', function () {
            echo '<div class="mlcssp-ve-admin-bar">ML CSS Pro — Editor Visual ativo <a href="' . esc_url(remove_query_arg('mlcssp_visual')) . '">Sair</a></div>';
        }, 99999);
    }

    /** Detect the current page's identity for page-specific editing. */
    private function detect_page_context(): array {
        $url = home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
        $post_id = 0;
        $title = '';
        $body_class = '';
        $post_type = '';
        if (is_singular()) {
            $post_id = (int) get_the_ID();
            $title = get_the_title($post_id);
            $post_type = (string) get_post_type($post_id);
            if (is_page()) {
                $body_class = 'page-id-' . $post_id;
            } else {
                $body_class = 'postid-' . $post_id;
            }
        } elseif (is_front_page() || is_home()) {
            $title = get_bloginfo('name') . ' — Home';
            $body_class = 'home';
            $front = (int) get_option('page_on_front');
            if ($front) {
                $body_class = 'page-id-' . $front;
                $post_id = $front;
            }
        } else {
            $title = wp_get_document_title();
        }
        $current_url = home_url($_SERVER['REQUEST_URI'] ?? '/');
        $current_url = remove_query_arg('mlcssp_visual', $current_url);
        return [
            'url'        => esc_url_raw($current_url),
            'title'      => $title,
            'post_id'    => $post_id,
            'post_type'  => $post_type,
            'body_class' => $body_class,
            'page_key'   => $body_class !== '' ? sanitize_key($body_class) : sanitize_key('url-' . md5($current_url)),
        ];
    }

    public function handle_visual_save(): void {
        check_ajax_referer('mlcssp_visual_save');
        MLCSSP_License::guard_write(true);
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized.', 403);
        }
        $selector = isset($_POST['selector']) ? sanitize_text_field(wp_unslash((string) $_POST['selector'])) : '';
        $property = isset($_POST['property']) ? sanitize_text_field(wp_unslash((string) $_POST['property'])) : '';
        $value    = isset($_POST['value']) ? sanitize_text_field(wp_unslash((string) $_POST['value'])) : '';
        $device   = isset($_POST['device']) ? sanitize_key(wp_unslash((string) $_POST['device'])) : 'desktop';
        if (!in_array($device, ['desktop', 'tablet', 'mobile'], true)) {
            $device = 'desktop';
        }
        if ($selector === '' || $property === '' || $value === '') {
            wp_send_json_error('Dados incompletos.');
        }
        $page_scope = isset($_POST['page_scope']) ? sanitize_key(wp_unslash((string) $_POST['page_scope'])) : 'global';

        // Page-specific save path (additive — does not touch global storage).
        if ($page_scope === 'page') {
            $page_key   = isset($_POST['page_key']) ? sanitize_key(wp_unslash((string) $_POST['page_key'])) : '';
            $page_url   = isset($_POST['page_url']) ? esc_url_raw(wp_unslash((string) $_POST['page_url'])) : '';
            $page_title = isset($_POST['page_title']) ? sanitize_text_field(wp_unslash((string) $_POST['page_title'])) : '';
            $body_class = isset($_POST['body_class']) ? sanitize_html_class(wp_unslash((string) $_POST['body_class'])) : '';
            if ($page_key === '' || $body_class === '') {
                wp_send_json_error('Contexto de pagina ausente. Use Site inteiro ou recarregue a pagina.');
            }
            $pr = MLCSSP_Scanner::save_page_override($page_key, ['url' => $page_url, 'title' => $page_title, 'body_class' => $body_class], $device, $selector, $property, $value);
            if (empty($pr['saved'])) {
                wp_send_json_error('Override de pagina rejeitado: ' . ($pr['reason'] ?? 'desconhecido'));
            }
            $compiled = $this->css_engine->compile_cache();
            $page_css = MLCSSP_Scanner::build_page_css();
            $prefix = MLCSSP_Scanner::page_body_selector($body_class);
            $in_css = $prefix !== '' && str_contains($page_css, $prefix) && str_contains($page_css, $property);
            if (!$in_css) {
                wp_send_json_error('Override de pagina salvo mas nao aparece no CSS. Verifique a classe do body.');
            }
            $rhist = get_option('mlcsspro_responsive_history', []);
            if (!is_array($rhist)) { $rhist = []; }
            array_unshift($rhist, ['selector' => $prefix . ' ' . $selector, 'property' => $property, 'value' => $value, 'device' => $device . ' (pagina)', 'time' => current_time('mysql')]);
            update_option('mlcsspro_responsive_history', array_slice($rhist, 0, 50), false);
            wp_send_json_success([
                'message'    => 'Salvo apenas nesta pagina (' . $device . '), CSS recompilado.',
                'scope'      => 'page',
                'device'     => $device,
                'page_title' => $page_title,
                'selector'   => $prefix . ' ' . $selector,
            ]);
        }

        $result = MLCSSP_Scanner::save_override_device($device, $selector, $property, $value);
        if (empty($result['saved'])) {
            wp_send_json_error('Override rejeitado: ' . ($result['reason'] ?? 'desconhecido'));
        }
        $compiled = $this->css_engine->compile_cache();
        $asset = $this->css_engine->get_compiled_asset();

        // Hard-verify the override persists in compiled output.
        $override_css = MLCSSP_Scanner::build_override_css();
        $safe_sel = MLCSSP_Scanner::public_safe_selector($selector);
        $in_override = str_contains($override_css, $property) && str_contains($override_css, $safe_sel);
        // For tablet/mobile, also confirm the media query is present.
        if ($device !== 'desktop') {
            $bp = MLCSSP_Scanner::BREAKPOINTS[$device] ?? '';
            $in_override = $in_override && str_contains($override_css, $bp);
        }
        $in_file = false;
        if (!empty($asset['exists']) && !empty($asset['path'])) {
            $file_content = (string) @file_get_contents((string) $asset['path']);
            $in_file = str_contains($file_content, $property) && str_contains($file_content, $safe_sel);
        }

        if (!$in_override) {
            wp_send_json_error('Override salvo mas NAO aparece no CSS gerado. Propriedade: ' . $property);
        }
        if (!$compiled || !$in_file) {
            $this->store_last_save_diagnostic($selector, $safe_sel, $property, $value, false, false);
            wp_send_json_success([
                'message'     => 'Override salvo (' . $device . '). Fallback inline ativo.',
                'compiled'    => false,
                'fallback'    => true,
                'device'      => $device,
                'file_exists' => $asset['exists'] ?? false,
                'selector'    => $safe_sel,
            ]);
        }

        $this->store_last_save_diagnostic($selector, $safe_sel, $property, $value, true, true);

        // Responsive history record.
        $rhist = get_option('mlcsspro_responsive_history', []);
        if (!is_array($rhist)) { $rhist = []; }
        array_unshift($rhist, [
            'selector' => $selector,
            'property' => $property,
            'value' => $value,
            'device' => $device,
            'time' => current_time('mysql'),
        ]);
        $rhist = array_slice($rhist, 0, 50);
        update_option('mlcsspro_responsive_history', $rhist, false);

        wp_send_json_success([
            'message'     => 'Override salvo (' . $device . '), CSS compilado e verificado.',
            'compiled'    => true,
            'fallback'    => true,
            'file_exists' => true,
            'file_size'   => $asset['size'] ?? '0 B',
            'file_url'    => $asset['url'] ?? '',
            'selector'    => $safe_sel,
        ]);
    }

    /** Store diagnostic info about the last visual save. */
    private function store_last_save_diagnostic(string $selector, string $safe_sel, string $property, string $value, bool $compiled, bool $in_file): void {
        update_option('mlcsspro_last_save', [
            'selector' => $selector,
            'safe_selector' => $safe_sel,
            'property' => $property,
            'value' => $value,
            'compiled' => $compiled,
            'in_file' => $in_file,
            'time' => current_time('mysql'),
        ], false);
    }

    public function handle_page_search(): void {
        check_ajax_referer('mlcssp_page_search');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized.', 403);
        }
        $q = isset($_POST['q']) ? sanitize_text_field(wp_unslash((string) $_POST['q'])) : '';
        $show_drafts = isset($_POST['drafts']) && $_POST['drafts'] === '1';

        $public_types = get_post_types(['public' => true], 'names');
        unset($public_types['attachment']);
        $statuses = $show_drafts ? ['publish', 'draft', 'private', 'pending'] : ['publish'];

        $args = [
            'post_type'      => array_values($public_types),
            'post_status'    => $statuses,
            'posts_per_page' => 20,
            'orderby'        => $q !== '' ? 'relevance' : 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];
        if ($q !== '') {
            $args['s'] = $q;
        }
        $query = new WP_Query($args);
        $results = [];

        // Always include Home first when no search term.
        if ($q === '') {
            $results[] = [
                'title'  => get_bloginfo('name') . ' — Home',
                'type'   => 'home',
                'status' => 'publish',
                'url'    => home_url('/'),
            ];
        }
        foreach ($query->posts as $post) {
            $results[] = [
                'title'  => get_the_title($post) ?: '(sem titulo)',
                'type'   => $post->post_type,
                'status' => $post->post_status,
                'url'    => get_permalink($post),
            ];
        }
        wp_send_json_success($results);
    }

    public function handle_visual_reset(): void {
        check_ajax_referer('mlcssp_visual_save');
        MLCSSP_License::guard_write(true);
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized.', 403);
        }
        $selector = isset($_POST['selector']) ? sanitize_text_field(wp_unslash((string) $_POST['selector'])) : '';
        $property = isset($_POST['property']) ? sanitize_text_field(wp_unslash((string) $_POST['property'])) : '';
        $device   = isset($_POST['device']) ? sanitize_key(wp_unslash((string) $_POST['device'])) : 'desktop';
        if (!in_array($device, ['desktop', 'tablet', 'mobile'], true)) {
            $device = 'desktop';
        }
        if ($selector === '' || $property === '') {
            wp_send_json_error('Dados incompletos.');
        }
        $page_scope = isset($_POST['page_scope']) ? sanitize_key(wp_unslash((string) $_POST['page_scope'])) : 'global';

        // Page-specific reset — only touches this page's overrides.
        if ($page_scope === 'page') {
            $page_key = isset($_POST['page_key']) ? sanitize_key(wp_unslash((string) $_POST['page_key'])) : '';
            if ($page_key === '') {
                wp_send_json_error('Contexto de pagina ausente.');
            }
            if ($selector === '__page__') {
                // Reset the entire page (all selectors/devices) — never other pages.
                MLCSSP_Scanner::remove_page_override($page_key);
            } else {
                $dev = $device === 'desktop' && empty($_POST['device']) ? '' : $device;
                MLCSSP_Scanner::remove_page_override($page_key, $dev, $selector, $property);
            }
            $this->css_engine->compile_cache();
            wp_send_json_success(['message' => 'Reset de pagina aplicado e CSS recompilado.', 'scope' => 'page']);
        }

        if ($device !== 'desktop') {
            // Tablet/mobile reset — scoped to that device only.
            MLCSSP_Scanner::remove_override_device($device, $selector, $property);
            MLCSSP_Scanner::remove_override_device($device, MLCSSP_Scanner::public_safe_selector($selector), $property);
        } elseif ($property === '__all__') {
            // Remove all desktop overrides for this selector only — never touch others.
            $overrides = MLCSSP_Scanner::get_overrides();
            $safe = MLCSSP_Scanner::public_safe_selector($selector);
            unset($overrides[$selector], $overrides[$safe]);
            update_option(MLCSSP_Scanner::OVERRIDES_KEY, $overrides, false);
        } else {
            MLCSSP_Scanner::remove_override($selector, $property);
            // Also remove from the body-prefixed variant if stored that way.
            MLCSSP_Scanner::remove_override(MLCSSP_Scanner::public_safe_selector($selector), $property);
        }
        $this->css_engine->compile_cache();
        wp_send_json_success(['message' => 'Reset aplicado (' . $device . ') e CSS recompilado.', 'device' => $device]);
    }

    private function save_history(string $label): void {
        $history = get_option(MLCSSP_HISTORY_KEY, []);
        if (!is_array($history)) {
            $history = [];
        }
        $settings = $this->settings_manager->get_settings();
        $css_bytes = strlen((string) ($settings['global_css'] ?? '')) + strlen((string) ($settings['nicepage_css'] ?? ''));
        array_unshift($history, [
            'label' => sanitize_text_field($label),
            'time' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'css_size' => MLCSSP_Helpers::format_bytes($css_bytes),
            'css_bytes' => $css_bytes,
            'settings' => $settings,
        ]);
        $history = array_slice($history, 0, 20);
        update_option(MLCSSP_HISTORY_KEY, $history, false);
    }

    private function posted_tab(string $fallback): string {
        $tab = isset($_POST['mlcssp_tab']) ? sanitize_key((string) wp_unslash($_POST['mlcssp_tab'])) : $fallback;
        return $tab !== '' ? $tab : $fallback;
    }

    private function redirect_with_toast(string $tab, string $message, string $type): void {
        $url = add_query_arg([
            'page' => 'ml-css-pro',
            'tab' => $tab,
            'mlcssp_toast' => rawurlencode($message),
            'mlcssp_toast_type' => $type,
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
