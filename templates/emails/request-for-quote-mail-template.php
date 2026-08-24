<?php
/**
 * Request Quote email template
 *
 * @package WPHEKA_Rfq
 * @subpackage WPHEKA_Rfq_Mail
 * @since 1.0.0
 * @version 1.8.2
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

/*
 * WooCommerce's "Email improvements" feature (on by default since WooCommerce
 * 9.9) restyles every core email, and core templates opt in through these
 * classes: `font-family` and `text-align-*` instead of inline styles, plus
 * `email-order-details` on the table and `email-order-detail-heading` on the
 * heading. This template used to hardcode inline `font-family: 'Helvetica
 * Neue'...` and `border="1"` with inline text-align, which is the pre-9.9 look
 * -- so on a store with the modern design the quote email visibly did not match
 * any other WooCommerce email.
 */
$email_improvements_enabled = class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' )
	&& \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'email_improvements' );

$heading_class = $email_improvements_enabled ? 'email-order-detail-heading' : '';
$table_class   = $email_improvements_enabled ? 'email-order-details' : '';
$text_align    = is_rtl() ? 'right' : 'left';
$number_align  = $email_improvements_enabled ? ( is_rtl() ? 'left' : 'right' ) : $text_align;

/*
 * Rendered from wp-admin as well as from a real submission: WooCommerce's email
 * preview and its "Send a test email" button both call get_content() with no
 * quote and no customer behind it. Normalising here keeps every read below
 * unconditional.
 */
$rfq_data      = is_array( $rfq_data ) ? $rfq_data : array();
$customer_data = is_array( $customer_data ) ? $customer_data : array();
$customer_name = isset( $customer_data['name'] ) ? trim( (string) $customer_data['name'] ) : '';

/*
 * One decision for the header and the body. They used to be decided separately,
 * so hiding prices left a "Price" column header above rows that had no price
 * cell -- a table one column short of its own heading.
 */
$show_price = 'no' === wpheka_request_for_quote()->get_settings( 'hide_price' );

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
<?php
if ( '' !== $customer_name ) {
	printf(
		/* translators: %s: customer name. */
		esc_html__( 'You have received a quote request from %s. The request is as follows:', 'wpheka-request-for-quote' ),
		esc_html( $customer_name )
	);
} else {
	esc_html_e( 'You have received a quote request. The request is as follows:', 'wpheka-request-for-quote' );
}
?>
</p>

<?php
/**
 * Filter the heading above the requested products table.
 *
 * @since 1.8.2
 * @param string   $heading The heading text.
 * @param WC_Email $email   Email object.
 */
$quote_heading = apply_filters( 'wpheka_rfq_email_items_heading', __( 'Quote summary', 'wpheka-request-for-quote' ), $email );

if ( $quote_heading ) :
	?>
	<h2 class="<?php echo esc_attr( $heading_class ); ?>"><?php echo wp_kses_post( $quote_heading ); ?></h2>
	<?php
endif;

do_action( 'wpheka_rfq_email_before_items_table', $rfq_data, $email );

if ( empty( $rfq_data ) ) :
	?>
	<p class="font-family"><?php esc_html_e( 'This request contains no products.', 'wpheka-request-for-quote' ); ?></p>
	<?php
