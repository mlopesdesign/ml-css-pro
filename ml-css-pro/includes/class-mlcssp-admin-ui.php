<?php
if (!defined('ABSPATH')) {
    exit;
}

final class MLCSSP_Admin_UI {

    public function render(array $settings, array $license_state, array $license_config, array $css_report, string $active_tab, string $toast_message = '', string $toast_type = 'success', ?MLCSSP_License $license_manager = null): void {
        $tabs = [
            'dashboard' => 'Dashboard',
            'visual' => 'Visual',
            'cores' => 'Cores',
            'tipografia' => 'Tipografia',
            'botoes' => 'Botoes',
            'header_tab' => 'Header',
            'footer_tab' => 'Rodape',
            'forms_tab' => 'Formularios',
            'layout' => 'Layout',
            'pages' => 'Paginas',
            'presets' => 'Presets',
            'snippets' => 'Snippets',
            'history' => 'Historico',
            'inspector' => 'Seletores',
            'advanced' => 'Avancado',
            'license' => 'Licenca',
            'tools' => 'Ferramentas',
        ];
        if (!isset($tabs[$active_tab])) {
            $active_tab = 'dashboard';
        }
        if ($license_manager === null) {
            $license_manager = new MLCSSP_License();
        }
        $cache = (array) ($css_report['cache'] ?? []);
        $scan_meta = (array) ($css_report['scan_meta'] ?? []);
        $scan_results = (array) ($css_report['scan_results'] ?? []);
        $scan_overrides = (array) ($css_report['scan_overrides'] ?? []);
        ?>
        <div class="mlcssp-wrap" style="--mlcssp-brand:#155e6f;--mlcssp-brand-dark:#0d3f4c;--mlcssp-brand-soft:#e5f3f5;--mlcssp-ink:#1a2b3b;--mlcssp-muted:#6b7a8d;--mlcssp-line:#dfe6ec;">
            <div id="mlcssp-toast-area"></div>
            <?php if ($toast_message !== '') : ?>
                <script>document.addEventListener('DOMContentLoaded',function(){var a=document.getElementById('mlcssp-toast-area');if(!a)return;var e=document.createElement('div');e.className='mlcssp-toast mlcssp-toast-<?php echo esc_attr($toast_type); ?>';e.textContent=<?php echo wp_json_encode($toast_message); ?>;a.appendChild(e);requestAnimationFrame(function(){e.classList.add('is-visible')});setTimeout(function(){e.classList.remove('is-visible');setTimeout(function(){if(e.parentNode)e.parentNode.removeChild(e)},220)},4000)});</script>
            <?php endif; ?>
            <header class="mlcssp-header">
                <div class="mlcssp-header-left">
                    <?php if (file_exists(MLCSSP_DIR . 'assets/images/logo-wordpress.png')) : ?>
                        <img src="<?php echo esc_url(MLCSSP_URL . 'assets/images/logo-wordpress.png'); ?>" alt="ML CSS Pro" width="40" height="40" class="mlcssp-logo">
                    <?php endif; ?>
                    <div><h1 class="mlcssp-title"><?php echo esc_html((string) ($settings['plugin_title'] ?? 'ML CSS Pro')); ?></h1><p class="mlcssp-muted">v<?php echo esc_html(MLCSSP_VERSION); ?></p></div>
                </div>
            </header>
            <nav class="mlcssp-tab-nav" aria-label="Navegacao do plugin">
                <?php foreach ($tabs as $tab_key => $tab_label) : ?>
                    <button type="button" class="mlcssp-tab-button <?php echo $active_tab === $tab_key ? 'is-active' : ''; ?>" data-tab-target="mlcssp-tab-<?php echo esc_attr($tab_key); ?>"><?php echo esc_html($tab_label); ?></button>
                <?php endforeach; ?>
            </nav>
            <?php $this->dashboard_panel($active_tab, $settings, $css_report, $license_state, $license_manager, $scan_meta, $scan_results, $scan_overrides); ?>
            <?php $this->visual_panel($active_tab); ?>
            <?php $this->detected_category_panel('cores', $active_tab, $scan_results, $scan_overrides, ['body', 'headings', 'paragraphs', 'links'], ['color', 'background-color', 'background']); ?>
            <?php $this->detected_category_panel('tipografia', $active_tab, $scan_results, $scan_overrides, ['body', 'headings', 'paragraphs', 'links'], ['font-family', 'font-size', 'font-weight', 'line-height', 'text-transform', 'letter-spacing']); ?>
            <?php $this->detected_category_panel('botoes', $active_tab, $scan_results, $scan_overrides, ['buttons'], null); ?>
            <?php $this->detected_category_panel('header_tab', $active_tab, $scan_results, $scan_overrides, ['header', 'menus'], null); ?>
            <?php $this->detected_category_panel('footer_tab', $active_tab, $scan_results, $scan_overrides, ['footer'], null); ?>
            <?php $this->detected_category_panel('forms_tab', $active_tab, $scan_results, $scan_overrides, ['forms'], null); ?>
            <?php $this->detected_category_panel('layout', $active_tab, $scan_results, $scan_overrides, ['sections', 'cards', 'nicepage'], ['padding', 'margin', 'width', 'max-width', 'border-radius']); ?>
            <?php $this->pages_panel($active_tab, $settings); ?>
            <?php $this->presets_panel($active_tab); ?>
            <?php $this->snippets_panel($active_tab); ?>
            <?php $this->history_panel($active_tab); ?>
            <?php $this->inspector_panel($active_tab, $css_report); ?>
            <?php $this->advanced_panel($active_tab, $settings, $css_report); ?>
            <?php $this->license_panel($active_tab, $license_state, $license_config, $license_manager); ?>
            <?php $this->tools_panel($active_tab, $settings, $css_report); ?>
        </div>
        <?php
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    private function dashboard_panel(string $at, array $settings, array $report, array $ls, MLCSSP_License $lm, array $scan_meta, array $scan_results, array $overrides): void {
        $cache = (array) ($report['cache'] ?? []);
        ?>
        <section id="mlcssp-tab-dashboard" class="mlcssp-tab-panel <?php echo $at === 'dashboard' ? 'is-active' : ''; ?>" <?php echo $at === 'dashboard' ? '' : 'hidden'; ?>>
            <div class="mlcssp-grid-2">
                <article class="mlcssp-card">
                    <div class="mlcssp-card-header"><div><h2>Scanner de CSS do frontend</h2><p class="mlcssp-muted">Leia os estilos reais do site para detectar cores, fontes, botoes, header, rodape e mais.</p></div></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mlcssp_scan'); ?>
                        <input type="hidden" name="action" value="mlcssp_scan">
                        <div class="mlcssp-field" style="margin-bottom:12px;">
                            <label for="mlcssp-scan-url">URL para varredura (vazio = homepage)</label>
                            <input type="url" id="mlcssp-scan-url" name="mlcssp_scan_url" placeholder="<?php echo esc_attr(home_url('/')); ?>" style="width:100%;max-width:500px;">
                        </div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <button type="submit" name="mlcssp_scan_action" value="scan" class="button button-primary">Scan Site CSS</button>
                            <button type="submit" name="mlcssp_scan_action" value="clear_scan" class="button button-secondary">Limpar cache de varredura</button>
                            <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="button button-secondary">Abrir frontend</a>
                        </div>
                    </form>
                    <?php if ($scan_meta !== []) : ?>
                        <div class="mlcssp-scan-status" style="margin-top:16px;">
                            <div class="mlcssp-lint-stats">
                                <span><strong><?php echo esc_html((string) ($scan_meta['files'] ?? 0)); ?></strong> arquivos CSS</span>
                                <span><strong><?php echo esc_html((string) ($scan_meta['selectors'] ?? 0)); ?></strong> seletores</span>
                                <span><strong><?php echo esc_html((string) ($scan_meta['properties'] ?? 0)); ?></strong> propriedades</span>
                            </div>
                            <p class="mlcssp-muted" style="margin-top:6px;">Ultima varredura: <?php echo esc_html((string) ($scan_meta['scanned_at'] ?? '')); ?> &middot; URL: <?php echo esc_html((string) ($scan_meta['url'] ?? '')); ?></p>
                        </div>
                    <?php else : ?>
                        <p class="mlcssp-muted" style="margin-top:14px;">Nenhuma varredura realizada ainda. Clique em "Scan Site CSS" para detectar os estilos reais do seu site.</p>
                    <?php endif; ?>
                </article>
                <article class="mlcssp-card">
                    <div class="mlcssp-card-header"><div><h2>CSS compilado</h2><p class="mlcssp-muted">Arquivo de saida e status do enqueue.</p></div></div>
                    <ul class="mlcssp-list">
                        <li><strong>Arquivo:</strong> <?php echo esc_html((string) ($cache['path'] ?? '')); ?></li>
                        <li><strong>URL:</strong> <?php echo esc_html((string) ($cache['url'] ?? '')); ?></li>
                        <li><strong>Existe:</strong> <?php echo !empty($cache['exists']) ? 'Sim' : 'Nao'; ?></li>
                        <li><strong>Gravavel:</strong> <?php echo !empty($cache['writable']) ? 'Sim' : 'Nao'; ?></li>
                        <li><strong>Tamanho:</strong> <?php echo esc_html((string) ($cache['size'] ?? '0 B')); ?></li>
                        <li><strong>Compilado em:</strong> <?php echo esc_html((string) ($cache['last_compiled'] ?? '') ?: 'Nunca'); ?></li>
                        <li><strong>Posicao:</strong> <?php echo esc_html((string) ($settings['css_position'] ?? 'head') === 'footer' ? 'Rodape' : 'Cabecalho'); ?></li>
                        <li><strong>Overrides ativos:</strong> <?php echo esc_html((string) count($overrides)); ?> seletores</li>
                    </ul>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                        <?php wp_nonce_field('mlcssp_tools'); ?>
                        <input type="hidden" name="action" value="mlcssp_tools">
                        <button type="submit" name="mlcssp_tool_action" value="recompile" class="button button-secondary">Recompilar CSS</button>
                    </form>
                </article>
            </div>
            <?php if ($overrides !== []) : ?>
                <article class="mlcssp-card" style="margin-top:18px;">
                    <div class="mlcssp-card-header">
                        <div><h2>Overrides ativos (<?php echo count($overrides); ?>)</h2><p class="mlcssp-muted">Propriedades CSS alteradas a partir dos estilos detectados.</p></div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('mlcssp_override'); ?>
                            <input type="hidden" name="action" value="mlcssp_override">
                            <input type="hidden" name="mlcssp_tab" value="dashboard">
                            <button type="submit" name="mlcssp_override_action" value="clear_all" class="button button-secondary" data-confirm="Remover todos os overrides?">Limpar todos</button>
                        </form>
                    </div>
                    <div class="mlcssp-detected-grid">
                        <?php foreach ($overrides as $sel => $props) : ?>
                            <?php foreach ($props as $prop => $val) : ?>
                                <?php $this->render_override_row($sel, $prop, $val, 'dashboard'); ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endif; ?>
        </section>
        <?php
    }

    // =========================================================================
    // DETECTED CATEGORY PANELS (real scanner data)
    // =========================================================================

    /**
     * Render a tab panel with real detected styles for the given categories.
     *
     * @param string      $tab_key        Tab identifier.
     * @param string      $active_tab     Currently active tab.
     * @param array       $scan_results   Full scan results array.
     * @param array       $overrides      Current overrides.
     * @param array       $categories     Category keys to include.
     * @param array|null  $property_filter Only show these properties (null = all).
     */
    private function detected_category_panel(string $tab_key, string $active_tab, array $scan_results, array $overrides, array $categories, ?array $property_filter): void {
        $label_map = MLCSSP_Scanner::CATEGORY_LABELS;
        $items = array_filter($scan_results, function (array $r) use ($categories, $property_filter) {
            if (!in_array($r['group'] ?? '', $categories, true)) {
                return false;
            }
            if ($property_filter !== null && !in_array($r['property'] ?? '', $property_filter, true)) {
                return false;
            }
            return true;
        });
        $cat_label = implode(', ', array_map(fn(string $c) => $label_map[$c] ?? $c, $categories));
        ?>
        <section id="mlcssp-tab-<?php echo esc_attr($tab_key); ?>" class="mlcssp-tab-panel <?php echo $active_tab === $tab_key ? 'is-active' : ''; ?>" <?php echo $active_tab === $tab_key ? '' : 'hidden'; ?>>
            <article class="mlcssp-card">
                <div class="mlcssp-card-header"><div><h2>Estilos detectados: <?php echo esc_html($cat_label); ?></h2><p class="mlcssp-muted">Valores reais lidos do CSS do frontend. Altere e clique "Aplicar" para gerar um override CSS compilado.</p></div></div>
                <?php if ($items === []) : ?>
                    <p class="mlcssp-muted">Nenhum CSS detectado para esta categoria. Execute a varredura no Dashboard ou verifique se a pagina esta publica.</p>
                <?php else : ?>
                    <div class="mlcssp-detected-grid">
                        <?php
                        $grouped = [];
                        foreach ($items as $item) {
                            $sel = (string) ($item['selector'] ?? '');
                            $grouped[$sel][] = $item;
                        }
                        foreach ($grouped as $sel => $props) : ?>
                            <div class="mlcssp-detected-block">
                                <div class="mlcssp-detected-selector">
                                    <code><?php echo esc_html($sel); ?></code>
                                    <small class="mlcssp-muted"><?php echo esc_html((string) ($props[0]['source'] ?? '')); ?></small>
                                    <button type="button" class="button button-secondary mlcssp-copy-text" data-copy-value="<?php echo esc_attr($sel); ?>" style="min-height:28px;padding:0 10px;font-size:11px;">Copiar seletor</button>
                                </div>
                                <?php foreach ($props as $item) :
                                    $prop = (string) ($item['property'] ?? '');
                                    $val = (string) ($item['value'] ?? '');
                                    $is_color = in_array($prop, ['color', 'background-color', 'border-color'], true);
                                    $override_val = $overrides[$sel][$prop] ?? null;
                                    $display_val = $override_val !== null ? $override_val : $val;
                                    ?>
                                    <div class="mlcssp-detected-row">
                                        <span class="mlcssp-detected-prop"><?php echo esc_html($prop); ?></span>
                                        <?php if ($is_color) : ?>
                                            <span class="mlcssp-swatch" style="background:<?php echo esc_attr($val); ?>;"></span>
                                        <?php endif; ?>
                                        <span class="mlcssp-detected-val mlcssp-muted"><?php echo esc_html($val); ?></span>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mlcssp-override-form">
                                            <?php wp_nonce_field('mlcssp_override'); ?>
                                            <input type="hidden" name="action" value="mlcssp_override">
                                            <input type="hidden" name="mlcssp_tab" value="<?php echo esc_attr($tab_key); ?>">
                                            <input type="hidden" name="mlcssp_selector" value="<?php echo esc_attr($sel); ?>">
                                            <input type="hidden" name="mlcssp_property" value="<?php echo esc_attr($prop); ?>">
                                            <?php if ($is_color) : ?>
                                                <input type="color" name="mlcssp_value" value="<?php echo esc_attr($this->to_hex($display_val)); ?>" class="mlcssp-color-input">
                                            <?php else : ?>
                                                <input type="text" name="mlcssp_value" value="<?php echo esc_attr($display_val); ?>" class="mlcssp-text-input" placeholder="<?php echo esc_attr($val); ?>">
                                            <?php endif; ?>
                                            <button type="submit" name="mlcssp_override_action" value="apply" class="button button-primary" style="min-height:32px;padding:0 12px;">Aplicar</button>
                                            <?php if ($override_val !== null) : ?>
                                                <button type="submit" name="mlcssp_override_action" value="remove" class="button button-secondary" style="min-height:32px;padding:0 10px;font-size:11px;">Remover</button>
                                                <span class="mlcssp-override-badge">override</span>
                                            <?php endif; ?>
                                        </form>
                                        <button type="button" class="button button-secondary mlcssp-add-to-editor" data-sel="<?php echo esc_attr($sel); ?>" data-prop="<?php echo esc_attr($prop); ?>" data-val="<?php echo esc_attr($val); ?>" style="min-height:32px;padding:0 10px;font-size:11px;" title="Adicionar ao editor avancado">+ Editor</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>
        <?php
    }

    private function render_override_row(string $sel, string $prop, string $val, string $tab): void {
        $is_color = in_array($prop, ['color', 'background-color', 'border-color'], true);
        ?>
        <div class="mlcssp-detected-row">
            <code class="mlcssp-detected-prop"><?php echo esc_html($sel); ?></code>
            <span class="mlcssp-detected-prop"><?php echo esc_html($prop); ?></span>
            <?php if ($is_color) : ?><span class="mlcssp-swatch" style="background:<?php echo esc_attr($val); ?>;"></span><?php endif; ?>
            <span class="mlcssp-detected-val"><?php echo esc_html($val); ?></span>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mlcssp-override-form" style="margin-left:auto;">
                <?php wp_nonce_field('mlcssp_override'); ?>
                <input type="hidden" name="action" value="mlcssp_override">
                <input type="hidden" name="mlcssp_tab" value="<?php echo esc_attr($tab); ?>">
                <input type="hidden" name="mlcssp_selector" value="<?php echo esc_attr($sel); ?>">
                <input type="hidden" name="mlcssp_property" value="<?php echo esc_attr($prop); ?>">
                <button type="submit" name="mlcssp_override_action" value="remove" class="button button-secondary" style="min-height:28px;padding:0 10px;font-size:11px;">Remover</button>
            </form>
        </div>
        <?php
    }

    private function to_hex(string $val): string {
        $val = trim($val);
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $val)) {
            return $val;
        }
        if (preg_match('/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $val, $m)) {
            return sprintf('#%02x%02x%02x', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        return '#000000';
    }


    // =========================================================================
    // VISUAL EDITOR TAB
    // =========================================================================

    private function visual_panel(string $active_tab): void {
        $visual_url = add_query_arg(['mlcssp_visual' => '1'], home_url('/'));
        ?>
        <section id="mlcssp-tab-visual" class="mlcssp-tab-panel <?php echo $active_tab === 'visual' ? 'is-active' : ''; ?>" <?php echo $active_tab === 'visual' ? '' : 'hidden'; ?>>
            <article class="mlcssp-card">
                <div class="mlcssp-card-header"><div><h2>Editor visual</h2><p class="mlcssp-muted">Edite cores, fontes, espacamentos e bordas diretamente no frontend do site. Passe o mouse sobre qualquer elemento, clique para editar e salve as alteracoes.</p></div></div>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                    <a href="<?php echo esc_url($visual_url); ?>" target="_blank" class="button button-primary" style="font-size:15px;padding:10px 24px;height:auto;">Abrir editor visual</a>
                    <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="button button-secondary">Abrir frontend normal</a>
                </div>

                <div class="mlcssp-card" style="background:#fff;padding:18px;border:1px solid #e5eaf0;border-radius:14px;margin-bottom:16px;">
                    <h3 style="margin-top:0;">Escolher pagina para editar</h3>
                    <p class="mlcssp-muted">Busque qualquer pagina, post ou produto publico e abra o editor visual diretamente nele.</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                        <div class="mlcssp-field" style="flex:1;min-width:240px;margin:0;">
                            <label for="mlcssp-page-search">Buscar por titulo</label>
                            <input type="text" id="mlcssp-page-search" placeholder="Ex.: Sobre, Contato, Produto X" autocomplete="off">
                        </div>
                        <label style="font-size:12px;color:#6b7a8d;display:flex;align-items:center;gap:6px;padding-bottom:8px;"><input type="checkbox" id="mlcssp-page-drafts"> Mostrar rascunhos</label>
                        <button type="button" class="button button-secondary" id="mlcssp-page-search-btn" style="margin-bottom:2px;">Buscar</button>
                    </div>
                    <div id="mlcssp-page-results" class="mlcssp-page-results" style="margin-top:12px;"></div>
                </div>

                <div class="mlcssp-card" style="background:#fff;padding:18px;border:1px solid #e5eaf0;border-radius:14px;margin-bottom:16px;">
                    <h3 style="margin-top:0;">Editar URL especifica</h3>
                    <p class="mlcssp-muted">Informe uma URL do proprio site (mesmo dominio). URLs externas, wp-admin, login, ajax e REST sao rejeitadas.</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                        <div class="mlcssp-field" style="flex:1;min-width:280px;margin:0;">
                            <label for="mlcssp-direct-url">URL da pagina</label>
                            <input type="url" id="mlcssp-direct-url" placeholder="<?php echo esc_attr(home_url('/exemplo/')); ?>">
                        </div>
                        <button type="button" class="button button-primary" id="mlcssp-direct-url-btn" style="margin-bottom:2px;">Abrir editor nesta URL</button>
                    </div>
                    <p id="mlcssp-direct-url-msg" class="mlcssp-muted" style="margin-top:8px;"></p>
                </div>
                <script>
                (function(){
                    var home = <?php echo wp_json_encode(home_url('/')); ?>;
                    var homeHost = (function(){ try { return new URL(home).host; } catch(e){ return ''; } })();
                    var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
                    var nonce = <?php echo wp_json_encode(wp_create_nonce('mlcssp_page_search')); ?>;
                    function addFlag(u){ return u + (u.indexOf('?')>=0 ? '&' : '?') + 'mlcssp_visual=1'; }
                    var searchBtn = document.getElementById('mlcssp-page-search-btn');
                    var results = document.getElementById('mlcssp-page-results');
                    function doSearch(){
                        var q = document.getElementById('mlcssp-page-search').value;
                        var drafts = document.getElementById('mlcssp-page-drafts').checked ? '1':'0';
                        results.innerHTML = '<p class="mlcssp-muted">Buscando...</p>';
                        var fd = new FormData();
                        fd.append('action','mlcssp_page_search'); fd.append('_wpnonce',nonce);
                        fd.append('q',q); fd.append('drafts',drafts);
                        fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){
                            if(!res||!res.success||!res.data||!res.data.length){ results.innerHTML='<p class="mlcssp-muted">Nenhuma pagina encontrada.</p>'; return; }
                            var html='<table class="mlcssp-selector-table" style="width:100%;"><tbody>';
                            res.data.forEach(function(p){
                                html+='<tr><td><strong>'+p.title+'</strong><br><small class="mlcssp-muted">'+p.type+' &middot; '+p.status+'</small></td>'+
                                '<td><small class="mlcssp-muted">'+p.url+'</small></td>'+
                                '<td><a class="button button-primary" target="_blank" href="'+addFlag(p.url)+'">Abrir editor visual nesta pagina</a></td></tr>';
                            });
                            html+='</tbody></table>';
                            results.innerHTML=html;
                        }).catch(function(){ results.innerHTML='<p class="mlcssp-muted">Erro na busca.</p>'; });
                    }
                    searchBtn.addEventListener('click',doSearch);
                    document.getElementById('mlcssp-page-search').addEventListener('keydown',function(e){ if(e.key==='Enter'){e.preventDefault();doSearch();} });
                    var urlBtn = document.getElementById('mlcssp-direct-url-btn');
                    urlBtn.addEventListener('click',function(){
                        var val = document.getElementById('mlcssp-direct-url').value.trim();
                        var msg = document.getElementById('mlcssp-direct-url-msg');
                        var u;
                        try { u = new URL(val, home); } catch(e){ msg.textContent='URL invalida.'; msg.style.color='#b42318'; return; }
                        if(u.host !== homeHost){ msg.textContent='Rejeitado: a URL precisa ser do mesmo dominio ('+homeHost+').'; msg.style.color='#b42318'; return; }
                        if(/\/wp-admin|wp-login|admin-ajax|wp-json/i.test(u.pathname)){ msg.textContent='Rejeitado: URLs de admin/login/ajax/REST nao sao permitidas.'; msg.style.color='#b42318'; return; }
                        msg.textContent='Abrindo editor...'; msg.style.color='#159947';
                        window.open(addFlag(u.href),'_blank');
                    });
                })();
                </script>
                <div class="mlcssp-card" style="background:#f8fafb;padding:18px;border:1px solid #e5eaf0;border-radius:14px;">
                    <h3 style="margin-top:0;">Como funciona</h3>
                    <ol style="margin:0;padding-left:20px;line-height:1.8;">
                        <li>Clique em <strong>Abrir editor visual</strong> acima.</li>
                        <li>Passe o mouse sobre qualquer elemento — um contorno aparece.</li>
                        <li>Clique no elemento para abrir o painel lateral de edicao.</li>
                        <li>Ajuste cor, fundo, fonte, espacamento ou bordas.</li>
                        <li>Clique <strong>Pre-visualizar</strong> para ver antes de salvar.</li>
                        <li>Clique <strong>Salvar alteracao</strong> para gravar o override.</li>
                        <li>O CSS compilado e atualizado automaticamente.</li>
                    </ol>
                </div>
                <p class="mlcssp-muted" style="margin-top:14px;">O editor visual requer login de administrador. Visitantes comuns nunca veem o painel. As alteracoes sao salvas como overrides CSS no mesmo motor do scanner.</p>
            </article>
        </section>
        <?php
    }

