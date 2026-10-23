<?php
/**
 * Stored quote requests and the 1.9.0 page's server side, without a browser:
 * storing, stats, the status and delete handlers (nonce and capability refusals),
 * the settings rules, the table created on admin_init, the privacy exporter and
 * eraser, and uninstall with the setting off and on.
 *
 * Run by run.sh through WP-CLI eval-file. Everything it creates is removed or put
 * back before it ends; uninstall runs against copies it restores afterwards.
 */
require __DIR__ . '/lib.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';

global $wpdb;

$results = array();
$check   = static function ( $name, $ok, $detail = '' ) {
	$line = ( $ok ? 'PASS' : 'FAIL' ) . '|requests: ' . $name . ( $ok || '' === $detail ? '' : ' -- ' . $detail );
	file_put_contents( rfq_t_state( 'results' ), $line . "\n", FILE_APPEND );
	echo $line, "\n";
};

if ( ! class_exists( 'WPHEKA_Rfq_Admin_Page' ) ) {
	require_once WPHEKA_RFQ_PLUGIN_ABSPATH . 'includes/wpheka-ui/load.php';
	require_once WPHEKA_RFQ_PLUGIN_ABSPATH . 'includes/admin/class-wpheka-rfq-admin-page.php';
}
$ids   = rfq_t_ids();
$table = WPHEKA_Rfq_Requests::table();

// The handlers end in wp_die() or a redirect and exit; turn both into exceptions to look at them.
// WP-CLI's own redirect warning (with a backtrace) is removed first: the redirect is expected here.
remove_all_filters( 'wp_redirect' );
class Rfq_T_Stop extends Exception {}
add_filter(
	'wp_die_handler',
	static function () {
		return static function ( $message, $title = '', $args = array() ) {
			$code = is_array( $args ) && isset( $args['response'] ) ? (int) $args['response'] : 0;
			throw new Rfq_T_Stop( 'die:' . $code . ':' . wp_strip_all_tags( is_string( $message ) ? $message : '' ) );
		};
	}
);
add_filter(
	'wp_redirect',
	static function ( $location ) {
		throw new Rfq_T_Stop( 'redirect:' . $location );
	}
);
$call = static function ( $handler, array $post, $user_id ) {
	wp_set_current_user( $user_id );
	$_POST    = $post;
	$_REQUEST = $post;
	try {
		call_user_func( $handler );
		$out = 'returned';
	} catch ( Rfq_T_Stop $e ) {
		$out = $e->getMessage();
	}
	$_POST    = array();
	$_REQUEST = array();
	return $out;
};

$admin   = (int) $ids['admin'];
$manager = (int) $ids['manager'];
$sub     = (int) $ids['subscriber'];

