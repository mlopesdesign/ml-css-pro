<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('mlcsspro_settings');
delete_option('mlcsspro_presets');
delete_option('mlcsspro_history');
delete_option('mlcsspro_license');
delete_option('mlcsspro_cache_version');
delete_option('mlcsspro_compiled_at');
delete_option('mlcsspro_scan_results');
delete_option('mlcsspro_scan_meta');
delete_option('mlcsspro_overrides');
delete_option('mlcsspro_last_save');
delete_option('mlcsspro_overrides_responsive');
delete_option('mlcsspro_responsive_history');
delete_option('mlcsspro_page_overrides');

delete_transient('mlcssp_updater_' . md5('ml-css-pro'));

$upload = wp_upload_dir();
if (!empty($upload['basedir'])) {
    $compiled = trailingslashit($upload['basedir']) . 'ml-css-pro/compiled.css';
    if (file_exists($compiled)) {
        @unlink($compiled);
    }
}