    // =========================================================================
    // EXISTING PANELS (preserved from v1.4.0)
    // =========================================================================

    private function pages_panel(string $active_tab, array $settings): void {
        $this->open_visual_panel('pages', $active_tab, 'Ajustes por pagina', 'CSS por ID de pagina/post. Refinamento completo preparado para evolucao.');
        ?>
        <div class="mlcssp-field" style="grid-column:1 / -1;"><label for="mlcssp-page-id">ID da pagina</label><input type="number" id="mlcssp-page-id" name="mlcssp_css[page_target_id]" value="<?php echo esc_attr($settings['page_target_id'] ?? ''); ?>" placeholder="Ex.: 42" min="1"></div>
        <div class="mlcssp-field" style="grid-column:1 / -1;"><label for="mlcssp-page-css">CSS especifico para esta pagina</label><textarea id="mlcssp-page-css" class="mlcssp-codearea" name="mlcssp_css[page_css]" spellcheck="false"><?php echo esc_textarea($settings['page_css'] ?? ''); ?></textarea></div>
        <?php
        $this->close_visual_panel('Salvar CSS de pagina');
    }

    private function presets_panel(string $active_tab): void {
        $presets = (new MLCSSP_Settings())->get_builtin_presets();
        ?>
        <section id="mlcssp-tab-presets" class="mlcssp-tab-panel <?php echo $active_tab === 'presets' ? 'is-active' : ''; ?>" <?php echo $active_tab === 'presets' ? '' : 'hidden'; ?>>
            <article class="mlcssp-card">
                <div class="mlcssp-card-header"><div><h2>Presets visuais</h2><p class="mlcssp-muted">Paletas prontas: aplique com um clique e personalize depois.</p></div></div>
                <div class="mlcssp-preset-grid">
                    <?php foreach ($presets as $key => $preset) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mlcssp-preset-card">
                            <?php wp_nonce_field('mlcssp_tools'); ?>
                            <input type="hidden" name="action" value="mlcssp_tools">
                            <input type="hidden" name="mlcssp_preset" value="<?php echo esc_attr($key); ?>">
                            <strong><?php echo esc_html((string) ($preset['label'] ?? $key)); ?></strong>
                            <p class="mlcssp-muted"><?php echo esc_html((string) ($preset['description'] ?? '')); ?></p>
                            <?php
                            $ps = (array) ($preset['settings'] ?? []);
                            $bg = (string) ($ps['site_bg_color'] ?? '#ffffff');
                            $tx = (string) ($ps['site_text_color'] ?? '#333333');
                            $hd = (string) ($ps['heading_color'] ?? '#1a2b3b');
                            $bb = (string) ($ps['button_bg_color'] ?? '#155e6f');
                            $bt = (string) ($ps['button_text_color'] ?? '#ffffff');
                            ?>
                            <div class="mlcssp-preset-preview" style="background:<?php echo esc_attr($bg); ?>;color:<?php echo esc_attr($tx); ?>;">
                                <div class="mlcssp-preset-strip" style="background:<?php echo esc_attr($hd); ?>;color:<?php echo esc_attr($bg); ?>;font-size:10px;padding:4px 8px;border-radius:6px 6px 0 0;margin:-4px -4px 8px;">Cabecalho</div>
                                <div class="mlcssp-preset-preview-title" style="color:<?php echo esc_attr($hd); ?>;">Titulo exemplo</div>
                                <div class="mlcssp-preset-preview-text">Paragrafo de demonstracao com o estilo deste preset aplicado.</div>
                                <div class="mlcssp-preset-card-sample" style="background:rgba(0,0,0,.04);border:1px solid <?php echo esc_attr($hd); ?>22;border-radius:8px;padding:8px;margin:8px 0;font-size:11px;">Card de exemplo</div>
                                <span class="mlcssp-preset-preview-btn" style="background:<?php echo esc_attr($bb); ?>;color:<?php echo esc_attr($bt); ?>;">Botao</span>
                                <div class="mlcssp-preset-preview-swatches">
                                    <span class="mlcssp-preset-preview-swatch" style="background:<?php echo esc_attr($bg); ?>;"></span>
                                    <span class="mlcssp-preset-preview-swatch" style="background:<?php echo esc_attr($tx); ?>;"></span>
                                    <span class="mlcssp-preset-preview-swatch" style="background:<?php echo esc_attr($hd); ?>;"></span>
                                    <span class="mlcssp-preset-preview-swatch" style="background:<?php echo esc_attr($bb); ?>;"></span>
                                    <span class="mlcssp-preset-preview-swatch" style="background:<?php echo esc_attr($bt); ?>;"></span>
                                </div>
                            </div>
                            <button type="submit" name="mlcssp_tool_action" value="apply_preset" class="button button-primary" style="margin-top:10px;">Aplicar preset</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>
        <?php
    }

