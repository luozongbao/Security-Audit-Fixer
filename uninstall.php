<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

// Use current runtime prefix
$table = $wpdb->prefix . 'saf_scan_results';
$wpdb->query("DROP TABLE IF EXISTS `$table`");

delete_option('saf_db_version');
delete_option('saf_settings');
@unlink(WP_CONTENT_DIR . '/uploads/saf.log');