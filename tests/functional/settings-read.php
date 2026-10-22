<?php
// settings-read.php <key>|all -- what the plugin reads for one setting, or every stored setting, as JSON.
if ( 'all' === $args[0] ) {
	$all = function_exists( 'wpheka_rfq_framework_ready' ) && wpheka_rfq_framework_ready() ? wpheka_rfq_options()->all() : get_option( 'wpheka_rfq_general_settings', array() );
	echo wp_json_encode( $all );
	return;
}
echo wp_json_encode( wpheka_request_for_quote()->get_settings( $args[0] ) );