    private function history_panel(string $active_tab): void {
        $history = get_option(MLCSSP_HISTORY_KEY, []);
        $history = is_array($history) ? $history : [];
        ?>
        <section id="mlcssp-tab-history" class="mlcssp-tab-panel <?php echo $active_tab === 'history' ? 'is-active' : ''; ?>" <?php echo $active_tab === 'history' ? '' : 'hidden'; ?>>
            <article class="mlcssp-card">
                <div class="mlcssp-card-header">
                    <div><h2>Historico de revisoes</h2><p class="mlcssp-muted">Snapshots automaticos. Restaure ou remova quando precisar.</p></div>
                    <?php if ($history !== []) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('mlcssp_tools'); ?>
                            <input type="hidden" name="action" value="mlcssp_tools">
                            <button type="submit" name="mlcssp_tool_action" value="clear_history" class="button button-secondary" data-confirm="Limpar todo o historico?">Limpar historico</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if ($history === []) : ?>
                    <p class="mlcssp-muted">Nenhuma revisao salva ainda.</p>
                <?php else : ?>
                    <div class="mlcssp-revisions">
                        <?php foreach ($history as $index => $item) : ?>
                            <div class="mlcssp-revision-row">
                                <div class="mlcssp-revision-info">
                                    <strong><?php echo esc_html((string) ($item['label'] ?? 'Alteracao')); ?></strong>
                                    <small><?php echo esc_html((string) ($item['time'] ?? '')); ?><?php echo ((string) ($item['css_size'] ?? '')) !== '' ? ' &middot; CSS: ' . esc_html((string) $item['css_size']) : ''; ?></small>
                                </div>
                                <div class="mlcssp-revision-actions">
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('mlcssp_tools'); ?>
                                        <input type="hidden" name="action" value="mlcssp_tools">
                                        <input type="hidden" name="mlcssp_history_index" value="<?php echo esc_attr((string) $index); ?>">
                                        <button type="submit" name="mlcssp_tool_action" value="restore_history" class="button button-primary" data-confirm="Restaurar esta revisao?">Restaurar</button>
                                    </form>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('mlcssp_tools'); ?>
                                        <input type="hidden" name="action" value="mlcssp_tools">
                                        <input type="hidden" name="mlcssp_history_index" value="<?php echo esc_attr((string) $index); ?>">
                                        <button type="submit" name="mlcssp_tool_action" value="delete_history" class="button button-secondary" data-confirm="Remover esta revisao?">Remover</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>
        <?php
    }

