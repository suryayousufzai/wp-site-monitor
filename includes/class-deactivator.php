<?php
/**
 * Fired during plugin deactivation
 */
class WP_Site_Monitor_Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        self::clear_scheduled_hooks();
        
        // Note: We don't delete the database table or options
        // in case the user wants to reactivate later
    }
    
    /**
     * Clear all scheduled cron jobs
     */
    private static function clear_scheduled_hooks() {
        $timestamp = wp_next_scheduled('wp_site_monitor_hourly_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wp_site_monitor_hourly_check');
        }
        
        $timestamp = wp_next_scheduled('wp_site_monitor_daily_summary');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wp_site_monitor_daily_summary');
        }
    }
}
