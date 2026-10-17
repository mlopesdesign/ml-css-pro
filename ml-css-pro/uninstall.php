<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Desde 1.5.5 o CSS do usuario (configuracoes, presets, historico, overrides) e a licenca sao
// PRESERVADOS: reinstalar o plugin recupera tudo. Aqui saem so caches e dados regeneraveis.

delete_option('mlcsspro_cache_version');
delete_option('mlcsspro_compiled_at');
delete_option('mlcsspro_scan_results');
delete_option('mlcsspro_scan_meta');
delete_option('mlcsspro_last_save');

delete_transient('mlcssp_updater_' . md5('ml-css-pro'));
delete_transient('mlcssp_updater_' . md5('ml-css-pro|mlopesdesign/ml-css-pro-releases'));

$upload = wp_upload_dir();
if (!empty($upload['basedir'])) {
    $compiled = trailingslashit($upload['basedir']) . 'ml-css-pro/compiled.css';
    if (file_exists($compiled)) {
        @unlink($compiled);
    }
}

// 1.6.0: licenca pelo site tools — a licenca local fica (reinstalar recupera); so a
// renovacao diaria e desagendada.
wp_clear_scheduled_hook( 'mllc_ml_css_pro_refresh' );
