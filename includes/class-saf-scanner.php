<?php
if (!defined('ABSPATH')) exit;

class SAF_Scanner {

    public function run_full_scan() {
        global $wpdb;
        $issues = [];

        $issues = array_merge($issues, $this->check_versions());
        $issues = array_merge($issues, $this->check_plugins_themes_updates());
        $issues = array_merge($issues, $this->check_file_edit());
        $issues = array_merge($issues, $this->check_debug());
        $issues = array_merge($issues, $this->check_directory_indexes());
        $issues = array_merge($issues, $this->check_xmlrpc());
        $issues = array_merge($issues, $this->check_https());
        $issues = array_merge($issues, $this->check_admin_usernames());
        $issues = array_merge($issues, $this->check_users_password_policies());
        $issues = array_merge($issues, $this->check_file_permissions());
        $issues = array_merge($issues, $this->check_security_headers());
        $issues = array_merge($issues, $this->check_table_prefix());
        $issues = array_merge($issues, $this->check_version_exposure());

        $summary = sprintf('%d potential issues found', count($issues));

        $data = [
            'scan_time'       => current_time('mysql'),
            'scan_summary'    => $summary,
            'issues'          => wp_json_encode($issues),
            'fixed'           => wp_json_encode([]),
            'wp_core_version' => get_bloginfo('version'),
            'php_version'     => PHP_VERSION,
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field($_SERVER['SERVER_SOFTWARE']) : '',
        ];

        $table = $wpdb->prefix . 'saf_scan_results';
        $wpdb->insert($table, $data);
        $id = $wpdb->insert_id;

        return ['id' => $id, 'summary' => $summary, 'issues' => $issues];
    }

    private function issue($key, $title, $severity, $details, $fix_key = null) {
        return [
            'key' => $key, // unique per issue type
            'title' => $title,
            'severity' => $severity, // low|medium|high|critical
            'details' => $details,
            'fix_key' => $fix_key, // can be used for one-click fix
        ];
    }

    private function check_versions() {
        $issues = [];
        // WP core
        include_once ABSPATH . 'wp-includes/version.php';
        $current = get_bloginfo('version');
        // WordPress handles core updates; we can flag if auto-updates disabled
        if (!wp_is_auto_update_enabled_for_type('core')) {
            $issues[] = $this->issue(
                'core_auto_update_disabled',
                'Core auto-updates disabled',
                'medium',
                'Enable automatic core updates to reduce window of exposure.',
                'enable_core_auto_updates' // informational; implementing auto core updates programmatically is limited
            );
        }
        return $issues;
    }

    private function check_plugins_themes_updates() {
        $issues = [];
        // Plugins
        $updates = get_site_transient('update_plugins');
        if (!empty($updates->response)) {
            $count = count($updates->response);
            $issues[] = $this->issue(
                'plugins_outdated',
                'Outdated plugins detected',
                'high',
                "$count plugin(s) have updates available. Keeping plugins updated reduces vulnerabilities.",
                'update_all_plugins'
            );
        }
        // Themes
        $t_updates = get_site_transient('update_themes');
        if (!empty($t_updates->response)) {
            $count = count($t_updates->response);
            $issues[] = $this->issue(
                'themes_outdated',
                'Outdated themes detected',
                'medium',
                "$count theme(s) have updates available.",
                'update_all_themes'
            );
        }
        return $issues;
    }

    private function check_file_edit() {
        $issues = [];
        if (!defined('DISALLOW_FILE_EDIT') || DISALLOW_FILE_EDIT !== true) {
            $issues[] = $this->issue(
                'file_edit_enabled',
                'Theme/Plugin file editor is enabled',
                'medium',
                'DISALLOW_FILE_EDIT is not set to true. Attackers with admin access can edit PHP files from dashboard.',
                'disable_file_edit'
            );
        }
        return $issues;
    }

