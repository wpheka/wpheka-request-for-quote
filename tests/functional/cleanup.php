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
foreach ( array( 'in', 'out' ) as $k ) {
	if ( ! empty( $ids[ $k ] ) && ( $p = wc_get_product( $ids[ $k ] ) ) ) {
		$p->delete( true );
	}
}
if ( ! empty( $ids['admin'] ) ) {
	wp_delete_user( $ids['admin'] );
}
foreach ( array( 'settings' => 'wpheka_rfq_general_settings', 'quote_count' => 'wpheka_rfq_quote_count' ) as $k => $opt ) {
	if ( '__unset__' === ( $ids[ $k ] ?? '__unset__' ) ) {
		delete_option( $opt );
	} else {
		update_option( $opt, $ids[ $k ] );
	}
}
$sessions = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpheka_rfq_sessions WHERE session_id > %d", (int) $ids['max_session'] ) );
foreach ( array( 'mu', 'theme_mu' ) as $k ) {
	if ( ! empty( $ids[ $k ] ) && file_exists( $ids[ $k ] ) ) {
		unlink( $ids[ $k ] );
	}
}
if ( empty( $ids['was_active'] ) ) {
	deactivate_plugins( 'wpheka-request-for-quote/wpheka-request-for-quote.php' );
}
$left = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE 'ZZ RFQ%'" );
echo "cleanup: removed $sessions test session(s); leftover test posts: $left; plugin ", empty( $ids['was_active'] ) ? 'deactivated again' : 'left active', "\n";
