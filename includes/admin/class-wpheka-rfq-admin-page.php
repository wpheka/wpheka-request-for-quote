<?php
/**
 * WPHEKA > Request For Quote: the plugin's page, with Settings, Requests and Help tabs.
 *
 * Replaces the settings screen before 1.9.0, which saved by AJAX and could show
 * "Changes Updated" after a save that failed (CodeRabbit RFQ finding 3). The form now
 * posts to admin-post.php; the server decides what the next page says.
 *
 * @package WPHEKA_Rfq
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

use WPHEKA_Request_For_Quote\AdminUI\Fields;
use WPHEKA_Request_For_Quote\AdminUI\Icons;
use WPHEKA_Request_For_Quote\AdminUI\Menu;
use WPHEKA_Request_For_Quote\AdminUI\Page;

/**
 * WPHEKA_Rfq_Admin_Page Class.
 */
class WPHEKA_Rfq_Admin_Page {

	/**
	 * Page slug (the same as before 1.9.0, so links and bookmarks keep working).
	 */
	const PAGE = 'wpheka_request_for_quote';

	/**
	 * The admin-post action that saves the settings.
	 */
	const SAVE_ACTION = 'wpheka_rfq_save_settings';

	/**
	 * The admin-post action that changes a request's status.
	 */
	const STATUS_ACTION = 'wpheka_rfq_request_status';

	/**
	 * The admin-post action that deletes a request.
	 */
	const DELETE_ACTION = 'wpheka_rfq_request_delete';

	/**
	 * The wordpress.org slug.
	 */
	const SLUG = 'wpheka-request-for-quote';

	/**
	 * Requests per page on the Requests tab.
	 */
	const PER_PAGE = 20;

	/**
	 * The page hook WordPress returned, for loading assets on this page only.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::STATUS_ACTION, array( __CLASS__, 'handle_status' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * Capability for the page, its save and the request actions. Shop managers have it.
	 *
	 * @return string
	 */
	public static function capability() {
		return 'manage_woocommerce';
	}

