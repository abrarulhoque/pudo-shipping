<?php
namespace PUDO\Admin;

class Settings {
    private $settings_page = 'pudo-settings';
    private $option_group = 'pudo_options';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_settings']);
        add_action('admin_notices', [$this, 'show_api_status']);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('PUDO Settings', 'pudo-api-connector'),
            __('PUDO', 'pudo-api-connector'),
            'manage_options',
            $this->settings_page,
            [$this, 'render_settings_page'],
            'dashicons-location',
            56
        );
    }

    public function init_settings() {
        register_setting($this->option_group, 'pudo_partner_code');
        register_setting($this->option_group, 'pudo_partner_password');
        register_setting($this->option_group, 'pudo_test_mode');
        register_setting($this->option_group, 'pudo_notification_preference');
        register_setting($this->option_group, 'pudo_default_weight');
        register_setting($this->option_group, 'pudo_weight_unit');
        register_setting($this->option_group, 'pudo_dimension_unit');
        register_setting($this->option_group, 'pudo_default_width');
        register_setting($this->option_group, 'pudo_default_height');
        register_setting($this->option_group, 'pudo_default_length');
        register_setting($this->option_group, 'pudo_disable_ssl_verify');

        add_settings_section(
            'pudo_api_settings',
            __('API Settings', 'pudo-api-connector'),
            [$this, 'render_api_section'],
            $this->settings_page
        );

        add_settings_section(
            'pudo_shipping_settings',
            __('Shipping Settings', 'pudo-api-connector'),
            [$this, 'render_shipping_section'],
            $this->settings_page
        );

        // API Settings Fields
        add_settings_field(
            'pudo_partner_code',
            __('Partner Code', 'pudo-api-connector'),
            [$this, 'render_text_field'],
            $this->settings_page,
            'pudo_api_settings',
            ['label_for' => 'pudo_partner_code']
        );

        add_settings_field(
            'pudo_partner_password',
            __('Partner Password', 'pudo-api-connector'),
            [$this, 'render_password_field'],
            $this->settings_page,
            'pudo_api_settings',
            ['label_for' => 'pudo_partner_password']
        );

        add_settings_field(
            'pudo_test_mode',
            __('Test Mode', 'pudo-api-connector'),
            [$this, 'render_checkbox_field'],
            $this->settings_page,
            'pudo_api_settings',
            [
                'label_for' => 'pudo_test_mode',
                'description' => __('Enable test mode to use the PUDO test environment', 'pudo-api-connector')
            ]
        );

        // Shipping Settings Fields
        add_settings_field(
            'pudo_notification_preference',
            __('Notification Preference', 'pudo-api-connector'),
            [$this, 'render_select_field'],
            $this->settings_page,
            'pudo_shipping_settings',
            [
                'label_for' => 'pudo_notification_preference',
                'options' => [
                    '1' => __('SMS Only', 'pudo-api-connector'),
                    '2' => __('Email Only', 'pudo-api-connector'),
                    '3' => __('Both SMS and Email', 'pudo-api-connector')
                ]
            ]
        );

        // Default Dimensions Fields
        $dimension_fields = [
            'weight' => ['label' => __('Default Weight', 'pudo-api-connector'), 'default' => '5.0'],
            'width' => ['label' => __('Default Width', 'pudo-api-connector'), 'default' => '10.0'],
            'height' => ['label' => __('Default Height', 'pudo-api-connector'), 'default' => '2.0'],
            'length' => ['label' => __('Default Length', 'pudo-api-connector'), 'default' => '10.0']
        ];

        foreach ($dimension_fields as $key => $field) {
            add_settings_field(
                "pudo_default_{$key}",
                $field['label'],
                [$this, 'render_number_field'],
                $this->settings_page,
                'pudo_shipping_settings',
                [
                    'label_for' => "pudo_default_{$key}",
                    'default' => $field['default'],
                    'step' => '0.1',
                    'min' => '0'
                ]
            );
        }

        // Units Fields
        add_settings_field(
            'pudo_weight_unit',
            __('Weight Unit', 'pudo-api-connector'),
            [$this, 'render_select_field'],
            $this->settings_page,
            'pudo_shipping_settings',
            [
                'label_for' => 'pudo_weight_unit',
                'options' => [
                    'KG' => __('Kilograms (KG)', 'pudo-api-connector'),
                    'LBS' => __('Pounds (LBS)', 'pudo-api-connector')
                ]
            ]
        );

        add_settings_field(
            'pudo_dimension_unit',
            __('Dimension Unit', 'pudo-api-connector'),
            [$this, 'render_select_field'],
            $this->settings_page,
            'pudo_shipping_settings',
            [
                'label_for' => 'pudo_dimension_unit',
                'options' => [
                    'CM' => __('Centimeters (CM)', 'pudo-api-connector'),
                    'IN' => __('Inches (IN)', 'pudo-api-connector')
                ]
            ]
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->settings_page);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_api_section() {
        echo '<p>' . esc_html__('Configure your PUDO API credentials and settings.', 'pudo-api-connector') . '</p>';
    }

    public function render_shipping_section() {
        echo '<p>' . esc_html__('Configure default shipping parameters and preferences.', 'pudo-api-connector') . '</p>';
    }

    public function render_text_field($args) {
        $id = $args['label_for'];
        $value = get_option($id, '');
        ?>
        <input type="text" 
               id="<?php echo esc_attr($id); ?>" 
               name="<?php echo esc_attr($id); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <?php
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_password_field($args) {
        $id = $args['label_for'];
        $value = get_option($id, '');
        ?>
        <input type="password" 
               id="<?php echo esc_attr($id); ?>" 
               name="<?php echo esc_attr($id); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <?php
    }

    public function render_checkbox_field($args) {
        $id = $args['label_for'];
        $value = get_option($id, 'no');
        ?>
        <input type="checkbox" 
               id="<?php echo esc_attr($id); ?>" 
               name="<?php echo esc_attr($id); ?>" 
               value="yes" 
               <?php checked('yes', $value); ?>>
        <?php
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_select_field($args) {
        $id = $args['label_for'];
        $value = get_option($id, '');
        ?>
        <select id="<?php echo esc_attr($id); ?>" 
                name="<?php echo esc_attr($id); ?>">
            <?php foreach ($args['options'] as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" 
                        <?php selected($key, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_number_field($args) {
        $id = $args['label_for'];
        $value = get_option($id, $args['default'] ?? '0');
        ?>
        <input type="number" 
               id="<?php echo esc_attr($id); ?>" 
               name="<?php echo esc_attr($id); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               step="<?php echo esc_attr($args['step'] ?? '1'); ?>"
               min="<?php echo esc_attr($args['min'] ?? '0'); ?>"
               class="regular-text">
        <?php
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function show_api_status() {
        $screen = get_current_screen();
        if ($screen->id !== 'toplevel_page_' . $this->settings_page) {
            return;
        }

        $api = \PUDO\Core\Plugin::instance()->get_api();
        if (!$api->validate_credentials()) {
            echo '<div class="notice notice-error"><p>' . 
                 esc_html__('PUDO API credentials are invalid or not configured.', 'pudo-api-connector') . 
                 '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>' . 
                 esc_html__('PUDO API connection successful.', 'pudo-api-connector') . 
                 '</p></div>';
        }
    }
}
