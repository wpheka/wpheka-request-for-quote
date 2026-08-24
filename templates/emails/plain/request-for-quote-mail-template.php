<?php
/**
 * Request Quote email template, plain text.
 *
 * Until 1.8.2 the email class pointed template_plain at the HTML template, so a
 * store with "Email type: Plain text" mailed a full <!DOCTYPE html> document as
 * text/plain and the recipient read the markup. This file is the plain-text
 * rendering WooCommerce's own emails have always had beside their HTML one.
 *
 * @package WPHEKA_Rfq
 * @subpackage WPHEKA_Rfq_Mail
 * @since 1.8.2
 * @version 1.8.2
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

/*
 * Normalised the same way the HTML template does, and for the same reason:
 * WooCommerce's email preview and "Send a test email" render this from wp-admin,
 * where there is no quote session and no cart.
 */
$rfq_data      = is_array( $rfq_data ) ? $rfq_data : array();
$customer_data = is_array( $customer_data ) ? $customer_data : array();
$customer_name = isset( $customer_data['name'] ) ? trim( (string) $customer_data['name'] ) : '';
$show_price    = 'no' === wpheka_request_for_quote()->get_settings( 'hide_price' );

/**
 * Render a value as plain text.
 *
 * esc_html() is wrong in a plain-text email, not merely unnecessary. It encodes
 * the characters it is meant to neutralise in markup, and there is no markup
 * here to neutralise them for -- so the recipient reads the encoding. Measured:
 * "Bed & Breakfast Package" arrived as "Bed &amp; Breakfast Package", and
 * "Tom's \"Deluxe\" Kit" as "Tom&#039;s &quot;Deluxe&quot; Kit".
 *
 * WooCommerce's own plain-text templates call esc_html() and have the same
 * behaviour. Diverging from that convention is deliberate: an ampersand in a
 * product name is ordinary, and this release exists to make these emails
 * readable.
 *
 * Tags are stripped because values reach here from product names and admin
 * settings that may contain markup, and entities are decoded because a value
 * may arrive already encoded -- prices are, WooCommerce formats them with
 * &pound; and friends.
 *
 * @param mixed $value Value to render.
 * @return string
 */
$wpheka_rfq_plain = static function ( $value ) {
	return wp_strip_all_tags( html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' ) );
};

/*
 * Escaping is disabled for the body of this template, for the reason above. It
 * is re-enabled at the end of the file rather than left off, so anything added
 * below the closing marker is still checked.
 */
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.UnsafePrintingFunction

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo $wpheka_rfq_plain( $email_heading );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ( '' !== $customer_name ) {
	printf(
		/* translators: %s: customer name. */
		$wpheka_rfq_plain( __( 'You have received a quote request from %s. The request is as follows:', 'wpheka-request-for-quote' ) ),
		$wpheka_rfq_plain( $customer_name )
	);
} else {
	echo $wpheka_rfq_plain( __( 'You have received a quote request. The request is as follows:', 'wpheka-request-for-quote' ) );
}

echo "\n\n";

echo $wpheka_rfq_plain( wc_strtoupper( __( 'Quote summary', 'wpheka-request-for-quote' ) ) ) . "\n";
echo "----------------------------------------\n\n";

do_action( 'wpheka_rfq_email_before_items_table', $rfq_data, $email );

if ( empty( $rfq_data ) ) {
	echo $wpheka_rfq_plain( __( 'This request contains no products.', 'wpheka-request-for-quote' ) ) . "\n";
} else {
	foreach ( $rfq_data as $rfq_item_key => $rfq_item ) {
		$actual_product_id = empty( $rfq_item['variation_id'] ) ? $rfq_item['product_id'] : $rfq_item['variation_id'];
		$actual_product    = wc_get_product( $actual_product_id );

		if ( ! $actual_product ) {
			continue;
		}

		$product = apply_filters( 'wpheka_rfq_item_product', $actual_product, $rfq_item, $rfq_item_key );
		$qty     = isset( $rfq_item['quantity'] ) ? (int) $rfq_item['quantity'] : 1;

		/* translators: 1: product name, 2: quantity */
		echo $wpheka_rfq_plain( sprintf( __( '%1$s x %2$d', 'wpheka-request-for-quote' ), $product->get_name(), $qty ) );

		if ( $show_price ) {
			/*
			 * No cart in wp-admin, same fallback as the HTML template so a real
			 * send keeps producing the figure it always did.
			 */
			$subtotal = ( isset( WC()->cart ) && WC()->cart )
				? WC()->cart->get_product_subtotal( $product, $qty )
				: wc_price( wc_get_price_to_display( $product, array( 'qty' => $qty ) ) );

			echo ' - ' . $wpheka_rfq_plain( $subtotal );
		}

		echo "\n";
	}
}

do_action( 'wpheka_rfq_email_after_items_table', $rfq_data, $email );

if ( ! empty( $customer_data ) ) {
	$field_labels = array(
		'name'    => __( 'Name', 'wpheka-request-for-quote' ),
		'email'   => __( 'Email', 'wpheka-request-for-quote' ),
		'phone'   => __( 'Phone', 'wpheka-request-for-quote' ),
		'company' => __( 'Company', 'wpheka-request-for-quote' ),
		'message' => __( 'Message', 'wpheka-request-for-quote' ),
	);

	$ordered = array_merge( array_intersect_key( $field_labels, $customer_data ), $customer_data );
	$printed = false;

	foreach ( array_keys( $ordered ) as $cus_key ) {
		$value = isset( $customer_data[ $cus_key ] ) ? trim( (string) $customer_data[ $cus_key ] ) : '';

		if ( '' === $value ) {
			continue;
		}

		if ( ! $printed ) {
			echo "\n" . $wpheka_rfq_plain( wc_strtoupper( __( 'Customer details', 'wpheka-request-for-quote' ) ) ) . "\n";
			echo "----------------------------------------\n\n";
			$printed = true;
		}

		$label = isset( $field_labels[ $cus_key ] ) ? $field_labels[ $cus_key ] : ucfirst( str_replace( '_', ' ', $cus_key ) );

		echo $wpheka_rfq_plain( $label ) . ': ' . $wpheka_rfq_plain( wp_unslash( $value ) ) . "\n";
	}
}

echo "\n----------------------------------------\n\n";

/*
 * Line breaks converted before output, not left as markup.
 *
 * WC_Email::get_content() runs wp_strip_all_tags() over plain-text content,
 * and that removes <br /> without putting anything in its place. The default
 * footer is "{site_title}<br />{store_address}", so it arrived as
 * "My Store123 Some Street" -- run together on one line. WooCommerce's own
 * plain templates have the same quirk; this one does not, deliberately.
 */
$footer_text = apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
$footer_text = preg_replace( '#<br\s*/?>#i', "\n", (string) $footer_text );

echo trim( $wpheka_rfq_plain( $footer_text ) ) . "\n";

// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.UnsafePrintingFunction
