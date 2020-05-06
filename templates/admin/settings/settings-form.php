<?php
/**
 * Template to display settings form of the setting page
 *
 * @package WPHEKA_Rfq
 * @subpackage WPHEKA_Rfq_Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}
?>
<form method="post" id="plugin-settings-form">
	<div class='wpheka-box'>
		<fieldset class='mb22'>
			<legend class='wpheka-box-title-bar wpheka-box-title-bar__small mb22'><h3><?php esc_html_e( 'General:', 'wpheka-request-for-quote' ); ?></h3></legend>
			<div id="wpheka-plugin-form">
				<div id="wpheka-plugin-form-fields">
					<table class="form-table" role="presentation">
						<tr class="form-field form-required">
							<th scope="row"><?php esc_html_e( 'Hide Price', 'wpheka-request-for-quote' ); ?></th>
							<td>
								<label>
									<input type="checkbox" id="hide_price" name="hide_price" value="yes" <?php checked( 'yes', wpheka_request_for_quote()->get_settings( 'hide_price' ) ); ?> />
									<?php esc_html_e( 'Hide price from product pages.', 'wpheka-request-for-quote' ); ?>
								</label>
							</td>
						</tr>
						<tr class="form-field form-required">
							<th scope="row"><?php esc_html_e( 'Hide Add To Cart', 'wpheka-request-for-quote' ); ?></th>
							<td>
								<label>
									<input type="checkbox" id="hide_add_to_cart" name="hide_add_to_cart" value="yes" <?php checked( 'yes', wpheka_request_for_quote()->get_settings( 'hide_add_to_cart' ) ); ?> />
									<?php esc_html_e( 'Hide add to cart button from product pages.', 'wpheka-request-for-quote' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpheka_request_for_quote_page_id"><?php esc_html_e( 'Request Quote Page', 'wpheka-request-for-quote' ); ?></label></th>
							<td>
								<select name="wpheka_request_for_quote_page_id" style="min-width:300px" id="wpheka_request_for_quote_page_id">
									<option value=""><?php echo esc_attr( __( 'Select a page…' ) ); ?></option>
									<?php
									$wp_pages = get_pages();
									$wpheka_request_for_quote_page_id = get_option( 'wpheka_request_for_quote_page_id' );
									foreach ( $wp_pages as $wp_page ) {
										$option  = '<option value="' . $wp_page->ID . '" ' . selected( $wpheka_request_for_quote_page_id, $wp_page->ID ) . '>';
										$option .= $wp_page->post_title;
										$option .= '</option>';
										echo wp_kses(
											$option,
											array(
												'option' => array(
													'value'   => true,
													'selected' => true,
												),
											)
										);
									}
									?>
								</select>
								<p><?php esc_html_e( 'Page contents: [wpheka_request_for_quote]', 'wpheka-request-for-quote' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="button_type"><?php esc_html_e( 'Button Type', 'wpheka-request-for-quote' ); ?></label></th>
							<td>
								<select name="button_type" style="min-width:300px" id="button_type">
									<option value="button" <?php selected( wpheka_request_for_quote()->get_settings( 'button_type' ), 'button' ); ?>><?php echo esc_attr( __( 'Button' ) ); ?></option>
									<option value="link" <?php selected( wpheka_request_for_quote()->get_settings( 'button_type' ), 'link' ); ?>><?php echo esc_attr( __( 'Link' ) ); ?></option>
								</select>
							</td>
						</tr>

						<tr class="form-field form-required">
							<th scope="row"><?php esc_html_e( 'Button In Other Pages', 'wpheka-request-for-quote' ); ?></th>
							<td>
								<label>
									<input type="checkbox" id="button_in_other_pages" name="button_in_other_pages" value="yes" <?php checked( 'yes', wpheka_request_for_quote()->get_settings( 'button_in_other_pages' ) ); ?> />
									<?php esc_html_e( 'Enable request quote button in other archive pages.', 'wpheka-request-for-quote' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="button_link_text"><?php esc_html_e( 'Button/Link Text', 'wpheka-request-for-quote' ); ?></label></th>
							<td>
								<input name="button_link_text" type="text" id="button_link_text" style="width: 60%;" value="<?php echo esc_attr( wpheka_request_for_quote()->get_settings( 'button_link_text' ) ); ?>" />
							</td>
						</tr>
					</table>        
				</div>
			</div>      

		</fieldset>
	</div>
</form>
