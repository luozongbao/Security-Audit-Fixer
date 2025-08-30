<?php
if (!defined('ABSPATH')) exit;

class SAF_Activator {
    public static function activate() {
        global $wpdb;

        $table = $wpdb->prefix . 'saf_scan_results';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scan_time DATETIME NOT NULL,
            scan_summary TEXT NULL,
            issues JSON NULL,
            fixed JSON NULL,
            wp_core_version VARCHAR(20) NULL,
            php_version VARCHAR(20) NULL,
            server_software VARCHAR(255) NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        add_option('saf_db_version', SAF_DB_VERSION);
        add_option('saf_settings', [
            'auto_scan_daily' => false,
            'harden_xmlrpc' => true,
            'disable_file_edit' => true,
            'enforce_strong_passwords' => true,
            'limit_login_attempts' => false, // Placeholder (implement if you add module)
        ]);
    }
}