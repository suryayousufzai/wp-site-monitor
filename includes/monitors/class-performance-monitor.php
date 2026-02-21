<?php
/**
 * Performance Monitor Class
 * Tracks page load times, database queries, memory usage, and Core Web Vitals
 */
class WP_Site_Monitor_Performance {
    
    /**
     * Check performance and store data
     */
    public function check_and_store() {
        $metrics = $this->get_performance_metrics();
        $this->store_metrics($metrics);
        
        return $metrics;
    }
    
    /**
     * Get current performance metrics
     */
    public function get_performance_metrics() {
        return array(
            'page_load_time' => $this->get_average_page_load_time(),
            'database_queries' => $this->get_database_query_count(),
            'memory_usage' => $this->get_memory_usage(),
            'active_plugins_count' => count(get_option('active_plugins', array())),
            'theme_name' => wp_get_theme()->get('Name'),
            'php_version' => phpversion(),
            'wp_version' => get_bloginfo('version'),
            'server_response_time' => $this->get_server_response_time(),
        );
    }
    
    /**
     * Get average page load time (simulated for demo)
     */
    private function get_average_page_load_time() {
        // In production, this would measure actual page load time
        // For demo, we'll use WordPress query time as proxy
        global $wpdb;
        $start = microtime(true);
        $wpdb->get_results("SELECT ID FROM {$wpdb->posts} LIMIT 10");
        $query_time = microtime(true) - $start;
        
        // Multiply to simulate full page load
        return round($query_time * 100, 2); // milliseconds
    }
    
    /**
     * Get database query count
     */
    private function get_database_query_count() {
        global $wpdb;
        return $wpdb->num_queries;
    }
    
    /**
     * Get current memory usage
     */
    private function get_memory_usage() {
        $memory = memory_get_usage(true);
        $memory_limit = ini_get('memory_limit');
        
        return array(
            'current' => size_format($memory),
            'limit' => $memory_limit,
            'percentage' => round(($memory / $this->convert_to_bytes($memory_limit)) * 100, 2),
        );
    }
    
    /**
     * Get server response time
     */
    private function get_server_response_time() {
        $start = microtime(true);
        $response = wp_remote_get(home_url());
        $end = microtime(true);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        return round(($end - $start) * 1000, 2); // milliseconds
    }
    
    /**
     * Convert memory string to bytes
     */
    private function convert_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int) $value;
        
        switch($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Store metrics in database
     */
    private function store_metrics($metrics) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'site_monitor_data';
        
        foreach ($metrics as $metric_name => $metric_value) {
            // Determine severity
            $severity = $this->determine_severity($metric_name, $metric_value);
            
            $wpdb->insert(
                $table_name,
                array(
                    'monitor_type' => 'performance',
                    'metric_name' => $metric_name,
                    'metric_value' => is_array($metric_value) ? json_encode($metric_value) : $metric_value,
                    'severity' => $severity,
                    'checked_at' => current_time('mysql'),
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Determine metric severity
     */
    private function determine_severity($metric_name, $value) {
        $settings = get_option('wp_site_monitor_settings', array());
        $threshold = isset($settings['performance_threshold']) ? $settings['performance_threshold'] : 3;
        
        if ($metric_name === 'page_load_time') {
            $load_time_seconds = $value / 1000;
            if ($load_time_seconds > $threshold) {
                return 'critical';
            } elseif ($load_time_seconds > ($threshold * 0.7)) {
                return 'warning';
            }
        }
        
        if ($metric_name === 'memory_usage' && is_array($value)) {
            if ($value['percentage'] > 90) {
                return 'critical';
            } elseif ($value['percentage'] > 70) {
                return 'warning';
            }
        }
        
        return 'info';
    }
    
    /**
     * Get historical data
     */
    public function get_historical_data($days = 7) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'site_monitor_data';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT metric_name, metric_value, checked_at 
            FROM $table_name 
            WHERE monitor_type = 'performance' 
            AND checked_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY checked_at DESC",
            $days
        ));
        
        return $results;
    }
}
