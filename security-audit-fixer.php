<?php
/**
 * Plugin Name: Security Audit and Fixer
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

// Emergency recovery in wp-config.php if needed:
// define('SAF_DISABLE_LOGIN_REWRITE', true);

if (!defined('SAF_DISABLE_LOGIN_REWRITE')) {
    define('SAF_DISABLE_LOGIN_REWRITE', false);
}

add_action('plugins_loaded', function () {
    if (SAF_DISABLE_LOGIN_REWRITE) return;
    if (!function_exists('saf_get_login_slug')) return; // ensure helpers are loaded

    $slug = saf_get_login_slug();
    if (!$slug) return; // feature disabled

    // Make WP helpers resolve to the custom slug
    add_filter('login_url', function ($login, $redirect, $force_reauth) use ($slug) {
        $url = home_url('/' . $slug . '/');
        if (!empty($redirect)) {
            $url = add_query_arg('redirect_to', rawurlencode($redirect), $url);
        }
        return $url;
    }, 10, 3);

    add_action('template_redirect', function () use ($slug) {
        // Let logged-in users use wp-admin normally
        if (is_admin() && is_user_logged_in()) {
            return;
        }

        $uri   = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path  = trim(parse_url($uri, PHP_URL_PATH) ?? '/', '/');
        $qs    = (string)($_SERVER['QUERY_STRING'] ?? '');
        $meth  = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $is_logged_in = is_user_logged_in();

        // 1) Serve the standard login UI on the custom slug
        if (strcasecmp($path, $slug) === 0) {
            require_once ABSPATH . 'wp-login.php';
            exit;
        }

        // 2) wp-login.php handling
        $is_wp_login_path = (bool)preg_match('~(^|/)(wp-login\.php)(/)?$~i', $path);

        // Allow POST for compatibility
        if ($is_wp_login_path && $meth !== 'GET') {
            return;
        }

        // For anonymous GETs to wp-login.php, do NOT redirect — send 404
        if ($is_wp_login_path && $meth === 'GET' && !$is_logged_in) {
            saf_send_404();
        }

        // 3) For anonymous /wp-admin, do NOT redirect — send 404
        $is_wp_admin_path = (stripos($path, 'wp-admin') === 0);
        if ($is_wp_admin_path && !$is_logged_in) {
            saf_send_404();
        }

        // Optional: allow password reset links that point to wp-login.php by internally serving the UI.
        // Uncomment if you want reset links to work without exposing the slug or doing a 302:
        /*
        if ($is_wp_login_path && $meth === 'GET' && !$is_logged_in) {
            parse_str($qs, $params);
            $has_reset = isset($params['action']) && in_array($params['action'], ['rp','resetpass'], true) && isset($params['key'], $params['login']);
            if ($has_reset) {
                require_once ABSPATH . 'wp-login.php';
                exit;
            }
        }
        */
    }, 0);
});

if (!function_exists('saf_send_404')) {
    function saf_send_404() {
        status_header(404);
        nocache_headers();
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Not Found</title></head><body>404 Not Found</body></html>';
        exit;
    }
}

function saf_run_plugin() {
    $plugin = new SAF_Plugin();
    $plugin->run();
}

saf_run_plugin();

