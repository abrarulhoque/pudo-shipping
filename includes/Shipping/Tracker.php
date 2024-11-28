<?php
namespace PUDO\Shipping;

class Tracker {
    private $status_codes = [
        'REG' => 'Pending Delivery to Location',
        'ARR' => 'Scan In at Location',
        'DEL' => 'Picked Up By Customer',
        'RET' => 'Returned to Courier'
    ];

    public function __construct() {
        add_action('init', [$this, 'schedule_status_check']);
        add_action('pudo_check_shipment_statuses', [$this, 'check_shipment_statuses']);
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'display_tracking_info']);
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_tracking_info_frontend']);
        add_filter('woocommerce_order_status_changed', [$this, 'update_order_status_based_on_shipment'], 10, 4);
    }

    public function schedule_status_check() {
        if (!wp_next_scheduled('pudo_check_shipment_statuses')) {
            wp_schedule_event(time(), 'hourly', 'pudo_check_shipment_statuses');
        }
    }

    public function check_shipment_statuses() {
        global $wpdb;

        // Get orders with PUDO tracking numbers that haven't been delivered or returned
        $orders = wc_get_orders([
            'limit' => -1,
            'meta_key' => '_pudo_tracking_number',
            'meta_compare' => 'EXISTS',
            'status' => ['processing', 'completed']
        ]);

        $api = \PUDO\Core\Plugin::instance()->get_api();

        foreach ($orders as $order) {
            $tracking_number = get_post_meta($order->get_id(), '_pudo_tracking_number', true);
            $current_status = get_post_meta($order->get_id(), '_pudo_shipment_status', true);

            // Skip if already delivered or returned
            if (in_array($current_status, ['DEL', 'RET'])) {
                continue;
            }

            $result = $api->get_shipment_status($tracking_number);

            if (is_wp_error($result)) {
                $this->log_status_check_error($order->get_id(), $result->get_error_message());
                continue;
            }

            $new_status = $result['Status'] ?? '';
            if ($new_status && $new_status !== $current_status) {
                $this->update_shipment_status($order, $new_status);
            }
        }
    }

    private function update_shipment_status($order, $new_status) {
        // Update status meta
        update_post_meta($order->get_id(), '_pudo_shipment_status', $new_status);
        
        // Add order note
        $status_text = $this->status_codes[$new_status] ?? $new_status;
        $order->add_order_note(
            sprintf(
                __('PUDO Shipment Status Updated: %s', 'pudo-api-connector'),
                $status_text
            ),
            false, // Don't notify customer
            true // Added by system
        );

        // Log status change
        $this->log_status_change($order->get_id(), $new_status);

        // Update WooCommerce order status if needed
        $this->maybe_update_order_status($order, $new_status);
    }

    private function maybe_update_order_status($order, $shipment_status) {
        switch ($shipment_status) {
            case 'DEL':
                // Mark order as completed when customer picks up the package
                if ($order->get_status() !== 'completed') {
                    $order->update_status(
                        'completed',
                        __('Order automatically completed - Package picked up from PUDO point.', 'pudo-api-connector')
                    );
                }
                break;

            case 'RET':
                // Mark order as failed when package is returned
                if ($order->get_status() !== 'failed') {
                    $order->update_status(
                        'failed',
                        __('Order marked as failed - Package returned to courier.', 'pudo-api-connector')
                    );
                }
                break;
        }
    }

    public function display_tracking_info($order) {
        $tracking_number = get_post_meta($order->get_id(), '_pudo_tracking_number', true);
        if (!$tracking_number) {
            return;
        }

        $status = get_post_meta($order->get_id(), '_pudo_shipment_status', true);
        $status_text = $this->status_codes[$status] ?? $status;

        ?>
        <div class="pudo-tracking-info">
            <h4><?php esc_html_e('PUDO Tracking Information', 'pudo-api-connector'); ?></h4>
            <p>
                <strong><?php esc_html_e('Tracking Number:', 'pudo-api-connector'); ?></strong>
                <?php echo esc_html($tracking_number); ?>
            </p>
            <p>
                <strong><?php esc_html_e('Status:', 'pudo-api-connector'); ?></strong>
                <?php echo esc_html($status_text); ?>
            </p>
            <?php $this->display_status_history($order->get_id()); ?>
        </div>
        <?php
    }

    public function display_tracking_info_frontend($order) {
        if (!$order->has_status(['processing', 'completed', 'failed'])) {
            return;
        }

        $tracking_number = get_post_meta($order->get_id(), '_pudo_tracking_number', true);
        if (!$tracking_number) {
            return;
        }

        $status = get_post_meta($order->get_id(), '_pudo_shipment_status', true);
        $status_text = $this->status_codes[$status] ?? $status;

        ?>
        <h2><?php esc_html_e('Tracking Information', 'pudo-api-connector'); ?></h2>
        <table class="woocommerce-table pudo-tracking-info">
            <tbody>
                <tr>
                    <th><?php esc_html_e('Tracking Number:', 'pudo-api-connector'); ?></th>
                    <td><?php echo esc_html($tracking_number); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Current Status:', 'pudo-api-connector'); ?></th>
                    <td><?php echo esc_html($status_text); ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    private function display_status_history($order_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pudo_status_log';
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT status, timestamp FROM {$table_name} 
             WHERE order_id = %d 
             ORDER BY timestamp DESC",
            $order_id
        ));

        if (!$history) {
            return;
        }

        ?>
        <div class="pudo-status-history">
            <h4><?php esc_html_e('Status History', 'pudo-api-connector'); ?></h4>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Status', 'pudo-api-connector'); ?></th>
                        <th><?php esc_html_e('Date', 'pudo-api-connector'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $entry) : ?>
                        <tr>
                            <td><?php echo esc_html($this->status_codes[$entry->status] ?? $entry->status); ?></td>
                            <td><?php echo esc_html(
                                wp_date(
                                    get_option('date_format') . ' ' . get_option('time_format'),
                                    strtotime($entry->timestamp)
                                )
                            ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function log_status_change($order_id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'pudo_status_log';
        
        $wpdb->insert(
            $table_name,
            [
                'order_id' => $order_id,
                'status' => $status,
                'timestamp' => current_time('mysql')
            ],
            ['%d', '%s', '%s']
        );
    }

    private function log_status_check_error($order_id, $error_message) {
        if (!class_exists('\WC_Logger')) {
            return;
        }

        $logger = wc_get_logger();
        $logger->error(
            sprintf(
                'PUDO Status Check Error for Order #%d: %s',
                $order_id,
                $error_message
            ),
            ['source' => 'pudo-tracker']
        );
    }
}
