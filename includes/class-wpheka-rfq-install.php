<?php
/**
 * Installation related functions and actions.
 *
 * @package WPHEKA_Rfq
 * @version 1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WPHEKA_Rfq Class.
 */
class WPHEKA_Rfq_Install {


	/**
	 * Install WPHEKA_Rfq.
	 */
	public static function install() {
		// Determines whether WordPress is already installed.
		if ( ! is_blog_installed() ) {
			return;
		}

		// Check if we are not already running this routine.
		if ( 'yes' === get_transient( 'wpheka_rfq_installing' ) ) {
			return;
		}

		// If we made it till here nothing is running yet, lets set the transient now.
		set_transient( 'wpheka_rfq_installing', 'yes', MINUTE_IN_SECONDS * 10 );
		wc_maybe_define_constant( 'WPHEKA_RFQ_INSTALLING', true );

		self::create_tables();
		self::create_options();
		self::create_pages();

		delete_transient( 'wpheka_rfq_installing' );

		flush_rewrite_rules();
		do_action( 'wpheka_rfq_installed' );
	}

	/**
	 * Set up the database tables which the plugin needs to function.
	 *
	 * Tables:
	 *      wpheka_rfq_sessions - Plugin sessions table.
	 */
	private static function create_tables() {
		global $wpdb;

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$tables = "
CREATE TABLE {$wpdb->prefix}wpheka_rfq_sessions (
  session_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_key char(32) NOT NULL,
  session_value longtext NOT NULL,
  session_expiry BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY  (session_id),
  UNIQUE KEY session_key (session_key)
) $collate;
		";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		}

		dbDelta( $tables );
	}

	/**
	 * Default options.
	 *
	 * Sets up the default options used on the settings page.
	 */
	private static function create_options() {
		// Include settings so that we can run through defaults.

		$general_tab_settings = get_option( 'wpheka_rfq_general_settings' );

		if ( empty( $general_tab_settings ) ) {
			$general_settings                          = array();
			$general_settings['button_type']           = 'button';
			$general_settings['button_link_text']      = 'Add to quote';
			$general_settings['hide_add_to_cart']      = 'no';
			$general_settings['hide_price']            = 'no';
			$general_settings['button_in_other_pages'] = 'no';

			add_option( 'wpheka_rfq_general_settings', $general_settings, '', 'yes' );
		}
	}

	/**
	 * Create pages that the plugin relies on, storing page IDs in variables.
	 */
	private static function create_pages() {

		$pages = apply_filters(
			'wpheka_rfq_create_pages',
			array(
				'request_for_quote' => array(
					'name'    => _x( 'request-quote', 'Page slug', 'wpheka-request-for-quote' ),
					'title'   => _x( 'Request A Quote', 'Page title', 'wpheka-request-for-quote' ),
					'content' => '<!-- wp:shortcode -->[' . apply_filters( 'wpheka_rfq_shortcode_tag', 'wpheka_request_for_quote' ) . ']<!-- /wp:shortcode -->',
				),
			)
		);

		foreach ( $pages as $key => $page ) {
			self::wpheka_create_page( esc_sql( $page['name'] ), 'wpheka_' . $key . '_page_id', $page['title'], $page['content'], '' );
		}
	}

	/**
	 * Create a page and store the ID in an option.
	 *
	 * @param mixed  $slug Slug for the new page.
	 * @param string $option Option name to store the page's ID.
	 * @param string $page_title (default: '') Title for the new page.
	 * @param string $page_content (default: '') Content for the new page.
	 * @param int    $post_parent (default: 0) Parent for the new page.
	 * @return int page ID.
	 */
	private static function wpheka_create_page( $slug, $option = '', $page_title = '', $page_content = '', $post_parent = 0 ) {
		global $wpdb;

		$option_value = get_option( $option );

		if ( $option_value > 0 ) {
			$page_object = get_post( $option_value );

			if ( $page_object && 'page' === $page_object->post_type && ! in_array( $page_object->post_status, array( 'pending', 'trash', 'future', 'auto-draft' ), true ) ) {
				// Valid page is already in place.
				return $page_object->ID;
			}
		}

		if ( strlen( $page_content ) > 0 ) {
			// Search for an existing page with the specified page content (typically a shortcode).
			$shortcode        = str_replace( array( '<!-- wp:shortcode -->', '<!-- /wp:shortcode -->' ), '', $page_content );
			$valid_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' ) AND post_content LIKE %s LIMIT 1;", "%{$shortcode}%" ) );
		} else {
			// Search for an existing page with the specified page slug.
			$valid_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' )  AND post_name = %s LIMIT 1;", $slug ) );
		}

		$valid_page_found = apply_filters( 'wpheka_rfq_create_page_id', $valid_page_found, $slug, $page_content );

		if ( $valid_page_found ) {
			if ( $option ) {
				update_option( $option, $valid_page_found );
			}
			return $valid_page_found;
		}

		// Search for a matching valid trashed page.
		if ( strlen( $page_content ) > 0 ) {
			// Search for an existing page with the specified page content (typically a shortcode).
			$trashed_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_content LIKE %s LIMIT 1;", "%{$page_content}%" ) );
		} else {
			// Search for an existing page with the specified page slug.
			$trashed_page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_name = %s LIMIT 1;", $slug ) );
		}

		if ( $trashed_page_found ) {
			$page_id   = $trashed_page_found;
			$page_data = array(
				'ID'          => $page_id,
				'post_status' => 'publish',
			);
			wp_update_post( $page_data );
		} else {
			$page_data = array(
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_author'    => 1,
				'post_name'      => $slug,
				'post_title'     => $page_title,
				'post_content'   => $page_content,
				'post_parent'    => $post_parent,
				'comment_status' => 'closed',
			);
			$page_id   = wp_insert_post( $page_data );
		}

		if ( $option ) {
			update_option( $option, $page_id );
		}

		return $page_id;
	}
}
