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

		// Review request notice.
		add_action( 'admin_notices', array( $this, 'maybe_show_review_notice' ) );
		add_action( 'wp_ajax_wpheka_rfq_dismiss_review', array( $this, 'ajax_dismiss_review' ) );
		add_action( 'wp_ajax_wpheka_rfq_snooze_review', array( $this, 'ajax_snooze_review' ) );
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
	 * Determine whether to show the review request notice.
	 *
	 * @return bool
	 */
	private function should_show_review_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		/*
		 * Dashboard only. The ask used to appear on every admin screen, which is
		 * where a review request competes with warnings that actually need
		 * attention.
		 */
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'dashboard' !== $screen->id ) {
			return false;
		}

		/*
		 * Dismissal is per user. A site-wide flag let whichever administrator
		 * clicked first answer for everyone, and meant nobody else was ever
		 * asked.
		 *
		 * The old site-wide option is still honoured, so an install where
		 * someone already declined under 1.7.2 is not asked a second time. That
		 * matters more than the tidier storage: re-asking someone who has
		 * already said no is exactly how a plugin earns a reputation for
		 * nagging.
		 */
		if ( get_option( 'wpheka_rfq_review_dismissed' ) ) {
			return false;
		}

		if ( get_user_meta( get_current_user_id(), 'wpheka_rfq_review_dismissed', true ) ) {
			return false;
		}

		if ( get_transient( 'wpheka_rfq_review_snoozed' ) ) {
			return false;
		}

		/*
		 * Three quotes, and no timer. Time elapsed says nothing about whether
		 * the plugin was useful; a single quote could be the shop owner testing
		 * their own form. Three submissions is the plugin having done its job.
		 */
		if ( (int) get_option( 'wpheka_rfq_quote_count', 0 ) < 3 ) {
			return false;
		}

		return true;
	}

	/**
	 * Render the review request admin notice.
	 */
	public function maybe_show_review_notice() {
		if ( ! $this->should_show_review_notice() ) {
			return;
		}

		$review_url = 'https://wordpress.org/support/plugin/wpheka-request-for-quote/reviews/';
		$nonce      = wp_create_nonce( 'wpheka_rfq_review' );
		?>
		<div id="wpheka-rfq-review-notice" class="notice notice-info is-dismissible wpheka-rfq-review-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<p>
				<?php
				echo wp_kses(
					__( 'We hope you\'re enjoying <strong>Request For Quote</strong>! Could you please do us a BIG favor and leave a rating on WordPress.org to help us spread the word?', 'wpheka-request-for-quote' ),
					array( 'strong' => array() )
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer" data-wpheka-rfq-review="rate">
					<?php esc_html_e( 'Sure, you deserve it', 'wpheka-request-for-quote' ); ?>
				</a>
				<a class="button" href="#" data-wpheka-rfq-review="already">
					<?php esc_html_e( 'I already did', 'wpheka-request-for-quote' ); ?>
				</a>
				<a class="button" href="#" data-wpheka-rfq-review="later">
					<?php esc_html_e( 'Maybe later', 'wpheka-request-for-quote' ); ?>
				</a>
				<a href="#" data-wpheka-rfq-review="never" style="margin-left: 8px;">
					<?php esc_html_e( 'I don\'t want to leave a review', 'wpheka-request-for-quote' ); ?>
				</a>
			</p>
		</div>
		<script type="text/javascript">
		(function($) {
			$(document).on('click', '#wpheka-rfq-review-notice [data-wpheka-rfq-review]', function(e) {
				var $link  = $(this);
				var action = $link.data('wpheka-rfq-review');
				var nonce  = $('#wpheka-rfq-review-notice').data('nonce');
				var ajax   = (action === 'later') ? 'wpheka_rfq_snooze_review' : 'wpheka_rfq_dismiss_review';

				$.post(ajaxurl, { action: ajax, _ajax_nonce: nonce });

				if (action !== 'rate') {
					e.preventDefault();
					$('#wpheka-rfq-review-notice').fadeOut(200, function() { $(this).remove(); });
				} else {
					$('#wpheka-rfq-review-notice').fadeOut(200, function() { $(this).remove(); });
				}
			});

			$(document).on('click', '#wpheka-rfq-review-notice .notice-dismiss', function() {
				var nonce = $('#wpheka-rfq-review-notice').data('nonce');
				$.post(ajaxurl, { action: 'wpheka_rfq_snooze_review', _ajax_nonce: nonce });
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * AJAX: permanently dismiss the review request notice.
	 */
	public function ajax_dismiss_review() {
		check_ajax_referer( 'wpheka_rfq_review' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		update_user_meta( get_current_user_id(), 'wpheka_rfq_review_dismissed', 1 );
		wp_send_json_success();
	}

	/**
	 * AJAX: snooze the review request notice for 14 days.
	 */
	public function ajax_snooze_review() {
		check_ajax_referer( 'wpheka_rfq_review' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		set_transient( 'wpheka_rfq_review_snoozed', 1, 14 * DAY_IN_SECONDS );
		wp_send_json_success();
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
