<?php
/**
 * Empty quote list template
 *
 * @package WPHEKA_Rfq
 * @subpackage WPHEKA_Rfq_Frontend
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

do_action( 'wpheka_cart_is_empty' );

?>
<p class="empty-quote-list">
	<?php esc_html_e( 'No products in list', 'wpheka-request-for-quote' ); ?>
</p>
