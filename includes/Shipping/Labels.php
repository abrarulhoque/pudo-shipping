<?php
namespace PUDO\Shipping;

class Labels {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_shipping_label_meta_box']);
        add_action('wp_ajax_generate_pudo_label', [$this, 'generate_label']);
        add_action('woocommerce_order_status_changed', [$this, 'maybe_generate_label'], 10, 4);
    }

    public function add_shipping_label_meta_box() {
        add_meta_box(
            'pudo-shipping-label',
            __('PUDO Shipping Label', 'pudo-api-connector'),
            [$this, 'render_shipping_label_meta_box'],
            'shop_order',
            'side',
            'default'
        );
    }

    public function render_shipping_label_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }

        $shipping_method = '';
        foreach ($order->get_shipping_methods() as $method) {
            if (strpos($method->get_method_id(), 'pudo') !== false) {
                $shipping_method = $method;
                break;
            }
        }

        if (!$shipping_method) {
            echo '<p>' . esc_html__('This order does not use PUDO shipping.', 'pudo-api-connector') . '</p>';
            return;
        }

        $pudo_point_id = get_post_meta($order->get_id(), '_pudo_point_id', true);
        if (!$pudo_point_id) {
            echo '<p>' . esc_html__('No PUDO point selected for this order.', 'pudo-api-connector') . '</p>';
            return;
        }

        $tracking_number = get_post_meta($order->get_id(), '_pudo_tracking_number', true);
        $label_generated = get_post_meta($order->get_id(), '_pudo_label_generated', true);

        ?>
        <div class="pudo-label-container">
            <?php if ($label_generated) : ?>
                <p>
                    <strong><?php esc_html_e('Tracking Number:', 'pudo-api-connector'); ?></strong>
                    <?php echo esc_html($tracking_number); ?>
                </p>
                <button type="button" 
                        class="button button-primary" 
                        onclick="window.print();">
                    <?php esc_html_e('Print Label', 'pudo-api-connector'); ?>
                </button>
            <?php else : ?>
                <button type="button" 
                        class="button button-primary" 
                        id="generate-pudo-label" 
                        data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('generate_pudo_label')); ?>">
                    <?php esc_html_e('Generate Label', 'pudo-api-connector'); ?>
                </button>
            <?php endif; ?>
        </div>

        <div id="pudo-label-message"></div>

        <script>
        jQuery(document).ready(function($) {
            $('#generate-pudo-label').on('click', function() {
                var button = $(this);
                var message = $('#pudo-label-message');
                
                button.prop('disabled', true);
                message.html('<?php esc_html_e('Generating label...', 'pudo-api-connector'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'generate_pudo_label',
                        order_id: button.data('order-id'),
                        nonce: button.data('nonce')
                    },
                    success: function(response) {
                        if (response.success) {
                            message.html('<div class="notice notice-success"><p>' + 
                                       response.data.message + '</p></div>');
                            location.reload();
                        } else {
                            message.html('<div class="notice notice-error"><p>' + 
                                       response.data.message + '</p></div>');
                            button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        message.html('<div class="notice notice-error"><p><?php 
                            esc_html_e('An error occurred while generating the label.', 'pudo-api-connector'); 
                        ?></p></div>');
                        button.prop('disabled', false);
                    }
                });
            });
        });
        </script>

        <style>
        .pudo-shipping-label {
            padding: 20px;
            border: 1px solid #ddd;
            margin: 10px 0;
            font-family: monospace;
            white-space: pre-wrap;
        }
        @media print {
            #adminmenumain, #wpadminbar, #screen-meta, #screen-meta-links, 
            .button, #wpfooter {
                display: none !important;
            }
            .pudo-shipping-label {
                border: none;
            }
        }
        </style>
        <?php

        if ($label_generated) {
            $this->display_shipping_label($order);
        }
    }

    public function generate_label() {
        check_ajax_referer('generate_pudo_label', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(['message' => __('Permission denied.', 'pudo-api-connector')]);
            return;
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => __('Invalid order.', 'pudo-api-connector')]);
            return;
        }

        // Generate tracking number if not exists
        $tracking_number = get_post_meta($order_id, '_pudo_tracking_number', true);
        if (!$tracking_number) {
            $tracking_number = $this->generate_tracking_number($order);
            update_post_meta($order_id, '_pudo_tracking_number', $tracking_number);
        }

        // Register shipment with PUDO API
        $api = \PUDO\Core\Plugin::instance()->get_api();
        $result = $api->place_shipment([
            'trackingNumber' => $tracking_number,
            'customerEmail' => $order->get_billing_email(),
            'customerMobile' => $order->get_billing_phone(),
            'notificationPreference' => get_option('pudo_notification_preference', '3'),
            'customerName' => $order->get_formatted_billing_full_name(),
            'customerCompany' => $order->get_billing_company(),
            'dealerId' => get_post_meta($order_id, '_pudo_point_id', true)
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        update_post_meta($order_id, '_pudo_label_generated', 'yes');
        
        wp_send_json_success([
            'message' => __('Shipping label generated successfully.', 'pudo-api-connector')
        ]);
    }

    private function generate_tracking_number($order) {
        // Generate a unique tracking number
        $prefix = 'PUDO';
        $timestamp = time();
        $random = rand(1000, 9999);
        $order_number = $order->get_order_number();
        
        return sprintf('%s-%s-%s-%s', 
            $prefix, 
            $timestamp, 
            $random, 
            $order_number
        );
    }

    public function display_shipping_label($order) {
        $pudo_point_id = get_post_meta($order->get_id(), '_pudo_point_id', true);
        $pudo_point_name = get_post_meta($order->get_id(), '_pudo_point_name', true);
        $tracking_number = get_post_meta($order->get_id(), '_pudo_tracking_number', true);
        $formatted_address = get_post_meta($order->get_id(), '_pudo_formatted_address', true);

        if (!$formatted_address || !$tracking_number) {
            return;
        }

        ?>
        <div class="pudo-shipping-label">
            <strong><?php esc_html_e('Tracking Number:', 'pudo-api-connector'); ?></strong>
            <?php echo esc_html($tracking_number); ?>

            <hr>

            <?php echo esc_html($formatted_address); ?>

            <hr>

            <strong><?php esc_html_e('Order:', 'pudo-api-connector'); ?></strong> #<?php echo esc_html($order->get_order_number()); ?>
            <strong><?php esc_html_e('Date:', 'pudo-api-connector'); ?></strong> <?php echo esc_html($order->get_date_created()->date('Y-m-d H:i:s')); ?>
        </div>
        <?php
    }

    public function maybe_generate_label($order_id, $old_status, $new_status, $order) {
        // Automatically generate label when order status changes to processing or completed
        if (!in_array($new_status, ['processing', 'completed'])) {
            return;
        }

        // Check if this is a PUDO shipping order
        $has_pudo = false;
        foreach ($order->get_shipping_methods() as $method) {
            if (strpos($method->get_method_id(), 'pudo') !== false) {
                $has_pudo = true;
                break;
            }
        }

        if (!$has_pudo) {
            return;
        }

        // Check if label is already generated
        if (get_post_meta($order_id, '_pudo_label_generated', true)) {
            return;
        }

        // Generate tracking number if not exists
        $tracking_number = get_post_meta($order_id, '_pudo_tracking_number', true);
        if (!$tracking_number) {
            $tracking_number = $this->generate_tracking_number($order);
            update_post_meta($order_id, '_pudo_tracking_number', $tracking_number);
        }

        // Register shipment with PUDO API
        $api = \PUDO\Core\Plugin::instance()->get_api();
        $result = $api->place_shipment([
            'trackingNumber' => $tracking_number,
            'customerEmail' => $order->get_billing_email(),
            'customerMobile' => $order->get_billing_phone(),
            'notificationPreference' => get_option('pudo_notification_preference', '3'),
            'customerName' => $order->get_formatted_billing_full_name(),
            'customerCompany' => $order->get_billing_company(),
            'dealerId' => get_post_meta($order_id, '_pudo_point_id', true)
        ]);

        if (!is_wp_error($result)) {
            update_post_meta($order_id, '_pudo_label_generated', 'yes');
        }
    }
}