    private function snippets_panel(string $active_tab): void {
        $snippets = (new MLCSSP_Settings())->get_builtin_snippets();
        ?>
        <section id="mlcssp-tab-snippets" class="mlcssp-tab-panel <?php echo $active_tab === 'snippets' ? 'is-active' : ''; ?>" <?php echo $active_tab === 'snippets' ? '' : 'hidden'; ?>>
            <article class="mlcssp-card">
                <div class="mlcssp-card-header"><div><h2>Biblioteca de snippets</h2><p class="mlcssp-muted">Trechos prontos. Clique em "Inserir" para adicionar ao editor avancado.</p></div></div>
                <?php foreach ($snippets as $group_key => $group) : ?>
                    <h3 class="mlcssp-snippet-group"><?php echo esc_html((string) $group['label']); ?></h3>
                    <div class="mlcssp-snippet-grid">
                        <?php foreach ($group['items'] as $snippet) : ?>
                            <div class="mlcssp-snippet">
                                <div class="mlcssp-snippet-head"><strong><?php echo esc_html((string) $snippet['label']); ?></strong><div class="mlcssp-snippet-buttons"><button type="button" class="button button-primary mlcssp-snippet-insert" data-target="mlcssp-global-css">Inserir</button><button type="button" class="button button-secondary mlcssp-snippet-copy">Copiar</button></div></div>
                                <pre class="mlcssp-snippet-code" data-snippet-code><?php echo esc_html((string) $snippet['code']); ?></pre>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </article>
        </section>
        <?php
    }

