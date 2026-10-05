<?php
/**
 * settings.php [key=value ...] -- the baseline settings plus overrides, saved
 * the way the plugin saves them; prints what the plugin then reads back.
 */
require __DIR__ . '/lib.php';
$settings = array_merge( (array) get_option( 'wpheka_rfq_general_settings', array() ), rfq_t_baseline() );
foreach ( $args as $pair ) {
	list( $k, $v ) = array_pad( explode( '=', $pair, 2 ), 2, '' );
	$settings[ $k ] = $v;
}
if ( function_exists( 'wpheka_rfq_framework_ready' ) && wpheka_rfq_framework_ready() ) {
	wpheka_rfq_options()->update( $settings );
} else {
	update_option( 'wpheka_rfq_general_settings', $settings );
}
$read = array();
foreach ( $args as $pair ) {
	$k          = explode( '=', $pair, 2 )[0];
	$read[ $k ] = wpheka_request_for_quote()->get_settings( $k );
}
echo wp_json_encode( $read );
