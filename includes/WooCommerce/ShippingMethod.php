<?php
namespace PUDO\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

class ShippingMethod extends \WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        $this->id = 'pudo';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('PUDO Shipping', 'pudo-api-connector');
        $this->method_description = __('Allow customers to ship to PUDO points', 'pudo-api-connector');
        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];

        $this->init();
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', $this->method_title);
        $this->enabled = $this->get_option('enabled', 'yes');
        $this->tax_status = $this->get_option('tax_status', 'taxable');
        $this->cost = $this->get_option('cost', '0');
        $this->min_amount = $this->get_option('min_amount', '0');

        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->instance_form_fields = [
            'enabled' => [
                'title' => __('Enable', 'pudo-api-connector'),
                'type' => 'checkbox',
                'label' => __('Enable PUDO shipping', 'pudo-api-connector'),
                'default' => 'yes'
            ],
            'title' => [
                'title' => __('Title', 'pudo-api-connector'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'pudo-api-connector'),
                'default' => __('PUDO Point Delivery', 'pudo-api-connector'),
                'desc_tip' => true
            ],
            'tax_status' => [
                'title' => __('Tax Status', 'pudo-api-connector'),
                'type' => 'select',
                'description' => __('Set the tax status for this shipping method.', 'pudo-api-connector'),
                'default' => 'taxable',
                'options' => [
                    'taxable' => __('Taxable', 'pudo-api-connector'),
                    'none' => __('None', 'pudo-api-connector')
                ],
                'desc_tip' => true
            ],
            'cost' => [
                'title' => __('Cost', 'pudo-api-connector'),
                'type' => 'number',
                'description' => __('Enter a cost (excl. tax) or leave blank to disable.', 'pudo-api-connector'),
                'default' => '0',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min' => '0'
                ],
                'desc_tip' => true
            ],
            'min_amount' => [
                'title' => __('Minimum Order Amount', 'pudo-api-connector'),
                'type' => 'number',
                'description' => __('Minimum order amount for this shipping method to be available.', 'pudo-api-connector'),
                'default' => '0',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min' => '0'
                ],
                'desc_tip' => true
            ],
            'additional_settings' => [
                'title' => __('Additional Settings', 'pudo-api-connector'),
                'type' => 'title',
                'description' => __('Configure additional settings for PUDO shipping.', 'pudo-api-connector'),
            ],
            'max_distance' => [
                'title' => __('Maximum Distance (km)', 'pudo-api-connector'),
                'type' => 'number',
                'description' => __('Maximum distance in kilometers to show PUDO points.', 'pudo-api-connector'),
                'default' => '20',
                'custom_attributes' => [
                    'step' => '0.1',
                    'min' => '0'
                ],
                'desc_tip' => true
            ],
            'filter_services' => [
                'title' => __('Filter Services', 'pudo-api-connector'),
                'type' => 'multiselect',
                'description' => __('Only show PUDO points that support these services.', 'pudo-api-connector'),
                'options' => [
                    'FX' => __('Fax', 'pudo-api-connector'),
                    'TB' => __('Tobacco', 'pudo-api-connector'),
                    'AL' => __('Alcohol', 'pudo-api-connector'),
                    'RX' => __('Prescription', 'pudo-api-connector')
                ],
                'desc_tip' => true
            ]
        ];
    }

    public function calculate_shipping($package = []) {
        if ($this->min_amount > 0 && $package['contents_cost'] < $this->min_amount) {
            return;
        }

        $rate = [
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => $this->cost,
            'package' => $package,
        ];

        // Check if customer's postal code is within service area
        if (isset($package['destination']['postcode'])) {
            $api = \PUDO\Core\Plugin::instance()->get_api();
            $points = $api->search_dealers($package['destination']['postcode']);

            if (!is_wp_error($points) && !empty($points)) {
                $this->add_rate($rate);
            }
        }
    }

    public function is_available($package) {
        if ($this->enabled === 'no') {
            return false;
        }

        // Check minimum order amount
        if ($this->min_amount > 0 && $package['contents_cost'] < $this->min_amount) {
            return false;
        }

        // Check if we have a valid postal code
        if (empty($package['destination']['postcode'])) {
            return false;
        }

        return true;
    }

    public function admin_options() {
        // Check if API credentials are configured
        $api = \PUDO\Core\Plugin::instance()->get_api();
        if (!$api->validate_credentials()) {
            echo '<div class="notice notice-error"><p>' . 
                 esc_html__('PUDO API credentials are not configured. Please configure them in the PUDO settings page.', 'pudo-api-connector') . 
                 '</p></div>';
        }

        parent::admin_options();
    }
}
