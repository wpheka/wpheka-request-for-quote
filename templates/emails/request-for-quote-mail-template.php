<?php
/**
 * Request Quote email template
 *
 * @package WPHEKA_Rfq
 * @subpackage WPHEKA_Rfq_Mail
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

$text_align  = is_rtl() ? 'right' : 'left';
$margin_side = is_rtl() ? 'left' : 'right';

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

 <p><?php printf( __( 'You have received a quote request from %s. The request is as follows:', 'wpheka-request-for-quote' ), esc_html( $customer_data['name'] ) ); ?></p>

<h2><?php esc_html_e( 'Request Quote', 'wpheka-request-for-quote' ); ?></h2>

<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Product', 'wpheka-request-for-quote' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Quantity', 'wpheka-request-for-quote' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Price', 'wpheka-request-for-quote' ); ?></th>
			</tr>
		</thead>
<tbody>
<?php

foreach ( $rfq_data as $rfq_item_key => $rfq_item ) {
	$actual_product_id = empty( $rfq_item['variation_id'] ) ? $rfq_item['product_id'] : $rfq_item['variation_id'];

	$actual_product = wc_get_product( $actual_product_id );

	if ( ! $actual_product ) {
		continue;
	}

	$product    = apply_filters( 'wpheka_rfq_item_product', $actual_product, $rfq_item, $rfq_item_key );
	$product_id = apply_filters( 'wpheka_rfq_item_product_id', $rfq_item['product_id'], $rfq_item, $rfq_item_key );
	?>
<tr class="order_item">
<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
	<?php
	// Product name.
	echo wp_kses_post( $product->get_name() );
	?>
</td>
<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
	<?php
	$qty         = $rfq_item['quantity'];
	$qty_display = esc_html( $qty );
	echo wp_kses_post( $qty_display );
	?>
</td>
	<?php if ( wpheka_request_for_quote()->get_settings( 'hide_price' ) == 'no' ) { ?>
<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
		<?php
		echo wp_kses(
			WC()->cart->get_product_subtotal( $product, $rfq_item['quantity'] ),
			array(
				'span' => array(
					'class'   => true,
				),
			)
		);
		?>
</td>
	<?php } ?>
</tr>
<?php } ?>
</tbody>
</table>
</div>

<?php if ( ! empty( $customer_data ) ) : ?>
	<div style="font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; margin-bottom: 40px;">
		<h2><?php esc_html_e( 'Customer Details', 'wpheka-request-for-quote' ); ?></h2>
		<ul>
			<?php foreach ( $customer_data as $cus_key => $cust_val ) : ?>
				<li><strong><?php echo wp_kses_post( $cus_key ); ?>:</strong> <span class="text"><?php echo wp_kses_post( wp_unslash( $cust_val ) ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
endif;
do_action( 'woocommerce_email_footer', $email );
