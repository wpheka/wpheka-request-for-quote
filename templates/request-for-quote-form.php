<?php
/**
 * Request Quote form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
do_action( 'wpheka_before_rfq_form_start' );
?>
<form class="woocommerce-form wpheka-quote-product-list-mail-form" id="wpheka-quote-request-form" method="post">

	<?php do_action( 'wpheka_rfq_form_start' ); ?>

	<?php
		if(!empty( $message )) {
			echo wpautop( wptexturize( $message ) );
		} 
	?>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="rfq_display_name"><?php esc_html_e( 'Name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="rfq_display_name" id="rfq_display_name" value="<?php if( !empty($username) ) { echo esc_attr( $username ); } ?>" required />
	</p>
	<div class="clear"></div>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="rfq_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
		<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="rfq_email" id="rfq_email" autocomplete="email" value="<?php if( !empty($email) ) { echo esc_attr( $email ); } ?>" required />
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="rfq_message"><?php esc_html_e( 'Message', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
		<textarea class="woocommerce-Input" id="rfq_message" name="rfq_message" cols="45" rows="5" required></textarea>
	</p>

	<?php do_action( 'wpheka_rfq_form' ); ?>

	<p class="woocommerce-form-row form-row">
		<input type="hidden" name="wpheka_rfq_send_request" value="true" />
		<button type="submit" class="woocommerce-Button button wpheka-quote-request-form-submit" form="wpheka-quote-request-form" value="<?php esc_attr_e( 'Send Your Request', 'woocommerce' ); ?>"><?php esc_html_e( 'Send Your Request', 'woocommerce' ); ?></button>
	</p>

	<?php wp_nonce_field( 'rfq-send-request', 'wpheka-send-quote-request-nonce' ); ?>

	<?php do_action( 'wpheka_rfq_form_end' ); ?>

</form>

<?php do_action( 'wpheka_after_rfq_form_end' ); ?>