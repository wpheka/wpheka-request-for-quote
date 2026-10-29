<?php
/** Restores settings, counters and activation; deletes test products, user, sessions, mail capture. */
require __DIR__ . '/lib.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$ids = rfq_t_ids();
if ( ! $ids ) {
	echo "nothing to clean up\n";
	return;
}
global $wpdb;
foreach ( array( 'in', 'out', 'var_in', 'var_out', 'draft', 'private' ) as $k ) {
	if ( ! empty( $ids[ $k ] ) && ( $p = wc_get_product( $ids[ $k ] ) ) ) {
		$p->delete( true );
	}
}
foreach ( array( 'admin', 'manager', 'subscriber' ) as $k ) {
	if ( ! empty( $ids[ $k ] ) ) {
		wp_delete_user( $ids[ $k ] );
	}
}
if ( isset( $ids['max_request'] ) && class_exists( 'WPHEKA_Rfq_Requests' ) ) {
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . WPHEKA_Rfq_Requests::table() . ' WHERE id > %d', (int) $ids['max_request'] ) );
	WPHEKA_Rfq_Requests::flush_stats();
}
foreach ( array( 'settings' => 'wpheka_rfq_general_settings', 'quote_count' => 'wpheka_rfq_quote_count', 'delete_data' => 'wpheka_rfq_delete_data', 'mail_settings' => 'woocommerce_wpheka_rfq_mail_settings', 'page_id' => 'wpheka_request_for_quote_page_id' ) as $k => $opt ) {
	if ( '__unset__' === ( $ids[ $k ] ?? '__unset__' ) ) {
		delete_option( $opt );
	} else {
		update_option( $opt, $ids[ $k ] );
	}
}
$sessions = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpheka_rfq_sessions WHERE session_id > %d", (int) $ids['max_session'] ) );
foreach ( array( 'mu', 'theme_mu', 'notices_mu' ) as $k ) {
	if ( ! empty( $ids[ $k ] ) && file_exists( $ids[ $k ] ) ) {
		unlink( $ids[ $k ] );
	}
}
if ( empty( $ids['was_active'] ) ) {
	deactivate_plugins( 'wpheka-request-for-quote/wpheka-request-for-quote.php' );
}
$left = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE 'ZZ RFQ%'" );
echo "cleanup: removed $sessions test session(s); leftover test posts: $left; plugin ", empty( $ids['was_active'] ) ? 'deactivated again' : 'left active', "\n";
