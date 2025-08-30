<?php
/**
 * Plugin Name: Breach
 * Plugin URI:  https://atipat.lorwongam.com/
 * Description: Scans your WordPress site for common vulnerabilities and offers suggestions or one-click fixes.
 * Version:     1.0.0
 * Author:      Atipat Lorwongam
 * Author URI:  https://atipat.lorwongam.com/
 * Text Domain: security-audit-fixer
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('SAF_VERSION', '1.0.0');
define('SAF_PLUGIN_FILE', __FILE__);
define('SAF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAF_DB_VERSION', '1.0');

require_once SAF_PLUGIN_DIR . 'includes/helpers.php';
require_once SAF_PLUGIN_DIR . 'includes/class-saf-activator.php';
require_once SAF_PLUGIN_DIR . 'includes/class-saf-deactivator.php';
require_once SAF_PLUGIN_DIR . 'includes/class-saf-plugin.php';
require_once SAF_PLUGIN_DIR . 'includes/class-saf-admin.php';
require_once SAF_PLUGIN_DIR . 'includes/class-saf-scanner.php';
require_once SAF_PLUGIN_DIR . 'includes/class-saf-fixer.php';
require_once SAF_PLUGIN_DIR . 'includes/class-saf-logger.php';

register_activation_hook(__FILE__, ['SAF_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['SAF_Deactivator', 'deactivate']);

function saf_run_plugin() {
    $plugin = new SAF_Plugin();
    $plugin->run();
}
saf_run_plugin();