<?php
if (!defined('ABSPATH')) exit;

class SAF_Logger {
    public static function log($message, $context = []) {
        $entry = '[' . current_time('mysql') . '] ' . $message . ' ' . wp_json_encode($context) . PHP_EOL;
        $file = WP_CONTENT_DIR . '/uploads/saf.log';
        // Ensure directory exists
        if (!file_exists(dirname($file))) return;
        @file_put_contents($file, $entry, FILE_APPEND);
    }
}