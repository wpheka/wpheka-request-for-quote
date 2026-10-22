<?php
/**
 * Stored quote requests: the table, writing a request, the Requests tab's queries,
 * the stats plus WordPress's personal data exporter and eraser.
 *
 * Before 1.9.0 a quote request existed only as the email to the store. From 1.9.0 each
 * request is also stored, once its email has been sent.
 *
 * @package WPHEKA_Rfq
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WPHEKA_Rfq_Requests Class.
 */
class WPHEKA_Rfq_Requests {

	/**
	 * Table name, without the prefix.
	 */
	const TABLE = 'wpheka_rfq_requests';

	/**
	 * Schema version of the table. Raise it when the schema changes.
	 */
	const DB_VERSION = '1';

	/**
	 * Option holding the schema version that has been created.
	 */
	const DB_OPTION = 'wpheka_rfq_requests_db_version';

	/**
	 * Transient caching the stats.
	 */
	const STATS_TRANSIENT = 'wpheka_rfq_request_stats';

	/**
	 * Option for "Delete all data when the plugin is deleted".
	 */
	const DELETE_OPTION = 'wpheka_rfq_delete_data';

	/**
	 * Hook in.
	 */
	public static function init() {
		// Sites that update without reactivating still get the table.
		add_action( 'admin_init', array( __CLASS__, 'maybe_create_table' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Statuses a request can have, with their labels.
	 *
	 * @return array status => label
	 */
	public static function statuses() {
		return array(
			'new'     => __( 'New', 'wpheka-request-for-quote' ),
			'replied' => __( 'Replied', 'wpheka-request-for-quote' ),
			'closed'  => __( 'Closed', 'wpheka-request-for-quote' ),
		);
	}

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create or update the table when its schema version is not the current one.
	 */
	public static function maybe_create_table() {
		if ( self::DB_VERSION !== (string) get_option( self::DB_OPTION ) ) {
			self::create_table();
		}
	}

	/**
	 * Create the table with dbDelta and record the schema version.
	 */
	public static function create_table() {
		global $wpdb;

		$table   = self::table();
		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		// dbDelta needs two spaces before PRIMARY KEY's parenthesis and one column per line.
		$sql = "CREATE TABLE {$table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'new',
  customer_name varchar(200) NOT NULL DEFAULT '',
  customer_email varchar(200) NOT NULL DEFAULT '',
  customer_phone varchar(50) NOT NULL DEFAULT '',
  customer_company varchar(200) NOT NULL DEFAULT '',
  user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  message longtext NOT NULL,
  items longtext NOT NULL,
  currency varchar(10) NOT NULL DEFAULT '',
  total_estimate decimal(19,4) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY created_at (created_at),
  KEY customer_email (customer_email(191))
) {$collate};";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );

		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/**
	 * The items of a quote list, as stored: product id, variation id, name, quantity and the
	 * product's shop price at the time of the request.
	 *
	 * @param array $rfq_data The quote list (each item: product_id, variation_id, quantity).
	 * @return array[]
	 */
	public static function items_from_list( array $rfq_data ) {
		$items = array();
		foreach ( $rfq_data as $item ) {
			if ( ! is_array( $item ) || empty( $item['product_id'] ) ) {
				continue;
			}
			$variation_id = empty( $item['variation_id'] ) ? 0 : absint( $item['variation_id'] );
			$product      = wc_get_product( $variation_id ? $variation_id : absint( $item['product_id'] ) );
			$items[]      = array(
				'product_id'   => absint( $item['product_id'] ),
				'variation_id' => $variation_id,
				'name'         => $product ? wp_strip_all_tags( $product->get_name() ) : '',
				'quantity'     => max( 1, absint( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) ),
				'price'        => $product ? (float) $product->get_price() : 0.0,
			);
		}

		return $items;
	}

	/**
	 * Store a sent quote request.
	 *
	 * Called only after the request's email was sent, so a stored request always matches an
	 * email the store received.
	 *
	 * @param array $customer name, email, phone, company, message.
	 * @param array $rfq_data The quote list that was sent.
	 * @return int The new request's id (0 if nothing was stored).
	 */
	public static function store( array $customer, array $rfq_data ) {
		global $wpdb;

		$items = self::items_from_list( $rfq_data );
		if ( ! $items ) {
			return 0;
		}
		self::maybe_create_table();

		$total = 0.0;
		foreach ( $items as $item ) {
			$total += $item['price'] * $item['quantity'];
		}

		// The form fields have no length limit. wpdb refuses a whole insert when a value is longer
		// than its column, so fit each value to its column; the email already went out.
		$field = static function ( $key, $length ) use ( $customer ) {
			$value = isset( $customer[ $key ] ) ? (string) $customer[ $key ] : '';
			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
		};

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- the plugin's own table.
		$done = $wpdb->insert(
			self::table(),
			array(
				'created_at'       => gmdate( 'Y-m-d H:i:s' ),
				'status'           => 'new',
				'customer_name'    => $field( 'name', 200 ),
				'customer_email'   => $field( 'email', 200 ),
				'customer_phone'   => $field( 'phone', 50 ),
				'customer_company' => $field( 'company', 200 ),
				'user_id'          => get_current_user_id(),
				'message'          => isset( $customer['message'] ) ? (string) $customer['message'] : '',
				'items'            => wp_json_encode( $items ),
				'currency'         => get_woocommerce_currency(),
				'total_estimate'   => round( $total, 4 ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%f' )
		);
		self::flush_stats();

		if ( ! $done && function_exists( 'wc_get_logger' ) ) {
			// The email was sent; say why the copy for the Requests tab is missing.
			wc_get_logger()->error( 'Quote request not stored: ' . $wpdb->last_error, array( 'source' => 'wpheka-request-for-quote' ) );
		}

		return $done ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * One request.
	 *
	 * @param int $id Request id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), absint( $id ) ), ARRAY_A );

		return $row ? $row : null;
	}

	/**
	 * A page of requests, newest first.
	 *
	 * @param string $status   A status ('' for all).
	 * @param int    $page     Page, from 1.
	 * @param int    $per_page Rows per page.
	 * @return array { rows, total }
	 */
	public static function query( $status = '', $page = 1, $per_page = 20 ) {
		global $wpdb;

		$table  = self::table();
		$status = isset( self::statuses()[ $status ] ) ? $status : '';
		$offset = ( max( 1, (int) $page ) - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table.
		if ( '' !== $status ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, $status ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d', $table, $status, (int) $per_page, (int) $offset ), ARRAY_A );
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d', $table, (int) $per_page, (int) $offset ), ARRAY_A );
		}
		// phpcs:enable

		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * How many requests have a status.
	 *
	 * @param string $status Status.
	 * @return int
	 */
	public static function count( $status ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', self::table(), $status ) );
	}

	/**
	 * Change a request's status.
	 *
	 * @param int    $id     Request id.
	 * @param string $status new, replied or closed.
	 * @return bool Whether the request exists and now has that status.
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;

		if ( ! isset( self::statuses()[ $status ] ) || ! self::get( $id ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table.
		$done = false !== $wpdb->update( self::table(), array( 'status' => $status ), array( 'id' => absint( $id ) ), array( '%s' ), array( '%d' ) );
		self::flush_stats();

		return $done;
	}

	/**
	 * Delete a request.
	 *
	 * @param int $id Request id.
	 * @return bool Whether a request was deleted.
	 */
	public static function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table.
		$done = (bool) $wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );
		self::flush_stats();

		return $done;
	}

	/**
	 * The items of a stored request.
	 *
	 * @param array $row A request row.
	 * @return array[]
	 */
	public static function items( array $row ) {
		$items = json_decode( isset( $row['items'] ) ? (string) $row['items'] : '', true );

		return is_array( $items ) ? $items : array();
	}

	/**
	 * Clear the cached stats. Called whenever a request is stored, changed or deleted.
	 */
	public static function flush_stats() {
		delete_transient( self::STATS_TRANSIENT );
	}

	/**
	 * The numbers behind the stats cards, from the stored requests. Cached.
	 *
	 * @return array { stored, products, top_name, top_quantity, average, this_month, last_month, full_month }
	 */
	public static function stats() {
		$cached = get_transient( self::STATS_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = self::table();
		self::maybe_create_table();

		$this_month = gmdate( 'Y-m-01 00:00:00' );
		$last_month = gmdate( 'Y-m-01 00:00:00', strtotime( '-1 month', strtotime( $this_month ) ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table; the result is cached.
		$stored   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		$first    = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(created_at) FROM %i', $table ) );
		$average  = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT AVG(total_estimate) FROM %i WHERE currency = %s', $table, get_woocommerce_currency() ) );
		$now_n    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE created_at >= %s', $table, $this_month ) );
		$last_n   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE created_at >= %s AND created_at < %s', $table, $last_month, $this_month ) );
		$item_set = $wpdb->get_col( $wpdb->prepare( 'SELECT items FROM %i', $table ) );
		// phpcs:enable

		$products = 0;
		$by_item  = array();
		foreach ( (array) $item_set as $json ) {
			foreach ( self::items( array( 'items' => $json ) ) as $item ) {
				$quantity  = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
				$products += $quantity;
				$key       = (int) ( isset( $item['product_id'] ) ? $item['product_id'] : 0 );
				if ( ! isset( $by_item[ $key ] ) ) {
					$by_item[ $key ] = array(
						'quantity' => 0,
						'name'     => isset( $item['name'] ) ? (string) $item['name'] : '',
					);
				}
				$by_item[ $key ]['quantity'] += $quantity;
			}
		}

		$top_name     = '';
		$top_quantity = 0;
		foreach ( $by_item as $product_id => $entry ) {
			if ( $entry['quantity'] > $top_quantity ) {
				$top_quantity = $entry['quantity'];
				$product      = $product_id ? wc_get_product( $product_id ) : null;
				$top_name     = $product ? wp_strip_all_tags( $product->get_name() ) : $entry['name'];
			}
		}

		$stats = array(
			'stored'       => $stored,
			'products'     => $products,
			'top_name'     => $top_name,
			'top_quantity' => $top_quantity,
			'average'      => $stored ? $average : 0.0,
			'this_month'   => $now_n,
			'last_month'   => $last_n,
			// A month comparison needs a whole previous month: the first stored request is
			// from the first moment of last month or earlier.
			'full_month'   => '' !== $first && $first <= $last_month,
		);
		// A day at most, and never past the end of the month, so "vs last month" moves on time.
		$month_ends = strtotime( gmdate( 'Y-m-01 00:00:00', strtotime( '+1 month', strtotime( $this_month ) ) ) . ' UTC' );
		set_transient( self::STATS_TRANSIENT, $stats, max( 60, min( DAY_IN_SECONDS, $month_ends - time() ) ) );

		return $stats;
	}

	/**
	 * Requests stored for an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, from 1.
	 * @param int    $per   Rows per page.
	 * @return array[]
	 */
	private static function rows_for_email( $email, $page, $per ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE customer_email = %s ORDER BY id ASC LIMIT %d OFFSET %d', self::table(), $email, $per, ( max( 1, (int) $page ) - 1 ) * $per ), ARRAY_A );

		return $rows ? $rows : array();
	}

	/**
	 * Register the personal data exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['wpheka-request-for-quote'] = array(
			'exporter_friendly_name' => __( 'Quote requests', 'wpheka-request-for-quote' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Register the personal data eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['wpheka-request-for-quote'] = array(
			'eraser_friendly_name' => __( 'Quote requests', 'wpheka-request-for-quote' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Personal data exporter: every stored request with this email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, from 1.
	 * @return array { data, done }
	 */
	public static function export( $email, $page = 1 ) {
		$per  = 50;
		$rows = self::rows_for_email( $email, $page, $per );
		$data = array();
		foreach ( $rows as $row ) {
			$products = array();
			foreach ( self::items( $row ) as $item ) {
				$products[] = $item['name'] . ' × ' . (int) $item['quantity'];
			}
			$data[] = array(
				'group_id'    => 'wpheka-rfq-requests',
				'group_label' => __( 'Quote requests', 'wpheka-request-for-quote' ),
				'item_id'     => 'wpheka-rfq-request-' . $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Received', 'wpheka-request-for-quote' ),
						'value' => $row['created_at'] . ' UTC',
					),
					array(
						'name'  => __( 'Name', 'wpheka-request-for-quote' ),
						'value' => $row['customer_name'],
					),
					array(
						'name'  => __( 'Email', 'wpheka-request-for-quote' ),
						'value' => $row['customer_email'],
					),
					array(
						'name'  => __( 'Phone', 'wpheka-request-for-quote' ),
						'value' => $row['customer_phone'],
					),
					array(
						'name'  => __( 'Company', 'wpheka-request-for-quote' ),
						'value' => $row['customer_company'],
					),
					array(
						'name'  => __( 'Message', 'wpheka-request-for-quote' ),
						'value' => $row['message'],
					),
					array(
						'name'  => __( 'Products', 'wpheka-request-for-quote' ),
						'value' => implode( ', ', $products ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $rows ) < $per,
		);
	}

	/**
	 * Personal data eraser: delete every stored request with this email address.
	 *
	 * The whole request goes, as it holds nothing that is not about the customer.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, from 1 (ignored: deleted rows leave the result set).
	 * @return array { items_removed, items_retained, messages, done }
	 */
	public static function erase( $email, $page = 1 ) {
		unset( $page );
		$per     = 50;
		$rows    = self::rows_for_email( $email, 1, $per );
		$removed = false;
		foreach ( $rows as $row ) {
			$removed = self::delete( (int) $row['id'] ) || $removed;
		}

		// Rows that could not be deleted would come back on every call: stop and say so.
		$stuck = $rows && ! $removed;

		return array(
			'items_removed'  => $removed,
			'items_retained' => $stuck,
			'messages'       => $stuck ? array( __( 'Some quote requests could not be deleted. Delete them from WPHEKA > Request For Quote > Requests.', 'wpheka-request-for-quote' ) ) : array(),
			'done'           => $stuck || count( $rows ) < $per,
		);
	}

	/**
	 * Suggested text for the site's privacy policy.
	 */
	public static function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p>' . esc_html__( 'When you ask us for a quote, we store your name, email address, phone number and company (when you give them), your message, the products and quantities in your quote list and the date. We use them to answer your request.', 'wpheka-request-for-quote' ) . '</p>'
			. '<p>' . esc_html__( 'We keep a quote request until we delete it. You can ask us for a copy of your requests or ask us to delete them.', 'wpheka-request-for-quote' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Request For Quote', 'wpheka-request-for-quote' ), wp_kses_post( $text ) );
	}
}
