<?php
/**
 * REST API Controller
 * Provides endpoints for the React dashboard to fetch monitoring data
 */
class WP_Site_Monitor_REST_Controller {
    
    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Get current status
        register_rest_route('wp-site-monitor/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_current_status'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
        ));
        
        // Get historical data
        register_rest_route('wp-site-monitor/v1', '/history/(?P<type>[a-zA-Z]+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_historical_data'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
            'args' => array(
                'type' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return in_array($param, array('performance', 'security', 'health'));
                    }
                ),
                'days' => array(
                    'default' => 7,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // Get settings
        register_rest_route('wp-site-monitor/v1', '/settings', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_settings'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
        ));
        
        // Update settings
        register_rest_route('wp-site-monitor/v1', '/settings', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'update_settings'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
        ));
        
        // Run manual check
        register_rest_route('wp-site-monitor/v1', '/check', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'run_manual_check'),
            'permission_callback' => array(__CLASS__, 'check_permission'),
        ));
    }
    
    /**
     * Check if user has permission to access API
     */
    public static function check_permission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get current status from all monitors
     */
    public static function get_current_status() {
        $performance = new WP_Site_Monitor_Performance();
        $security = new WP_Site_Monitor_Security();
        $health = new WP_Site_Monitor_Health();
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'performance' => $performance->get_performance_metrics(),
                'security' => $security->get_current_status(),
                'health' => $health->get_health_metrics(),
                'last_check' => get_option('wp_site_monitor_last_check', current_time('mysql')),
            ),
        ));
    }
    
    /**
     * Get historical data for a specific monitor type
     */
    public static function get_historical_data($request) {
        $type = $request->get_param('type');
        $days = $request->get_param('days');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'site_monitor_data';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT metric_name, metric_value, severity, checked_at 
            FROM $table_name 
            WHERE monitor_type = %s 
            AND checked_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY checked_at ASC",
            $type,
            $days
        ));
        
        // Format data for charts
        $formatted_data = array();
        foreach ($results as $row) {
            $metric_name = $row->metric_name;
            if (!isset($formatted_data[$metric_name])) {
                $formatted_data[$metric_name] = array();
            }
            
            $formatted_data[$metric_name][] = array(
                'value' => $row->metric_value,
                'timestamp' => $row->checked_at,
                'severity' => $row->severity,
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $formatted_data,
        ));
    }
    
    /**
     * Get plugin settings
     */
    public static function get_settings() {
        $settings = get_option('wp_site_monitor_settings', array());
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $settings,
        ));
    }
    
    /**
     * Update plugin settings
     */
    public static function update_settings($request) {
        $new_settings = $request->get_json_params();
        
        // Sanitize settings
        $sanitized = array(
            'enable_email_alerts' => !empty($new_settings['enable_email_alerts']),
            'alert_email' => sanitize_email($new_settings['alert_email'] ?? get_option('admin_email')),
            'performance_threshold' => absint($new_settings['performance_threshold'] ?? 3),
            'enable_performance_monitor' => !empty($new_settings['enable_performance_monitor']),
            'enable_security_monitor' => !empty($new_settings['enable_security_monitor']),
            'enable_health_monitor' => !empty($new_settings['enable_health_monitor']),
        );
        
        update_option('wp_site_monitor_settings', $sanitized);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $sanitized,
        ));
    }
    
    /**
     * Run a manual monitoring check
     */
    public static function run_manual_check() {
        $performance = new WP_Site_Monitor_Performance();
        $security = new WP_Site_Monitor_Security();
        $health = new WP_Site_Monitor_Health();
        
        $performance->check_and_store();
        $security->check_and_store();
        $health->check_and_store();
        
        update_option('wp_site_monitor_last_check', current_time('mysql'));
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Manual check completed successfully',
            'data' => array(
                'performance' => $performance->get_performance_metrics(),
                'security' => $security->get_current_status(),
                'health' => $health->get_health_metrics(),
            ),
        ));
    }
}
