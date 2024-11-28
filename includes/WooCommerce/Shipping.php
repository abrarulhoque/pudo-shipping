<?php
namespace PUDO\WooCommerce;

class Shipping {
    public function __construct() {
        add_action('woocommerce_shipping_init', [$this, 'init_shipping_method']);
        add_filter('woocommerce_shipping_methods', [$this, 'add_shipping_method']);
        add_action('woocommerce_after_shipping_rate', [$this, 'add_pudo_point_selector'], 10, 2);
        add_action('wp_ajax_get_pudo_points', [$this, 'get_pudo_points']);
        add_action('wp_ajax_nopriv_get_pudo_points', [$this, 'get_pudo_points']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_pudo_point_details']);
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'display_pudo_point_details']);
    }

    public function init_shipping_method() {
        require_once PUDO_PLUGIN_DIR . 'includes/WooCommerce/ShippingMethod.php';
    }

    public function add_shipping_method($methods) {
        $methods['pudo'] = '\PUDO\WooCommerce\ShippingMethod';
        return $methods;
    }

    public function add_pudo_point_selector($method, $index) {
        if ($method->get_method_id() !== 'pudo') {
            return;
        }

        $chosen_method = WC()->session->get('chosen_shipping_methods');
        $chosen_method = is_array($chosen_method) ? current($chosen_method) : '';
        $is_selected = strpos($chosen_method, 'pudo') !== false;

        if (!$is_selected) {
            return;
        }

        ?>
        <div class="pudo-point-selector" style="margin-top: 15px;">
            <div id="pudo-points-container">
                <div id="pudo-points-loading" style="display: none;">
                    <?php esc_html_e('Loading PUDO points...', 'pudo-api-connector'); ?>
                </div>
                <div id="pudo-points-list"></div>
            </div>
            <input type="hidden" name="selected_pudo_point" id="selected_pudo_point" />
        </div>
        <script type="text/template" id="pudo-point-template">
            <div class="pudo-point" data-dealer-id="{dealer_id}">
                <label>
                    <input type="radio" name="pudo_point" value="{dealer_id}" {checked}>
                    <strong>{name}</strong><br>
                    {address}<br>
                    {city}, {state} {postal_code}<br>
                    <small>{distance} km away</small>
                </label>
            </div>
        </script>
        <?php
    }

    public function get_pudo_points() {
        check_ajax_referer('pudo_checkout_nonce', 'nonce');

        $postal_code = isset($_POST['postal_code']) ? sanitize_text_field($_POST['postal_code']) : '';
        
        if (empty($postal_code)) {
            wp_send_json_error(['message' => __('Postal code is required', 'pudo-api-connector')]);
            return;
        }

        $api = \PUDO\Core\Plugin::instance()->get_api();
        $result = $api->search_dealers($postal_code);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
            return;
        }

        wp_send_json_success([
            'points' => $this->format_pudo_points($result)
        ]);
    }

    private function format_pudo_points($points) {
        $formatted = [];
        foreach ($points as $point) {
            $formatted[] = [
                'id' => $point['DealerId'],
                'name' => $point['Name'],
                'address' => $point['Address1'],
                'city' => $point['City'],
                'state' => $point['State'],
                'postal_code' => $point['PostalCode'],
                'distance' => isset($point['Distance']) ? round($point['Distance'], 2) : 0,
                'latitude' => $point['Latitude'],
                'longitude' => $point['Longitude'],
                'services' => $point['SupportedServices']
            ];
        }
        return $formatted;
    }

    public function save_pudo_point_details($order_id) {
        if (!isset($_POST['selected_pudo_point'])) {
            return;
        }

        $pudo_point = sanitize_text_field($_POST['selected_pudo_point']);
        if (empty($pudo_point)) {
            return;
        }

        // Decode the JSON string containing PUDO point details
        $point_details = json_decode(stripslashes($pudo_point), true);
        if (empty($point_details) || !is_array($point_details)) {
            return;
        }

        // Save PUDO point details as order meta
        update_post_meta($order_id, '_pudo_point_id', $point_details['id']);
        update_post_meta($order_id, '_pudo_point_name', $point_details['name']);
        update_post_meta($order_id, '_pudo_point_address', $point_details['address']);
        update_post_meta($order_id, '_pudo_point_city', $point_details['city']);
        update_post_meta($order_id, '_pudo_point_state', $point_details['state']);
        update_post_meta($order_id, '_pudo_point_postal_code', $point_details['postal_code']);
        
        // Format the address for the shipping label
        $formatted_address = sprintf(
            "PUDO%s - %s\n%s\n%s, %s %s",
            $point_details['id'],
            $point_details['name'],
            $point_details['address'],
            $point_details['city'],
            $point_details['state'],
            $point_details['postal_code']
        );
        update_post_meta($order_id, '_pudo_formatted_address', $formatted_address);
    }

    public function display_pudo_point_details($order) {
        $pudo_point_id = get_post_meta($order->get_id(), '_pudo_point_id', true);
        
        if (empty($pudo_point_id)) {
            return;
        }

        $point_name = get_post_meta($order->get_id(), '_pudo_point_name', true);
        $point_address = get_post_meta($order->get_id(), '_pudo_point_address', true);
        $point_city = get_post_meta($order->get_id(), '_pudo_point_city', true);
        $point_state = get_post_meta($order->get_id(), '_pudo_point_state', true);
        $point_postal_code = get_post_meta($order->get_id(), '_pudo_point_postal_code', true);

        ?>
        <h3><?php esc_html_e('PUDO Point Details', 'pudo-api-connector'); ?></h3>
        <p>
            <strong><?php echo esc_html($point_name); ?></strong><br>
            <?php echo esc_html($point_address); ?><br>
            <?php echo esc_html($point_city); ?>, <?php echo esc_html($point_state); ?> <?php echo esc_html($point_postal_code); ?><br>
            <strong><?php esc_html_e('PUDO ID:', 'pudo-api-connector'); ?></strong> <?php echo esc_html($pudo_point_id); ?>
        </p>
        <?php
    }
}
