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
            case 'change_table_prefix':
                $desired = isset($args['new_prefix']) ? (string) $args['new_prefix'] : '';
                return $this->change_table_prefix($desired);
            case 'hide_wp_version_meta':
                return $this->hide_wp_version_meta();
            case 'remove_readme_html':
                return $this->remove_readme_html();
            case 'remove_license_txt':
                return $this->remove_file_in_root('license.txt');
            case 'remove_install_script':
                return $this->remove_file('wp-admin/install.php');
            case 'remove_upgrade_script':
                return $this->remove_file('wp-admin/upgrade.php');
            case 'handle_debug_log':
                return $this->handle_debug_log();
            case 'block_wp_config_htaccess':
                return $this->block_wp_config_htaccess();
            case 'remove_phpinfo':
                return $this->remove_file('phpinfo.php');
            case 'remove_env':
                return $this->remove_file('.env');
            case 'block_wp_config_htaccess':
                // If Apache, apply .htaccess automatically; otherwise, return a code that UI can use.
                if (saf_server_is_apache()) {
                    return $this->block_wp_config_htaccess();
                } else {
                    // Signal UI to show Nginx advisory modal by returning a special marker
                    return 'NEED_SERVER_RULE';
                }
            case 'block_readme_htaccess':
                // example if you want similar behavior for readme; not required if you delete the file
                if (saf_server_is_apache()) {
                    return $this->block_file_htaccess('readme.html');
                } else {
                    return 'NEED_SERVER_RULE';
                }
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

    private function change_table_prefix($new_prefix) {
        global $wpdb;

        $new_prefix_raw = (string) $new_prefix;
        $new_prefix = trim($new_prefix_raw);

        // Validation: start with letter, only [A-Za-z0-9_], end with underscore, not 'wp_'
        if ($new_prefix === '' || strtolower($new_prefix) === 'wp_') return false;
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*_$/', $new_prefix)) return false;

        $old_prefix = $wpdb->prefix;
        if ($new_prefix === $old_prefix) return false;

        // Get all WordPress tables with the old prefix
        $tables = $wpdb->get_col($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->esc_like($old_prefix) . '%'
        ));

        if (empty($tables)) return false;

        // Collision check: ensure no table already exists with the new prefix names
        foreach ($tables as $old_table) {
            $suffix = substr($old_table, strlen($old_prefix));
            $new_table = $new_prefix . $suffix;
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $new_table));
            if ($exists) {
                // Collision: abort to avoid overwriting
                return false;
            }
        }

        // Begin transactional-ish sequence where possible
        // Note: MySQL transactions may not apply globally if tables are MyISAM. We proceed carefully.
        $renamed = [];
        foreach ($tables as $old_table) {
            $suffix = substr($old_table, strlen($old_prefix));
            $new_table = $new_prefix . $suffix;
            $sql = "RENAME TABLE `$old_table` TO `$new_table`";
            $ok = $wpdb->query($sql);
            if ($ok === false) {
                // Attempt rollback
                foreach (array_reverse($renamed) as $pair) {
                    $wpdb->query("RENAME TABLE `{$pair['new']}` TO `{$pair['old']}`");
                }
                return false;
            }
            $renamed[] = ['old' => $old_table, 'new' => $new_table];
        }

        // Update wp_options and wp_usermeta keys that contain prefix in meta keys
        // These two tables have rows where option_name/meta_key start with old prefix:
        // - {prefix}options: option_name like '{old_prefix}user_roles'
        // - {prefix}usermeta: meta_key like '{old_prefix}%'
        $options_table = $new_prefix . 'options';     // already renamed above
        $usermeta_table = $new_prefix . 'usermeta';   // already renamed above

        // Update option_name user_roles
        $old_user_roles = $old_prefix . 'user_roles';
        $new_user_roles = $new_prefix . 'user_roles';
        $wpdb->query($wpdb->prepare(
            "UPDATE `$options_table` SET option_name = %s WHERE option_name = %s",
            $new_user_roles, $old_user_roles
        ));

        // Update all meta_key occurrences that start with old prefix
        $wpdb->query($wpdb->prepare(
            "UPDATE `$usermeta_table` SET meta_key = REPLACE(meta_key, %s, %s) WHERE meta_key LIKE %s",
            $old_prefix, $new_prefix, $wpdb->esc_like($old_prefix) . '%'
        ));

        // Update wp-config.php $table_prefix
        $wp_config = ABSPATH . 'wp-config.php';
        if (!is_writable($wp_config)) {
            // rollback tables if we cannot persist config
            foreach (array_reverse($renamed) as $pair) {
                $wpdb->query("RENAME TABLE `{$pair['new']}` TO `{$pair['old']}`");
            }
            return false;
        }

        $content = file_get_contents($wp_config);
        // Try to replace existing $table_prefix assignment
        $pattern = '/^\s*\$table_prefix\s*=\s*[\'"][^\'"]+[\'"]\s*;\s*$/m';
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, "\$table_prefix = '{$new_prefix}';", $content, 1);
        } else {
            // Append if not found (rare)
            $content .= "\n\$table_prefix = '{$new_prefix}';\n";
        }
        $ok = file_put_contents($wp_config, $content);
        if ($ok === false) {
            // rollback tables if config write fails
            foreach (array_reverse($renamed) as $pair) {
                $wpdb->query("RENAME TABLE `{$pair['new']}` TO `{$pair['old']}`");
            }
            return false;
        }

        // Flush internal prefix and caches for current process
        $wpdb->set_prefix($new_prefix);
        wp_cache_flush();

        return true;
    }

    private function hide_wp_version_meta() {
        // Persist our intent in options so it applies on every request
        saf_update_option('hide_wp_version_meta', '1');

        // Apply immediately in this request too
        add_action('init', function(){
            remove_action('wp_head', 'wp_generator');
        });

        return true;
    }

    private function remove_readme_html() {
        $path = ABSPATH . 'readme.html';
        if (file_exists($path)) {
            if (!is_writable($path)) {
                // Try to rename to prevent public access if deletion not permitted
                $renamed = @rename($path, ABSPATH . 'readme.removed.html');
                return (bool) $renamed;
            }
            return @unlink($path);
        }
        return true; // already removed
    }

    private function remove_file_in_root($filename) {
        $path = ABSPATH . ltrim($filename, '/');
        if (!file_exists($path)) return true;
        if (!is_writable($path)) return false;
        return @unlink($path);
    }

    private function remove_file($relative) {
        $path = ABSPATH . ltrim($relative, '/');
        if (!file_exists($path)) return true;
        if (!is_writable($path)) return false;
        return @unlink($path);
    }

    private function handle_debug_log() {
        // 1) Delete or rotate wp-content/debug.log if exists
        $path = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($path)) {
            // Try to rotate with timestamp; if not writable, try to truncate
            if (is_writable($path)) {
                $rotated = @rename($path, WP_CONTENT_DIR . '/debug-' . date('Ymd-His') . '.log');
                if (!$rotated) {
                    // Truncate if rename failed
                    @file_put_contents($path, '');
                }
            } else {
                // If not writable, we cannot fix; return false so UI shows failure
                return false;
            }
        }
        // 2) Disable WP_DEBUG_LOG and WP_DEBUG in wp-config.php if they are enabled
        $wp_config = ABSPATH . 'wp-config.php';
        if (is_writable($wp_config)) {
            $content = file_get_contents($wp_config);
            // Force WP_DEBUG_LOG false
            $content = preg_replace("/define\s*\(\s*'WP_DEBUG_LOG'\s*,\s*true\s*\)\s*;/", "define('WP_DEBUG_LOG', false);", $content);
            if (strpos($content, "define('WP_DEBUG_LOG'") === false) {
                $content .= "\nif (!defined('WP_DEBUG_LOG')) define('WP_DEBUG_LOG', false);\n";
            }
            // Optionally disable WP_DEBUG to prevent new logs on production
            $content = preg_replace("/define\s*\(\s*'WP_DEBUG'\s*,\s*true\s*\)\s*;/", "define('WP_DEBUG', false);", $content);
            if (strpos($content, "define('WP_DEBUG'") === false) {
                $content .= "\nif (!defined('WP_DEBUG')) define('WP_DEBUG', false);\n";
            }
            $ok = file_put_contents($wp_config, $content);
            if ($ok === false) return false;
        }
        return true;
    }

    private function block_wp_config_htaccess() {
        // Only effective on Apache. We’ll add a protection stanza to the root .htaccess.
        $ht = ABSPATH . '.htaccess';
        $rule = "\n# SAF: protect wp-config.php\n<Files wp-config.php>\n  Require all denied\n</Files>\n";
        if (!file_exists($ht)) {
            // Create new .htaccess if root is writable
            if (!is_writable(ABSPATH)) return false;
            return file_put_contents($ht, $rule) !== false;
        }
        if (!is_writable($ht)) return false;
        $content = file_get_contents($ht);
        if (strpos($content, '<Files wp-config.php>') !== false) return true; // already protected
        return file_put_contents($ht, $content . $rule) !== false;
    }

    private function block_file_htaccess($filename) {
        $ht = ABSPATH . '.htaccess';
        $fname = basename($filename);
        $rule = "\n# SAF: protect {$fname}\n<Files {$fname}>\n  Require all denied\n</Files>\n";
        if (!file_exists($ht)) {
            if (!is_writable(ABSPATH)) return false;
            return file_put_contents($ht, $rule) !== false;
        }
        if (!is_writable($ht)) return false;
        $content = file_get_contents($ht);
        if (strpos($content, "<Files {$fname}>") !== false) return true;
        return file_put_contents($ht, $content . $rule) !== false;
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

// Enforce hiding generator meta if setting is on
add_action('init', function () {
    // Remove version in HTML head
    remove_action('wp_head', 'wp_generator');

    // Remove REST API discovery link (sometimes used as a clue)
    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('template_redirect', 'rest_output_link_header', 11);

    // Remove RSD and WLW links
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
}, 0);

// Also ensure the generator tag is blank if some themes/plugins force it:
add_filter('the_generator', '__return_empty_string', 99);