    private function inspector_panel(string $active_tab, array $report): void {
        $targets = (array) ($report['target_examples'] ?? []);
        $detected = (array) ($report['detected_nicepage'] ?? []);
        $preview_url = (string) ($report['preview_url'] ?? home_url('/'));
        $scope = (string) ($report['nicepage_scope'] ?? 'body.mlcssp-nicepage-page');
        $scan_results = (array) ($report['scan_results'] ?? []);
        ?>
        <section id="mlcssp-tab-inspector" class="mlcssp-tab-panel <?php echo $active_tab === 'inspector' ? 'is-active' : ''; ?>" <?php echo $active_tab === 'inspector' ? '' : 'hidden'; ?>>
            <div class="mlcssp-grid-2">
                <article class="mlcssp-card">
                    <div class="mlcssp-card-header"><div><h2>Alvos comuns</h2></div></div>
                    <table class="mlcssp-selector-table"><tbody>
                    <?php foreach ($targets as $sel => $label) : ?>
                        <tr><td><code><?php echo esc_html((string) $sel); ?></code></td><td><?php echo esc_html((string) $label); ?></td><td><button type="button" class="button button-secondary mlcssp-copy-text" data-copy-value="<?php echo esc_attr((string) $sel); ?>">Copiar</button></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </article>
                <article class="mlcssp-card">
                    <div class="mlcssp-card-header"><div><h2>Inventario de seletores detectados</h2><p class="mlcssp-muted">Todos os seletores encontrados na ultima varredura.</p></div></div>
                    <?php
                    $all_sels = array_unique(array_column($scan_results, 'selector'));
                    sort($all_sels);
                    if ($all_sels === []) : ?>
                        <p class="mlcssp-muted">Nenhum seletor detectado. Execute a varredura no Dashboard.</p>
                    <?php else : ?>
                        <div class="mlcssp-selector-chips">
                            <?php foreach ($all_sels as $sel) : ?>
                                <button type="button" class="mlcssp-chip mlcssp-copy-text" data-copy-value="<?php echo esc_attr((string) $sel); ?>"><?php echo esc_html((string) $sel); ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($detected !== []) : ?>
                        <div class="mlcssp-card-header" style="margin-top:18px;"><div><h2>Nicepage detectados</h2><p class="mlcssp-muted">Escopo: <code><?php echo esc_html($scope); ?></code></p></div></div>
                        <div class="mlcssp-selector-chips">
                            <?php foreach ($detected as $sel) : ?>
                                <button type="button" class="mlcssp-chip mlcssp-copy-text" data-copy-value="<?php echo esc_attr((string) $sel); ?>"><?php echo esc_html((string) $sel); ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </div>
        </section>
        <?php
    }

