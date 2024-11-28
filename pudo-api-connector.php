<?php
/**
 * Plugin Name: PUDO API Connector
 * Description: Integrates WooCommerce with PUDO shipping services for convenient pickup/delivery options
 * Version: 1.0.0
 * Author: Cline
 * Text Domain: pudo-api-connector
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin constants
define('PUDO_PLUGIN_VERSION', '1.0.0');
define('PUDO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PUDO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PUDO_API_PROD_URL', 'https://partnerapi.pudoinc.com/PUDOService.svc');
define('PUDO_API_TEST_URL', 'https://testpartnerapi.pudoinc.com/PUDOService.svc');

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'PUDO\\';
    $base_dir = PUDO_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
function pudo_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . 
                 esc_html__('PUDO API Connector requires WooCommerce to be installed and active.', 'pudo-api-connector') . 
                 '</p></div>';
        });
        return;
    }

    // Initialize plugin components
    \PUDO\Core\Plugin::instance();
}
add_action('plugins_loaded', 'pudo_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create necessary database tables
    \PUDO\Core\Installer::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if necessary
    \PUDO\Core\Installer::deactivate();
});
