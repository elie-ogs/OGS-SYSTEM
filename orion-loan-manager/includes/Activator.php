<?php
if (!defined('ABSPATH')) exit;

class OLM_Activator {
    public static function activate() {
        // Create tables
        OLM_Models::maybe_install();

        // Schedule daily batch at local midnight
        if (!wp_next_scheduled('olm_daily_batch')) {
            // Run approx at 00:05 site time
            $timestamp = self::next_local_midnight_timestamp() + 300;
            wp_schedule_event($timestamp, 'daily', 'olm_daily_batch');
        }
    }

    private static function next_local_midnight_timestamp() {
        $tz_string = get_option('timezone_string');
        if (!$tz_string) { $offset = (float) get_option('gmt_offset'); $tz_string = timezone_name_from_abbr('', $offset*3600, 0) ?: 'UTC'; }
        $dt = new DateTime('now', new DateTimeZone($tz_string));
        $dt->modify('+1 day')->setTime(0,0,0);
        return $dt->getTimestamp();
    }
}
