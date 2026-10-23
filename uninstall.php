<?php
/**
 * Uninstall Request For Quote.
 *
 * Everything is kept unless "Delete all data when the plugin is deleted" is on (option
 * `wpheka_rfq_delete_data`, off by default). With it on, the stored quote requests, the
 * quote list sessions, the plugin's options and transients and its review prompt user meta
 * are removed. The quote list page is site content and is kept.
 *
 * @package WPHEKA_Rfq
 * @since   1.9.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! function_exists( 'wpheka_rfq_uninstall_site' ) ) {
	/**
	 * Remove the current site's data, if that site turned the setting on.
	 */
	function wpheka_rfq_uninstall_site() {
		global $wpdb;

		if ( 'yes' !== get_option( 'wpheka_rfq_delete_data' ) ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall removes the plugin's own tables, options and meta.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpheka_rfq_requests" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpheka_rfq_sessions" );

		foreach ( array( 'wpheka_rfq_general_settings', 'wpheka_request_for_quote_page_id', 'wpheka_rfq_quote_count', 'wpheka_rfq_activation_time', 'wpheka_rfq_requests_db_version', 'wpheka_rfq_delete_data', 'woocommerce_wpheka_rfq_mail_settings' ) as $wpheka_rfq_option ) {
			delete_option( $wpheka_rfq_option );
		}

		foreach ( array( '_transient_', '_transient_timeout_' ) as $wpheka_rfq_kind ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $wpheka_rfq_kind . 'wpheka_rfq_' ) . '%' ) );
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ( %s, %s )", 'wpheka_rfq_review_dismissed', 'wpheka_rfq_review_snoozed_until' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
}

// Each site of a network decides for itself.
if ( is_multisite() ) {
	$wpheka_rfq_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $wpheka_rfq_sites as $wpheka_rfq_site_id ) {
		switch_to_blog( $wpheka_rfq_site_id );
		wpheka_rfq_uninstall_site();
		restore_current_blog();
	}
} else {
	wpheka_rfq_uninstall_site();
}
