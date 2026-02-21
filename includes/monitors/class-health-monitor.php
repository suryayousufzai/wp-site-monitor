<?php
/**
 * Health Monitor Class
 * Checks disk space, database size, and overall site health score
 */
class WP_Site_Monitor_Health {
    
    /**
     * Check health and store data
     */
    public function check_and_store() {
        $metrics = $this->get_health_metrics();
        $this->store_metrics($metrics);
        
        return $metrics;
    }
    
    /**
     * Get current health metrics
     */
    public function get_health_metrics() {
        return array(
            'disk_space' => $this->get_disk_space(),
            'database_size' => $this->get_database_size(),
            'uploads_directory_size' => $this->get_uploads_size(),
            'health_score' => $this->calculate_health_score(),
            'cron_status' => $this->check_cron_status(),
            'rest_api_status' => $this->check_rest_api_status(),
        );
    }
    
    /**
     * Get disk space information
     */
    private function get_disk_space() {
        $total = disk_total_space(ABSPATH);
        $free = disk_free_space(ABSPATH);
        $used = $total - $free;
        
        return array(
            'total' => size_format($total),
            'used' => size_format($used),
            'free' => size_format($free),
            'percentage_used' => round(($used / $total) * 100, 2),
        );
    }
    
    /**
     * Get database size
     */
    private function get_database_size() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'posts';
        $size_query = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(data_length + index_length) as size
            FROM information_schema.TABLES 
            WHERE table_schema = %s
            AND table_name LIKE %s",
            DB_NAME,
            $wpdb->esc_like($wpdb->prefix) . '%'
        ));
        
        $db_size = isset($size_query->size) ? $size_query->size : 0;
        
        return array(
            'size' => size_format($db_size),
            'bytes' => $db_size,
        );
    }
    
    /**
     * Get uploads directory size
     */
    private function get_uploads_size() {
        $upload_dir = wp_upload_dir();
        $size = $this->get_directory_size($upload_dir['basedir']);
        
        return array(
            'size' => size_format($size),
            'bytes' => $size,
        );
    }
    
    /**
     * Calculate directory size recursively
     */
    private function get_directory_size($directory) {
        $size = 0;
        
        if (!is_dir($directory)) {
            return 0;
        }
        
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
        
        return $size;
    }
    
    /**
     * Calculate overall health score (0-100)
     */
    private function calculate_health_score() {
        $score = 100;
        $issues = array();
        
        // Check disk space
        $disk = $this->get_disk_space();
        if ($disk['percentage_used'] > 90) {
            $score -= 20;
            $issues[] = 'High disk usage';
        } elseif ($disk['percentage_used'] > 75) {
            $score -= 10;
        }
        
        // Check for outdated components
        $security = new WP_Site_Monitor_Security();
        $security_data = $security->get_current_status();
        
        if (!empty($security_data['outdated_plugins'])) {
            $count = count($security_data['outdated_plugins']);
            $score -= min($count * 5, 25);
            $issues[] = $count . ' outdated plugins';
        }
        
        if (!empty($security_data['outdated_themes'])) {
            $score -= 10;
            $issues[] = 'Outdated themes';
        }
        
        // Check SSL
        if (!$security_data['ssl_status']['enabled']) {
            $score -= 15;
            $issues[] = 'No SSL certificate';
        }
        
        // Check WordPress version
        if (is_array($security_data['wp_version_current']) && !$security_data['wp_version_current']['is_current']) {
            $score -= 10;
            $issues[] = 'WordPress not up to date';
        }
        
        return array(
            'score' => max($score, 0),
            'grade' => $this->get_grade_from_score($score),
            'issues' => $issues,
        );
    }
    
    /**
     * Get letter grade from score
     */
    private function get_grade_from_score($score) {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
    
    /**
     * Check if WP Cron is working
     */
    private function check_cron_status() {
        $doing_cron = defined('DOING_CRON') && DOING_CRON;
        $disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        
        return array(
            'enabled' => !$disabled,
            'running' => $doing_cron,
            'status' => !$disabled ? 'working' : 'disabled',
        );
    }
    
    /**
     * Check if REST API is accessible
     */
    private function check_rest_api_status() {
        $response = wp_remote_get(rest_url());
        
        return array(
            'accessible' => !is_wp_error($response),
            'status_code' => is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response),
        );
    }
    
    /**
     * Store metrics in database
     */
    private function store_metrics($metrics) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'site_monitor_data';
        
        foreach ($metrics as $metric_name => $metric_value) {
            $severity = $this->determine_severity($metric_name, $metric_value);
            
            $wpdb->insert(
                $table_name,
                array(
                    'monitor_type' => 'health',
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
        if ($metric_name === 'disk_space' && is_array($value)) {
            if ($value['percentage_used'] > 90) {
                return 'critical';
            } elseif ($value['percentage_used'] > 75) {
                return 'warning';
            }
        }
        
        if ($metric_name === 'health_score' && is_array($value)) {
            if ($value['score'] < 60) {
                return 'critical';
            } elseif ($value['score'] < 80) {
                return 'warning';
            }
        }
        
        return 'info';
    }
}
