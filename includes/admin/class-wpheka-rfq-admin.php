<?php
/**
 * WPHEKA_Rfq
 *
 * @package WPHEKA_Rfq
 * @author      WPHEKA
 * @link        https://wpheka.com/
 * @since       1.0
 * @version     1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WPHEKA_Rfq_Admin Class.
 *
 * @class WPHEKA_Rfq_Admin
 */
class WPHEKA_Rfq_Admin {


	/**
	 * WPHEKA_Rfq_Admin Constructor.
	 */
	public function __construct() {
		add_filter( 'plugin_action_links_' . WPHEKA_RFQ_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links' ) );

		// Add menu pages.
		add_action( 'admin_menu', array( $this, 'wpheka_add_pages' ) );

		// admin script and style.
		add_action( 'admin_enqueue_scripts', array( &$this, 'wpheka_enqueue_admin_scripts_styles' ) );
	}

	/**
	 * Admin Scripts
	 */
	public function wpheka_enqueue_admin_scripts_styles() {
		global $wp_heka_rfq;
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		$suffix    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'wpheka_admin_css', $wp_heka_rfq->plugin_url . 'assets/admin/css/admin.css', array(), $wp_heka_rfq->version );
		if ( 'wpheka_page_wpheka_request_for_quote' == $screen_id ) {
			wp_enqueue_style( 'wpheka_common_css', $wp_heka_rfq->plugin_url . 'assets/admin/css/common.css', array(), $wp_heka_rfq->version );
			wp_enqueue_script( 'wpheka_plugin_loader_js', $wp_heka_rfq->plugin_url . 'assets/admin/js/plugin-loader.js', array( 'jquery' ), $wp_heka_rfq->version, true );
			wp_enqueue_script( 'wpheka_admin_settings_js', $wp_heka_rfq->plugin_url . 'assets/admin/js/admin-settings.js', array( 'jquery' ), $wp_heka_rfq->version, true );
			
			wp_localize_script( 'wpheka_admin_settings_js', 'wpheka_admin_params', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'save-plugin-data' )
			));
		}
	}

	/**
	 * Add menu items.
	 */
	public function wpheka_add_pages() {
		global $admin_page_hooks;

		if ( ! isset( $admin_page_hooks['wpheka_plugin_panel'] ) ) {
			$position   = apply_filters( 'wpheka_plugins_menu_item_position', '55.5' );
			$capability = apply_filters( 'wpheka_plugin_panel_menu_page_capability', 'manage_options' );
			$show       = apply_filters( 'wpheka_plugin_panel_menu_page_show', true );

			// WPHEKA text must not be translated.
			if ( ! ! $show ) {
				add_menu_page( 'wpheka_plugin_panel', 'WPHEKA', $capability, 'wpheka_plugin_panel', null, wpheka_request_for_quote()->wpheka_get_admin_menu_logo(), $position );
			}
		}

		add_submenu_page( 'wpheka_plugin_panel', __( 'WPHEKA Request For Quote', 'wpheka-request-for-quote' ), __( 'Request For Quote', 'wpheka-request-for-quote' ), 'manage_options', 'wpheka_request_for_quote', array( $this, 'show_wpheka_request_for_quote_panel' ) );
		/* === Duplicate Items Hack === */
		remove_submenu_page( 'wpheka_plugin_panel', 'wpheka_plugin_panel' );
	}

	/**
	 * Plugin settings panel.
	 */
	public function show_wpheka_request_for_quote_panel() {
		global $wp_heka_rfq;
		$options = get_option( 'wpheka_rfq_general_settings' );
		$logo_url = $wp_heka_rfq->plugin_url . 'assets/admin/images/control-panel-icon.png';
		?>
		<div class="wrap">
			<div class='wpheka-page-bar'>
				<img class='logo' src='<?php echo esc_url( $logo_url ); ?>' height='32px'>
				<h3>WPHEKA Request For Quote Control</h3>
			</div>
			<hr class="wp-header-end" />
			<div class='wpheka-page-wrapper'>
				<div class='wpheka-sidebar'>
					<?php
					include plugin_dir_path( WPHEKA_RFQ_PLUGIN_FILE ) . 'templates/admin/settings/settings-form-submit.php';
					include plugin_dir_path( WPHEKA_RFQ_PLUGIN_FILE ) . 'templates/admin/settings/sidebar-support.php';
					?>
				</div>
				<div class='wpheka-main-content'>
					<div class='wpheka-box'>
						<div class='wpheka-box-title-bar'>
							<h3><?php esc_html_e( 'Settings', 'wpheka-request-for-quote' ); ?></h3>
						</div>
						<div class='wpheka-box-content'>
							<div class='content mb22'>
								<p><?php esc_html_e( 'This WooCommerce extension give your customers the possibility to request custom quotes.', 'wpheka-request-for-quote' ); ?>
								</p>
							</div>
							<?php require plugin_dir_path( WPHEKA_RFQ_PLUGIN_FILE ) . 'templates/admin/settings/settings-form.php'; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param mixed $links Plugin Action links.
	 *
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=wpheka_request_for_quote' ) . '" aria-label="' . esc_attr__( 'View plugin settings', 'wpheka-request-for-quote' ) . '">' . esc_html__( 'Settings', 'wpheka-request-for-quote' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}
}

new WPHEKA_Rfq_Admin();
