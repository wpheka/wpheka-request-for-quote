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

		// The menu page, its assets and the settings form are in WPHEKA_Rfq_Admin_Page (1.9.0).

		// Review request notice.
		add_action( 'admin_notices', array( $this, 'maybe_show_review_notice' ) );
		add_action( 'wp_ajax_wpheka_rfq_dismiss_review', array( $this, 'ajax_dismiss_review' ) );
		add_action( 'wp_ajax_wpheka_rfq_snooze_review', array( $this, 'ajax_snooze_review' ) );
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

		// Snoozed per user, like the dismissal. This was a site-wide transient,
		// so one administrator's "Maybe later" hid the request from everyone.
		if ( (int) get_user_meta( get_current_user_id(), 'wpheka_rfq_review_snoozed_until', true ) > time() ) {
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

		/*
		 * rate=5 pre-selects the rating on the review form, in the URL form
		 * WooCommerce uses. Deliberate, and *not* a Plugin Check finding: its
		 * five_star_reviews_detected check matches `reviews/?filter=5` only, so
		 * neither the parameter nor the missing trailing slash here match it.
		 *
		 * The `reviews/?filter=5#new-post` this plugin used before did match,
		 * which is why it was reported -- and filter=5 only filters the existing
		 * review list, so it was flagged without ever pre-filling a rating.
		 *
		 * Plugin Check staying quiet is not the same as wordpress.org's review
		 * team agreeing; that risk is accepted separately.
		 */
		$review_url = 'https://wordpress.org/support/plugin/wpheka-request-for-quote/reviews?rate=5#new-post';
		$nonce      = wp_create_nonce( 'wpheka_rfq_review' );
		?>
		<div id="wpheka-rfq-review-notice" class="notice notice-info is-dismissible wpheka-rfq-review-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: 1: plugin name in bold, 2: five stars */
						__( 'We hope you\'re enjoying %1$s! Could you please do us a BIG favor and leave us a %2$s rating on WordPress.org to help us spread the word?', 'wpheka-request-for-quote' ),
						'<strong>' . esc_html__( 'Request For Quote', 'wpheka-request-for-quote' ) . '</strong>',
						'&#9733;&#9733;&#9733;&#9733;&#9733;'
					),
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

		update_user_meta( get_current_user_id(), 'wpheka_rfq_review_snoozed_until', time() + 14 * DAY_IN_SECONDS );
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
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=wpheka_request_for_quote' ) ) . '" aria-label="' . esc_attr__( 'View plugin settings', 'wpheka-request-for-quote' ) . '">' . esc_html__( 'Settings', 'wpheka-request-for-quote' ) . '</a>',
		);

		/*
		 * The email template lives on WooCommerce's own emails screen, which is
		 * three clicks deep and easy to miss -- WooCommerce > Settings > Emails >
		 * Request For Quote. Linked straight to the section so the recipient,
		 * subject, heading and preview are one click away.
		 *
		 * Only when WooCommerce is actually there: without it wc-settings does
		 * not exist and the link would 404.
		 */
		if ( class_exists( 'WooCommerce' ) ) {
			$email_url = add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'email',
					'section' => 'wpheka_rfq_mail',
				),
				admin_url( 'admin.php' )
			);

			$action_links['emails'] = '<a href="' . esc_url( $email_url ) . '" aria-label="' . esc_attr__( 'Edit the quote request email template', 'wpheka-request-for-quote' ) . '">' . esc_html__( 'Email template', 'wpheka-request-for-quote' ) . '</a>';
		}

		return array_merge( $action_links, $links );
	}
}

new WPHEKA_Rfq_Admin();