else :
	?>
	<div style="margin-bottom: 40px;">
		<table class="td font-family <?php echo esc_attr( $table_class ); ?>" cellspacing="0" cellpadding="6" style="width: 100%;" border="1">
			<thead>
				<tr>
					<th class="td text-align-<?php echo esc_attr( $text_align ); ?>" scope="col"><?php esc_html_e( 'Product', 'wpheka-request-for-quote' ); ?></th>
					<th class="td text-align-<?php echo esc_attr( $number_align ); ?>" scope="col"><?php esc_html_e( 'Quantity', 'wpheka-request-for-quote' ); ?></th>
					<?php if ( $show_price ) : ?>
						<th class="td text-align-<?php echo esc_attr( $number_align ); ?>" scope="col"><?php esc_html_e( 'Price', 'wpheka-request-for-quote' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $rfq_data as $rfq_item_key => $rfq_item ) {
				$actual_product_id = empty( $rfq_item['variation_id'] ) ? $rfq_item['product_id'] : $rfq_item['variation_id'];
				$actual_product    = wc_get_product( $actual_product_id );

				if ( ! $actual_product ) {
					continue;
				}

				$product    = apply_filters( 'wpheka_rfq_item_product', $actual_product, $rfq_item, $rfq_item_key );
				$product_id = apply_filters( 'wpheka_rfq_item_product_id', $rfq_item['product_id'], $rfq_item, $rfq_item_key );
				$qty        = isset( $rfq_item['quantity'] ) ? (int) $rfq_item['quantity'] : 1;
				?>
				<tr class="order_item">
					<td class="td font-family text-align-<?php echo esc_attr( $text_align ); ?>" style="vertical-align: middle; word-wrap: break-word;">
						<?php echo wp_kses_post( $product->get_name() ); ?>
					</td>
					<td class="td font-family text-align-<?php echo esc_attr( $number_align ); ?>" style="vertical-align: middle;">
						<?php echo esc_html( $qty ); ?>
					</td>
					<?php
					if ( $show_price ) {
						/*
						 * The cart is a front-end object and does not exist in
						 * wp-admin, so the preview and the test email used to die
						 * here on "Call to a member function get_product_subtotal()
						 * on null". Real sends still take the cart path, so the
						 * figure in a genuine quote email is unchanged; the
						 * fallback only runs where there is no cart to ask.
						 */
						$subtotal = ( isset( WC()->cart ) && WC()->cart )
							? WC()->cart->get_product_subtotal( $product, $qty )
							: wc_price( wc_get_price_to_display( $product, array( 'qty' => $qty ) ) );
						?>
						<td class="td font-family text-align-<?php echo esc_attr( $number_align ); ?>" style="vertical-align: middle;">
							<?php
							echo wp_kses(
								$subtotal,
								array(
									'span' => array( 'class' => true ),
									'bdi'  => array(),
								)
							);
							?>
						</td>
						<?php
					}
					?>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
	</div>
	<?php
endif;

do_action( 'wpheka_rfq_email_after_items_table', $rfq_data, $email );

if ( ! empty( $customer_data ) ) :
	// Labels, and the order they read best in. Anything unexpected still shows.
	$field_labels = array(
		'name'    => __( 'Name', 'wpheka-request-for-quote' ),
		'email'   => __( 'Email', 'wpheka-request-for-quote' ),
		'phone'   => __( 'Phone', 'wpheka-request-for-quote' ),
		'company' => __( 'Company', 'wpheka-request-for-quote' ),
		'message' => __( 'Message', 'wpheka-request-for-quote' ),
	);

	$ordered = array_merge( array_intersect_key( $field_labels, $customer_data ), $customer_data );
	$rows    = array_filter(
		array_intersect_key( $customer_data, $ordered ),
		static function ( $value ) {
			return '' !== trim( (string) $value );
		}
	);

	if ( ! empty( $rows ) ) :
		?>
		<h2 class="<?php echo esc_attr( $heading_class ); ?>"><?php esc_html_e( 'Customer details', 'wpheka-request-for-quote' ); ?></h2>

		<?php
		/*
		 * Deliberately without the `email-order-details` class the items table
		 * uses. Core styles that class for a summary table whose last row is all
		 * <td> -- `.email-order-details tbody tr:last-child td` adds a bottom
		 * border -- and this table's last row starts with a <th> label, so the
		 * rule drew across the value column only and stopped short of the label.
		 */
		?>
		<div style="margin-bottom: 40px;">
			<table class="td font-family" cellspacing="0" cellpadding="6" style="width: 100%;" border="1">
				<tbody>
				<?php foreach ( array_keys( $ordered ) as $cus_key ) : ?>
					<?php
					if ( ! isset( $rows[ $cus_key ] ) ) {
						continue;
					}

					$label = isset( $field_labels[ $cus_key ] ) ? $field_labels[ $cus_key ] : ucfirst( str_replace( '_', ' ', $cus_key ) );
					?>
					<tr>
						<th class="td text-align-<?php echo esc_attr( $text_align ); ?>" scope="row" style="width: 30%;"><?php echo esc_html( $label ); ?></th>
						<td class="td font-family text-align-<?php echo esc_attr( $text_align ); ?>" style="word-wrap: break-word;">
							<?php echo wp_kses_post( nl2br( wp_unslash( (string) $rows[ $cus_key ] ) ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	endif;
endif;

do_action( 'woocommerce_email_footer', $email );
