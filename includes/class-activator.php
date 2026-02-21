<?php
/**
 * Fired during plugin activation
 */
class WP_Site_Monitor_Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_tables();
        self::schedule_cron_jobs();
        self::set_default_options();
        
        // Store activation time
        update_option('wp_site_monitor_activated', time());
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'site_monitor_data';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            monitor_type varchar(50) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value text NOT NULL,
            severity varchar(20) DEFAULT 'info',
            checked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY monitor_type (monitor_type),
            KEY checked_at (checked_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Schedule WP Cron jobs
     */
    private static function schedule_cron_jobs() {
        // Hourly monitoring check
        if (!wp_next_scheduled('wp_site_monitor_hourly_check')) {
            wp_schedule_event(time(), 'hourly', 'wp_site_monitor_hourly_check');
        }
        
        // Daily summary email
        if (!wp_next_scheduled('wp_site_monitor_daily_summary')) {
            wp_schedule_event(time(), 'daily', 'wp_site_monitor_daily_summary');
        }
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_settings = array(
            'enable_email_alerts' => true,
            'alert_email' => get_option('admin_email'),
            'performance_threshold' => 3, // seconds
            'enable_performance_monitor' => true,
            'enable_security_monitor' => true,
            'enable_health_monitor' => true,
        );
        
        if (!get_option('wp_site_monitor_settings')) {
            add_option('wp_site_monitor_settings', $default_settings);
        }
    }
}
