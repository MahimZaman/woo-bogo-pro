<?php

/**
 * Plugin Name: Woo B.O.G.O Pro
 * Description: Add BOGO rules per product (product data tab). Automatically adds free products when thresholds are met.
 * Version: 1.0.1
 * Author: Mahim Zaman
 * Text Domain: wbogo
 */

defined('ABSPATH') || exit;

if (! defined('WBOGO_PATH')) {
    define('WBOGO_PATH', trailingslashit(plugin_dir_path(__FILE__)));
}
if (! defined('WBOGO_URL')) {
    define('WBOGO_URL', trailingslashit(plugin_dir_url(__FILE__)));
}

/* Includes */
require_once WBOGO_PATH . 'includes/class-wbogo-admin.php';
require_once WBOGO_PATH . 'includes/class-wbogo-frontend.php';
require_once WBOGO_PATH . 'includes/class-wbogo-cart.php';

/* Init */
add_action('plugins_loaded', function () {
    load_plugin_textdomain('wbogo', false, dirname(plugin_basename(__FILE__)) . '/languages');
    WBOGO_Admin::instance();
    WBOGO_Frontend::instance();
    WBOGO_Cart::instance();
});
