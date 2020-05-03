<?php
/**
 * Empty quote list page
 */

defined( 'ABSPATH' ) || exit;

do_action( 'wpheka_cart_is_empty' );

?>
<p class="empty-quote-list">
	<?php esc_html_e( 'No products in list', wpheka_request_for_quote()->text_domain ); ?>
</p>
