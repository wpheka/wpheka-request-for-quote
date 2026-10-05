<?php
/**
 * Activates the plugin if needed, saves the site's RFQ settings, creates test
 * products (prefixed "ZZ RFQ"), an administrator, and a must-use plugin that
 * captures outgoing mail. cleanup.php puts everything back.
 */
require __DIR__ . '/lib.php';
global $wpdb;
$ids = array(
	'was_active'  => is_plugin_active( 'wpheka-request-for-quote/wpheka-request-for-quote.php' ),
	'settings'    => get_option( 'wpheka_rfq_general_settings', '__unset__' ),
	'quote_count' => get_option( 'wpheka_rfq_quote_count', '__unset__' ),
	'max_session' => (int) $wpdb->get_var( "SELECT MAX(session_id) FROM {$wpdb->prefix}wpheka_rfq_sessions" ),
);
if ( ! $ids['was_active'] ) {
	activate_plugin( 'wpheka-request-for-quote/wpheka-request-for-quote.php' );
}
$page_id = (int) get_option( 'wpheka_request_for_quote_page_id' );
if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
	echo "FATAL: no published quote page (wpheka_request_for_quote_page_id)\n";
	exit( 1 );
}
$ids['quote_page'] = get_permalink( $page_id );

$simple = function ( $name, $stock ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_regular_price( '19.99' );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( $stock );
	$p->set_backorders( 'no' );
	$p->set_status( 'publish' );
	return $p->save();
};
$ids['in']  = $simple( 'ZZ RFQ In Stock', 10 );
$ids['out'] = $simple( 'ZZ RFQ Out Of Stock', 0 );
$ids['in_url']  = get_permalink( $ids['in'] );
$ids['out_url'] = get_permalink( $ids['out'] );
$ids['shop_url'] = add_query_arg( 's', 'ZZ RFQ', home_url( '/' ) ) . '&post_type=product';

$uid = wp_insert_user( array( 'user_login' => 'zz_rfq_admin', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'zz-rfq-admin@example.invalid', 'role' => 'administrator' ) );
$exp = time() + 2 * HOUR_IN_SECONDS;
$ids['admin']   = $uid;
$ids['cookies'] = array(
	array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'auth' ), 'domain' => 'localhost', 'path' => wp_parse_url( admin_url(), PHP_URL_PATH ) ),
	array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'logged_in' ), 'domain' => 'localhost', 'path' => wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ),
);
$ids['admin_url']   = admin_url();
$ids['admin_email'] = get_option( 'admin_email' );

$mu = WP_CONTENT_DIR . '/mu-plugins/zz-rfq-functional-mail.php';
file_put_contents( $mu, '<?php
// Written by wpheka-request-for-quote/tests/functional; deleted when the suite ends.
add_filter( "pre_wp_mail", function ( $null, $atts ) {
	file_put_contents( ' . var_export( rfq_t_state( 'mail.log' ), true ) . ', wp_json_encode( array( "to" => $atts["to"], "subject" => $atts["subject"], "message" => $atts["message"] ) ) . "\n", FILE_APPEND );
	return true;
}, 10, 2 );
' );
$ids['mu'] = $mu;

// Lets the suite view product pages under Storefront, the standard WooCommerce
// theme, as well as the site's own theme -- for its own requests only.
$theme_mu = WP_CONTENT_DIR . '/mu-plugins/zz-rfq-functional-theme.php';
file_put_contents( $theme_mu, '<?php
// Written by wpheka-request-for-quote/tests/functional; deleted when the suite ends.
if ( isset( $_COOKIE["zz_rfq_theme"] ) && "storefront" === $_COOKIE["zz_rfq_theme"] ) {
	add_filter( "stylesheet", function () { return "storefront"; } );
	add_filter( "template", function () { return "storefront"; } );
}
' );
$ids['theme_mu']   = $theme_mu;
$ids['site_theme'] = get_stylesheet();
$ids['themes']     = array_values( array_unique( array_filter( array( get_stylesheet(), wp_get_theme( 'storefront' )->exists() ? 'storefront' : '' ) ) ) );
rfq_t_save_ids( $ids );
echo "created products and an administrator; plugin was ", $ids['was_active'] ? 'active' : 'inactive (activated for the suite)', "\n";
