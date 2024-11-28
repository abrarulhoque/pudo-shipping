<?php
namespace PUDO\Core;

class Installer {
    public static function activate() {
        self::create_tables();
        self::create_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('pudo_check_shipment_statuses');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'pudo_status_log';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            status varchar(10) NOT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY status (status),
            KEY timestamp (timestamp)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function create_default_options() {
        // API Settings
        add_option('pudo_test_mode', 'yes');
        add_option('pudo_partner_code', 'VapeSocietyStg');
        add_option('pudo_partner_password', 'V4peS0c1ety2024*');
        
        // Shipping Settings
        add_option('pudo_notification_preference', '3'); // Both SMS and Email
        add_option('pudo_default_weight', '5.0');
        add_option('pudo_weight_unit', 'KG');
        add_option('pudo_dimension_unit', 'CM');
        add_option('pudo_default_width', '10.0');
        add_option('pudo_default_height', '2.0');
        add_option('pudo_default_length', '10.0');
    }

    public static function check_requirements() {
        $errors = [];

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = sprintf(
                __('PUDO API Connector requires PHP version 7.4 or higher. Your current version is %s.', 'pudo-api-connector'),
                PHP_VERSION
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.8', '<')) {
            $errors[] = sprintf(
                __('PUDO API Connector requires WordPress version 5.8 or higher. Your current version is %s.', 'pudo-api-connector'),
                $wp_version
            );
        }

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            $errors[] = __('PUDO API Connector requires WooCommerce to be installed and activated.', 'pudo-api-connector');
        } else {
            // Check WooCommerce version
            if (version_compare(WC()->version, '5.0', '<')) {
                $errors[] = sprintf(
                    __('PUDO API Connector requires WooCommerce version 5.0 or higher. Your current version is %s.', 'pudo-api-connector'),
                    WC()->version
                );
            }
        }

        // Check if cURL is installed
        if (!function_exists('curl_version')) {
            $errors[] = __('PUDO API Connector requires the cURL PHP extension to be installed.', 'pudo-api-connector');
        }

        // Check if JSON extension is installed
        if (!function_exists('json_decode')) {
            $errors[] = __('PUDO API Connector requires the JSON PHP extension to be installed.', 'pudo-api-connector');
        }

        return $errors;
    }

    public static function create_required_directories() {
        // Create assets directories
        $directories = [
            PUDO_PLUGIN_DIR . 'assets',
            PUDO_PLUGIN_DIR . 'assets/css',
            PUDO_PLUGIN_DIR . 'assets/js',
        ];

        foreach ($directories as $directory) {
            if (!file_exists($directory)) {
                wp_mkdir_p($directory);
            }
        }
    }
}
