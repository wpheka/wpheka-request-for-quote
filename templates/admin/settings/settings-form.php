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
						<tr>
							<th scope="row"><?php esc_html_e( 'Show "Add to quote" button to:', 'wpheka-request-for-quote' ); ?></th>
							<td>
								<label>
									<input type="radio" name="user_type" value="all" <?php checked( 'all', wpheka_request_for_quote()->get_settings( 'user_type' ), true ); ?> />
									<?php esc_html_e( 'All users', 'wpheka-request-for-quote' ); ?>
								</label><br/>
								<label>
									<input type="radio" name="user_type" value="logged" <?php checked( 'logged', wpheka_request_for_quote()->get_settings( 'user_type' ), true ); ?> />
									<?php esc_html_e( 'Only logged-in users', 'wpheka-request-for-quote' ); ?>
								</label><br/>
								<label>
									<input type="radio" name="user_type" value="guests" <?php checked( 'guests', wpheka_request_for_quote()->get_settings( 'user_type' ), true ); ?> />
									<?php esc_html_e( 'Only guest users', 'wpheka-request-for-quote' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Out of stock products:', 'wpheka-request-for-quote' ); ?></th>
							<td>
								<label>
									<input type="radio" name="out_of_stock_option" value="show_all" <?php checked( 'show_all', wpheka_request_for_quote()->get_settings( 'out_of_stock_option' ), true ); ?> />
									<?php esc_html_e( 'Show button on all products (including out of stock)', 'wpheka-request-for-quote' ); ?>
								</label><br/>
								<label>
									<input type="radio" name="out_of_stock_option" value="only_out_of_stock" <?php checked( 'only_out_of_stock', wpheka_request_for_quote()->get_settings( 'out_of_stock_option' ), true ); ?> />
									<?php esc_html_e( 'Show button ONLY on out of stock products', 'wpheka-request-for-quote' ); ?>
								</label><br/>
								<label>
									<input type="radio" name="out_of_stock_option" value="hide_out_of_stock" <?php checked( 'hide_out_of_stock', wpheka_request_for_quote()->get_settings( 'out_of_stock_option' ), true ); ?> />
									<?php esc_html_e( 'Hide button on out of stock products', 'wpheka-request-for-quote' ); ?>
								</label>
							</td>
						</tr>
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
									<option value=""><?php echo esc_attr( __( 'Select a page…', 'wpheka-request-for-quote' ) ); ?></option>
									<?php
									$wp_pages = get_pages();
									$wpheka_request_for_quote_page_id = get_option( 'wpheka_request_for_quote_page_id' );
									foreach ( $wp_pages as $wp_page ) {
										$option  = '<option value="' . esc_attr( $wp_page->ID ) . '" ' . selected( $wpheka_request_for_quote_page_id, $wp_page->ID, false ) . '>';
										$option .= esc_html( $wp_page->post_title );
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
									<option value="button" <?php selected( wpheka_request_for_quote()->get_settings( 'button_type' ), 'button' ); ?>><?php echo esc_attr( __( 'Button', 'wpheka-request-for-quote' ) ); ?></option>
									<option value="link" <?php selected( wpheka_request_for_quote()->get_settings( 'button_type' ), 'link' ); ?>><?php echo esc_attr( __( 'Link', 'wpheka-request-for-quote' ) ); ?></option>
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
							<th scope="row"><?php esc_html_e( 'Button position on product page:', 'wpheka-request-for-quote' ); ?></th>
							<td>
								<label>
									<input type="radio" name="button_position" value="after" <?php checked( 'after', wpheka_request_for_quote()->get_settings( 'button_position' ), true ); ?> />
									<?php esc_html_e( 'After "Add to cart" button', 'wpheka-request-for-quote' ); ?>
								</label><br/>
								<label>
									<input type="radio" name="button_position" value="before" <?php checked( 'before', wpheka_request_for_quote()->get_settings( 'button_position' ), true ); ?> />
									<?php esc_html_e( 'Before "Add to cart" button', 'wpheka-request-for-quote' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="button_link_text"><?php esc_html_e( 'Button/Link Text', 'wpheka-request-for-quote' ); ?></label></th>
							<td>
								<input name="button_link_text" type="text" id="button_link_text" style="width: 60%;" value="<?php echo esc_attr( wpheka_request_for_quote()->get_settings( 'button_link_text' ) ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'After clicking "Add to quote":', 'wpheka-request-for-quote' ); ?></th>
							<td>
								<label>
									<input type="radio" name="after_click_action" value="show_link" <?php checked( 'show_link', wpheka_request_for_quote()->get_settings( 'after_click_action' ), true ); ?> />
									<?php esc_html_e( 'Show a link to the quote list', 'wpheka-request-for-quote' ); ?>
								</label><br/>
								<label>
									<input type="radio" name="after_click_action" value="redirect" <?php checked( 'redirect', wpheka_request_for_quote()->get_settings( 'after_click_action' ), true ); ?> />
									<?php esc_html_e( 'Automatically redirect to quote list page', 'wpheka-request-for-quote' ); ?>
								</label>
							</td>
						</tr>
						<?php
						/*
						 * "Required" is meaningless with the phone field switched off, so it
						 * is rendered from the parent's state rather than on its own. It used
						 * to render its stored value regardless, which showed a checked
						 * "Make phone number required" sitting under an unchecked "Show phone
						 * number field" -- a requirement on a field that was never output.
						 */
						$wpheka_rfq_show_phone     = 'yes' === wpheka_request_for_quote()->get_settings( 'show_phone_field' );
						$wpheka_rfq_phone_required = $wpheka_rfq_show_phone && 'yes' === wpheka_request_for_quote()->get_settings( 'phone_required' );
						?>
						<tr class="form-field">
							<th scope="row"><?php esc_html_e( 'Additional Form Fields', 'wpheka-request-for-quote' ); ?></th>
							<td>
								<fieldset class="wpheka-field-group">
									<legend class="screen-reader-text"><?php esc_html_e( 'Phone number field', 'wpheka-request-for-quote' ); ?></legend>
									<label class="wpheka-check" for="show_phone_field">
										<input type="checkbox" id="show_phone_field" name="show_phone_field" value="yes" <?php checked( true, $wpheka_rfq_show_phone ); ?> />
										<span><?php esc_html_e( 'Show phone number field', 'wpheka-request-for-quote' ); ?></span>
									</label>
									<label class="wpheka-check wpheka-check--child<?php echo $wpheka_rfq_show_phone ? '' : ' is-disabled'; ?>" for="phone_required">
										<input type="checkbox" id="phone_required" name="phone_required" value="yes" <?php checked( true, $wpheka_rfq_phone_required ); ?> <?php disabled( false, $wpheka_rfq_show_phone ); ?> />
										<span><?php esc_html_e( 'Make phone number required', 'wpheka-request-for-quote' ); ?></span>
									</label>
								</fieldset>

								<fieldset class="wpheka-field-group">
									<legend class="screen-reader-text"><?php esc_html_e( 'Company name field', 'wpheka-request-for-quote' ); ?></legend>
									<label class="wpheka-check" for="show_company_field">
										<input type="checkbox" id="show_company_field" name="show_company_field" value="yes" <?php checked( 'yes', wpheka_request_for_quote()->get_settings( 'show_company_field' ) ); ?> />
										<span><?php esc_html_e( 'Show company name field', 'wpheka-request-for-quote' ); ?></span>
									</label>
								</fieldset>

								<p class="wpheka-field-hint"><?php esc_html_e( 'Shown on the quote request form, after name and email.', 'wpheka-request-for-quote' ); ?></p>
							</td>
						</tr>
					</table>        
				</div>
			</div>      

		</fieldset>
	</div>
</form>
