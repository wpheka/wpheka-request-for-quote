<?php
/**
 * Add to quote template frontend
 *
 * @package WPHEKA_Rfq
 * @subpackage WPHEKA_Rfq_Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}
global $product; ?>

<div class="wpheka-add-to-quote-button-wrapper add-to-quote-<?php echo esc_attr( $product_id ); ?>">
<?php
if ( wpheka_request_for_quote()->check_product_exists_in_quote_list( $product_id ) ) {
	$wpheka_rfq_product_already_in_list_message = apply_filters( 'wpheka_rfq_product_already_in_list_message', __( 'Product already in the quote list.', 'wpheka-request-for-quote' ) );
	$wpheka_rfq_product_added_view_browse_list  = apply_filters( 'wpheka_rfq_product_added_view_browse_list', __( 'Browse the list', 'wpheka-request-for-quote' ) );
	echo '<div class="wpheka_rfq_add_item_response' . esc_html( $product_id ) . ' wpheka_rfq_add_item_response_message">' . esc_html( $wpheka_rfq_product_already_in_list_message ) . '</div>';
	echo '<div class="wpheka_rfq_add_item_browse-list' . esc_html( $product_id ) . ' wpheka_rfq_add_item_browse_message"><a href="' . esc_url( wpheka_request_for_quote()->get_rfq_page_url() ) . '"> ' . esc_html( $wpheka_rfq_product_added_view_browse_list ) . ' </a></div>';
} elseif ( wpheka_request_for_quote()->get_settings( 'button_type' ) == 'link' ) {
	?>
<a href="javascript:;" class="<?php echo esc_attr( $class ); ?> wpheka-add-to-quote-button" data-product_id="<?php echo esc_attr( $product_id ); ?>" data-wp_nonce="<?php echo esc_attr( $wpnonce ); ?>">
	<?php echo wp_kses_post( $label ); ?>
</a>
<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-loading" alt="loading" width="16" height="16" style="display: none;" />
<?php } else { ?>
<button type="button" data-product_id="<?php echo esc_attr( $product_id ); ?>" data-wp_nonce="<?php echo esc_attr( $wpnonce ); ?>" class="components-button is-button is-default is-primary wpheka-add-to-quote-button <?php echo esc_attr( $class ); ?>"><?php echo wp_kses_post( $label ); ?></button>
<?php } ?>
</div>
<div class="clear"></div>