    private function advanced_panel(string $active_tab, array $settings, array $report = []): void {
        $this->open_visual_panel('advanced', $active_tab, 'Editor de codigo', 'CSS manual complementar com realce de sintaxe, numeracao de linhas e validacao.');
        $lint_global = (array) ($report['lint_global'] ?? []);
        $lint_nicepage = (array) ($report['lint_nicepage'] ?? []);
        ?>
        <div class="mlcssp-field" style="grid-column:1 / -1;">
            <div class="mlcssp-editor-head"><label for="mlcssp-global-css">CSS global manual</label><button type="button" class="button button-secondary mlcssp-editor-expand" data-editor-target="mlcssp-global-css">Expandir</button></div>
            <textarea id="mlcssp-global-css" class="mlcssp-codearea mlcssp-code-editor" name="mlcssp_css[global_css]" spellcheck="false" data-mlcssp-editor><?php echo esc_textarea($settings['global_css'] ?? ''); ?></textarea>
            <?php $this->lint_summary($lint_global); ?>
        </div>
        <div class="mlcssp-field" style="grid-column:1 / -1;">
            <div class="mlcssp-editor-head"><label for="mlcssp-nicepage-css">CSS Nicepage manual</label><button type="button" class="button button-secondary mlcssp-editor-expand" data-editor-target="mlcssp-nicepage-css">Expandir</button></div>
            <textarea id="mlcssp-nicepage-css" class="mlcssp-codearea mlcssp-code-editor" name="mlcssp_css[nicepage_css]" spellcheck="false" data-mlcssp-editor><?php echo esc_textarea($settings['nicepage_css'] ?? ''); ?></textarea>
            <?php $this->lint_summary($lint_nicepage); ?>
        </div>
        <?php
        $this->close_visual_panel('Salvar CSS avancado');
    }

    private function lint_summary(array $lint): void {
        if ($lint === []) { return; }
        $stats = (array) ($lint['stats'] ?? []);
        $errors = (array) ($lint['errors'] ?? []);
        $warnings = (array) ($lint['warnings'] ?? []);
        ?>
        <div class="mlcssp-lint">
            <div class="mlcssp-lint-stats">
                <span><strong><?php echo esc_html((string) ($stats['rules'] ?? 0)); ?></strong> regras</span>
                <span><strong><?php echo esc_html((string) ($stats['declarations'] ?? 0)); ?></strong> declaracoes</span>
                <span><strong><?php echo esc_html((string) ($stats['lines'] ?? 0)); ?></strong> linhas</span>
                <span><strong><?php echo esc_html((string) ($stats['important'] ?? 0)); ?></strong> !important</span>
            </div>
            <?php if ($errors !== []) : ?><ul class="mlcssp-lint-list mlcssp-lint-error"><?php foreach ($errors as $msg) : ?><li><?php echo esc_html((string) $msg); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ($warnings !== []) : ?><ul class="mlcssp-lint-list mlcssp-lint-warning"><?php foreach ($warnings as $msg) : ?><li><?php echo esc_html((string) $msg); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ($errors === [] && $warnings === []) : ?><p class="mlcssp-lint-ok">Nenhum problema detectado.</p><?php endif; ?>
        </div>
        <?php
    }

