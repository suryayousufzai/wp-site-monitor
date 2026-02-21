<?php
/**
 * Admin Class
 * Handles WordPress admin interface and enqueues React dashboard
 */
class WP_Site_Monitor_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Add plugin admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Site Monitor', 'wp-site-monitor'),
            __('Site Monitor', 'wp-site-monitor'),
            'manage_options',
            'wp-site-monitor',
            array($this, 'render_admin_page'),
            'dashicons-performance',
            30
        );
    }
    
    /**
     * Render admin page (React app will mount here)
     */
    public function render_admin_page() {
        echo '<div id="wp-site-monitor-root"></div>';
    }
    
    /**
     * Enqueue admin assets (React app)
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin page
        if ($hook !== 'toplevel_page_wp-site-monitor') {
            return;
        }
        
        // For development, we'll include a simple inline React app
        // In production, this would load compiled React build files
        
        wp_enqueue_style(
            'wp-site-monitor-admin',
            WP_SITE_MONITOR_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            WP_SITE_MONITOR_VERSION
        );
        
        // Enqueue React and ReactDOM from CDN
        wp_enqueue_script(
            'react',
            'https://unpkg.com/react@18/umd/react.production.min.js',
            array(),
            '18.0.0',
            true
        );
        
        wp_enqueue_script(
            'react-dom',
            'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js',
            array('react'),
            '18.0.0',
            true
        );
        
        // Enqueue our React app
        wp_enqueue_script(
            'wp-site-monitor-app',
            WP_SITE_MONITOR_PLUGIN_URL . 'admin/js/dashboard-compiled.js',
            array('react', 'react-dom'),
            WP_SITE_MONITOR_VERSION,
            true
        );
        
        // Pass data to JavaScript
        wp_localize_script('wp-site-monitor-app', 'wpSiteMonitor', array(
            'apiUrl' => rest_url('wp-site-monitor/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'siteUrl' => get_site_url(),
        ));
    }
}
