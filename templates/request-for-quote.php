<?php
/**
 * Template to display request quote product list in the request quote page
 *
 * @package WPHEKA_Rfq
 * @subpackage WPHEKA_Rfq_Frontend
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

function_exists( 'wc_nocache_headers' ) && wc_nocache_headers();

do_action( 'wpheka_before_rfq_list' ); ?>

<form class="woocommerce-cart-form wpheka-quote-product-list-form" id="wpheka-quote-request-list-form" method="post">
	<?php do_action( 'wpheka_before_rfq_list_table' ); ?>

	<table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">
		<thead>
			<tr>
				<th class="product-remove">&nbsp;</th>
				<th class="product-thumbnail">&nbsp;</th>
				<th class="product-name"><?php esc_html_e( 'Product', 'wpheka-request-for-quote' ); ?></th>
				<?php if ( wpheka_request_for_quote()->get_settings( 'hide_price' ) == 'no' ) { ?>
				<th class="product-price"><?php esc_html_e( 'Price', 'wpheka-request-for-quote' ); ?></th>
				<?php } ?>
				<th class="product-quantity"><?php esc_html_e( 'Quantity', 'wpheka-request-for-quote' ); ?></th>
				<?php if ( wpheka_request_for_quote()->get_settings( 'hide_price' ) == 'no' ) { ?>
				<th class="product-subtotal"><?php esc_html_e( 'Subtotal', 'wpheka-request-for-quote' ); ?></th>
				<?php } ?>
			</tr>
		</thead>
		<tbody>
			<?php
			do_action( 'wpheka_before_rfq_list_contents' );

			foreach ( $rfq_data as $rfq_item_key => $rfq_item ) {
				$actual_product_id = empty( $rfq_item['variation_id'] ) ? $rfq_item['product_id'] : $rfq_item['variation_id'];

				$actual_product = wc_get_product( $actual_product_id );

				if ( ! $actual_product ) {
					if ( array_key_exists( $rfq_item_key, $rfq_data ) ) {
						unset( $rfq_data[ $rfq_item_key ] );

						if ( empty( $rfq_data ) ) {
							wpheka_request_for_quote()->session->set( 'rfq', array() );
						} else {
							wpheka_request_for_quote()->session->set( 'rfq', $rfq_data );
						}
					}
					continue;
				}

				$_product   = apply_filters( 'wpheka_rfq_item_product', $actual_product, $rfq_item, $rfq_item_key );
				$product_id = apply_filters( 'wpheka_rfq_item_product_id', $rfq_item['product_id'], $rfq_item, $rfq_item_key );

				if ( $_product && $_product->exists() && $rfq_item['quantity'] > 0 && apply_filters( 'wpheka_rfq_item_visible', true, $rfq_item, $rfq_item_key ) ) {
					$product_permalink = apply_filters( 'wpheka_rfq_item_permalink', $_product->is_visible() ? $_product->get_permalink( $rfq_item ) : '', $rfq_item, $rfq_item_key );
					?>
					<tr class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'wpheka_rfq_item_class', 'rfq_item', $rfq_item, $rfq_item_key ) ); ?>">

						<td class="product-remove">
							<?php
								echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									'wpheka_rfq_item_remove_link',
									sprintf(
										'<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s" data-rfq_item_key="%s">&times;</a>',
										esc_url( wpheka_request_for_quote()->get_rfq_remove_url( $rfq_item_key ) ),
										esc_html__( 'Remove this item', 'wpheka-request-for-quote' ),
										esc_attr( $product_id ),
										esc_attr( $_product->get_sku() ),
										esc_attr( $rfq_item_key )
									),
									$rfq_item_key
								);
							?>
							<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" class="ajax-loading" alt="loading" width="16" height="16" style="visibility:hidden" />
						</td>

						<td class="product-thumbnail">
						<?php
						$thumbnail = apply_filters( 'wpheka_rfq_item_thumbnail', $_product->get_image(), $rfq_item, $rfq_item_key );

						if ( ! $product_permalink ) {
							echo $thumbnail; // PHPCS: XSS ok.
						} else {
							printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail ); // PHPCS: XSS ok.
						}
						?>
						</td>

						<td class="product-name" data-title="<?php esc_attr_e( 'Product', 'wpheka-request-for-quote' ); ?>">
						<?php
						if ( ! $product_permalink ) {
							echo wp_kses_post( apply_filters( 'wpheka_rfq_item_name', $_product->get_name(), $rfq_item, $rfq_item_key ) . '&nbsp;' );
						} else {
							echo wp_kses_post( apply_filters( 'wpheka_rfq_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $rfq_item, $rfq_item_key ) );
						}

						do_action( 'wpheka_after_rfq_item_name', $rfq_item, $rfq_item_key );

						// Backorder notification.
						if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $rfq_item['quantity'] ) ) {
							echo wp_kses_post( apply_filters( 'wpheka_rfq_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'wpheka-request-for-quote' ) . '</p>', $product_id ) );
						}
						?>
						</td>
						<?php if ( wpheka_request_for_quote()->get_settings( 'hide_price' ) == 'no' ) { ?>
						<td class="product-price" data-title="<?php esc_attr_e( 'Price', 'wpheka-request-for-quote' ); ?>">
							<?php
								echo apply_filters( 'wpheka_rfq_item_price', WC()->cart->get_product_price( $_product ), $rfq_item, $rfq_item_key ); // PHPCS: XSS ok.
							?>
						</td>
						<?php } ?>
						<td class="product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'wpheka-request-for-quote' ); ?>">
						<?php
						if ( $_product->is_sold_individually() ) {
							$product_quantity = sprintf( '1 <input type="hidden" name="rfq[%s][quantity]" value="1" />', $rfq_item_key );
						} else {
							$product_quantity = woocommerce_quantity_input(
								array(
									'input_name'   => "rfq[{$rfq_item_key}][quantity]",
									'input_value'  => $rfq_item['quantity'],
									'max_value'    => $_product->get_max_purchase_quantity(),
									'min_value'    => '0',
									'product_name' => $_product->get_name(),
								),
								$_product,
								false
							);
						}

						echo apply_filters( 'wpheka_rfq_item_quantity', $product_quantity, $rfq_item_key, $rfq_item ); // PHPCS: XSS ok.
						?>
						</td>
						<?php if ( wpheka_request_for_quote()->get_settings( 'hide_price' ) == 'no' ) { ?>
						<td class="product-subtotal" data-title="<?php esc_attr_e( 'Subtotal', 'wpheka-request-for-quote' ); ?>">
							<?php
								echo apply_filters( 'wpheka_rfq_item_subtotal', WC()->cart->get_product_subtotal( $_product, $rfq_item['quantity'] ), $rfq_item, $rfq_item_key ); // PHPCS: XSS ok.
							?>
						</td>
						<?php } ?>
					</tr>
					<?php
				}
			}
			?>

			<?php do_action( 'wpheka_rfq_list_contents' ); ?>

			<tr>
				<td colspan="6" class="actions">

					<button type="submit" class="button" name="update_rfq" value="<?php esc_attr_e( 'Update list', 'wpheka-request-for-quote' ); ?>"><?php esc_html_e( 'Update list', 'wpheka-request-for-quote' ); ?></button>

					<?php do_action( 'wpheka_rfq_actions' ); ?>

					<?php wp_nonce_field( 'wpheka-rfq', 'wpheka-rfq-nonce' ); ?>
				</td>
			</tr>

			<?php do_action( 'wpheka_after_rfq_list_contents' ); ?>
		</tbody>
	</table>
	<?php do_action( 'wpheka_after_rfq_list_table' ); ?>
</form>

<?php do_action( 'wpheka_after_rfq_list' ); ?>
