<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MLCSSP_Settings {
    public function get_defaults(): array {
        return [
            'plugin_title' => 'ML CSS Pro',
            'plugin_subtitle' => 'Painel visual para alterar cores, fontes, botoes e secoes sem escrever CSS',
            'menu_label' => 'ML CSS Pro',
            'primary_status' => 'CSS controlado',
            'secondary_status' => 'Nicepage seguro',
            'accent_note' => 'Use os controles visuais para gerar CSS automaticamente. O editor de codigo fica apenas como modo avancado.',
            'enable_license_tab' => '1',
            'enable_reports_tab' => '1',
            'css_enabled' => '1',
            'css_position' => 'head',
            'css_priority' => '9999',
            'css_minify' => '1',
            'css_safe_mode' => '1',
            'nicepage_mode' => 'detected',
            'nicepage_scope' => 'body.mlcssp-nicepage-page',
            'visual_enabled' => '1',
            'visual_target' => 'site',
            'site_bg_color' => '',
            'site_text_color' => '',
            'heading_color' => '',
            'heading_hover_color' => '',
            'heading_font' => '',
            'heading_size' => '',
            'heading_weight' => '',
            'heading_line_height' => '',
            'heading_letter_spacing' => '',
            'body_font' => '',
            'body_size' => '',
            'body_line_height' => '',
            'link_color' => '',
            'link_hover_color' => '',
            'button_bg_color' => '',
            'button_text_color' => '',
            'button_hover_bg_color' => '',
            'button_hover_text_color' => '',
            'button_border_color' => '',
            'button_border_width' => '',
            'button_radius' => '',
            'button_padding_y' => '',
            'button_padding_x' => '',
            'button_shadow' => '0',
            'button_hover_animation' => '0',
            'button_text_transform' => '',
            'header_bg_color' => '',
            'header_text_color' => '',
            'header_hover_color' => '',
            'header_height' => '',
            'header_item_gap' => '',
            'header_font_size' => '',
            'header_font_weight' => '',
            'header_sticky_bg_color' => '',
            'header_border_color' => '',
            'header_shadow' => '0',
            'footer_bg_color' => '',
            'footer_text_color' => '',
            'footer_link_color' => '',
            'footer_link_hover_color' => '',
            'footer_padding_y' => '',
            'footer_font_size' => '',
            'footer_title_color' => '',
            'footer_divider_color' => '',
            'container_max_width' => '',
            'container_padding_x' => '',
            'block_spacing_y' => '',
            'section_bg_color' => '',
            'section_padding_y' => '',
            'card_bg_color' => '',
            'card_radius' => '',
            'card_shadow' => '0',
            'form_field_bg_color' => '',
            'form_field_text_color' => '',
            'form_placeholder_color' => '',
            'form_border_color' => '',
            'form_focus_border_color' => '',
            'form_radius' => '',
            'form_field_height' => '',
            'tablet_body_size' => '',
            'tablet_heading_size' => '',
            'tablet_section_padding_y' => '',
            'tablet_container_width' => '',
            'mobile_body_size' => '',
            'mobile_heading_size' => '',
            'mobile_section_padding_y' => '',
            'mobile_container_width' => '',
            'mobile_stack_blocks' => '0',
            'page_override_enabled' => '0',
            'page_override_id' => '',
            'custom_selector' => '',
            'custom_color' => '',
            'custom_bg_color' => '',
            'custom_font' => '',
            'custom_size' => '',
            'global_css' => '',
            'nicepage_css' => '',
        ];
    }

    public function get_builtin_presets(): array {
        return [
            'clean-claro' => [
                'label' => 'Clean Claro',
                'settings' => [
                    'site_bg_color' => '#ffffff',
                    'site_text_color' => '#102a43',
                    'heading_color' => '#102a43',
                    'link_color' => '#155e6f',
                    'button_bg_color' => '#155e6f',
                    'button_text_color' => '#ffffff',
                    'button_radius' => '8px',
                    'body_font' => 'system-ui',
                    'heading_font' => 'Georgia',
                ],
            ],
            'institucional-azul' => [
                'label' => 'Institucional Azul',
                'settings' => [
                    'site_bg_color' => '#f3f7f8',
                    'site_text_color' => '#102a43',
                    'heading_color' => '#114b5a',
                    'link_color' => '#155e6f',
                    'button_bg_color' => '#114b5a',
                    'button_text_color' => '#ffffff',
                    'header_bg_color' => '#114b5a',
                    'header_text_color' => '#ffffff',
                ],
            ],
            'editorial-neutro' => [
                'label' => 'Editorial Neutro',
                'settings' => [
                    'site_bg_color' => '#f7f7f5',
                    'site_text_color' => '#242424',
                    'heading_color' => '#111111',
                    'link_color' => '#4a4a4a',
                    'button_bg_color' => '#111111',
                    'button_text_color' => '#ffffff',
                    'heading_font' => 'Georgia',
                    'body_font' => 'Verdana',
                ],
            ],
            'pastel-elegante' => [
                'label' => 'Pastel Elegante',
                'settings' => [
                    'site_bg_color' => '#fff7f2',
                    'site_text_color' => '#463f3a',
                    'heading_color' => '#6d597a',
                    'link_color' => '#b56576',
                    'button_bg_color' => '#b56576',
                    'button_text_color' => '#ffffff',
                    'section_bg_color' => '#f4e8e1',
                ],
            ],
            'escuro-sofisticado' => [
                'label' => 'Escuro Sofisticado',
                'settings' => [
                    'site_bg_color' => '#121212',
                    'site_text_color' => '#f3f4f6',
                    'heading_color' => '#ffffff',
                    'link_color' => '#8dd3c7',
                    'button_bg_color' => '#8dd3c7',
                    'button_text_color' => '#121212',
                    'card_bg_color' => '#1f2937',
                ],
            ],
        ];
    }

    /**
     * Practical, ready-to-use CSS snippets organized by category. Inserted into
     * the editor at the cursor without overwriting existing CSS.
     *
     * @return array<string,array{label:string,items:array<int,array{key:string,label:string,code:string}>}>
     */
    public function get_builtin_snippets(): array {
        return [
            'buttons' => [
                'label' => 'Botoes',
                'items' => [
                    [
                        'key' => 'btn_rounded',
                        'label' => 'Botao arredondado moderno',
                        'code' => ".btn, .button, .wp-block-button__link, .u-btn {\n  border-radius: 10px;\n  padding: 12px 24px;\n  font-weight: 600;\n  transition: all .2s ease;\n}",
                    ],
                    [
                        'key' => 'btn_hover_lift',
                        'label' => 'Hover com elevacao',
                        'code' => ".btn:hover, .button:hover, .wp-block-button__link:hover, .u-btn:hover {\n  transform: translateY(-2px);\n  box-shadow: 0 12px 28px rgba(15, 23, 42, .18);\n}",
                    ],
                    [
                        'key' => 'btn_outline',
                        'label' => 'Botao outline',
                        'code' => ".btn-outline {\n  background: transparent;\n  border: 2px solid currentColor;\n  border-radius: 8px;\n  padding: 10px 22px;\n}",
                    ],
                ],
            ],
            'menus' => [
                'label' => 'Menus',
                'items' => [
                    [
                        'key' => 'menu_spacing',
                        'label' => 'Espacamento e hover do menu',
                        'code' => ".menu a, .u-nav a, .h-navigation a {\n  padding: 8px 14px;\n  transition: color .2s ease;\n}\n.menu a:hover, .u-nav a:hover, .h-navigation a:hover {\n  opacity: .85;\n}",
                    ],
                    [
                        'key' => 'menu_underline',
                        'label' => 'Sublinhado animado no item',
                        'code' => ".menu a {\n  position: relative;\n}\n.menu a::after {\n  content: \"\";\n  position: absolute;\n  left: 14px;\n  right: 14px;\n  bottom: 4px;\n  height: 2px;\n  background: currentColor;\n  transform: scaleX(0);\n  transition: transform .2s ease;\n}\n.menu a:hover::after {\n  transform: scaleX(1);\n}",
                    ],
                ],
            ],
            'nicepage' => [
                'label' => 'Secoes Nicepage',
                'items' => [
                    [
                        'key' => 'np_section_padding',
                        'label' => 'Padding das secoes Nicepage',
                        'code' => "body.mlcssp-nicepage-page [class*=\"u-section-\"] {\n  padding-top: 60px;\n  padding-bottom: 60px;\n}",
                    ],
                    [
                        'key' => 'np_sheet_width',
                        'label' => 'Largura do conteudo (u-sheet)',
                        'code' => "body.mlcssp-nicepage-page .u-sheet {\n  max-width: 1200px;\n  margin-left: auto;\n  margin-right: auto;\n}",
                    ],
                    [
                        'key' => 'np_card',
                        'label' => 'Card Nicepage com sombra',
                        'code' => "body.mlcssp-nicepage-page .u-container-style {\n  border-radius: 16px;\n  box-shadow: 0 18px 42px rgba(15, 23, 42, .12);\n}",
                    ],
                ],
            ],
            'typography' => [
                'label' => 'Tipografia',
                'items' => [
                    [
                        'key' => 'type_readable',
                        'label' => 'Leitura confortavel',
                        'code' => "body {\n  line-height: 1.7;\n  letter-spacing: .01em;\n}\np {\n  margin-bottom: 1.1em;\n}",
                    ],
                    [
                        'key' => 'type_headings',
                        'label' => 'Titulos com peso e espaco',
                        'code' => "h1, h2, h3, .u-title {\n  font-weight: 700;\n  line-height: 1.2;\n  margin-bottom: .5em;\n}",
                    ],
                ],
            ],
            'forms' => [
                'label' => 'Formularios',
                'items' => [
                    [
                        'key' => 'form_fields',
                        'label' => 'Campos com foco visivel',
                        'code' => "input, textarea, select, .u-input {\n  border-radius: 8px;\n  border: 1px solid #d9e2ec;\n  padding: 10px 12px;\n}\ninput:focus, textarea:focus, select:focus {\n  outline: 2px solid #155e6f;\n  outline-offset: 1px;\n}",
                    ],
                ],
            ],
            'spacing' => [
                'label' => 'Espacamento',
                'items' => [
                    [
                        'key' => 'spacing_rhythm',
                        'label' => 'Ritmo vertical entre blocos',
                        'code' => ".entry-content > * + * {\n  margin-top: 1.5rem;\n}",
                    ],
                    [
                        'key' => 'spacing_container',
                        'label' => 'Respiro lateral no container',
                        'code' => ".container, .u-sheet, .entry-content {\n  padding-left: 20px;\n  padding-right: 20px;\n}",
                    ],
                ],
            ],
            'responsive' => [
                'label' => 'Correcoes responsivas',
                'items' => [
                    [
                        'key' => 'resp_images',
                        'label' => 'Imagens fluidas',
                        'code' => "img {\n  max-width: 100%;\n  height: auto;\n}",
                    ],
                    [
                        'key' => 'resp_stack',
                        'label' => 'Empilhar colunas no celular',
                        'code' => "@media (max-width: 767px) {\n  .u-layout-row, .wp-block-columns {\n    display: block;\n  }\n  h1, .u-title {\n    font-size: 26px;\n  }\n}",
                    ],
                ],
            ],
        ];
    }

    public function get_settings(): array {
        $saved = get_option(MLCSSP_OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        return wp_parse_args($saved, $this->get_defaults());
    }

    public function save(array $settings): void {
        update_option(MLCSSP_OPTION_KEY, wp_parse_args($settings, $this->get_defaults()), false);
    }

    public function register(): void {
        register_setting('mlcssp_settings_group', MLCSSP_OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_all'],
            'default' => $this->get_defaults(),
        ]);
    }

    public function sanitize_all($input): array {
        $input = is_array($input) ? $input : [];
        $settings = $this->sanitize_settings($input);
        return $this->sanitize_css_settings($input, $settings);
    }

    public function sanitize_settings($input): array {
        $input = is_array($input) ? $input : [];
        $current = $this->get_settings();
        $current['plugin_title'] = sanitize_text_field((string) ($input['plugin_title'] ?? $current['plugin_title']));
        $current['plugin_subtitle'] = sanitize_text_field((string) ($input['plugin_subtitle'] ?? $current['plugin_subtitle']));
        $current['menu_label'] = sanitize_text_field((string) ($input['menu_label'] ?? $current['menu_label']));
        $current['primary_status'] = sanitize_text_field((string) ($input['primary_status'] ?? $current['primary_status']));
        $current['secondary_status'] = sanitize_text_field((string) ($input['secondary_status'] ?? $current['secondary_status']));
        $current['accent_note'] = sanitize_textarea_field((string) ($input['accent_note'] ?? $current['accent_note']));
        $current['enable_license_tab'] = empty($input['enable_license_tab']) ? '0' : '1';
        $current['enable_reports_tab'] = empty($input['enable_reports_tab']) ? '0' : '1';
        return $current;
    }

    public function sanitize_css_settings($input, ?array $base = null): array {
        $input = is_array($input) ? $input : [];
        $current = $base ?: $this->get_settings();
        $position = sanitize_key((string) ($input['css_position'] ?? $current['css_position']));
        $nicepage_mode = sanitize_key((string) ($input['nicepage_mode'] ?? $current['nicepage_mode']));

        $current['css_enabled'] = array_key_exists('css_enabled', $input) ? (empty($input['css_enabled']) ? '0' : '1') : $current['css_enabled'];
        $current['css_position'] = in_array($position, ['head', 'footer'], true) ? $position : 'head';
        $current['css_priority'] = (string) max(1, min(99999, absint($input['css_priority'] ?? $current['css_priority'])));
        $current['css_minify'] = array_key_exists('css_minify', $input) ? (empty($input['css_minify']) ? '0' : '1') : $current['css_minify'];
        $current['css_safe_mode'] = array_key_exists('css_safe_mode', $input) ? (empty($input['css_safe_mode']) ? '0' : '1') : $current['css_safe_mode'];
        $current['nicepage_mode'] = in_array($nicepage_mode, ['detected', 'all', 'disabled'], true) ? $nicepage_mode : 'detected';
        $current['nicepage_scope'] = sanitize_text_field((string) ($input['nicepage_scope'] ?? $current['nicepage_scope']));
        $current['visual_enabled'] = array_key_exists('visual_enabled', $input) ? (empty($input['visual_enabled']) ? '0' : '1') : $current['visual_enabled'];
        $current['visual_target'] = $this->sanitize_choice((string) ($input['visual_target'] ?? $current['visual_target']), ['site', 'nicepage', 'both'], 'site');
        foreach ([
            'site_bg_color', 'site_text_color', 'heading_color', 'heading_hover_color', 'link_color', 'link_hover_color',
            'button_bg_color', 'button_text_color', 'button_hover_bg_color', 'button_hover_text_color', 'button_border_color',
            'header_bg_color', 'header_text_color', 'header_hover_color', 'header_sticky_bg_color', 'header_border_color',
            'footer_bg_color', 'footer_text_color', 'footer_link_color', 'footer_link_hover_color', 'footer_title_color', 'footer_divider_color',
            'section_bg_color', 'card_bg_color',
            'form_field_bg_color', 'form_field_text_color', 'form_placeholder_color', 'form_border_color', 'form_focus_border_color',
            'custom_color', 'custom_bg_color',
        ] as $key) {
            $current[$key] = $this->sanitize_color((string) ($input[$key] ?? $current[$key]));
        }
        foreach (['heading_font', 'body_font', 'custom_font'] as $key) {
            $current[$key] = $this->sanitize_font((string) ($input[$key] ?? $current[$key]));
        }
        foreach ([
            'heading_size', 'heading_line_height', 'heading_letter_spacing', 'body_size', 'body_line_height',
            'button_border_width', 'button_radius', 'button_padding_y', 'button_padding_x',
            'header_height', 'header_item_gap', 'header_font_size', 'footer_padding_y', 'footer_font_size',
            'container_max_width', 'container_padding_x', 'block_spacing_y', 'section_padding_y', 'card_radius',
            'form_radius', 'form_field_height',
            'tablet_body_size', 'tablet_heading_size', 'tablet_section_padding_y', 'tablet_container_width',
            'mobile_body_size', 'mobile_heading_size', 'mobile_section_padding_y', 'mobile_container_width',
            'custom_size',
        ] as $key) {
            $current[$key] = $this->sanitize_css_length((string) ($input[$key] ?? $current[$key]));
        }
        foreach (['heading_weight', 'header_font_weight'] as $key) {
            $current[$key] = $this->sanitize_weight((string) ($input[$key] ?? $current[$key]));
        }
        $current['button_text_transform'] = $this->sanitize_choice((string) ($input['button_text_transform'] ?? $current['button_text_transform']), ['', 'none', 'uppercase', 'lowercase', 'capitalize'], '');
        $current['card_shadow'] = array_key_exists('card_shadow', $input) ? (empty($input['card_shadow']) ? '0' : '1') : $current['card_shadow'];
        $current['button_shadow'] = array_key_exists('button_shadow', $input) ? (empty($input['button_shadow']) ? '0' : '1') : $current['button_shadow'];
        $current['button_hover_animation'] = array_key_exists('button_hover_animation', $input) ? (empty($input['button_hover_animation']) ? '0' : '1') : $current['button_hover_animation'];
        $current['header_shadow'] = array_key_exists('header_shadow', $input) ? (empty($input['header_shadow']) ? '0' : '1') : $current['header_shadow'];
        $current['mobile_stack_blocks'] = array_key_exists('mobile_stack_blocks', $input) ? (empty($input['mobile_stack_blocks']) ? '0' : '1') : $current['mobile_stack_blocks'];
        $current['page_override_enabled'] = array_key_exists('page_override_enabled', $input) ? (empty($input['page_override_enabled']) ? '0' : '1') : $current['page_override_enabled'];
        $current['page_override_id'] = (string) absint($input['page_override_id'] ?? $current['page_override_id']);
        $current['custom_selector'] = $this->sanitize_selector((string) ($input['custom_selector'] ?? $current['custom_selector']));
        $current['global_css'] = MLCSSP_CSS_Engine::sanitize_css((string) ($input['global_css'] ?? $current['global_css']));
        $current['nicepage_css'] = MLCSSP_CSS_Engine::sanitize_css((string) ($input['nicepage_css'] ?? $current['nicepage_css']));
        return $current;
    }

    private function sanitize_choice(string $value, array $allowed, string $fallback): string {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function sanitize_color(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
            return strtolower($value);
        }
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(\s*,\s*(0|1|0?\.\d+))?\s*\)$/', $value)) {
            return $value;
        }
        return '';
    }

    private function sanitize_font(string $value): string {
        $value = sanitize_text_field($value);
        $allowed = ['Arial', 'Helvetica', 'Georgia', 'Times New Roman', 'Verdana', 'Tahoma', 'Trebuchet MS', 'Courier New', 'Roboto', 'Open Sans', 'Montserrat', 'Poppins', 'Lato', 'Inter', 'system-ui'];
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_weight(string $value): string {
        $allowed = ['', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold'];
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function sanitize_css_length(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{1,4}(\.\d{1,2})?(px|rem|em|%)$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{1,4}$/', $value)) {
            return $value . 'px';
        }
        return '';
    }

    private function sanitize_selector(string $value): string {
        $value = trim(wp_strip_all_tags($value));
        if ($value === '') {
            return '';
        }
        if (strlen($value) > 180 || preg_match('/[{}<>;]/', $value)) {
            return '';
        }
        return $value;
    }
}
