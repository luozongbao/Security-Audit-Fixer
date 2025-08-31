<?php
if (!defined('ABSPATH')) exit;

function saf_capability() {
    return 'manage_options';
}

function saf_verify_admin_action($nonce_action, $nonce_name = '_wpnonce') {
    if (!current_user_can(saf_capability())) return false;
    if (!isset($_REQUEST[$nonce_name])) return false;
    return wp_verify_nonce(sanitize_text_field($_REQUEST[$nonce_name]), $nonce_action);
}

function saf_get_option($key, $default = null) {
    $opts = get_option('saf_settings', []);
    return isset($opts[$key]) ? $opts[$key] : $default;
}

function saf_update_option($key, $value) {
    $opts = get_option('saf_settings', []);
    $opts[$key] = $value;
    update_option('saf_settings', $opts);
}

function saf_bool($val) {
    return filter_var($val, FILTER_VALIDATE_BOOLEAN);
}

function saf_server_is_apache() {
    if (function_exists('apache_get_modules')) return true;
    $ss = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower($_SERVER['SERVER_SOFTWARE']) : '';
    return strpos($ss, 'apache') !== false;
}

function saf_server_is_nginx() {
    $ss = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower($_SERVER['SERVER_SOFTWARE']) : '';
    return strpos($ss, 'nginx') !== false;
}

// Option accessors
function saf_get_login_slug() {
    $slug = get_option('saf_login_slug', '');
    $slug = trim((string)$slug, "/ \t\n\r\0\x0B");
    if ($slug && preg_match('/^[a-z0-9-]{3,64}$/i', $slug)) {
        return strtolower($slug);
    }
    return '';
}

function saf_set_login_slug($slug) {
    $slug = trim((string)$slug, "/ \t\n\r\0\x0B");
    if (!preg_match('/^[a-z0-9-]{3,64}$/i', $slug)) return false;
    if (in_array(strtolower($slug), ['wp-login','wp-admin','login','admin'], true)) return false;
    return update_option('saf_login_slug', strtolower($slug));
}

function saf_disable_login_slug() {
    return delete_option('saf_login_slug');
}





