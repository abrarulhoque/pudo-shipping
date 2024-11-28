<?php
namespace PUDO\Core;

class Plugin {
    private static $instance = null;
    private $api;
    private $admin;
    private $shipping;
    private $labels;
    private $tracker;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }

    private function init_hooks() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
    }

    private function init_components() {
        // Initialize core components
        $this->api = new \PUDO\API\Client();
        $this->admin = new \PUDO\Admin\Settings();
        $this->shipping = new \PUDO\WooCommerce\Shipping();
        $this->labels = new \PUDO\Shipping\Labels();
        $this->tracker = new \PUDO\Shipping\Tracker();
    }

    public function load_textdomain() {
        load_plugin_textdomain('pudo-api-connector', false, dirname(plugin_basename(PUDO_PLUGIN_DIR)) . '/languages/');
    }

    public function admin_scripts() {
        wp_enqueue_style('pudo-admin', PUDO_PLUGIN_URL . 'assets/css/admin.css', [], PUDO_PLUGIN_VERSION);
        wp_enqueue_script('pudo-admin', PUDO_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], PUDO_PLUGIN_VERSION, true);
        
        wp_localize_script('pudo-admin', 'pudoAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pudo_admin_nonce'),
        ]);
    }

    public function frontend_scripts() {
        if (is_checkout()) {
            wp_enqueue_style('pudo-checkout', PUDO_PLUGIN_URL . 'assets/css/checkout.css', [], PUDO_PLUGIN_VERSION);
            wp_enqueue_script('pudo-checkout', PUDO_PLUGIN_URL . 'assets/js/checkout.js', ['jquery'], PUDO_PLUGIN_VERSION, true);
            
            wp_localize_script('pudo-checkout', 'pudoCheckout', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pudo_checkout_nonce'),
            ]);
        }
    }

    public function get_api() {
        return $this->api;
    }

    public function get_admin() {
        return $this->admin;
    }

    public function get_shipping() {
        return $this->shipping;
    }

    public function get_labels() {
        return $this->labels;
    }

    public function get_tracker() {
        return $this->tracker;
    }
}
