<?php
/**
 * Plugin Name: Request For Quote
 * Plugin URI: https://www.wpheka.com/product/request-for-quote/
 * Description: The <code><strong>Request For Quote</strong></code> plugin allows your customers to submit quotes for any product and negotiate with you for the best price.
 * Version: 1.6.1
 * Author: WPHEKA
 * Author URI: https://www.wpheka.com
 * Text Domain: wpheka-request-for-quote
 * Domain Path: /languages/
 * Requires at least: 4.8
 * Tested up to: 6.8.3
 * Requires Plugins: woocommerce
 * WC requires at least: 3.0
 * WC tested up to: 10.3.4
 * Woo: 10.3.4
 * License: GPLv3 or later
 *
 * @package   WPHEKA_Rfq
 * @author    WPHEKA
 * @link      https://wpheka.com
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define WPHEKA_RFQ_PLUGIN_FILE.
if (! defined('WPHEKA_RFQ_PLUGIN_FILE')) {
    define('WPHEKA_RFQ_PLUGIN_FILE', __FILE__);
}

// Include the main WPHEKA_Rfq class.
if (! class_exists('WPHEKA_Rfq')) {
    include_once dirname(__FILE__) . '/includes/class-wpheka-rfq.php';
}

/**
 * Main instance of WPHEKA_Rfq.
 *
 * Returns the main instance of WPHEKA_Rfq to prevent the need to use globals.
 *
 * @since  1.0
 * @return WPHEKA_Rfq
 */
function wpheka_request_for_quote()
{
    return WPHEKA_Rfq::instance();
}

// Global for backwards compatibility.
$GLOBALS['wp_heka_rfq'] = wpheka_request_for_quote();
