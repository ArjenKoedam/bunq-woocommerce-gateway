<?php
/**
 * WC_Bunq_Gateway Class
 *
 * @package Bunq_Payment_Gateway
 */

defined('ABSPATH') || exit;

/**
 * Bunq Payment Gateway
 *
 * Provides a Bunq Payment Gateway with iDeal, Credit Card, and Bancontact options.
 *
 * @class       WC_Bunq_Gateway
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 */
class WC_Bunq_Gateway extends WC_Payment_Gateway {

    /**
     * Bunq username
     *
     * @var string
     */
    private $bunq_username;

    /**
     * Bunq URL template
     *
     * @var string
     */
    private $bunq_url_template;

    /**
     * Available payment methods
     *
     * @var array
     */
    private $payment_methods = array(
        'ideal' => 'iDeal',
        'card' => 'Credit Card',
        'bancontact' => 'Bancontact'
    );

    /**
     * Constructor for the gateway
     */
    public function __construct() {
        $this->id                 = 'bunq';
        $this->icon               = '';
        $this->has_fields         = true;
        $this->method_title       = __('Bunq Payment Gateway', 'bunq-payment-gateway');
        $this->method_description = __('Accept payments via Bunq with iDeal, Credit Card, and Bancontact', 'bunq-payment-gateway');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');
        $this->bunq_username      = $this->get_option('bunq_username', 'mayaworldtrading');
        $this->bunq_url_template  = $this->get_option('bunq_url_template', 'https://bunq.me/%s/%s/%s/%s');
        $this->enabled            = $this->get_option('enabled');
        $this->testmode           = 'yes' === $this->get_option('testmode');
        $this->debug              = 'yes' === $this->get_option('debug');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_bunq_gateway', array($this, 'check_bunq_response'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

        // Add meta box for manual order confirmation
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        add_action('wp_ajax_bunq_confirm_payment', array($this, 'ajax_confirm_payment'));

        // Log function
        $this->log_debug = function($message) {
            if ($this->debug) {
                if (empty($this->logger)) {
                    $this->logger = wc_get_logger();
                }
                $this->logger->debug($message, array('source' => 'bunq-gateway'));
            }
        };
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'bunq-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable Bunq Payment Gateway', 'bunq-payment-gateway'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'bunq-payment-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'bunq-payment-gateway'),
                'default'     => __('Bunq Payment', 'bunq-payment-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'bunq-payment-gateway'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'bunq-payment-gateway'),
                'default'     => __('Pay securely using iDeal, Credit Card, or Bancontact via Bunq.', 'bunq-payment-gateway'),
                'desc_tip'    => true,
            ),
            'bunq_username' => array(
                'title'       => __('Bunq Username', 'bunq-payment-gateway'),
                'type'        => 'text',
                'description' => __('Your Bunq.me username (e.g., mayaworldtrading). This will be used to generate payment URLs.', 'bunq-payment-gateway'),
                'default'     => 'mayaworldtrading',
                'desc_tip'    => true,
                'placeholder' => __('Enter your Bunq username', 'bunq-payment-gateway'),
            ),
            'bunq_url_template' => array(
                'title'       => __('Bunq URL Template', 'bunq-payment-gateway'),
                'type'        => 'text',
                'description' => __('The URL template for Bunq payment links. Use %s placeholders for: username, amount, order number, and payment method (in that order).', 'bunq-payment-gateway'),
                'default'     => 'https://bunq.me/%s/%s/%s/%s',
                'desc_tip'    => true,
                'placeholder' => 'https://bunq.me/%s/%s/%s/%s',
                'css'         => 'min-width: 400px;',
            ),
            'testmode' => array(
                'title'       => __('Test mode', 'bunq-payment-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable Test Mode', 'bunq-payment-gateway'),
                'default'     => 'no',
                'description' => __('Place the payment gateway in test mode.', 'bunq-payment-gateway'),
            ),
            'debug' => array(
                'title'       => __('Debug log', 'bunq-payment-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'bunq-payment-gateway'),
                'default'     => 'no',
                'description' => sprintf(__('Log Bunq events inside %s', 'bunq-payment-gateway'), '<code>' . WC_Log_Handler_File::get_log_file_path('bunq-gateway') . '</code>'),
            ),
            'auto_complete' => array(
                'title'       => __('Auto Complete Orders', 'bunq-payment-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Automatically mark orders as completed after payment', 'bunq-payment-gateway'),
                'default'     => 'no',
                'description' => __('If enabled, orders will be automatically marked as completed. Otherwise, they will be marked as processing.', 'bunq-payment-gateway'),
            ),
        );
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields() {
        // Display description
        if ($this->description) {
            echo '<p>' . wp_kses_post($this->description) . '</p>';
        }

        // Display test mode notice
        if ($this->testmode) {
            echo '<p class="bunq-test-mode-notice">' . __('TEST MODE ENABLED. No real payments will be processed.', 'bunq-payment-gateway') . '</p>';
        }

        // Display payment method options
        echo '<div class="bunq-payment-methods">';
        echo '<p class="form-row form-row-wide">';
        echo '<label>' . __('Select Payment Method', 'bunq-payment-gateway') . ' <span class="required">*</span></label>';
        
        foreach ($this->payment_methods as $method_key => $method_name) {
            $logo_file = '';
            switch ($method_key) {
                case 'ideal':
                    $logo_file = 'IDEAL_Logo_Bunq.png';
                    break;
                case 'card':
                    $logo_file = 'Creditcard_Logo_Bunq.png';
                    break;
                case 'bancontact':
                    $logo_file = 'Bancontant_Logo_Bunq.png';
                    break;
            }
            
            $logo_url = BUNQ_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/images/' . $logo_file;
            
            echo '<label class="bunq-payment-method-option">';
            echo '<input type="radio" name="bunq_payment_method" value="' . esc_attr($method_key) . '" class="bunq-payment-method-radio" required />';
            echo '<span class="bunq-method-label">';
            echo '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($method_name) . '" class="bunq-payment-logo" />';
            echo '<span class="bunq-method-name">' . esc_html($method_name) . '</span>';
            echo '</span>';
            echo '</label>';
        }
        
        echo '</p>';
        echo '</div>';
    }

    /**
     * Validate payment fields on the frontend
     */
    public function validate_fields() {
        if (empty($_POST['bunq_payment_method']) || !isset($this->payment_methods[$_POST['bunq_payment_method']])) {
            wc_add_notice(__('Please select a payment method.', 'bunq-payment-gateway'), 'error');
            return false;
        }
        return true;
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Store the selected payment method
        $payment_method = sanitize_text_field($_POST['bunq_payment_method']);
        $order->update_meta_data('_bunq_payment_method', $payment_method);
        $order->save();

        // Mark as pending payment
        $order->update_status('pending', __('Awaiting Bunq payment', 'bunq-payment-gateway'));

        // Log the transaction
        call_user_func($this->log_debug, 'Processing payment for order #' . $order_id . ' with method: ' . $payment_method);

        // Return redirect to receipt page
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    /**
     * Receipt page
     *
     * @param int $order_id
     */
    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        // Get the payment method
        $payment_method = $order->get_meta('_bunq_payment_method');
        
        // Generate Bunq payment URL
        $payment_url = $this->generate_bunq_payment_url($order, $payment_method);

        echo '<p>' . __('Thank you for your order. You will be redirected to Bunq to complete your payment.', 'bunq-payment-gateway') . '</p>';
        echo '<p><a class="button alt" href="' . esc_url($payment_url) . '">' . __('Pay Now', 'bunq-payment-gateway') . '</a></p>';
        
        // Auto redirect
        echo '<script type="text/javascript">
            setTimeout(function() {
                window.location.href = "' . esc_js($payment_url) . '";
            }, 2000);
        </script>';
    }

    /**
     * Generate Bunq payment URL
     *
     * @param WC_Order $order
     * @param string $payment_method
     * @return string
     */
    private function generate_bunq_payment_url($order, $payment_method) {
        $amount = number_format($order->get_total(), 2, '.', '');
        $order_number = $order->get_order_number();
        
        // Store return URL in order meta for later use
        $return_url = $this->get_return_url($order);
        $order->update_meta_data('_bunq_return_url', $return_url);
        $order->save();

        // Generate Bunq.me URL using the configured template
        // Format: {template} with placeholders for username, amount, order number, and payment method
        $bunq_url = sprintf(
            $this->bunq_url_template,
            $this->bunq_username,
            $amount,
            $order_number,
            $payment_method
        );

        call_user_func($this->log_debug, 'Generated Bunq payment URL: ' . $bunq_url . ' for order #' . $order_number);

        return $bunq_url;
    }

    /**
     * Check for Bunq response
     */
    public function check_bunq_response() {
        // Handle the response from Bunq
        // This is a webhook/callback handler if Bunq supports it
        
        if (isset($_GET['order_id'])) {
            $order_id = absint($_GET['order_id']);
            $order = wc_get_order($order_id);

            if ($order) {
                call_user_func($this->log_debug, 'Bunq response received for order #' . $order_id);
                
                // Redirect to the return URL
                $return_url = $order->get_meta('_bunq_return_url');
                if ($return_url) {
                    wp_redirect($return_url);
                    exit;
                }
            }
        }
    }

    /**
     * Add meta box for manual payment confirmation
     */
    public function add_order_meta_box() {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
            && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'bunq_payment_confirmation',
            __('Bunq Payment Confirmation', 'bunq-payment-gateway'),
            array($this, 'order_meta_box_content'),
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Meta box content for manual payment confirmation
     *
     * @param WP_Post|WC_Order $post_or_order
     */
    public function order_meta_box_content($post_or_order) {
        $order = ($post_or_order instanceof WC_Order) ? $post_or_order : wc_get_order($post_or_order->ID);

        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        $payment_method = $order->get_meta('_bunq_payment_method');
        $payment_method_name = isset($this->payment_methods[$payment_method]) ? $this->payment_methods[$payment_method] : 'Unknown';

        echo '<div class="bunq-meta-box">';
        echo '<p><strong>' . __('Payment Method:', 'bunq-payment-gateway') . '</strong> ' . esc_html($payment_method_name) . '</p>';
        echo '<p><strong>' . __('Order Status:', 'bunq-payment-gateway') . '</strong> ' . esc_html(wc_get_order_status_name($order->get_status())) . '</p>';

        if ($order->has_status(array('pending', 'on-hold'))) {
            echo '<p class="bunq-confirm-description">' . __('If you have confirmed the payment was received, you can manually complete this order.', 'bunq-payment-gateway') . '</p>';
            echo '<button type="button" class="button button-primary bunq-confirm-payment" data-order-id="' . esc_attr($order->get_id()) . '">' . __('Confirm Payment', 'bunq-payment-gateway') . '</button>';
            echo '<span class="bunq-confirm-loader" style="display:none;">' . __('Processing...', 'bunq-payment-gateway') . '</span>';
        } else {
            echo '<p class="bunq-order-complete">' . __('This order has been processed.', 'bunq-payment-gateway') . '</p>';
        }

        echo '</div>';

        // Add inline JavaScript for AJAX
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.bunq-confirm-payment').on('click', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var orderId = button.data('order-id');
                var loader = $('.bunq-confirm-loader');
                
                if (!confirm('<?php echo esc_js(__('Are you sure you want to confirm this payment?', 'bunq-payment-gateway')); ?>')) {
                    return;
                }
                
                button.prop('disabled', true);
                loader.show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bunq_confirm_payment',
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce('bunq_confirm_payment'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php echo esc_js(__('Error confirming payment', 'bunq-payment-gateway')); ?>');
                            button.prop('disabled', false);
                            loader.hide();
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Error confirming payment', 'bunq-payment-gateway')); ?>');
                        button.prop('disabled', false);
                        loader.hide();
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for manual payment confirmation
     */
    public function ajax_confirm_payment() {
        check_ajax_referer('bunq_confirm_payment', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Permission denied', 'bunq-payment-gateway')));
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found', 'bunq-payment-gateway')));
        }

        // Update order status
        $auto_complete = 'yes' === $this->get_option('auto_complete');
        
        if ($auto_complete) {
            $order->update_status('completed', __('Payment manually confirmed via Bunq gateway', 'bunq-payment-gateway'));
        } else {
            $order->update_status('processing', __('Payment manually confirmed via Bunq gateway', 'bunq-payment-gateway'));
        }

        $order->add_order_note(__('Payment confirmed manually by store administrator.', 'bunq-payment-gateway'));

        call_user_func($this->log_debug, 'Payment manually confirmed for order #' . $order_id);

        wp_send_json_success(array('message' => __('Payment confirmed successfully', 'bunq-payment-gateway')));
    }

    /**
     * Validate Bunq username field
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    public function validate_bunq_username_field($key, $value) {
        $value = sanitize_text_field($value);
        
        if (empty($value)) {
            WC_Admin_Settings::add_error(__('Bunq Username is required. Please enter your Bunq.me username.', 'bunq-payment-gateway'));
            return $this->get_option($key, 'mayaworldtrading');
        }
        
        return $value;
    }

    /**
     * Validate Bunq URL template field
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    public function validate_bunq_url_template_field($key, $value) {
        $value = sanitize_text_field($value);
        
        if (empty($value)) {
            WC_Admin_Settings::add_error(__('Bunq URL Template is required.', 'bunq-payment-gateway'));
            return $this->get_option($key, 'https://bunq.me/%s/%s/%s/%s');
        }
        
        // Count the number of %s placeholders
        $placeholder_count = substr_count($value, '%s');
        
        if ($placeholder_count !== 4) {
            WC_Admin_Settings::add_error(__('Bunq URL Template must contain exactly 4 placeholders (%s) for username, amount, order number, and payment method.', 'bunq-payment-gateway'));
            return $this->get_option($key, 'https://bunq.me/%s/%s/%s/%s');
        }
        
        // Basic URL validation
        if (!filter_var(str_replace('%s', 'test', $value), FILTER_VALIDATE_URL)) {
            WC_Admin_Settings::add_error(__('Bunq URL Template must be a valid URL format.', 'bunq-payment-gateway'));
            return $this->get_option($key, 'https://bunq.me/%s/%s/%s/%s');
        }
        
        return $value;
    }
}