// ---------------------------------------------------------------- the table
WPHEKA_Rfq_Requests::maybe_create_table();
$check( 'the requests table exists', $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
$want = array( 'id', 'created_at', 'status', 'customer_name', 'customer_email', 'customer_phone', 'customer_company', 'user_id', 'message', 'items', 'currency', 'total_estimate' );
$check( 'the table has every column', array() === array_diff( $want, $cols ), wp_json_encode( $cols ) );
$keys = $wpdb->get_col( "SHOW INDEX FROM {$table}", 2 );
$check( 'status and created_at are indexed', in_array( 'status', $keys, true ) && in_array( 'created_at', $keys, true ), wp_json_encode( $keys ) );

// ---------------------------------------------------------------- stats with no data
$wpdb->query( "DELETE FROM {$table} WHERE id > " . (int) $ids['max_request'] );
$before_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
WPHEKA_Rfq_Requests::flush_stats();
$empty = WPHEKA_Rfq_Requests::stats();
if ( 0 === $before_rows ) {
	$check( 'stats with no stored request: nothing counted, no month comparison', 0 === $empty['stored'] && 0 === $empty['products'] && '' === $empty['top_name'] && ! $empty['full_month'], wp_json_encode( $empty ) );
} else {
	$check( 'stats with no stored request: skipped, the store already has requests', true );
}

// ---------------------------------------------------------------- storing
$list = array(
	$ids['in'] . ''          => array( 'product_id' => $ids['in'], 'quantity' => 3 ),
	$ids['var_in'] . '-' . $ids['var_in_variation'] => array( 'product_id' => $ids['var_in'], 'variation_id' => $ids['var_in_variation'], 'quantity' => 2 ),
);
$id = WPHEKA_Rfq_Requests::store(
	array(
		'name'    => 'Zz Store Test',
		'email'   => 'zz-rfq-store@example.invalid',
		'phone'   => '2045550100',
		'company' => 'Zz Co',
		'message' => 'Please quote.',
	),
	$list
);
$row   = WPHEKA_Rfq_Requests::get( $id );
$items = $row ? WPHEKA_Rfq_Requests::items( $row ) : array();
$check( 'a stored request has its customer, message and status New', $row && 'Zz Store Test' === $row['customer_name'] && 'zz-rfq-store@example.invalid' === $row['customer_email'] && 'Zz Co' === $row['customer_company'] && '2045550100' === $row['customer_phone'] && 'Please quote.' === $row['message'] && 'new' === $row['status'], wp_json_encode( $row ) );
$check( 'its items hold product, variation, name, quantity and list price', 2 === count( $items ) && (int) $ids['in'] === (int) $items[0]['product_id'] && 3 === (int) $items[0]['quantity'] && 19.99 === (float) $items[0]['price'] && (int) $ids['var_in_variation'] === (int) $items[1]['variation_id'] && '' !== $items[1]['name'], wp_json_encode( $items ) );
$check( 'its estimate is list price times quantity, in the store currency', abs( (float) $row['total_estimate'] - ( 19.99 * 3 + 24.99 * 2 ) ) < 0.001 && get_woocommerce_currency() === $row['currency'], $row['total_estimate'] . ' ' . $row['currency'] );
$check( 'its time is stored in UTC', abs( strtotime( $row['created_at'] . ' UTC' ) - time() ) < 120, $row['created_at'] );
$check( 'an empty quote list stores nothing', 0 === WPHEKA_Rfq_Requests::store( array( 'email' => 'zz-rfq-store@example.invalid' ), array() ) );

// Values longer than their column are fitted, not refused: wpdb would drop the whole insert.
$long_rid = WPHEKA_Rfq_Requests::store(
	array(
		'name'    => 'Zz ' . str_repeat( 'Long', 80 ),
		'email'   => 'zz-rfq-long@example.invalid',
		'phone'   => str_repeat( '1', 80 ),
		'company' => str_repeat( 'É', 260 ),
	),
	array( array( 'product_id' => $ids['in'], 'quantity' => 1 ) )
);
$long_row = $long_rid ? WPHEKA_Rfq_Requests::get( $long_rid ) : null;
$check( 'a request with overlong name, phone and company is still stored', $long_row && 50 === strlen( $long_row['customer_phone'] ) && 200 === mb_strlen( $long_row['customer_company'] ), wp_json_encode( $long_row ? array( strlen( $long_row['customer_phone'] ), mb_strlen( $long_row['customer_company'] ) ) : null ) );
if ( $long_rid ) {
	WPHEKA_Rfq_Requests::delete( $long_rid );
}

// ---------------------------------------------------------------- stats with data
$stats = WPHEKA_Rfq_Requests::stats();
$check( 'stats count the products across requests', $stats['products'] >= 5 && $stats['stored'] >= 1, wp_json_encode( $stats ) );
$check( 'the stats are cached and cleared when a request is stored', false !== get_transient( WPHEKA_Rfq_Requests::STATS_TRANSIENT ) );
$second = WPHEKA_Rfq_Requests::store( array( 'name' => 'Zz Second', 'email' => 'zz-rfq-other@example.invalid' ), array( array( 'product_id' => $ids['in'], 'quantity' => 4 ) ) );
$stats2 = WPHEKA_Rfq_Requests::stats();
$check( 'after another request the stats add up again', $stats2['products'] === $stats['products'] + 4 && $stats2['stored'] === $stats['stored'] + 1, wp_json_encode( $stats2 ) );
$check( 'the most requested product is the one with the highest quantity', get_the_title( $ids['in'] ) === $stats2['top_name'] && $stats2['top_quantity'] >= 7, $stats2['top_name'] . ' ' . $stats2['top_quantity'] );
$check( 'no month comparison before a whole previous month is stored', ( $stats2['full_month'] ) === ( (string) $wpdb->get_var( "SELECT MIN(created_at) FROM {$table}" ) <= gmdate( 'Y-m-01 00:00:00', strtotime( '-1 month', strtotime( gmdate( 'Y-m-01' ) ) ) ) ) );

// ---------------------------------------------------------------- status and delete handlers
$nonce = static function ( $action, $user ) {
	wp_set_current_user( $user );
	return wp_create_nonce( $action );
};
$status_post = static function ( $rid, $status, $user ) use ( $nonce ) {
	return array(
		'action'     => WPHEKA_Rfq_Admin_Page::STATUS_ACTION,
		'request_id' => (string) $rid,
		'status'     => $status,
		'_wpnonce'   => $nonce( WPHEKA_Rfq_Admin_Page::STATUS_ACTION . '_' . $rid, $user ),
	);
};
$out = $call( array( 'WPHEKA_Rfq_Admin_Page', 'handle_status' ), $status_post( $id, 'replied', $manager ), $manager );
$check( 'a shop manager can change a status', 0 === strpos( $out, 'redirect:' ) && false !== strpos( $out, 'rfq_notice=status' ) && 'replied' === WPHEKA_Rfq_Requests::get( $id )['status'], $out );
$out = $call( array( 'WPHEKA_Rfq_Admin_Page', 'handle_status' ), $status_post( $id, 'closed', $sub ), $sub );
$check( 'a subscriber is refused with 403 and the status is unchanged', 0 === strpos( $out, 'die:403' ) && 'replied' === WPHEKA_Rfq_Requests::get( $id )['status'], $out );
$bad             = $status_post( $id, 'closed', $manager );
$bad['_wpnonce'] = 'bogus';
$out             = $call( array( 'WPHEKA_Rfq_Admin_Page', 'handle_status' ), $bad, $manager );
$check( 'a status change without a valid nonce is refused and changes nothing', 0 === strpos( $out, 'die:' ) && 'replied' === WPHEKA_Rfq_Requests::get( $id )['status'], $out );
$other = $status_post( $second, 'closed', $manager );
$other['request_id'] = (string) $id; // The nonce of another request.
$out = $call( array( 'WPHEKA_Rfq_Admin_Page', 'handle_status' ), $other, $manager );
$check( 'one request\'s nonce does not change another request', 0 === strpos( $out, 'die:' ) && 'replied' === WPHEKA_Rfq_Requests::get( $id )['status'], $out );
$out = $call( array( 'WPHEKA_Rfq_Admin_Page', 'handle_status' ), $status_post( $id, 'bogus', $manager ), $manager );
$check( 'an unknown status is refused with an error notice', false !== strpos( $out, 'rfq_notice=error' ) && 'replied' === WPHEKA_Rfq_Requests::get( $id )['status'], $out );

$delete_post = static function ( $rid, $user ) use ( $nonce ) {
	return array(
		'action'     => WPHEKA_Rfq_Admin_Page::DELETE_ACTION,
		'request_id' => (string) $rid,
		'_wpnonce'   => $nonce( WPHEKA_Rfq_Admin_Page::DELETE_ACTION . '_' . $rid, $user ),
	);
};
$out = $call( array( 'WPHEKA_Rfq_Admin_Page', 'handle_delete' ), $delete_post( $second, $sub ), $sub );
$check( 'a subscriber cannot delete a request', 0 === strpos( $out, 'die:403' ) && null !== WPHEKA_Rfq_Requests::get( $second ), $out );
$bad             = $delete_post( $second, $manager );
$bad['_wpnonce'] = 'bogus';
$out             = $call( array( 'WPHEKA_Rfq_Admin_Page', 'handle_delete' ), $bad, $manager );
$check( 'a delete without a valid nonce is refused', 0 === strpos( $out, 'die:' ) && null !== WPHEKA_Rfq_Requests::get( $second ), $out );
$out = $call( array( 'WPHEKA_Rfq_Admin_Page', 'handle_delete' ), $delete_post( $second, $manager ), $manager );
$check( 'a shop manager can delete a request', false !== strpos( $out, 'rfq_notice=deleted' ) && null === WPHEKA_Rfq_Requests::get( $second ), $out );

// ---------------------------------------------------------------- the settings rules
$clean = WPHEKA_Rfq_Admin_Page::sanitize(
	array(
		'user_type'           => 'everyone',
		'button_type'         => 'link',
		'button_link_text'    => '   ',
		'show_phone_field'    => 'no',
		'phone_required'      => 'yes',
		'hide_price'          => 'yes',
		'after_click_action'  => 'redirect',
		'out_of_stock_option' => 'only_out_of_stock',
	),
	42
);
$check( 'an empty button text is saved as "Add to quote"', 'Add to quote' === $clean['button_link_text'], $clean['button_link_text'] );
$check( 'a value outside the choices falls back to the first choice', 'all' === $clean['user_type'] && 'after' === $clean['button_position'], wp_json_encode( $clean ) );
$check( 'phone required is saved off while the phone field is off', 'no' === $clean['phone_required'] );
$check( 'the quote page is also stored in the settings array, as before', '42' === $clean['wpheka_request_for_quote_page_id'] );
$check( 'the save writes all 13 keys', 13 === count( $clean ), wp_json_encode( array_keys( $clean ) ) );
$saved_settings = get_option( 'wpheka_rfq_general_settings' );
update_option( 'wpheka_rfq_general_settings', array_merge( (array) $saved_settings, array( 'button_link_text' => '' ) ) );
$check( 'an empty stored button text prints "Add to quote" on the shop', 'Add to quote' === wpheka_request_for_quote()->get_button_text(), wpheka_request_for_quote()->get_button_text() );
update_option( 'wpheka_rfq_general_settings', $saved_settings );
$check( 'the save handler refuses a subscriber', 0 === strpos( $call( array( 'WPHEKA_Rfq_Admin_Page', 'handle_save' ), array( '_wpnonce' => $nonce( WPHEKA_Rfq_Admin_Page::SAVE_ACTION, $sub ), 'wpheka_request_for_quote_page_id' => '1' ), $sub ), 'die:403' ) && get_option( 'wpheka_rfq_general_settings' ) === $saved_settings );
$out = $call( array( 'WPHEKA_Rfq_Admin_Page', 'handle_save' ), array( '_wpnonce' => $nonce( WPHEKA_Rfq_Admin_Page::SAVE_ACTION, $admin ), 'wpheka_request_for_quote_page_id' => '', 'wpheka_rfq_general_settings' => array( 'button_link_text' => 'Should not save' ) ), $admin );
$check( 'a save with no quote page stores nothing and returns with the error flag', false !== strpos( $out, 'w-error=page' ) && false === strpos( $out, 'w-saved' ) && get_option( 'wpheka_rfq_general_settings' ) === $saved_settings, $out );

// ---------------------------------------------------------------- the table is created on admin_init
$wpdb->query( "RENAME TABLE {$table} TO {$table}_zzkeep" );
update_option( WPHEKA_Rfq_Requests::DB_OPTION, '0' );
// The admin_init callback itself (firing admin_init here would also run other plugins' handlers).
$hooked = false !== has_action( 'admin_init', array( 'WPHEKA_Rfq_Requests', 'maybe_create_table' ) );
WPHEKA_Rfq_Requests::maybe_create_table();
$made = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
$check( 'an update without reactivating creates the table on admin_init', $hooked && $made && WPHEKA_Rfq_Requests::DB_VERSION === get_option( WPHEKA_Rfq_Requests::DB_OPTION ) );
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
$wpdb->query( "RENAME TABLE {$table}_zzkeep TO {$table}" );

// ---------------------------------------------------------------- privacy
$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
$check( 'a personal data exporter and eraser are registered', isset( $exporters['wpheka-request-for-quote'], $erasers['wpheka-request-for-quote'] ) );
$export = call_user_func( $exporters['wpheka-request-for-quote']['callback'], 'zz-rfq-store@example.invalid', 1 );
$flat   = wp_json_encode( $export );
$check( 'the exporter returns the stored request with its details', 1 === count( $export['data'] ) && $export['done'] && false !== strpos( $flat, 'Zz Store Test' ) && false !== strpos( $flat, 'Please quote.' ) && false !== strpos( $flat, '2045550100' ), substr( $flat, 0, 300 ) );
$check( 'the exporter finds nothing for another address', array() === call_user_func( $exporters['wpheka-request-for-quote']['callback'], 'nobody@example.invalid', 1 )['data'] );
$erase = call_user_func( $erasers['wpheka-request-for-quote']['callback'], 'zz-rfq-store@example.invalid', 1 );
$check( 'the eraser removes the requests of that address', true === $erase['items_removed'] && $erase['done'] && null === WPHEKA_Rfq_Requests::get( $id ), wp_json_encode( $erase ) );
// wp_add_privacy_policy_content() only accepts text in wp-admin (is_admin()) during admin_init:
// set an admin screen and mark admin_init as running, as a real admin page load does.
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
set_current_screen( 'dashboard' );
global $wp_current_filter;
$wp_current_filter[] = 'admin_init';
WPHEKA_Rfq_Requests::privacy_policy_content();
array_pop( $wp_current_filter );
unset( $GLOBALS['current_screen'] );
// get_suggested_policy_text() compares with the site's privacy page; read what was added instead.
$registered = new ReflectionProperty( 'WP_Privacy_Policy_Content', 'policy_content' );
$registered->setAccessible( true );
$policy = wp_json_encode( $registered->getValue() );
$check( 'suggested privacy policy text is added', false !== strpos( $policy, 'Request For Quote' ) && false !== strpos( $policy, 'quote request' ), substr( $policy, 0, 200 ) );

// ---------------------------------------------------------------- uninstall
$keep_rid = WPHEKA_Rfq_Requests::store( array( 'name' => 'Zz Uninstall', 'email' => 'zz-rfq-uninstall@example.invalid' ), array( array( 'product_id' => $ids['in'], 'quantity' => 1 ) ) );
$names    = array( 'wpheka_rfq_general_settings', 'wpheka_request_for_quote_page_id', 'wpheka_rfq_quote_count', 'wpheka_rfq_activation_time', 'wpheka_rfq_requests_db_version', 'wpheka_rfq_delete_data', 'woocommerce_wpheka_rfq_mail_settings' );
$backup   = array();
foreach ( $names as $name ) {
	$backup[ $name ] = get_option( $name, '__unset__' );
}
// Uninstall also removes every user's review-prompt meta: keep a copy to put back.
$meta_backup = $wpdb->get_results( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ( 'wpheka_rfq_review_dismissed', 'wpheka_rfq_review_snoozed_until' )", ARRAY_A );
$run_uninstall = static function () {
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		define( 'WP_UNINSTALL_PLUGIN', 'wpheka-request-for-quote/wpheka-request-for-quote.php' );
	}
	include WPHEKA_RFQ_PLUGIN_ABSPATH . 'uninstall.php';
	wp_cache_flush();
};

update_option( 'wpheka_rfq_delete_data', 'no' );
$run_uninstall();
$check( 'uninstall with the setting off keeps the requests and the settings', null !== WPHEKA_Rfq_Requests::get( $keep_rid ) && false !== get_option( 'wpheka_rfq_general_settings' ) && false !== get_option( 'wpheka_request_for_quote_page_id' ) );

// The "on" case on copies: the real tables are renamed out of the way first.
$sessions = $wpdb->prefix . 'wpheka_rfq_sessions';
$wpdb->query( "CREATE TABLE {$table}_zzcopy LIKE {$table}" );
$wpdb->query( "INSERT INTO {$table}_zzcopy SELECT * FROM {$table}" );
$wpdb->query( "CREATE TABLE {$sessions}_zzcopy LIKE {$sessions}" );
$wpdb->query( "INSERT INTO {$sessions}_zzcopy SELECT * FROM {$sessions}" );
update_option( 'wpheka_rfq_delete_data', 'yes' );
$run_uninstall();
$gone_tables = null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) && null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sessions ) );
$left        = array_filter(
	$names,
	static function ( $name ) {
		return false !== get_option( $name, false );
	}
);
$check( 'uninstall with the setting on drops the tables and deletes every option, the counter and schema version included', $gone_tables && array() === $left, 'left: ' . wp_json_encode( array_values( $left ) ) );
// Put everything back.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
$wpdb->query( "RENAME TABLE {$table}_zzcopy TO {$table}" );
$wpdb->query( "DROP TABLE IF EXISTS {$sessions}" );
$wpdb->query( "RENAME TABLE {$sessions}_zzcopy TO {$sessions}" );
foreach ( $backup as $name => $value ) {
	if ( '__unset__' === $value ) {
		delete_option( $name );
	} else {
		update_option( $name, $value );
	}
}
foreach ( $meta_backup as $meta ) {
	update_user_meta( (int) $meta['user_id'], $meta['meta_key'], maybe_unserialize( $meta['meta_value'] ) );
}
wp_cache_flush();
WPHEKA_Rfq_Requests::delete( $keep_rid );
$check( 'uninstall test put everything back', $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) && get_option( 'wpheka_rfq_general_settings' ) === $backup['wpheka_rfq_general_settings'] );

wp_set_current_user( 0 );
WPHEKA_Rfq_Requests::flush_stats();
$check( 'ran to the end', true );
