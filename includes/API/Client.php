<?php
namespace PUDO\API;

class Client {
    private $partner_code;
    private $partner_password;
    private $api_url;

    public function __construct() {
        $this->partner_code = get_option('pudo_partner_code', '');
        $this->partner_password = get_option('pudo_partner_password', '');
        $this->api_url = get_option('pudo_test_mode', 'yes') === 'yes' ? PUDO_API_TEST_URL : PUDO_API_PROD_URL;
    }

    /**
     * Search for nearby PUDO points
     *
     * @param string $postal_code
     * @param array $params Optional parameters
     * @return array|WP_Error
     */
    public function search_dealers($postal_code, $params = []) {
        $default_params = [
            'partnerCode' => $this->partner_code,
            'partnerPassword' => $this->partner_password,
            'address' => $postal_code,
            'weight' => get_option('pudo_default_weight', '5.0'),
            'weightUnit' => get_option('pudo_weight_unit', 'KG'),
            'dimensionUnit' => get_option('pudo_dimension_unit', 'CM'),
            'width' => get_option('pudo_default_width', '10.0'),
            'height' => get_option('pudo_default_height', '2.0'),
            'length' => get_option('pudo_default_length', '10.0')
        ];

        $params = wp_parse_args($params, $default_params);
        
        return $this->make_request('/SearchDealers', $params);
    }

    /**
     * Place a new shipment
     *
     * @param array $shipment_data
     * @return array|WP_Error
     */
    public function place_shipment($shipment_data) {
        $required_fields = [
            'trackingNumber',
            'customerEmail',
            'customerMobile',
            'notificationPreference'
        ];

        foreach ($required_fields as $field) {
            if (empty($shipment_data[$field])) {
                return new \WP_Error(
                    'missing_required_field',
                    sprintf(__('Missing required field: %s', 'pudo-api-connector'), $field)
                );
            }
        }

        $params = array_merge([
            'partnerCode' => $this->partner_code,
            'partnerPassword' => $this->partner_password
        ], $shipment_data);

        return $this->make_request('/PlaceShipment', $params);
    }

    /**
     * Get shipment status
     *
     * @param string $tracking_number
     * @return array|WP_Error
     */
    public function get_shipment_status($tracking_number) {
        $params = [
            'partnerCode' => $this->partner_code,
            'partnerPassword' => $this->partner_password,
            'trackingNumber' => $tracking_number
        ];

        return $this->make_request('/PlaceShipmentStatus', $params);
    }

    /**
     * Make API request
     *
     * @param string $endpoint
     * @param array $params
     * @return array|WP_Error
     */
    private function make_request($endpoint, $params) {
        $url = trailingslashit($this->api_url) . ltrim($endpoint, '/');

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'body' => wp_json_encode($params),
            'timeout' => 30,
            'sslverify' => !get_option('pudo_disable_ssl_verify', false)
        ]);

        if (is_wp_error($response)) {
            $this->log_error($endpoint, $response->get_error_message(), $params);
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            $error_message = sprintf(
                __('API request failed with status code: %d', 'pudo-api-connector'),
                $status_code
            );
            $this->log_error($endpoint, $error_message, $params);
            return new \WP_Error('api_error', $error_message);
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = __('Invalid JSON response from API', 'pudo-api-connector');
            $this->log_error($endpoint, $error_message, $params);
            return new \WP_Error('invalid_json', $error_message);
        }

        return $data;
    }

    /**
     * Log API errors
     *
     * @param string $endpoint
     * @param string $message
     * @param array $context
     */
    private function log_error($endpoint, $message, $context = []) {
        if (!class_exists('\WC_Logger')) {
            return;
        }

        $logger = wc_get_logger();
        $context = array_merge(['source' => 'pudo-api'], $context);
        
        $logger->error(
            sprintf('[%s] %s: %s', date('Y-m-d H:i:s'), $endpoint, $message),
            $context
        );
    }

    /**
     * Validate API credentials
     *
     * @return bool
     */
    public function validate_credentials() {
        if (empty($this->partner_code) || empty($this->partner_password)) {
            return false;
        }

        // Try to make a simple API call to validate credentials
        $result = $this->search_dealers('M5V 2T6');
        return !is_wp_error($result);
    }
}
