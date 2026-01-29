<?php
/**
 * Plugin Name: Bunq Payment Gateway for WooCommerce
 * Plugin URI: https://github.com/ArjenKoedam/bunq-woocommerce-gateway
 * Description: Accept payments via Bunq with iDeal, Credit Card, and Bancontact payment methods
 * Version: 7.7.7
 * Author: CYTUNO
 * Author URI: https://cytuno.com
 * Text Domain: bunq-payment-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 *
 * @package Bunq_Payment_Gateway
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('BUNQ_PAYMENT_GATEWAY_VERSION', '1.1.0');
define('BUNQ_PAYMENT_GATEWAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BUNQ_PAYMENT_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active
 */
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * Initialize the gateway
 */
add_action('plugins_loaded', 'bunq_payment_gateway_init', 11);

function bunq_payment_gateway_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Include the gateway class
    require_once BUNQ_PAYMENT_GATEWAY_PLUGIN_DIR . 'includes/class-wc-bunq-gateway.php';

    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'bunq_add_gateway_class');
}

/**
 * Add the gateway to WooCommerce
 *
 * @param array $gateways
 * @return array
 */
function bunq_add_gateway_class($gateways) {
    $gateways[] = 'WC_Bunq_Gateway';
    return $gateways;
}

/**
 * Add custom action links on plugin page
 *
 * @param array $links
 * @return array
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'bunq_plugin_action_links');

function bunq_plugin_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=bunq') . '">' . __('Settings', 'bunq-payment-gateway') . '</a>',
    );
    return array_merge($plugin_links, $links);
}

/**
 * Enqueue admin styles and scripts
 */
add_action('admin_enqueue_scripts', 'bunq_admin_enqueue_scripts');

function bunq_admin_enqueue_scripts($hook) {
    if ('woocommerce_page_wc-settings' !== $hook) {
        return;
    }

    wp_enqueue_style(
        'bunq-admin-styles',
        BUNQ_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        BUNQ_PAYMENT_GATEWAY_VERSION
    );
}

/**
 * Enqueue frontend styles and scripts
 */
add_action('wp_enqueue_scripts', 'bunq_enqueue_scripts');

function bunq_enqueue_scripts() {
    if (is_checkout()) {
        wp_enqueue_style(
            'bunq-checkout-styles',
            BUNQ_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/css/checkout.css',
            array(),
            BUNQ_PAYMENT_GATEWAY_VERSION
        );

        wp_enqueue_script(
            'bunq-checkout-scripts',
            BUNQ_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/js/checkout.js',
            array('jquery'),
            BUNQ_PAYMENT_GATEWAY_VERSION,
            true
        );
    }
}

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'bunq_payment_gateway_activate');

function bunq_payment_gateway_activate() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Bunq Payment Gateway requires WooCommerce to be installed and active.', 'bunq-payment-gateway'),
            'Plugin dependency check',
            array('back_link' => true)
        );
    }
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'bunq_payment_gateway_deactivate');

function bunq_payment_gateway_deactivate() {
    // Cleanup if needed
}
