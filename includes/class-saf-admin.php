<?php
if (!defined('ABSPATH')) exit;

class SAF_Admin {
    public function init() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_post_saf_run_scan', [$this, 'handle_run_scan']);
        add_action('admin_post_saf_apply_fix', [$this, 'handle_apply_fix']);
        add_action('admin_post_saf_save_settings', [$this, 'handle_save_settings']);
    }

    public function menu() {
        $cap = saf_capability();
        add_menu_page(
            __('Security Audit', 'security-audit-fixer'),
            __('Security Audit', 'security-audit-fixer'),
            $cap,
            'saf_dashboard',
            [$this, 'render_dashboard'],
            'dashicons-shield',
            60
        );

        add_submenu_page('saf_dashboard', __('Scan', 'security-audit-fixer'), __('Scan', 'security-audit-fixer'), $cap, 'saf_scan', [$this, 'render_scan']);
        add_submenu_page('saf_dashboard', __('Fixes', 'security-audit-fixer'), __('Fixes', 'security-audit-fixer'), $cap, 'saf_fixes', [$this, 'render_fixes']);
        add_submenu_page('saf_dashboard', __('Settings', 'security-audit-fixer'), __('Settings', 'security-audit-fixer'), $cap, 'saf_settings', [$this, 'render_settings']);
        add_submenu_page('saf_dashboard', __('Logs', 'security-audit-fixer'), __('Logs', 'security-audit-fixer'), $cap, 'saf_logs', [$this, 'render_logs']);
    }

    public function assets($hook) {
        if (strpos($hook, 'saf_') === false) return;
        wp_enqueue_style('saf-admin', SAF_PLUGIN_URL . 'assets/css/admin.css', [], SAF_VERSION);
        wp_enqueue_script('saf-admin', SAF_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], SAF_VERSION, true);
    }

    public function render_dashboard() {
        require SAF_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function render_scan() {
        require SAF_PLUGIN_DIR . 'admin/views/scan.php';
    }

    public function render_fixes() {
        require SAF_PLUGIN_DIR . 'admin/views/fixes.php';
    }

    public function render_settings() {
        require SAF_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function render_logs() {
        require SAF_PLUGIN_DIR . 'admin/views/logs.php';
    }

    public function handle_run_scan() {
        if (!saf_verify_admin_action('saf_run_scan')) wp_die(__('Invalid request', 'security-audit-fixer'));
        $scanner = new SAF_Scanner();
        $result = $scanner->run_full_scan();
        wp_redirect(add_query_arg(['page' => 'saf_scan', 'scan_id' => $result['id'] ?? 0, 'done' => 1], admin_url('admin.php')));
        exit;
    }

    public function handle_apply_fix() {
        if (!saf_verify_admin_action('saf_apply_fix')) wp_die(__('Invalid request', 'security-audit-fixer'));

        $fix_key = isset($_POST['fix_key']) ? sanitize_text_field($_POST['fix_key']) : '';
        $fixer = new SAF_Fixer();

        // Rename admin user flow
        if ($fix_key === 'rename_admin_user') {
            $new_username_raw = isset($_POST['new_username']) ? (string) wp_unslash($_POST['new_username']) : '';
            $new_username = sanitize_user($new_username_raw, true);

            $error = '';
            if (empty($new_username)) {
                $error = __('Please provide a valid username.', 'security-audit-fixer');
            } elseif (strtolower($new_username) === 'admin') {
                $error = __('New username cannot be “admin”.', 'security-audit-fixer');
            } elseif ($new_username !== $new_username_raw) {
                $error = __('Username contains invalid characters. Allowed: letters, numbers, and . _ - @', 'security-audit-fixer');
            } elseif (username_exists($new_username)) {
                $error = __('That username is already taken. Please choose another.', 'security-audit-fixer');
            }

            if ($error) {
                SAF_Logger::log('Rename admin validation failed', ['error' => $error]);
                $url = add_query_arg([
                    'page' => 'saf_fixes',
                    'applied' => 0,
                    'saf_rename_error' => rawurlencode($error),
                    'saf_new_username' => rawurlencode($new_username_raw),
                ], admin_url('admin.php'));
                wp_redirect($url);
                exit;
            }

            $ok = $fixer->apply_fix($fix_key, ['new_username' => $new_username]);
            SAF_Logger::log('Fix applied', ['fix_key' => $fix_key, 'ok' => $ok]);
            wp_redirect(add_query_arg(['page' => 'saf_fixes', 'applied' => $ok ? 1 : 0], admin_url('admin.php')));
            exit;
        }

        // Change table prefix flow
        if ($fix_key === 'change_table_prefix') {
            $new_prefix_raw = isset($_POST['new_prefix']) ? (string) wp_unslash($_POST['new_prefix']) : '';
            $new_prefix = trim($new_prefix_raw);

            // Validation: start letter, allowed chars, ends with underscore, not wp_
            $error = '';
            if ($new_prefix === '') {
                $error = __('Please provide a new table prefix.', 'security-audit-fixer');
            } elseif (strtolower($new_prefix) === 'wp_') {
                $error = __('New table prefix cannot be "wp_".', 'security-audit-fixer');
            } elseif (!preg_match('/^[A-Za-z][A-Za-z0-9_]*_$/', $new_prefix)) {
                $error = __('Prefix must start with a letter, contain only letters/numbers/underscores, and end with an underscore.', 'security-audit-fixer');
            }

            if ($error) {
                SAF_Logger::log('Change table prefix validation failed', ['error' => $error]);
                $url = add_query_arg([
                    'page' => 'saf_fixes',
                    'applied' => 0,
                    'saf_prefix_error' => rawurlencode($error),
                    'saf_new_prefix' => rawurlencode($new_prefix_raw),
                ], admin_url('admin.php'));
                wp_redirect($url);
                exit;
            }

            $ok = $fixer->apply_fix($fix_key, ['new_prefix' => $new_prefix]);
            SAF_Logger::log('Fix applied', ['fix_key' => $fix_key, 'ok' => $ok]);
            if (!$ok) {
                $url = add_query_arg([
                    'page' => 'saf_fixes',
                    'applied' => 0,
                    'saf_prefix_error' => rawurlencode(__('Failed to change table prefix. See logs and ensure backups.', 'security-audit-fixer')),
                    'saf_new_prefix' => rawurlencode($new_prefix_raw),
                ], admin_url('admin.php'));
                wp_redirect($url);
                exit;
            }

            wp_redirect(add_query_arg(['page' => 'saf_fixes', 'applied' => 1], admin_url('admin.php')));
            exit;
        }

        if ($fix_key === 'saf_fix_all_exposed') {
            // Define a set of safe, non-destructive fixes
            $bulk = [
                'remove_readme_html',
                'remove_license_txt',
                'remove_install_script',
                'remove_upgrade_script',
                'handle_debug_log',
                'remove_phpinfo',
                'remove_env',
                // wp-config.php is special; attempt auto for Apache, else show advisory
                'block_wp_config_htaccess'
            ];
            $fixer = new SAF_Fixer();
            $need_advice = false;
            $all_ok = true;
            foreach ($bulk as $fx) {
                $res = $fixer->apply_fix($fx, []);
                if ($res === 'NEED_SERVER_RULE') {
                    $need_advice = true;
                } elseif (!$res) {
                    $all_ok = false;
                }
            }
            if ($need_advice) {
                wp_redirect(add_query_arg(['page' => 'saf_fixes', 'saf_server_advice' => 'block_wp_config_htaccess'], admin_url('admin.php')));
                exit;
            }
            wp_redirect(add_query_arg(['page' => 'saf_fixes', 'applied' => $all_ok ? 1 : 0], admin_url('admin.php')));
            exit;
        }

        if ($fix_key === 'set_custom_login_url') {
            $slug_raw = isset($_POST['login_slug']) ? (string) wp_unslash($_POST['login_slug']) : '';
            $slug = strtolower(trim($slug_raw));

            $error = '';
            if (!preg_match('/^[a-z0-9-]{3,64}$/i', $slug)) {
                $error = __('Please enter 3–64 characters (letters, numbers, dashes only).', 'security-audit-fixer');
            } elseif (in_array($slug, ['wp-login','wp-admin','login','admin'], true)) {
                $error = __('Please choose a less predictable slug.', 'security-audit-fixer');
            }

            if ($error) {
                $url = add_query_arg([
                    'page' => 'saf_fixes',
                    'applied' => 0,
                    'saf_login_slug_error' => rawurlencode($error),
                    'saf_login_slug_value' => rawurlencode($slug_raw),
                ], admin_url('admin.php'));
                wp_redirect($url);
                exit;
            }

            $ok = (new SAF_Fixer())->apply_fix($fix_key, ['login_slug' => $slug]);
            wp_redirect(add_query_arg(['page' => 'saf_fixes', 'applied' => $ok ? 1 : 0], admin_url('admin.php')));
            exit;
        }        

        // Custom login URL flow (from Settings page)        
        if ($fix_key === 'set_custom_login_url') {
            // Disable request from Settings: remove the option and finish
            if (!empty($_POST['disable_login_slug'])) {
                saf_disable_login_slug(); // deletes the option
                wp_redirect(add_query_arg(['page' => 'saf_settings', 'applied' => 1], admin_url('admin.php')));
                exit;
            }

            // Save/update
            $slug_raw = isset($_POST['login_slug']) ? (string) wp_unslash($_POST['login_slug']) : '';
            $slug = strtolower(trim($slug_raw));

            $error = '';
            if (!preg_match('/^[a-z0-9-]{3,64}$/i', $slug)) {
                $error = __('Please enter 3–64 characters (letters, numbers, dashes only).', 'security-audit-fixer');
            } elseif (in_array($slug, ['wp-login','wp-admin','login','admin'], true)) {
                $error = __('Please choose a less predictable slug.', 'security-audit-fixer');
            }

            if ($error) {
                $url = add_query_arg([
                    'page' => 'saf_settings',
                    'applied' => 0,
                    'saf_login_slug_error' => rawurlencode($error),
                ], admin_url('admin.php'));
                wp_redirect($url);
                exit;
            }

            $ok = saf_set_login_slug($slug);
            wp_redirect(add_query_arg(['page' => 'saf_settings', 'applied' => $ok ? 1 : 0], admin_url('admin.php')));
            exit;
        }

        // Default handling

        $ok = $fixer->apply_fix($fix_key, $args ?? []);

        if ($ok === 'NEED_SERVER_RULE') {
            // Send server advisory params
            $url = add_query_arg([
                'page' => 'saf_fixes',
                'saf_server_advice' => $fix_key,
            ], admin_url('admin.php'));
            wp_redirect($url);
            exit;
        }

        SAF_Logger::log('Fix applied', ['fix_key' => $fix_key, 'ok' => (bool)$ok]);
        wp_redirect(add_query_arg(['page' => 'saf_fixes', 'applied' => $ok ? 1 : 0], admin_url('admin.php')));
        exit;
    }

    public function handle_save_settings() {
        if (!saf_verify_admin_action('saf_save_settings')) wp_die(__('Invalid request', 'security-audit-fixer'));

        $keys = ['auto_scan_daily', 'harden_xmlrpc', 'disable_file_edit', 'enforce_strong_passwords', 'limit_login_attempts'];
        foreach ($keys as $k) {
            $val = isset($_POST[$k]) ? '1' : '0';
            saf_update_option($k, $val);
        }
        SAF_Logger::log('Settings updated', ['settings' => get_option('saf_settings')]);
        wp_redirect(add_query_arg(['page' => 'saf_settings', 'saved' => 1], admin_url('admin.php')));
        exit;
    }
}