    private function check_debug() {
        $issues = [];
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $issues[] = $this->issue(
                'debug_enabled',
                'WP_DEBUG is enabled',
                'medium',
                'Debug output may leak sensitive information on production sites.',
                'disable_debug'
            );
        }
        return $issues;
    }

    private function check_directory_indexes() {
        $issues = [];
        // Basic heuristic: check if .htaccess contains Options -Indexes on Apache
        if (function_exists('apache_get_modules')) {
            $ht = ABSPATH . '.htaccess';
            if (file_exists($ht)) {
                $content = file_get_contents($ht);
                if (strpos($content, 'Options -Indexes') === false) {
                    $issues[] = $this->issue(
                        'dir_indexing',
                        'Directory listing may be enabled',
                        'low',
                        'Consider adding "Options -Indexes" to .htaccess to prevent directory listing.',
                        'add_htaccess_no_indexes'
                    );
                }
            }
        }
        return $issues;
    }

    private function check_xmlrpc() {
        $issues = [];
        // If hardening is desired
        if (saf_bool(saf_get_option('harden_xmlrpc', true))) {
            // WordPress loads XML-RPC by default; we recommend blocking if not needed.
            $issues[] = $this->issue(
                'xmlrpc_enabled',
                'XML-RPC is enabled',
                'low',
                'If you do not use Jetpack or external apps, consider disabling XML-RPC to reduce attack surface.',
                'disable_xmlrpc'
            );
        }
        return $issues;
    }

    private function check_https() {
        $issues = [];
        if (!is_ssl()) {
            $issues[] = $this->issue(
                'not_using_https',
                'Site not enforcing HTTPS',
                'high',
                'Your site is not currently using SSL. Enable HTTPS and force SSL for admin and login.',
                'force_ssl_admin'
            );
        }
        return $issues;
    }

    private function check_admin_usernames() {
        $issues = [];
        $user = get_user_by('login', 'admin');
        if ($user) {
            $issues[] = $this->issue(
                'admin_username_present',
                'Default "admin" username exists',
                'high',
                'Attackers commonly brute-force the "admin" username. Rename or remove it.',
                'rename_admin_user'
            );
        }
        return $issues;
    }

    private function check_users_password_policies() {
        $issues = [];
        // We can’t read password strength, but we can enforce strong password settings via filters/policies in Fixer.
        if (!saf_bool(saf_get_option('enforce_strong_passwords', true))) {
            $issues[] = $this->issue(
                'weak_password_policy',
                'Strong passwords not enforced',
                'medium',
                'Enable strong password enforcement to reduce brute-force risk.',
                'enable_strong_password_policy'
            );
        }
        return $issues;
    }

    private function check_file_permissions() {
        $issues = [];
        $paths = [
            'wp-config.php' => ABSPATH . 'wp-config.php',
            '.htaccess' => ABSPATH . '.htaccess',
        ];
        foreach ($paths as $label => $path) {
            if (file_exists($path)) {
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                // Suggest 0640 or stricter for wp-config, 0644 for .htaccess
                if ($label === 'wp-config.php' && (int)$perms > 640) {
                    $issues[] = $this->issue(
                        'wpconfig_perms',
                        'Loose wp-config.php permissions',
                        'high',
                        "wp-config.php permissions are $perms. Consider 0640.",
                        'harden_wpconfig_perms'
                    );
                }
                if ($label === '.htaccess' && (int)$perms > 644) {
                    $issues[] = $this->issue(
                        'htaccess_perms',
                        'Loose .htaccess permissions',
                        'medium',
                        ".htaccess permissions are $perms. Consider 0644.",
                        'harden_htaccess_perms'
                    );
                }
            }
        }
        return $issues;
    }

    private function check_security_headers() {
        $issues = [];
        // We can’t reliably read actual runtime headers from admin. Recommend rules in .htaccess/nginx.
        $issues[] = $this->issue(
            'missing_security_headers',
            'Common security headers not enforced',
            'medium',
            'Consider adding headers: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Content-Security-Policy.',
            'add_basic_security_headers'
        );
        return $issues;
    }
    
    private function check_table_prefix() {
        global $wpdb;
        $issues = [];
        $current = $wpdb->prefix;
        if ($current === 'wp_') {
            $issues[] = $this->issue(
                'default_table_prefix',
                'Default table prefix "wp_" detected',
                'medium',
                'Using the default "wp_" prefix is predictable. Change to a custom prefix like "site123_".',
                'change_table_prefix'
            );
        }
        return $issues;
    }

    private function check_version_exposure() {
        $issues = [];

        // Check whether wp_generator is active (i.e., not removed)
        // We can detect by checking if someone already removed the action.
        if (has_action('wp_head', 'wp_generator') !== false) {
            $issues[] = $this->issue(
                'wp_version_meta_exposed',
                'WordPress version exposed in HTML',
                'medium',
                'Your site may output the WordPress version via the generator meta tag.',
                'hide_wp_version_meta'
            );
        }

        // Check if readme.html exists in the ABSPATH and is readable (publicly likely accessible)
        $readme_path = ABSPATH . 'readme.html';
        if (file_exists($readme_path)) {
            $issues[] = $this->issue(
                'readme_html_present',
                'readme.html is present and may expose version',
                'low',
                'The default WordPress readme.html file can reveal your version to scanners.',
                'remove_readme_html'
            );
        }

        return $issues;
    }

}