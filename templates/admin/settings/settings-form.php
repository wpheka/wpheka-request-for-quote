<?php
if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}
?>
<form method="post" id="plugin-settings-form">
	<div class='wpheka-box'>
		<fieldset class='mb22'>
			<legend class='wpheka-box-title-bar wpheka-box-title-bar__small mb22'><h3><?php esc_html_e( 'General:', wpheka_request_for_quote()->text_domain ); ?></h3></legend>
			<div id="wpheka-plugin-form">
				<div id="wpheka-plugin-form-fields">
					<table class="form-table" role="presentation">
						<tr class="form-field form-required">
							<th scope="row"><?php _e( 'Hide Price', wpheka_request_for_quote()->text_domain ); ?></th>
							<td>
								<label>
									<input type="checkbox" id="hide_price" name="hide_price" value="yes" <?php checked('yes', wpheka_request_for_quote()->get_settings('hide_price') ); ?> />
									<?php _e( 'Hide price from product pages.', wpheka_request_for_quote()->text_domain ); ?>
								</label>
							</td>
						</tr>
						<tr class="form-field form-required">
							<th scope="row"><?php _e( 'Hide Add To Cart', wpheka_request_for_quote()->text_domain ); ?></th>
							<td>
								<label>
									<input type="checkbox" id="hide_add_to_cart" name="hide_add_to_cart" value="yes" <?php checked('yes', wpheka_request_for_quote()->get_settings('hide_add_to_cart') ); ?> />
									<?php _e( 'Hide add to cart button from product pages.', wpheka_request_for_quote()->text_domain ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpheka_request_for_quote_page_id"><?php _e( 'Request Quote Page', wpheka_request_for_quote()->text_domain ); ?></label></th>
							<td>
								<select name="wpheka_request_for_quote_page_id" style="min-width:300px" id="wpheka_request_for_quote_page_id">
									<option value=""><?php echo esc_attr( __( 'Select a page…' ) ); ?></option>
									<?php 
									$pages = get_pages();
									$wpheka_request_for_quote_page_id = get_option('wpheka_request_for_quote_page_id');
									foreach ( $pages as $page ) {
									$option = '<option value="' . $page->ID . '" ' . selected( $wpheka_request_for_quote_page_id, $page->ID ) . '>';
									$option .= $page->post_title;
									$option .= '</option>';
									echo $option;
									}
									?>
								</select>
								<p><?php _e( 'Page contents: [wpheka_request_for_quote]', wpheka_request_for_quote()->text_domain ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="button_type"><?php _e( 'Button Type', wpheka_request_for_quote()->text_domain ); ?></label></th>
							<td>
								<select name="button_type" style="min-width:300px" id="button_type">
									<option value="button" <?php selected( wpheka_request_for_quote()->get_settings('button_type'), 'button' ); ?>><?php echo esc_attr( __( 'Button' ) ); ?></option>
									<option value="link" <?php selected( wpheka_request_for_quote()->get_settings('button_type'), 'link' ); ?>><?php echo esc_attr( __( 'Link' ) ); ?></option>
								</select>
							</td>
						</tr>

						<tr class="form-field form-required">
							<th scope="row"><?php _e( 'Button In Other Pages', wpheka_request_for_quote()->text_domain ); ?></th>
							<td>
								<label>
									<input type="checkbox" id="button_in_other_pages" name="button_in_other_pages" value="yes" <?php checked('yes', wpheka_request_for_quote()->get_settings('button_in_other_pages') ); ?> />
									<?php _e( 'Enable request quote button in other archive pages.', wpheka_request_for_quote()->text_domain ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="button_link_text"><?php _e( 'Button/Link Text', wpheka_request_for_quote()->text_domain ); ?></label></th>
							<td>
								<input name="button_link_text" type="text" id="button_link_text" style="width: 60%;" value="<?php echo esc_attr( wpheka_request_for_quote()->get_settings('button_link_text') ); ?>" />
							</td>
						</tr>
					</table>		
				</div>
			</div>		

		</fieldset>
	</div>
</form>