    private function license_panel(string $active_tab, array $license_state, array $license_config, MLCSSP_License $license_manager): void {
        ?>
        <section id="mlcssp-tab-license" class="mlcssp-tab-panel <?php echo $active_tab === 'license' ? 'is-active' : ''; ?>" <?php echo $active_tab === 'license' ? '' : 'hidden'; ?>>
            <article class="mlcssp-card">
                <div class="mlcssp-card-header"><div><h2>Licenca do plugin</h2><p class="mlcssp-muted">Gerenciamento de chave e nivel de acesso.</p></div></div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mlcssp_save_license'); ?>
                    <input type="hidden" name="action" value="mlcssp_save_license">
                    <div class="mlcssp-field"><label for="mlcssp-license-key">Chave de licenca</label><input type="text" id="mlcssp-license-key" name="mlcssp_license[key]" value="<?php echo esc_attr((string) ($license_state['key'] ?? '')); ?>" placeholder="Insira sua chave"></div>
                    <div class="mlcssp-field"><label for="mlcssp-license-email">Email</label><input type="email" id="mlcssp-license-email" name="mlcssp_license[email]" value="<?php echo esc_attr((string) ($license_state['email'] ?? '')); ?>" placeholder="email@exemplo.com"></div>
                    <p class="mlcssp-muted">Status: <strong><?php echo esc_html((string) ($license_state['tier'] ?? 'free')); ?></strong></p>
                    <button type="submit" class="button button-primary">Salvar licenca</button>
                </form>
            </article>
        </section>
        <?php
    }

    private function tools_panel(string $active_tab, array $settings, array $report): void {
        $cache = (array) ($report['cache'] ?? []);
        ?>
        <section id="mlcssp-tab-tools" class="mlcssp-tab-panel <?php echo $active_tab === 'tools' ? 'is-active' : ''; ?>" <?php echo $active_tab === 'tools' ? '' : 'hidden'; ?>>
            <div class="mlcssp-grid-2">
                <article class="mlcssp-card">
                    <div class="mlcssp-card-header"><div><h2>Exportar / Importar</h2></div></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:16px;">
                        <?php wp_nonce_field('mlcssp_tools'); ?>
                        <input type="hidden" name="action" value="mlcssp_tools">
                        <button type="submit" name="mlcssp_tool_action" value="export" class="button button-primary">Exportar JSON</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mlcssp_tools'); ?>
                        <input type="hidden" name="action" value="mlcssp_tools">
                        <div class="mlcssp-field"><label for="mlcssp-import-json">Colar JSON de importacao</label><textarea id="mlcssp-import-json" name="mlcssp_import_json" class="mlcssp-codearea" rows="4" placeholder='{"plugin":"ML CSS Pro",...}'></textarea></div>
                        <button type="submit" name="mlcssp_tool_action" value="import" class="button button-secondary">Importar</button>
                    </form>
                </article>
                <article class="mlcssp-card">
                    <div class="mlcssp-card-header"><div><h2>Cache e compilacao</h2></div></div>
                    <ul class="mlcssp-list">
                        <li><strong>Arquivo:</strong> <?php echo esc_html((string) ($cache['path'] ?? '')); ?></li>
                        <li><strong>URL:</strong> <?php echo esc_html((string) ($cache['url'] ?? '')); ?></li>
                        <li><strong>Existe:</strong> <?php echo !empty($cache['exists']) ? 'Sim' : 'Nao'; ?></li>
                        <li><strong>Gravavel:</strong> <?php echo !empty($cache['writable']) ? 'Sim' : 'Nao'; ?></li>
                        <li><strong>Tamanho:</strong> <?php echo esc_html((string) ($cache['size'] ?? '0 B')); ?></li>
                        <li><strong>Compilado em:</strong> <?php echo esc_html((string) ($cache['last_compiled'] ?? '') ?: 'Nunca'); ?></li>
                    </ul>
                    <div style="display:flex;gap:10px;margin-top:12px;">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('mlcssp_tools'); ?><input type="hidden" name="action" value="mlcssp_tools">
                            <button type="submit" name="mlcssp_tool_action" value="recompile" class="button button-primary">Recompilar</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('mlcssp_tools'); ?><input type="hidden" name="action" value="mlcssp_tools">
                            <button type="submit" name="mlcssp_tool_action" value="clear_cache" class="button button-secondary">Limpar cache</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('mlcssp_tools'); ?><input type="hidden" name="action" value="mlcssp_tools">
                            <button type="submit" name="mlcssp_tool_action" value="reset" class="button button-secondary" data-confirm="Restaurar padrao?">Reset padrao</button>
                        </form>
                    </div>
                </article>
            </div>
            <?php $this->diagnostico_panel($report); ?>
        </section>
        <?php
    }

