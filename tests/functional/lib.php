<?php
/** Shared helpers for the functional suite, loaded through WP-CLI eval-file. */
function rfq_t_state( $file ) {
	return rtrim( getenv( 'RFQ_STATE' ), '/' ) . '/' . $file;
}
function rfq_t_ids() {
	$path = rfq_t_state( 'ids.json' );
	return file_exists( $path ) ? json_decode( file_get_contents( $path ), true ) : array();
}
function rfq_t_save_ids( $ids ) {
	file_put_contents( rfq_t_state( 'ids.json' ), wp_json_encode( $ids ) );
}
/** The baseline every case starts from; a case overrides single keys. */
function rfq_t_baseline() {
	return array(
		'user_type'            => 'all',
		'out_of_stock_option'  => 'show_all',
		'hide_price'           => 'yes',
		'hide_add_to_cart'     => 'yes',
		'button_type'          => 'button',
		'button_position'      => 'before',
		'button_in_other_pages'=> 'yes',
		'button_link_text'     => 'Add to quote',
		'after_click_action'   => 'show_link',
		'show_phone_field'     => 'yes',
		'phone_required'       => 'yes',
		'show_company_field'   => 'yes',
	);
}
