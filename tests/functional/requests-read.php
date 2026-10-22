<?php
// requests-read.php [seed N | clear | others] -- the requests the suite stored (id above the one at setup), newest
// first, as JSON. "seed N" first stores N requests for the Requests tab cases.
require __DIR__ . '/lib.php';
global $wpdb;
$ids = rfq_t_ids();
if ( 'clear' === ( $args[0] ?? '' ) ) {
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . WPHEKA_Rfq_Requests::table() . ' WHERE id > %d', (int) $ids['max_request'] ) );
	WPHEKA_Rfq_Requests::flush_stats();
}
if ( 'others' === ( $args[0] ?? '' ) ) {
	echo (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . WPHEKA_Rfq_Requests::table() . ' WHERE id <= %d', (int) $ids['max_request'] ) );
	return;
}
if ( 'seed' === ( $args[0] ?? '' ) ) {
	for ( $i = 1; $i <= (int) ( $args[1] ?? 1 ); $i++ ) {
		WPHEKA_Rfq_Requests::store(
			array( 'name' => 'Zz Seed ' . $i, 'email' => 'zz-rfq-seed' . $i . '@example.invalid', 'company' => 'Zz Seed Co', 'message' => 'Seeded request ' . $i ),
			array( array( 'product_id' => $ids['in'], 'quantity' => $i ) )
		);
	}
}
echo wp_json_encode( $wpdb->get_results( $wpdb->prepare( 'SELECT id, status, customer_name, customer_email, items, total_estimate FROM ' . WPHEKA_Rfq_Requests::table() . ' WHERE id > %d ORDER BY id DESC', (int) $ids['max_request'] ), ARRAY_A ) );