    private function diagnostico_panel(array $report): void {
        $cache = (array) ($report['cache'] ?? []);
        $overrides = MLCSSP_Scanner::get_overrides();
        $override_count = 0;
        foreach ($overrides as $props) { $override_count += is_array($props) ? count($props) : 0; }
        $override_css = MLCSSP_Scanner::build_override_css();
        $bust = !empty($cache['path']) && file_exists((string) $cache['path']) ? (string) filemtime((string) $cache['path']) : MLCSSP_VERSION;
        $last_save = get_option('mlcsspro_last_save', []);
        $last_save = is_array($last_save) ? $last_save : [];
        $inline_has = $override_css !== '';
        $file_has = false;
        if (!empty($cache['exists']) && !empty($cache['path'])) {
            $fc = (string) @file_get_contents((string) $cache['path']);
            $file_has = str_contains($fc, '!important') && strlen($fc) > 30;
        }
        ?>
        <article class="mlcssp-card" style="margin-top:18px;">
            <div class="mlcssp-card-header"><div><h2>Diagnostico de aplicacao</h2><p class="mlcssp-muted">Pipeline completo: override → compilacao → enqueue → frontend publico.</p></div></div>
            <div class="mlcssp-grid-2" style="gap:14px;">
                <div>
                    <h3 style="margin-top:0;">Pipeline</h3>
                    <ul class="mlcssp-list" style="font-size:13px;">
                        <li><strong>Overrides ativos:</strong> <?php echo esc_html((string) count($overrides)); ?> seletores (<?php echo esc_html((string) $override_count); ?> props)</li>
                        <?php $dev_counts = MLCSSP_Scanner::count_overrides_by_device(); ?>
                        <li><strong>Desktop:</strong> <?php echo esc_html((string) $dev_counts['desktop']); ?> props &middot; <strong>Tablet:</strong> <?php echo esc_html((string) $dev_counts['tablet']); ?> props &middot; <strong>Mobile:</strong> <?php echo esc_html((string) $dev_counts['mobile']); ?> props</li>
                        <li><strong>Media queries:</strong> Tablet @media (max-width:1024px), Mobile @media (max-width:767px)</li>
                        <?php $page_count = MLCSSP_Scanner::count_page_overrides(); $page_data = MLCSSP_Scanner::get_page_overrides(); ?>
                        <li><strong>Overrides por pagina:</strong> <?php echo esc_html((string) $page_count); ?> props em <?php echo esc_html((string) count($page_data)); ?> pagina(s)</li>
                        <?php if ($page_data !== []) : ?>
                            <li><strong>Paginas com overrides:</strong> <?php echo esc_html(implode(', ', array_map(static fn($p) => (string) ($p['title'] ?: $p['body_class']), array_slice($page_data, 0, 5)))); ?></li>
                        <?php endif; ?>
                        <li><strong>Compiled existe:</strong> <?php echo !empty($cache['exists']) ? '<span style="color:#159947;">Sim</span>' : '<span style="color:#b42318;">NAO</span>'; ?></li>
                        <li><strong>Compiled tem overrides:</strong> <?php echo $file_has ? '<span style="color:#159947;">Sim</span>' : '<span style="color:#b42318;">NAO</span>'; ?></li>
                        <li><strong>Inline fallback tem overrides:</strong> <?php echo $inline_has ? '<span style="color:#159947;">Sim</span>' : '<span style="color:#b42318;">NAO</span>'; ?></li>
                        <li><strong>Public enqueue:</strong> wp_enqueue_scripts prio <?php echo esc_html((string) ($report['priority'] ?? '99990')); ?> <span style="color:#159947;">(sem gate de login)</span></li>
                        <li><strong>Inline fallback:</strong> wp_head 99999 + wp_footer 1 <span style="color:#159947;">(sem gate de login)</span></li>
                        <li><strong>URL:</strong> <?php echo esc_html((string) ($cache['url'] ?? '')); ?></li>
                        <li><strong>Tamanho:</strong> <?php echo esc_html((string) ($cache['size'] ?? '0 B')); ?></li>
                        <li><strong>Cache-bust:</strong> <?php echo esc_html($bust); ?></li>
                        <li><strong>Seletores com body prefix:</strong> <span style="color:#159947;">Sim (v1.4.7+)</span></li>
                    </ul>
                    <?php if ($last_save !== []) : ?>
                        <h3>Ultimo salvamento visual</h3>
                        <ul class="mlcssp-list" style="font-size:13px;">
                            <li><strong>Seletor original:</strong> <?php echo esc_html((string) ($last_save['selector'] ?? '')); ?></li>
                            <li><strong>Seletor publico:</strong> <?php echo esc_html((string) ($last_save['safe_selector'] ?? '')); ?></li>
                            <li><strong>Propriedade:</strong> <?php echo esc_html((string) ($last_save['property'] ?? '')); ?></li>
                            <li><strong>Valor:</strong> <?php echo esc_html((string) ($last_save['value'] ?? '')); ?></li>
                            <li><strong>Compilado:</strong> <?php echo !empty($last_save['compiled']) ? 'Sim' : 'Nao'; ?></li>
                            <li><strong>No arquivo:</strong> <?php echo !empty($last_save['in_file']) ? 'Sim' : 'Nao'; ?></li>
                            <li><strong>Hora:</strong> <?php echo esc_html((string) ($last_save['time'] ?? '')); ?></li>
                        </ul>
                    <?php endif; ?>
                    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('mlcssp_tools'); ?><input type="hidden" name="action" value="mlcssp_tools">
                            <button type="submit" name="mlcssp_tool_action" value="recompile" class="button button-primary">Recompilar CSS</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('mlcssp_tools'); ?><input type="hidden" name="action" value="mlcssp_tools">
                            <button type="submit" name="mlcssp_tool_action" value="clear_cache" class="button button-secondary">Limpar cache</button>
                        </form>
                        <?php if (!empty($cache['url'])) : ?>
                            <a href="<?php echo esc_url((string) $cache['url']); ?>" target="_blank" class="button button-secondary">Abrir compiled.css</a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="button button-secondary">Abrir frontend</a>
                    </div>
                    <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;padding:12px;border:1px dashed var(--mlcssp-line,#dfe6ec);border-radius:10px;background:#fafcfd;">
                        <strong style="width:100%;font-size:13px;">Teste de aplicacao:</strong>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('mlcssp_tools'); ?><input type="hidden" name="action" value="mlcssp_tools">
                            <button type="submit" name="mlcssp_tool_action" value="test_color_h1" class="button button-primary" data-confirm="Aplicar cor vermelha no H1 para teste?">Testar cor no H1</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('mlcssp_tools'); ?><input type="hidden" name="action" value="mlcssp_tools">
                            <button type="submit" name="mlcssp_tool_action" value="remove_test_color_h1" class="button button-secondary">Remover teste H1</button>
                        </form>
                    </div>
                </div>
                <div>
                    <h3 style="margin-top:0;">CSS de overrides gerado</h3>
                    <?php if ($override_css !== '') : ?>
                        <pre style="background:#0f172a;color:#e5eef6;padding:14px;border-radius:10px;max-height:300px;overflow:auto;font-size:12px;line-height:1.55;white-space:pre-wrap;"><?php echo esc_html($override_css); ?></pre>
                        <button type="button" class="button button-secondary mlcssp-copy-text" data-copy-value="<?php echo esc_attr($override_css); ?>" style="margin-top:8px;">Copiar CSS gerado</button>
                    <?php else : ?>
                        <p class="mlcssp-muted">Nenhum override salvo.</p>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function open_visual_panel(string $tab, string $active_tab, string $title, string $subtitle): void {
        ?>
        <section id="mlcssp-tab-<?php echo esc_attr($tab); ?>" class="mlcssp-tab-panel <?php echo $active_tab === $tab ? 'is-active' : ''; ?>" <?php echo $active_tab === $tab ? '' : 'hidden'; ?>>
            <article class="mlcssp-card">
                <div class="mlcssp-card-header"><div><h2><?php echo esc_html($title); ?></h2><p class="mlcssp-muted"><?php echo esc_html($subtitle); ?></p></div></div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mlcssp_save_css'); ?>
                    <input type="hidden" name="action" value="mlcssp_save_css">
                    <input type="hidden" name="mlcssp_tab" value="<?php echo esc_attr($tab); ?>">
                    <div class="mlcssp-visual-grid">
        <?php
    }

    private function close_visual_panel(string $button_label): void {
        ?>
                    </div>
                    <div class="mlcssp-visual-footer"><button type="submit" class="button button-primary"><?php echo esc_html($button_label); ?></button></div>
                </form>
            </article>
        </section>
        <?php
    }
}
