<?php
/**
 * Plugin Name: WPHEKA Request For Quote
 * Plugin URI: https://wpheka.com
 * Description: The <code><strong>WPHEKA Request For Quote</strong></code> plugin allows your customers to submit quotes for any product and negotiate with you for the best price.
 * Version: 1.1
 * Author: WPHEKA
 * Author URI: https://github.com/akshayadev
 * Text Domain: wpheka-request-for-quote
 * Domain Path: /languages/
 * Requires at least: 4.4
 * Tested up to: 5.4.1
 * WC requires at least: 3.0.0
 * WC tested up to: 4.1.0
 * License: GPLv3 or later
 *
 * @package   WPHEKA_Rfq
 * @author    WPHEKA
 * @link      https://wpheka.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define WPHEKA_RFQ_PLUGIN_FILE.
if ( ! defined( 'WPHEKA_RFQ_PLUGIN_FILE' ) ) {
	define( 'WPHEKA_RFQ_PLUGIN_FILE', __FILE__ );
}

// Include the main WPHEKA_Rfq class.
if ( ! class_exists( 'WPHEKA_Rfq' ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-wpheka-rfq.php';
}

/**
 * Main instance of WPHEKA_Rfq.
 *
 * Returns the main instance of WPHEKA_Rfq to prevent the need to use globals.
 *
 * @since  1.0
 * @return WPHEKA_Rfq
 */
function wpheka_request_for_quote() {
	return WPHEKA_Rfq::instance();
}

// Global for backwards compatibility.
$GLOBALS['wp_heka_rfq'] = wpheka_request_for_quote();
