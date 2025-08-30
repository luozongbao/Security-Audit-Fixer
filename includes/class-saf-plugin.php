<?php
if (!defined('ABSPATH')) exit;

class SAF_Plugin {
    public function run() {
        if (is_admin()) {
            $admin = new SAF_Admin();
            $admin->init();
        }
        // Cron for auto-scan
        add_action('saf_daily_scan_event', [$this, 'run_daily_scan']);
        $this->maybe_schedule_cron();
    }

    private function maybe_schedule_cron() {
        $enabled = saf_bool(saf_get_option('auto_scan_daily', false));
        if ($enabled && !wp_next_scheduled('saf_daily_scan_event')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'saf_daily_scan_event');
        }
        if (!$enabled && wp_next_scheduled('saf_daily_scan_event')) {
            wp_clear_scheduled_hook('saf_daily_scan_event');
        }
    }

    public function run_daily_scan() {
        $scanner = new SAF_Scanner();
        $result = $scanner->run_full_scan();
        SAF_Logger::log('Daily scan complete.', ['result_id' => $result['id'] ?? null]);
    }
}