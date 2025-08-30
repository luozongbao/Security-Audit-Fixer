<?php
if (!defined('ABSPATH')) exit;

class SAF_Fixer {

    public function apply_fix($fix_key, $args = []) {
        if (!current_user_can(saf_capability())) return false;

        switch ($fix_key) {
            case 'disable_file_edit':
                return $this->disable_file_edit();
            case 'disable_debug':
                return $this->disable_debug();
            case 'add_htaccess_no_indexes':
                return $this->add_htaccess_line('Options -Indexes');
            case 'disable_xmlrpc':
                return $this->disable_xmlrpc();
            case 'force_ssl_admin':
                return $this->force_ssl_admin();
            case 'rename_admin_user':
                $desired = isset($args['new_username']) ? (string) $args['new_username'] : '';
                return $this->rename_admin_user($desired);
            case 'enable_strong_password_policy':
                return $this->enable_strong_password_policy();
            case 'harden_wpconfig_perms':
                return $this->chmod_path(ABSPATH . 'wp-config.php', 0640);
            case 'harden_htaccess_perms':
                return $this->chmod_path(ABSPATH . '.htaccess', 0644);
            case 'add_basic_security_headers':
                return $this->add_security_headers();
            case 'update_all_plugins':
                return $this->update_all_plugins();
            case 'update_all_themes':
                return $this->update_all_themes();
            default:
                return false;
        }
    }

    private function disable_file_edit() {
        $wp_config = ABSPATH . 'wp-config.php';
        if (!is_writable($wp_config)) return false;
        $content = file_get_contents($wp_config);
        if (strpos($content, "define('DISALLOW_FILE_EDIT'") !== false) return true;
        $insert = "\nif (!defined('DISALLOW_FILE_EDIT')) define('DISALLOW_FILE_EDIT', true);\n";
        $ok = file_put_contents($wp_config, $content . $insert);
        return $ok !== false;
    }

    private function disable_debug() {
        $wp_config = ABSPATH . 'wp-config.php';
        if (!is_writable($wp_config)) return false;
        $content = file_get_contents($wp_config);

        // Attempt to set WP_DEBUG false
        $content = preg_replace("/define\s*\(\s*'WP_DEBUG'\s*,\s*true\s*\)\s*;/", "define('WP_DEBUG', false);", $content);
        if (strpos($content, "define('WP_DEBUG'") === false) {
            $content .= "\ndefine('WP_DEBUG', false);\n";
        }
        $ok = file_put_contents($wp_config, $content);
        return $ok !== false;
    }

    private function add_htaccess_line($line) {
        $path = ABSPATH . '.htaccess';
        if (!file_exists($path)) {
            if (!is_writable(ABSPATH)) return false;
            return file_put_contents($path, $line . PHP_EOL) !== false;
        }
        if (!is_writable($path)) return false;
        $content = file_get_contents($path);
        if (strpos($content, $line) !== false) return true;
        return file_put_contents($path, $content . PHP_EOL . $line . PHP_EOL) !== false;
    }

    private function disable_xmlrpc() {
        // Add a filter via options that our plugin will enforce (see below)
        saf_update_option('disable_xmlrpc_filter', '1');
        add_filter('xmlrpc_enabled', '__return_false');
        return true;
    }

    private function force_ssl_admin() {
        $wp_config = ABSPATH . 'wp-config.php';
        if (!is_writable($wp_config)) return false;
        $content = file_get_contents($wp_config);
        if (strpos($content, "FORCE_SSL_ADMIN") !== false) return true;
        $insert = "\nif (!defined('FORCE_SSL_ADMIN')) define('FORCE_SSL_ADMIN', true);\n";
        return file_put_contents($wp_config, $content . $insert) !== false;
    }

    private function rename_admin_user($desired_username = '') {
        $user = get_user_by('login', 'admin');
        if (!$user) return true; // Nothing to do

        $desired_username = sanitize_user($desired_username, true);

        // Strict enforcement: must be provided and valid, not “admin”, and unique.
        if (empty($desired_username)) return false;
        if (strtolower($desired_username) === 'admin') return false;
        if (username_exists($desired_username)) return false;

        global $wpdb;
        $updated = $wpdb->update($wpdb->users, ['user_login' => $desired_username], ['ID' => $user->ID]);
        if (false === $updated) return false;

        clean_user_cache($user->ID);
        $userdata = get_userdata($user->ID);
        if ($userdata && $userdata->user_nicename === 'admin') {
            wp_update_user(['ID' => $user->ID, 'user_nicename' => $desired_username]);
        }
        if ($userdata && $userdata->display_name === 'admin') {
            wp_update_user(['ID' => $user->ID, 'display_name' => $desired_username]);
        }

        // If the current logged-in user is that admin, force re-login for safety
        if (get_current_user_id() === (int)$user->ID) {
            wp_clear_auth_cookie();
            wp_set_auth_cookie($user->ID); // will still work by ID
        }

        return true;
    }

    private function enable_strong_password_policy() {
        // We set an option; enforcement is via a filter hook below.
        saf_update_option('enforce_strong_passwords', '1');
        return true;
    }

    private function chmod_path($path, $mode) {
        if (!file_exists($path)) return false;
        return @chmod($path, $mode);
    }

    private function add_security_headers() {
        // We’ll toggle an option; headers are added via send_headers hook (in Logger or Admin bootstrap).
        saf_update_option('add_security_headers', '1');
        add_action('send_headers', [$this, 'send_headers_cb']);
        return true;
    }

    public function send_headers_cb() {
        if (!saf_bool(saf_get_option('add_security_headers', false))) return;
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer-when-downgrade');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        // CSP is highly site-specific; provide a safe default comment only:
        // header("Content-Security-Policy: default-src 'self';");
    }

    private function update_all_plugins() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        wp_cache_flush();
        $updates = get_site_transient('update_plugins');
        if (empty($updates->response)) return true;

        $skin = new Automatic_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $result = $upgrader->bulk_upgrade(array_keys($updates->response));
        return is_array($result);
    }

    private function update_all_themes() {
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        wp_cache_flush();
        $updates = get_site_transient('update_themes');
        if (empty($updates->response)) return true;

        $skin = new Automatic_Upgrader_Skin();
        $upgrader = new Theme_Upgrader($skin);
        $result = $upgrader->bulk_upgrade(array_keys($updates->response));
        return is_array($result);
    }
}

// Global hooks tied to options set above
add_filter('xmlrpc_enabled', function($enabled){
    if (saf_bool(saf_get_option('disable_xmlrpc_filter', false))) return false;
    return $enabled;
});

add_action('user_profile_update_errors', function($errors, $update, $user){
    if (!saf_bool(saf_get_option('enforce_strong_passwords', false))) return;
    if (!empty($_POST['pass1'])) {
        $pwd = (string) $_POST['pass1'];
        // Simple policy: 10+ chars, upper, lower, number, symbol
        $strong = (strlen($pwd) >= 10) &&
                  preg_match('/[A-Z]/', $pwd) &&
                  preg_match('/[a-z]/', $pwd) &&
                  preg_match('/\d/', $pwd) &&
                  preg_match('/[^A-Za-z0-9]/', $pwd);
        if (!$strong) {
            $errors->add('weak_password', __('Password does not meet strength requirements.', 'security-audit-fixer'));
        }
    }
}, 10, 3);

add_action('send_headers', function(){
    if (!saf_bool(saf_get_option('add_security_headers', false))) return;
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer-when-downgrade');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
});