	/**
	 * URL of a tab.
	 *
	 * @param string $tab  settings, requests or help.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $tab = 'settings', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short query args.
	}

	/**
	 * Register the page under the shared WPHEKA menu.
	 */
	public static function register_page() {
		$hook = Menu::add_page( __( 'Request For Quote', 'wpheka-request-for-quote' ), __( 'Request For Quote', 'wpheka-request-for-quote' ), self::capability(), self::PAGE, array( __CLASS__, 'render' ), Icons::menu_icon() );

		self::$hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Load the kit on this page only.
	 *
	 * @param string $hook Page hook.
	 */
	public static function enqueue( $hook ) {
		if ( '' === self::$hook || self::$hook !== $hook ) {
			return;
		}
		Page::enqueue( 'wpheka-request-for-quote', plugins_url( 'assets/wpheka-ui/', WPHEKA_RFQ_PLUGIN_FILE ) );
	}

	/**
	 * Stop with a 403 unless the user may use this page.
	 */
	private static function require_capability() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'wpheka-request-for-quote' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * The settings as stored.
	 *
	 * @return array
	 */
	public static function settings() {
		$settings = function_exists( 'wpheka_rfq_framework_ready' ) && wpheka_rfq_framework_ready() ? wpheka_rfq_options()->all() : get_option( 'wpheka_rfq_general_settings', array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * A stored setting. When it is missing: the value the front end uses.
	 *
	 * @param string $key      Key.
	 * @param string $fallback What the front end does when the key is missing.
	 * @return string
	 */
	private static function value( $key, $fallback ) {
		$settings = self::settings();

		return isset( $settings[ $key ] ) && false !== $settings[ $key ] ? (string) $settings[ $key ] : $fallback;
	}

	/**
	 * The choices a key may take, with the fallback first.
	 *
	 * @return array[] key => allowed values
	 */
	private static function choices() {
		return array(
			'user_type'           => array( 'all', 'logged', 'guests' ),
			'out_of_stock_option' => array( 'show_all', 'only_out_of_stock', 'hide_out_of_stock' ),
			'button_position'     => array( 'after', 'before' ),
			'button_type'         => array( 'button', 'link' ),
			'after_click_action'  => array( 'show_link', 'redirect' ),
		);
	}

	/**
	 * Turn the posted form into the stored settings: the same keys and fallbacks as the
	 * settings screen before 1.9.0, with each choice kept to its allowed values.
	 *
	 * @param array $raw     Unslashed `wpheka_rfq_general_settings[...]` values.
	 * @param int   $page_id The quote page.
	 * @return array
	 */
	public static function sanitize( array $raw, $page_id ) {
		$settings = array( 'wpheka_request_for_quote_page_id' => (string) $page_id );

		foreach ( self::choices() as $key => $allowed ) {
			$value            = isset( $raw[ $key ] ) ? sanitize_key( (string) $raw[ $key ] ) : '';
			$settings[ $key ] = in_array( $value, $allowed, true ) ? $value : $allowed[0];
		}
		foreach ( array( 'hide_price', 'hide_add_to_cart', 'button_in_other_pages', 'show_phone_field', 'phone_required', 'show_company_field' ) as $key ) {
			$settings[ $key ] = isset( $raw[ $key ] ) && 'yes' === $raw[ $key ] ? 'yes' : 'no';
		}
		// A required phone number makes no sense without the phone field.
		if ( 'no' === $settings['show_phone_field'] ) {
			$settings['phone_required'] = 'no';
		}
		$text                         = isset( $raw['button_link_text'] ) ? sanitize_text_field( (string) $raw['button_link_text'] ) : '';
		$settings['button_link_text'] = '' === $text ? __( 'Add to quote', 'wpheka-request-for-quote' ) : $text;

		return $settings;
	}

	/**
	 * Save the settings form (admin-post).
	 */
	public static function handle_save() {
		check_admin_referer( self::SAVE_ACTION );
		self::require_capability();

		$post    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is sanitised in sanitize().
		$page_id = isset( $post['wpheka_request_for_quote_page_id'] ) ? absint( $post['wpheka_request_for_quote_page_id'] ) : 0;

		// No quote page: save nothing and say why, as the screen before 1.9.0 did.
		if ( ! $page_id ) {
			wp_safe_redirect( self::url( 'settings', array( 'w-error' => 'page' ) ) );
			exit;
		}

		$raw      = isset( $post['wpheka_rfq_general_settings'] ) && is_array( $post['wpheka_rfq_general_settings'] ) ? $post['wpheka_rfq_general_settings'] : array();
		$settings = self::sanitize( $raw, $page_id );

		// Merged into the stored array, so a key another plugin added survives.
		if ( function_exists( 'wpheka_rfq_framework_ready' ) && wpheka_rfq_framework_ready() ) {
			wpheka_rfq_options()->update( $settings );
		} else {
			update_option( 'wpheka_rfq_general_settings', array_merge( self::settings(), $settings ) );
		}
		update_option( 'wpheka_request_for_quote_page_id', (string) $page_id );
		update_option( WPHEKA_Rfq_Requests::DELETE_OPTION, isset( $post[ WPHEKA_Rfq_Requests::DELETE_OPTION ] ) && 'yes' === $post[ WPHEKA_Rfq_Requests::DELETE_OPTION ] ? 'yes' : 'no' );

		// Every setting changes product, shop or quote page markup held in page caches.
		if ( class_exists( 'WPHEKA_Rfq_Cache' ) ) {
			WPHEKA_Rfq_Cache::purge_all();
		}

		wp_safe_redirect( self::url( 'settings', array( 'w-saved' => 1 ) ) );
		exit;
	}

	/**
	 * Change a request's status (admin-post).
	 */
	public static function handle_status() {
		$id = isset( $_POST['request_id'] ) ? absint( wp_unslash( $_POST['request_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line, per request.
		check_admin_referer( self::STATUS_ACTION . '_' . $id );
		self::require_capability();

		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$done   = WPHEKA_Rfq_Requests::set_status( $id, $status );

		wp_safe_redirect( self::url( 'requests', array( 'rfq_notice' => $done ? 'status' : 'error' ) ) );
		exit;
	}

	/**
	 * Delete a request (admin-post).
	 */
	public static function handle_delete() {
		$id = isset( $_POST['request_id'] ) ? absint( wp_unslash( $_POST['request_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line, per request.
		check_admin_referer( self::DELETE_ACTION . '_' . $id );
		self::require_capability();

		$done = WPHEKA_Rfq_Requests::delete( $id );

		wp_safe_redirect( self::url( 'requests', array( 'rfq_notice' => $done ? 'deleted' : 'error' ) ) );
		exit;
	}

	/**
	 * Links for the header, rail and Help tab.
	 *
	 * @return array
	 */
	private static function links() {
		return array(
			'docs'    => 'https://www.wpheka.com/product/request-for-quote/',
			'support' => 'https://wordpress.org/support/plugin/' . self::SLUG . '/',
			'changes' => 'https://wordpress.org/plugins/' . self::SLUG . '/#developers',
			'review'  => 'https://wordpress.org/support/plugin/' . self::SLUG . '/reviews/#new-post',
			'contact' => 'https://www.wpheka.com/contact/',
		);
	}

	/**
	 * The tab being shown.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab to show.

		return in_array( $tab, array( 'requests', 'help' ), true ) ? $tab : 'settings';
	}

	/**
	 * The page.
	 */
	public static function render() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		$tab   = self::current_tab();
		$links = self::links();
		$new   = WPHEKA_Rfq_Requests::count( 'new' );

		Page::open(
			array(
				'icon'     => plugins_url( 'assets/wpheka-ui/icons/' . self::SLUG . '.png', WPHEKA_RFQ_PLUGIN_FILE ),
				'name'     => __( 'Request For Quote', 'wpheka-request-for-quote' ),
				'version'  => WPHEKA_RFQ_VERSION,
				'badges'   => array( array( __( 'Free', 'wpheka-request-for-quote' ), 'mint' ) ),
				'subtitle' => __( 'Let customers build a quote list and ask for your price.', 'wpheka-request-for-quote' ),
				'crumb'    => __( 'Request For Quote', 'wpheka-request-for-quote' ),
				'links'    => array(
					array( __( 'Docs', 'wpheka-request-for-quote' ), $links['docs'], 'book' ),
					array( __( 'Support', 'wpheka-request-for-quote' ), $links['support'], 'chat' ),
					array( __( "What's new", 'wpheka-request-for-quote' ), $links['changes'], 'sparkle' ),
				),
				'cta'      => array( __( 'Rate on WordPress.org', 'wpheka-request-for-quote' ), $links['review'], 'star', 'ghost' ),
				'tabs'     => array(
					array( __( 'Settings', 'wpheka-request-for-quote' ), self::url( 'settings' ), 'settings' === $tab ),
					array( __( 'Requests', 'wpheka-request-for-quote' ), self::url( 'requests' ), 'requests' === $tab, $new, $new > 0 ),
					array( __( 'Help', 'wpheka-request-for-quote' ), self::url( 'help' ), 'help' === $tab ),
				),
			)
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display flags after our own redirects.
		$error = 'settings' === $tab && isset( $_GET['w-error'] ) && 'page' === sanitize_key( wp_unslash( $_GET['w-error'] ) )
			? __( 'Request for quote page is not set. Choose the page that holds your quote list, then save again.', 'wpheka-request-for-quote' )
			: '';
		$saved = 'settings' === $tab && isset( $_GET['w-saved'] ) && '' === $error;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'help' === $tab ) {
			self::render_help( $links );
		} else {
			self::render_stats();
			if ( 'requests' === $tab ) {
				self::render_requests();
			} else {
				self::render_settings( $links );
			}
		}

		Page::close(
			array(
				'slug'       => self::SLUG,
				'icons_url'  => plugins_url( 'assets/wpheka-ui/icons/', WPHEKA_RFQ_PLUGIN_FILE ),
				'short_name' => __( 'Request For Quote', 'wpheka-request-for-quote' ),
				'review_url' => $links['review'],
				'form'       => 'settings' === $tab ? 'rfq-settings' : '',
				'saved'      => $saved,
				'error'      => $error,
			)
		);
	}

	/**
	 * A money amount as plain text in the store currency.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function money( $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * The stats strip: the all-time counter plus numbers from the stored requests.
	 */
	private static function render_stats() {
		$stats = WPHEKA_Rfq_Requests::stats();
		$since = __( 'Counted from version 1.9.0', 'wpheka-request-for-quote' );
		$none  = __( 'None yet', 'wpheka-request-for-quote' );

		$requests_meta = __( 'All time', 'wpheka-request-for-quote' );
		$up            = false;
		if ( $stats['full_month'] ) {
			$diff = (int) $stats['this_month'] - (int) $stats['last_month'];
			$up   = $diff > 0;
			/* translators: %s: difference in requests, with a sign, for example +3 or -2. */
			$requests_meta = sprintf( __( '%s vs last month', 'wpheka-request-for-quote' ), ( $diff > 0 ? '+' : '' ) . number_format_i18n( $diff ) );
		}

		Page::stats(
			array(
				array(
					'label' => __( 'Quote requests', 'wpheka-request-for-quote' ),
					'icon'  => 'form',
					'value' => number_format_i18n( (int) get_option( 'wpheka_rfq_quote_count', 0 ) ),
					'meta'  => $requests_meta,
					'up'    => $up,
				),
				array(
					'label' => __( 'Products requested', 'wpheka-request-for-quote' ),
					'icon'  => 'box',
					'value' => $stats['stored'] ? number_format_i18n( (int) $stats['products'] ) : $none,
					'meta'  => $stats['stored'] ? __( 'Across all requests', 'wpheka-request-for-quote' ) : $since,
				),
				array(
					'label' => __( 'Most requested', 'wpheka-request-for-quote' ),
					'icon'  => 'star',
					'mint'  => true,
					'value' => $stats['stored'] && '' !== $stats['top_name'] ? esc_html( $stats['top_name'] ) : $none,
					/* translators: %s: number of units requested. */
					'meta'  => $stats['stored'] ? sprintf( _n( '%s requested', '%s requested', (int) $stats['top_quantity'], 'wpheka-request-for-quote' ), number_format_i18n( (int) $stats['top_quantity'] ) ) : $since,
				),
				array(
					'label' => __( 'Average request', 'wpheka-request-for-quote' ),
					'icon'  => 'chart',
					'value' => $stats['stored'] ? esc_html( self::money( (float) $stats['average'] ) ) : $none,
					'meta'  => $stats['stored'] ? __( 'Estimated at list price', 'wpheka-request-for-quote' ) : $since,
				),
			)
		);
	}

	/**
	 * The Settings tab.
	 *
	 * @param array $links Help links.
	 */
	private static function render_settings( array $links ) {
		$nav = array(
			array( 'quote-button', 'cursor', __( 'Quote button', 'wpheka-request-for-quote' ) ),
			array( 'product-pages', 'tag', __( 'Product pages', 'wpheka-request-for-quote' ) ),
			array( 'quote-list', 'list', __( 'Quote list', 'wpheka-request-for-quote' ) ),
			array( 'request-form', 'form', __( 'Request form', 'wpheka-request-for-quote' ) ),
			array( 'emails', 'mail', __( 'Emails', 'wpheka-request-for-quote' ) ),
			array( 'advanced', 'sliders', __( 'Advanced', 'wpheka-request-for-quote' ) ),
		);

		echo '<div class="w-stack"><div class="w-layout"><div>';
		Page::nav( $nav );
		echo '</div><form id="rfq-settings" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-w-form>';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '">';
		wp_nonce_field( self::SAVE_ACTION );
		self::render_fields();
		// Without JavaScript the save bar never appears, so the form keeps a plain button.
		echo '<noscript><p><button type="submit" class="w-btn w-btn--primary">' . esc_html__( 'Save changes', 'wpheka-request-for-quote' ) . '</button></p></noscript>';
		echo '</form><aside class="w-rail" aria-label="' . esc_attr__( 'Review and help', 'wpheka-request-for-quote' ) . '">';
		Page::review_card( __( 'Enjoying Request For Quote?', 'wpheka-request-for-quote' ), __( 'A quick review helps other store owners find it and keeps it improving.', 'wpheka-request-for-quote' ), $links['review'] );
		Page::help_card(
			array(
				array( __( 'Read the documentation', 'wpheka-request-for-quote' ), $links['docs'], 'book' ),
				array( __( 'Ask in the support forum', 'wpheka-request-for-quote' ), $links['support'], 'chat' ),
				array( __( 'Contact WPHEKA', 'wpheka-request-for-quote' ), $links['contact'], 'mail' ),
			)
		);
		echo '</aside></div></div>';
	}

	/**
	 * Pages to choose the quote list page from.
	 *
	 * @return array id => title
	 */
	private static function page_options() {
		$options = array( '' => __( 'Choose a page', 'wpheka-request-for-quote' ) );
		// Published pages only: customers cannot open a draft or private page. A page chosen
		// before it was unpublished stays in the list, so saving other settings keeps it.
		$pages   = get_pages( array( 'post_status' => 'publish' ) );
		$current = absint( get_option( 'wpheka_request_for_quote_page_id' ) );
		if ( $current && ! in_array( $current, wp_list_pluck( $pages, 'ID' ), true ) && get_post( $current ) ) {
			$pages[] = get_post( $current );
		}
		foreach ( $pages as $page ) {
			$options[ $page->ID ] = '' === $page->post_title ? '#' . $page->ID : $page->post_title;
		}

		return $options;
	}

	/**
	 * Print the settings sections.
	 */
	private static function render_fields() {
		$field = static function ( $key ) {
			return 'wpheka_rfq_general_settings[' . $key . ']';
		};

		Fields::section_start( 'quote-button', __( 'Quote button', 'wpheka-request-for-quote' ), __( 'Where the "Add to quote" button appears and who sees it.', 'wpheka-request-for-quote' ) );
		Fields::segmented_row(
			array(
				'name'    => $field( 'user_type' ),
				'value'   => self::value( 'user_type', 'all' ),
				'label'   => __( 'Show the button to', 'wpheka-request-for-quote' ),
				'options' => array(
					'all'    => __( 'All users', 'wpheka-request-for-quote' ),
					'logged' => __( 'Logged-in users', 'wpheka-request-for-quote' ),
					'guests' => __( 'Guests', 'wpheka-request-for-quote' ),
				),
			)
		);
		Fields::segmented_row(
			array(
				'name'    => $field( 'out_of_stock_option' ),
				'value'   => self::value( 'out_of_stock_option', 'show_all' ),
				'label'   => __( 'Show the button on', 'wpheka-request-for-quote' ),
				'help'    => __( 'Every product, only products that are out of stock (to ask about them) or only products in stock.', 'wpheka-request-for-quote' ),
				'stack'   => true,
				'options' => array(
					'show_all'          => __( 'Every product', 'wpheka-request-for-quote' ),
					'only_out_of_stock' => __( 'Out of stock only', 'wpheka-request-for-quote' ),
					'hide_out_of_stock' => __( 'In stock only', 'wpheka-request-for-quote' ),
				),
			)
		);
		Fields::segmented_row(
			array(
				'name'    => $field( 'button_position' ),
				'value'   => self::value( 'button_position', 'after' ),
				'label'   => __( 'Position on the product page', 'wpheka-request-for-quote' ),
				'options' => array(
					'before' => __( 'Before Add to cart', 'wpheka-request-for-quote' ),
					'after'  => __( 'After Add to cart', 'wpheka-request-for-quote' ),
				),
			)
		);
		Fields::segmented_row(
			array(
				'name'    => $field( 'button_type' ),
				'value'   => self::value( 'button_type', 'button' ),
				'label'   => __( 'Style', 'wpheka-request-for-quote' ),
				'options' => array(
					'button' => __( 'Button', 'wpheka-request-for-quote' ),
					'link'   => __( 'Link', 'wpheka-request-for-quote' ),
				),
			)
		);
		Fields::text_row(
			array(
				'name'        => $field( 'button_link_text' ),
				'value'       => self::value( 'button_link_text', __( 'Add to quote', 'wpheka-request-for-quote' ) ),
				'label'       => __( 'Button text', 'wpheka-request-for-quote' ),
				'help'        => __( 'Left empty, the button says "Add to quote".', 'wpheka-request-for-quote' ),
				'placeholder' => __( 'Add to quote', 'wpheka-request-for-quote' ),
			)
		);
		Fields::switch_row(
			array(
				'name'    => $field( 'button_in_other_pages' ),
				'checked' => 'yes' === self::value( 'button_in_other_pages', 'no' ),
				'label'   => __( 'Show on shop and category pages', 'wpheka-request-for-quote' ),
				'help'    => __( 'Adds the button under each product in product lists.', 'wpheka-request-for-quote' ),
			)
		);
		Fields::section_end();

		Fields::section_start( 'product-pages', __( 'Product pages', 'wpheka-request-for-quote' ), __( 'Turn your store into a catalogue when prices are agreed per customer.', 'wpheka-request-for-quote' ) );
		Fields::switch_row(
			array(
				'name'    => $field( 'hide_price' ),
				'checked' => 'yes' === self::value( 'hide_price', 'no' ),
				'label'   => __( 'Hide prices', 'wpheka-request-for-quote' ),
				'help'    => __( 'Removes prices from the shop, product pages, the quote list and the quote email.', 'wpheka-request-for-quote' ),
			)
		);
		Fields::switch_row(
			array(
				'name'    => $field( 'hide_add_to_cart' ),
				'checked' => 'yes' === self::value( 'hide_add_to_cart', 'no' ),
				'label'   => __( 'Hide the Add to cart button', 'wpheka-request-for-quote' ),
				'help'    => __( 'Customers can only ask for a quote.', 'wpheka-request-for-quote' ),
			)
		);
		Fields::section_end();

		$page_id = (int) get_option( 'wpheka_request_for_quote_page_id' );
		$view    = $page_id ? get_permalink( $page_id ) : '';
		Fields::section_start( 'quote-list', __( 'Quote list', 'wpheka-request-for-quote' ) );
		Fields::select_row(
			array(
				'name'    => 'wpheka_request_for_quote_page_id',
				'value'   => $page_id ? (string) $page_id : '',
				'label'   => __( 'Quote list page', 'wpheka-request-for-quote' ),
				'help'    => __( 'The page with the [wpheka_request_for_quote] shortcode.', 'wpheka-request-for-quote' ) . ( $view ? ' <a href="' . esc_url( $view ) . '" target="_blank" rel="noopener">' . esc_html__( 'View page', 'wpheka-request-for-quote' ) . '</a>' : '' ),
				'options' => self::page_options(),
			)
		);
		Fields::segmented_row(
			array(
				'name'    => $field( 'after_click_action' ),
				'value'   => self::value( 'after_click_action', 'show_link' ),
				'label'   => __( 'After "Add to quote"', 'wpheka-request-for-quote' ),
				'options' => array(
					'show_link' => __( 'Show a link', 'wpheka-request-for-quote' ),
					'redirect'  => __( 'Go to the quote list', 'wpheka-request-for-quote' ),
				),
			)
		);
		Fields::section_end();

		Fields::section_start( 'request-form', __( 'Request form', 'wpheka-request-for-quote' ), __( 'Extra fields on the form, after name and email.', 'wpheka-request-for-quote' ) );
		Fields::switch_row(
			array(
				'name'     => $field( 'show_phone_field' ),
				'checked'  => 'yes' === self::value( 'show_phone_field', 'no' ),
				'label'    => __( 'Phone number field', 'wpheka-request-for-quote' ),
				'controls' => 'rfq-phone-rows',
			)
		);
		Fields::sub_start( 'rfq-phone-rows' );
		Fields::switch_row(
			array(
				'name'    => $field( 'phone_required' ),
				'checked' => 'yes' === self::value( 'phone_required', 'no' ),
				'label'   => __( 'Make the phone number required', 'wpheka-request-for-quote' ),
				'help'    => __( 'Customers must fill it in to send the request.', 'wpheka-request-for-quote' ),
			)
		);
		Fields::sub_end();
		Fields::switch_row(
			array(
				'name'    => $field( 'show_company_field' ),
				'checked' => 'yes' === self::value( 'show_company_field', 'no' ),
				'label'   => __( 'Company name field', 'wpheka-request-for-quote' ),
			)
		);
		Fields::section_end();

		Fields::section_start( 'emails', __( 'Emails', 'wpheka-request-for-quote' ), __( 'Who gets new quote requests and what the email says.', 'wpheka-request-for-quote' ) );
		self::render_email_row();
		Fields::section_end();

		Fields::section_start( 'advanced', __( 'Advanced', 'wpheka-request-for-quote' ) );
		Fields::switch_row(
			array(
				'name'    => WPHEKA_Rfq_Requests::DELETE_OPTION,
				'checked' => 'yes' === get_option( WPHEKA_Rfq_Requests::DELETE_OPTION, 'no' ),
				'label'   => __( 'Delete all data when the plugin is deleted', 'wpheka-request-for-quote' ),
				'help'    => __( 'Removes the stored quote requests and the plugin settings when you delete the plugin. Left off, deleting and reinstalling keeps everything.', 'wpheka-request-for-quote' ),
			)
		);
		Fields::section_end();

		Fields::find_empty();
	}

	/**
	 * The quote email as a status row: on or off, the recipient and a link to edit it.
	 * No switch here, so it cannot be turned off from this page by accident.
	 */
	private static function render_email_row() {
		$mailer = function_exists( 'WC' ) ? WC()->mailer() : null;
		$email  = $mailer && isset( $mailer->emails['WPHEKA_Rfq_Mail'] ) ? $mailer->emails['WPHEKA_Rfq_Mail'] : null;
		$edit   = admin_url( 'admin.php?page=wc-settings&tab=email&section=wpheka_rfq_mail' );
		$on     = $email && $email->is_enabled();
		$to     = $email ? (string) $email->get_recipient() : '';

		$help = $on
			/* translators: %s: email address. */
			? sprintf( esc_html__( 'Sent to %s.', 'wpheka-request-for-quote' ), esc_html( $to ) )
			: esc_html__( 'Off in WooCommerce, so quote requests cannot be sent. Turn it on to receive them.', 'wpheka-request-for-quote' );
		$help .= ' <a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit email', 'wpheka-request-for-quote' ) . '</a>';

		Fields::info_row(
			array(
				'label' => __( 'New quote request', 'wpheka-request-for-quote' ),
				'help'  => $help,
				'pill'  => $on ? __( 'On', 'wpheka-request-for-quote' ) : __( 'Off', 'wpheka-request-for-quote' ),
				'kind'  => $on ? 'ok' : 'bad',
			)
		);
	}

	/**
	 * The Requests tab.
	 */
	private static function render_requests() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters and a notice code.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$notice = isset( $_GET['rfq_notice'] ) ? sanitize_key( wp_unslash( $_GET['rfq_notice'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$statuses = WPHEKA_Rfq_Requests::statuses();
		$status   = isset( $statuses[ $status ] ) ? $status : '';
		$notices  = array(
			'status'  => array( '', __( 'Status changed.', 'wpheka-request-for-quote' ) ),
			'deleted' => array( '', __( 'Request deleted.', 'wpheka-request-for-quote' ) ),
			'error'   => array( ' w-notice--warn', __( 'That request could not be changed. It may have been deleted already.', 'wpheka-request-for-quote' ) ),
		);
		$pills    = array(
			'new'     => 'info',
			'replied' => 'ok',
			'closed'  => 'plain',
		);

		echo '<div class="w-stack rfq-requests">';
		if ( isset( $notices[ $notice ] ) ) {
			echo '<div class="w-notice' . esc_attr( $notices[ $notice ][0] ) . '" role="status"><p>' . esc_html( $notices[ $notice ][1] ) . '</p></div>';
		}
		echo '<section class="w-section" aria-labelledby="rfq-requests-t"><div class="w-section-head"><h2 id="rfq-requests-t">' . esc_html__( 'Quote requests', 'wpheka-request-for-quote' ) . '</h2>';
		echo '<p>' . esc_html__( 'Every quote request customers sent, newest first. The quote email to the store is sent as before.', 'wpheka-request-for-quote' ) . '</p></div><div class="w-card">';

		$result = WPHEKA_Rfq_Requests::query( $status, $paged, self::PER_PAGE );
		if ( $result['total'] || $status ) {
			echo '<form method="get" class="w-toolbar w-filters rfq-filters"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><input type="hidden" name="tab" value="requests">';
			echo '<select class="w-select" name="status" aria-label="' . esc_attr__( 'Status', 'wpheka-request-for-quote' ) . '"><option value="">' . esc_html__( 'All requests', 'wpheka-request-for-quote' ) . '</option>';
			foreach ( $statuses as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select><button type="submit" class="w-btn w-btn--secondary">' . esc_html__( 'Filter', 'wpheka-request-for-quote' ) . '</button></form>';
		}

		if ( ! $result['rows'] ) {
			if ( $result['total'] ) {
				// Past the last page, for example an old link.
				echo '<div class="w-empty"><h3>' . esc_html__( 'No requests on this page.', 'wpheka-request-for-quote' ) . '</h3><p><a href="' . esc_url( self::url( 'requests' ) ) . '">' . esc_html__( 'Go to the first page', 'wpheka-request-for-quote' ) . '</a></p></div>';
			} elseif ( $status ) {
				echo '<div class="w-empty"><h3>' . esc_html__( 'No requests with this status.', 'wpheka-request-for-quote' ) . '</h3><p>' . esc_html__( 'Choose "All requests" to see every request.', 'wpheka-request-for-quote' ) . '</p></div>';
			} else {
				echo '<div class="w-empty"><h3>' . esc_html__( 'No quote requests yet.', 'wpheka-request-for-quote' ) . '</h3><p>' . esc_html__( 'Requests are stored from version 1.9.0. Each request a customer sends appears here, as well as in your email.', 'wpheka-request-for-quote' ) . '</p></div>';
			}
			echo '</div></section></div>';
			return;
		}

		echo '<div class="w-table-wrap"><table class="w-table rfq-requests-table"><thead><tr><th>' . esc_html__( 'Request', 'wpheka-request-for-quote' ) . '</th><th>' . esc_html__( 'Customer', 'wpheka-request-for-quote' ) . '</th><th>' . esc_html__( 'Products', 'wpheka-request-for-quote' ) . '</th><th>' . esc_html__( 'Status', 'wpheka-request-for-quote' ) . '</th><th>' . esc_html__( 'Received', 'wpheka-request-for-quote' ) . '</th><th aria-label="' . esc_attr__( 'Actions', 'wpheka-request-for-quote' ) . '"></th></tr></thead><tbody>';

		foreach ( $result['rows'] as $row ) {
			$id       = (int) $row['id'];
			$items    = WPHEKA_Rfq_Requests::items( $row );
			$received = strtotime( $row['created_at'] . ' UTC' );
			$lines    = array();
			foreach ( $items as $item ) {
				$lines[] = $item['name'] . ' × ' . number_format_i18n( (int) $item['quantity'] );
			}

			echo '<tr data-request="' . esc_attr( $id ) . '">';
			echo '<td><strong>#' . esc_html( $id ) . '</strong>' . ( '' !== $row['message'] ? '<br><span class="w-wrap">' . esc_html( wp_trim_words( $row['message'], 20 ) ) . '</span>' : '' ) . '</td>';
			echo '<td class="w-wrap">' . esc_html( '' !== $row['customer_name'] ? $row['customer_name'] : __( 'No name given', 'wpheka-request-for-quote' ) );
			echo '<br><a href="mailto:' . esc_attr( $row['customer_email'] ) . '">' . esc_html( $row['customer_email'] ) . '</a>';
			if ( '' !== $row['customer_company'] ) {
				echo '<br>' . esc_html( $row['customer_company'] );
			}
			if ( '' !== $row['customer_phone'] ) {
				echo '<br>' . esc_html( $row['customer_phone'] );
			}
			echo '</td>';
			/* translators: %s: number of products. */
			echo '<td class="w-wrap"><strong>' . esc_html( sprintf( _n( '%s product', '%s products', count( $items ), 'wpheka-request-for-quote' ), number_format_i18n( count( $items ) ) ) ) . '</strong><br>' . esc_html( implode( ', ', $lines ) );
			if ( (float) $row['total_estimate'] > 0 ) {
				/* translators: %s: estimated total at list price. */
				echo '<br><span class="w-help">' . esc_html( sprintf( __( 'About %s at list price', 'wpheka-request-for-quote' ), html_entity_decode( wp_strip_all_tags( wc_price( (float) $row['total_estimate'], array( 'currency' => $row['currency'] ) ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ) ) ) . '</span>';
			}
			echo '</td><td>';
			echo '<span class="w-pill w-pill--' . esc_attr( isset( $pills[ $row['status'] ] ) ? $pills[ $row['status'] ] : 'info' ) . '">' . esc_html( isset( $statuses[ $row['status'] ] ) ? $statuses[ $row['status'] ] : $row['status'] ) . '</span>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="w-decision rfq-status-form">';
			wp_nonce_field( self::STATUS_ACTION . '_' . $id );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::STATUS_ACTION ) . '"><input type="hidden" name="request_id" value="' . esc_attr( $id ) . '">';
			echo '<select class="w-select" name="status" aria-label="' . esc_attr( sprintf( /* translators: %s: request number. */ __( 'Status of request #%s', 'wpheka-request-for-quote' ), $id ) ) . '">';
			foreach ( $statuses as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $row['status'], $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select> <button type="submit" class="w-btn w-btn--secondary w-btn--sm">' . esc_html__( 'Change', 'wpheka-request-for-quote' ) . '</button></form></td>';
			/* translators: %s: time since the request, for example "3 hours". */
			echo '<td title="' . esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $received ) ) . '">' . esc_html( sprintf( __( '%s ago', 'wpheka-request-for-quote' ), human_time_diff( $received ) ) ) . '</td>';
			echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="w-decision rfq-delete-form" onsubmit="return window.confirm(this.dataset.confirm);" data-confirm="' . esc_attr__( 'Delete this request? This cannot be undone.', 'wpheka-request-for-quote' ) . '">';
			wp_nonce_field( self::DELETE_ACTION . '_' . $id );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::DELETE_ACTION ) . '"><input type="hidden" name="request_id" value="' . esc_attr( $id ) . '">';
			echo '<button type="submit" class="w-btn w-btn--danger w-btn--sm">' . esc_html__( 'Delete', 'wpheka-request-for-quote' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table></div>';

		$pages = (int) ceil( $result['total'] / self::PER_PAGE );
		if ( $pages > 1 ) {
			echo '<nav class="w-pages rfq-pages" aria-label="' . esc_attr__( 'Request pages', 'wpheka-request-for-quote' ) . '">' . wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%', self::url( 'requests', $status ? array( 'status' => $status ) : array() ) ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			) . '</nav>';
		}
		echo '</div></section></div>';
	}

	/**
	 * The Help tab.
	 *
	 * @param array $links Help links.
	 */
	private static function render_help( array $links ) {
		$cards = array(
			array( 'book', __( 'Documentation', 'wpheka-request-for-quote' ), __( 'Step-by-step guides for every Request For Quote setting.', 'wpheka-request-for-quote' ), $links['docs'] ),
			array( 'chat', __( 'Support forum', 'wpheka-request-for-quote' ), __( 'Ask a question. The developers who built the plugin answer.', 'wpheka-request-for-quote' ), $links['support'] ),
			array( 'sparkle', __( "What's new", 'wpheka-request-for-quote' ), __( 'Every change in each version, in plain words.', 'wpheka-request-for-quote' ), $links['changes'] ),
		);
		echo '<div class="w-flow">';
		foreach ( $cards as $card ) {
			echo '<a class="w-card w-flow-step" href="' . esc_url( $card[3] ) . '" target="_blank" rel="noopener"><span class="w-ico w-ico--lg">' . Icons::get( $card[0] ) . '</span><h3>' . esc_html( $card[1] ) . '</h3><p>' . esc_html( $card[2] ) . '<span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wpheka-request-for-quote' ) . '</span></p></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '</div>';
	}
}
