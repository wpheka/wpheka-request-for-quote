<?php
/**
 * WPHEKA_Rfq
 *
 * @package WPHEKA_Rfq
 * @since   1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main WPHEKA_Rfq Class.
 *
 * @class WPHEKA_Rfq
 */
final class WPHEKA_Rfq {


	/**
	 * WPHEKA_Rfq version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * WPHEKA_Rfq text domain.
	 *
	 * @var string
	 */
	public $text_domain = 'wpheka-request-for-quote';

	/**
	 * WPHEKA_Rfq plugin url.
	 *
	 * @var string
	 */
	public $plugin_url;


	/**
	 * Session instance.
	 *
	 * @var WC_Session|WPHEKA_RFQ_Session_Handler
	 */
	public $session = null;

	/**
	 * Session data
	 *
	 * @var $rfq_data array
	 */
	public $rfq_data = array();

	/**
	 * The single instance of the class.
	 *
	 * @var WPHEKA_Rfq
	 * @since 1.0
	 */
	protected static $_instance = null;

	/**
	 * Main WPHEKA_Rfq Instance.
	 *
	 * Ensures only one instance of WPHEKA_Rfq is loaded or can be loaded.
	 *
	 * @since 1.0
	 * @static
	 * @see wpheka_request_for_quote()
	 * @return WPHEKA_Rfq - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'wpheka-request-for-quote' ), '1.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'wpheka-request-for-quote' ), '1.0' );
	}

	/**
	 * WPHEKA_Rfq Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();

		do_action( 'wpheka_rfq_loaded' );
	}

	/**
	 * Hook into actions and filters.
	 *
	 * @since 1.0
	 */
	private function init_hooks() {
		register_activation_hook( WPHEKA_RFQ_PLUGIN_FILE, array( 'WPHEKA_Rfq_Install', 'install' ) );
		add_action( 'init', array( $this, 'init' ), 5 );
		add_action( 'init', array( 'WPHEKA_Rfq_Shortcodes', 'init' ) );
		add_filter( 'woocommerce_email_classes', array( $this, 'include_rfq_emails' ) );
	}

