<?php
/**
 * Plugin Name: WP Site Monitor
 * Plugin URI: https://github.com/suryayousufzai/wp-site-monitor
 * Description: Comprehensive WordPress site monitoring for performance, security, and health tracking
 * Version: 1.0.0
 * Author: Surya Yousufzai
 * Author URI: https://suryayousufzai.github.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-site-monitor
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WP_SITE_MONITOR_VERSION', '1.0.0');
define('WP_SITE_MONITOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_SITE_MONITOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_SITE_MONITOR_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class WP_Site_Monitor {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once WP_SITE_MONITOR_PLUGIN_DIR . 'includes/class-activator.php';
        require_once WP_SITE_MONITOR_PLUGIN_DIR . 'includes/class-deactivator.php';
        
        // Monitor classes
        require_once WP_SITE_MONITOR_PLUGIN_DIR . 'includes/monitors/class-performance-monitor.php';
        require_once WP_SITE_MONITOR_PLUGIN_DIR . 'includes/monitors/class-security-monitor.php';
        require_once WP_SITE_MONITOR_PLUGIN_DIR . 'includes/monitors/class-health-monitor.php';
        
        // API
        require_once WP_SITE_MONITOR_PLUGIN_DIR . 'includes/api/class-rest-controller.php';
        
        // Admin
        require_once WP_SITE_MONITOR_PLUGIN_DIR . 'includes/admin/class-admin.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array('WP_Site_Monitor_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('WP_Site_Monitor_Deactivator', 'deactivate'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));
        add_action('rest_api_init', array('WP_Site_Monitor_REST_Controller', 'register_routes'));
        
        // Scheduled monitoring
        add_action('wp_site_monitor_hourly_check', array($this, 'run_hourly_checks'));
    }
    
    /**
     * Initialize plugin components
     */
    public function init_components() {
        if (is_admin()) {
            new WP_Site_Monitor_Admin();
        }
    }
    
    /**
     * Run hourly monitoring checks
     */
    public function run_hourly_checks() {
        $performance = new WP_Site_Monitor_Performance();
        $security = new WP_Site_Monitor_Security();
        $health = new WP_Site_Monitor_Health();
        
        // Run checks and store data
        $performance->check_and_store();
        $security->check_and_store();
        $health->check_and_store();
        
        // Send alerts if needed
        $this->check_and_send_alerts();
    }
    
    /**
     * Check for issues and send email alerts
     */
    private function check_and_send_alerts() {
        $settings = get_option('wp_site_monitor_settings', array());
        
        if (empty($settings['enable_email_alerts'])) {
            return;
        }
        
        $issues = array();
        
        // Check for critical issues
        $security = new WP_Site_Monitor_Security();
        $security_data = $security->get_current_status();
        
        if (!empty($security_data['outdated_plugins'])) {
            $issues[] = count($security_data['outdated_plugins']) . ' outdated plugins detected';
        }
        
        if (!empty($issues)) {
            $this->send_alert_email($issues);
        }
    }
    
    /**
     * Send alert email
     */
    private function send_alert_email($issues) {
        $settings = get_option('wp_site_monitor_settings', array());
        $email = !empty($settings['alert_email']) ? $settings['alert_email'] : get_option('admin_email');
        
        $subject = '[' . get_bloginfo('name') . '] Site Monitor Alert';
        $message = "The following issues were detected:\n\n";
        $message .= implode("\n", $issues);
        $message .= "\n\nPlease log in to your WordPress admin to review.";
        
        wp_mail($email, $subject, $message);
    }
}

// Initialize plugin
function wp_site_monitor_init() {
    return WP_Site_Monitor::get_instance();
}

// Start the plugin
wp_site_monitor_init();
