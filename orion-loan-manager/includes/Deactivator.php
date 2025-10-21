<?php
if (!defined('ABSPATH')) exit;

class OLM_Deactivator {
    public static function deactivate() {
        // Clear cron
        $ts = wp_next_scheduled('olm_daily_batch');
        if ($ts) wp_unschedule_event($ts, 'olm_daily_batch');
    }
}