	/**
	 * Init WPHEKA_Rfq when WordPress Initialises.
	 */
	public function init() {
		// Before init action.
		do_action( 'before_wpheka_rfq_init' );

		// Set up localisation.
		$this->load_plugin_textdomain();

		// Initialize session for frontend requests.
		if ( $this->is_request( 'frontend' ) ) {
			$this->initialize_session();
		}

		// Init action.
		do_action( 'wpheka_rfq_init' );
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * Locales found in:
	 *      - WP_LANG_DIR/wpheka-request-for-quote/wpheka-request-for-quote-LOCALE.mo
	 *      - WP_LANG_DIR/plugins/wpheka-request-for-quote-LOCALE.mo
	 */
	public function load_plugin_textdomain() {
		$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, $this->text_domain );

		unload_textdomain( $this->text_domain );
		load_textdomain( $this->text_domain, WP_LANG_DIR . '/wpheka-request-for-quote/wpheka-request-for-quote-' . $locale . '.mo' );
		load_plugin_textdomain( $this->text_domain, false, plugin_basename( dirname( WPHEKA_RFQ_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Define PT Constants.
	 */
	private function define_constants() {

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data      = get_plugin_data( WPHEKA_RFQ_PLUGIN_FILE );
		$this->version    = $plugin_data['Version'];
		$this->plugin_url = trailingslashit( plugins_url( '', WPHEKA_RFQ_PLUGIN_FILE ) );

		$this->define( 'WPHEKA_RFQ_SESSION_CACHE_GROUP', 'wpheka_rfq_session_id' );
		$this->define( 'WPHEKA_RFQ_PLUGIN_ABSPATH', dirname( WPHEKA_RFQ_PLUGIN_FILE ) . '/' );
		$this->define( 'WPHEKA_RFQ_PLUGIN_BASENAME', plugin_basename( WPHEKA_RFQ_PLUGIN_FILE ) );
		$this->define( 'WPHEKA_RFQ_PLUGIN_VERSION', $this->version );
		$this->define( 'WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH', WPHEKA_RFQ_PLUGIN_ABSPATH . 'templates/' );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * What type of request is this?
	 *
	 * @param  string $type admin, ajax, cron or frontend.
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' ) && ! defined( 'REST_REQUEST' );
		}
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes() {

		/**
		 * Core classes.
		 */
		include_once WPHEKA_RFQ_PLUGIN_ABSPATH . 'includes/class-wpheka-rfq-install.php';
		include_once WPHEKA_RFQ_PLUGIN_ABSPATH . 'includes/class-wpheka-rfq-shortcodes.php';

		// Include admin class.
		if ( $this->is_request( 'admin' ) ) {
			include_once WPHEKA_RFQ_PLUGIN_ABSPATH . 'includes/admin/class-wpheka-rfq-admin.php';
		}

		// Include frontend class.
		if ( $this->is_request( 'frontend' ) ) {
			if ( ! class_exists( 'WC_Session' ) ) {
				include_once WC()->plugin_path() . '/includes/abstracts/abstract-wc-session.php';
			}

			include_once WPHEKA_RFQ_PLUGIN_ABSPATH . 'includes/class-wpheka-rfq-session-handler.php';
			include_once WPHEKA_RFQ_PLUGIN_ABSPATH . 'includes/class-wpheka-rfq-frontend.php';
		}

		// Include ajax class.
		if ( $this->is_request( 'ajax' ) ) {
			include_once WPHEKA_RFQ_PLUGIN_ABSPATH . 'includes/class-wpheka-rfq-ajax.php';
		}
	}

	/**
	 * Get the default SVG logo
	 *
	 * @return string default logo image url
	 */
	public function wpheka_get_admin_menu_logo() {
		return $this->plugin_url . 'assets/admin/images/wp-heka-menu-icon-22.svg';
	}

	/**
	 * Initialize the session class.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function initialize_session() {

		if ( headers_sent() ) {
			return;
		}

		if ( ! did_action( 'before_wpheka_rfq_init' ) || doing_action( 'before_wpheka_rfq_init' ) ) {
			return;
		}

		// Session class, handles session data for users - can be overwritten if custom handler is needed.
		$session_class = apply_filters( 'wpheka_rfq_session_handler', 'WPHEKA_RFQ_Session_Handler' );
		if ( is_null( $this->session ) || ! $this->session instanceof $session_class ) {
			$this->session = new $session_class();
			$this->session->init();
		}
	}

	/**
	 * Get request for quote data
	 */
	public function get_rfq_data() {
		return $this->session->get( 'rfq', array() );
	}

	/**
	 * Clear request for quote data
	 */
	public function clear_rfq_data() {
		$this->session->set( 'rfq', array() );
	}

	/**
	 * Check product exists in request quote list
	 *
	 * @param  int $product_id product id.
	 */
	public function check_product_exists_in_quote_list( $product_id ) {
		$rfq_data = $this->session->get( 'rfq', array() );

		if ( empty( $rfq_data ) ) {
			return false;
		}

		foreach ( $rfq_data as $rfq_item_key => $rfq_item ) {
			if ( strpos( $rfq_item_key, '-' ) !== false ) {
				$rfq_item_key_arr = explode( '-', $rfq_item_key );

				if ( array_key_exists( $product_id, $rfq_item_key_arr ) ) {
					return true;
				}
			} elseif ( array_key_exists( $product_id, $rfq_data ) ) {
				return true;
			}
		}

		return false;
	}



	/**
	 * Get request for quote url
	 */
	public function get_rfq_page_url() {
		$request_for_quote_page_id = get_option( 'wpheka_request_for_quote_page_id' );

		if ( function_exists( 'wpml_object_id_filter' ) ) {
			global $sitepress;
			if ( ! is_null( $sitepress ) && is_callable( array( $sitepress, 'get_current_language' ) ) ) {
				$request_for_quote_page_id = wpml_object_id_filter( $request_for_quote_page_id, 'post', true, $sitepress->get_current_language() );
			}
		}

		$base_url = get_the_permalink( $request_for_quote_page_id );

		return apply_filters( 'wpheka_request_page_url', $base_url );
	}

	/**
	 * Gets the url to remove an item from the quote list.
	 *
	 * @since 1.0
	 * @param string $rfq_item_key contains the id of the quote item.
	 * @return string url to page
	 */
	public function get_rfq_remove_url( $rfq_item_key ) {
		$rfq_page_url = $this->get_rfq_page_url();
		return apply_filters( 'woocommerce_get_remove_url', $rfq_page_url ? wp_nonce_url( add_query_arg( 'remove_item', $rfq_item_key, $rfq_page_url ), 'wpheka-rfq' ) : '' );
	}

	/**
	 * Is_product - Returns true when viewing a single product.
	 *
	 * @return bool
	 */
	public function is_product_page() {
		return is_singular( array( 'product' ) );
	}

	/**
	 * Update quote product item quantity.
	 *
	 * @param  string $rfq_item_key qute item key.
	 * @param  int    $quantity qute item quantity.
	 */
	public function update_rfq_item_quantity( $rfq_item_key, $quantity ) {
		$rfq_data = $this->get_rfq_data();
		if ( ! empty( $rfq_data ) ) {
			if ( array_key_exists( $rfq_item_key, $rfq_data ) ) {
				$rfq_data[ $rfq_item_key ]['quantity'] = $quantity;
				wpheka_request_for_quote()->session->set( 'rfq', $rfq_data );
			}
		}
	}

	/**
	 * Is_cart - Returns true when viewing the cart page.
	 *
	 * @return bool
	 */
	public function is_rfq_page() {
		$page_id = get_option( 'wpheka_request_for_quote_page_id' );

		return ( $page_id && is_page( $page_id ) ) || defined( 'WPHEKA_RFQ_PAGE' ) || wc_post_content_has_shortcode( 'wpheka_request_for_quote' );
	}

	/**
	 * Add request for quote email
	 *
	 * @access public
	 * @param  array $email_classes email classes.
	 * @return $email_classes
	 */
	public function include_rfq_emails( $email_classes ) {
		// Add custom email class.
		$email_classes['WPHEKA_Rfq_Mail'] = include WPHEKA_RFQ_PLUGIN_ABSPATH . 'includes/emails/class-wpheka-rfq-mail.php';
		return $email_classes;
	}

	/**
	 * Get plugin settings
	 *
	 * @param  array $field setting field.
	 */
	public function get_settings( $field ) {

		if ( empty( $field ) ) {
			return false;
		}

		$tab_option_name = 'wpheka_rfq_general_settings';
		$tab_settings    = get_option( $tab_option_name );

		if ( empty( $tab_settings ) ) {
			return false;
		}

		return isset( $tab_settings[ $field ] ) ? $tab_settings[ $field ] : false;
	}
}
