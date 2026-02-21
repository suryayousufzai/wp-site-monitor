<?php
/**
 * Security Monitor Class
 * Checks for outdated plugins/themes, SSL status, and login attempts
 */
class WP_Site_Monitor_Security {
    
    /**
     * Check security and store data
     */
    public function check_and_store() {
        $metrics = $this->get_current_status();
        $this->store_metrics($metrics);
        
        return $metrics;
    }
    
    /**
     * Get current security status
     */
    public function get_current_status() {
        return array(
            'outdated_plugins' => $this->get_outdated_plugins(),
            'outdated_themes' => $this->get_outdated_themes(),
            'ssl_status' => $this->check_ssl_status(),
            'wp_version_current' => $this->is_wp_version_current(),
            'php_version_supported' => $this->is_php_version_supported(),
            'file_permissions' => $this->check_file_permissions(),
            'admin_user_exists' => $this->check_admin_username(),
        );
    }
    
    /**
     * Get list of outdated plugins
     */
    private function get_outdated_plugins() {
        $outdated = array();
        
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $update_plugins = get_site_transient('update_plugins');
        
        if (!empty($update_plugins->response)) {
            foreach ($update_plugins->response as $plugin_file => $plugin_data) {
                if (isset($all_plugins[$plugin_file])) {
                    $outdated[] = array(
                        'name' => $all_plugins[$plugin_file]['Name'],
                        'current_version' => $all_plugins[$plugin_file]['Version'],
                        'new_version' => $plugin_data->new_version,
                    );
                }
            }
        }
        
        return $outdated;
    }
    
    /**
     * Get list of outdated themes
     */
    private function get_outdated_themes() {
        $outdated = array();
        $update_themes = get_site_transient('update_themes');
        
        if (!empty($update_themes->response)) {
            foreach ($update_themes->response as $theme_slug => $theme_data) {
                $theme = wp_get_theme($theme_slug);
                if ($theme->exists()) {
                    $outdated[] = array(
                        'name' => $theme->get('Name'),
                        'current_version' => $theme->get('Version'),
                        'new_version' => $theme_data['new_version'],
                    );
                }
            }
        }
        
        return $outdated;
    }
    
    /**
     * Check SSL certificate status
     */
    private function check_ssl_status() {
        $site_url = get_site_url();
        $is_ssl = (strpos($site_url, 'https://') === 0);
        
        return array(
            'enabled' => $is_ssl,
            'url' => $site_url,
            'status' => $is_ssl ? 'secure' : 'not_secure',
        );
    }
    
    /**
     * Check if WordPress version is current
     */
    private function is_wp_version_current() {
        global $wp_version;
        $update_core = get_site_transient('update_core');
        
        if (!isset($update_core->updates[0])) {
            return true;
        }
        
        $latest_version = $update_core->updates[0]->current;
        
        return array(
            'current' => $wp_version,
            'latest' => $latest_version,
            'is_current' => version_compare($wp_version, $latest_version, '>='),
        );
    }
    
    /**
     * Check if PHP version is supported
     */
    private function is_php_version_supported() {
        $php_version = phpversion();
        $min_version = '7.4';
        $recommended_version = '8.0';
        
        return array(
            'current' => $php_version,
            'minimum' => $min_version,
            'recommended' => $recommended_version,
            'is_supported' => version_compare($php_version, $min_version, '>='),
            'is_recommended' => version_compare($php_version, $recommended_version, '>='),
        );
    }
    
    /**
     * Check critical file permissions
     */
    private function check_file_permissions() {
        $wp_config = ABSPATH . 'wp-config.php';
        $issues = array();
        
        if (file_exists($wp_config)) {
            $perms = substr(sprintf('%o', fileperms($wp_config)), -4);
            if ($perms !== '0600' && $perms !== '0400') {
                $issues[] = array(
                    'file' => 'wp-config.php',
                    'current' => $perms,
                    'recommended' => '0600',
                );
            }
        }
        
        return $issues;
    }
    
    /**
     * Check if default 'admin' username exists
     */
    private function check_admin_username() {
        $admin_user = get_user_by('login', 'admin');
        
        return array(
            'exists' => (bool) $admin_user,
            'warning' => (bool) $admin_user, // It's a security risk
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
                    'monitor_type' => 'security',
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
        if ($metric_name === 'outdated_plugins' && !empty($value)) {
            return count($value) > 5 ? 'critical' : 'warning';
        }
        
        if ($metric_name === 'outdated_themes' && !empty($value)) {
            return 'warning';
        }
        
        if ($metric_name === 'ssl_status' && is_array($value) && !$value['enabled']) {
            return 'critical';
        }
        
        if ($metric_name === 'wp_version_current' && is_array($value) && !$value['is_current']) {
            return 'warning';
        }
        
        if ($metric_name === 'admin_user_exists' && is_array($value) && $value['exists']) {
            return 'warning';
        }
        
        return 'info';
    }
}
