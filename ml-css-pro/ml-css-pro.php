<?php
/**
 * Plugin Name: ML CSS Pro
 * Plugin URI: https://mlopesdesign.com.br
 * Description: Controlador visual de CSS para WordPress e Nicepage, com scanner de CSS do frontend, deteccao real de seletores, editor de codigo com realce de sintaxe, validacao de CSS, biblioteca de snippets, historico com restauracao e assistente de seletores.
 * Version: 1.5.5
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Marcio Lopes
 * Author URI: https://mlopesdesign.com.br
 * License: GPL2+
 * Text Domain: ml-css-pro
 * Update URI: https://github.com/mlopesdesign/ml-css-pro-releases
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MLCSSP_VERSION', '1.5.5');
define('MLCSSP_FILE', __FILE__);
define('MLCSSP_BASENAME', plugin_basename(__FILE__));
define('MLCSSP_DIR', plugin_dir_path(__FILE__));
define('MLCSSP_URL', plugin_dir_url(__FILE__));
define('MLCSSP_OPTION_KEY', 'mlcsspro_settings');
define('MLCSSP_PRESETS_KEY', 'mlcsspro_presets');
define('MLCSSP_HISTORY_KEY', 'mlcsspro_history');
define('MLCSSP_CACHE_VERSION_KEY', 'mlcsspro_cache_version');
define('MLCSSP_COMPILED_AT_KEY', 'mlcsspro_compiled_at');

require_once MLCSSP_DIR . 'includes/class-mlcssp-helpers.php';
require_once MLCSSP_DIR . 'includes/class-mlcssp-license.php';
require_once MLCSSP_DIR . 'includes/class-mlcssp-linter.php';
require_once MLCSSP_DIR . 'includes/class-mlcssp-settings.php';
require_once MLCSSP_DIR . 'includes/class-mlcssp-css-engine.php';
require_once MLCSSP_DIR . 'includes/class-mlcssp-scanner.php';
require_once MLCSSP_DIR . 'includes/class-mlcssp-admin-ui.php';
require_once MLCSSP_DIR . 'includes/Core/Updater.php';
require_once MLCSSP_DIR . 'includes/class-mlcssp-plugin.php';

function mlcssp_run_plugin(): void {
    static $instance = null;
    if ($instance === null) {
        $instance = new MLCSSP_Plugin();
    }
}
mlcssp_run_plugin();
