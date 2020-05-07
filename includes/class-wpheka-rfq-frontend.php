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

if ( ! class_exists( 'WPHEKA_Rfq_Frontend', false ) ) :

	/**
	 * WPHEKA_Rfq_Frontend Class.
	 */
	class WPHEKA_Rfq_Frontend {


		/**
		 * WPHEKA_Rfq_Frontend Constructor.
		 */
		public function __construct() {

			// Custom styles and javascripts.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );

			// Show button in product details page.
			add_action( 'woocommerce_single_product_summary', array( $this, 'add_button_product_page' ), 35 );

			// Request quote form.
			add_action( 'wpheka_after_rfq_list', array( $this, 'add_request_quote_form' ) );

			add_action( 'wpheka_rfq_content_start', array( $this, 'add_parent_div_before_rfq_list_form' ) );

			add_action( 'wpheka_rfq_content_end', array( $this, 'close_parent_div_after_rfq_mail_form' ) );

			if ( wpheka_request_for_quote()->get_settings( 'hide_add_to_cart' ) == 'yes' ) {
				// Hide add to cart from store.
				remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
				// add_filter( 'woocommerce_is_purchasable', '__return_false');.
			}

			if ( wpheka_request_for_quote()->get_settings( 'hide_price' ) == 'yes' ) {
				// Hide price from store.
				remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
			}

			if ( wpheka_request_for_quote()->get_settings( 'button_in_other_pages' ) == 'yes' ) {
				add_action( 'woocommerce_after_shop_loop_item', array( $this, 'add_quote_button_in_loop' ), 15 );
			}
		}

		/**
		 * Enqueue Scripts and Styles
		 *
		 * @return void
		 * @since  1.0
		 * @author WPHEKA
		 */
		public function enqueue_styles_scripts() {
			global $wp_heka_rfq;

			if ( ( wpheka_request_for_quote()->is_rfq_page() ) || ( wpheka_request_for_quote()->is_product_page() ) ) {
				// Iclude WordPress core components buttons.
				wp_enqueue_style( 'wpheka_rfq_frontend_css', $wp_heka_rfq->plugin_url . 'assets/frontend/css/dist/components/style.css', array(), '1.0' );

				// Enqueue loading overlay.
				wp_enqueue_script( 'wpheka_rfq_loadingoverlay_js', $wp_heka_rfq->plugin_url . 'assets/frontend/js/loadingoverlay.min.js', array( 'jquery' ), '2.1.7', true );

				// Enqueue plugin frontend js.
				wp_register_script( 'wpheka_rfq_frontend_single_js', $wp_heka_rfq->plugin_url . 'assets/frontend/js/frontend.js', array( 'jquery', 'jquery-blockui', 'wpheka_rfq_loadingoverlay_js' ), '1.0', true );

				$localize_script_args = array(
					'ajax_url'                         => admin_url( 'admin-ajax.php' ),
					'i18n_no_matching_variations_text' => esc_attr__( 'Sorry, no products matched your selection. Please choose a different combination.', 'wpheka-request-for-quote' ),
					'i18n_make_a_selection_text'       => esc_attr__( 'Please select some product options before adding this product to your cart.', 'wpheka-request-for-quote' ),
					'i18n_unavailable_text'            => esc_attr__( 'Sorry, this product is unavailable. Please choose a different combination.', 'wpheka-request-for-quote' ),
					'add_to_quote_nonce'               => wp_create_nonce( 'wpheka-add-to-quote-ajax-action' ),
				);

				wp_localize_script( 'wpheka_rfq_frontend_single_js', 'wpheka_rfq_frontend_params', $localize_script_args );

				wp_enqueue_style( 'wpheka_rfq_frontend_css', $wp_heka_rfq->plugin_url . 'assets/frontend/css/frontend.css', array(), '1.0' );
				wp_enqueue_script( 'wpheka_rfq_frontend_single_js' );
			}

			if ( is_shop() || is_product_taxonomy() ) {
				// Iclude WordPress core components buttons.
				wp_enqueue_style( 'wpheka_rfq_frontend_css', $wp_heka_rfq->plugin_url . 'assets/frontend/css/dist/components/style.css', array(), '1.0' );

				wp_register_script( 'wpheka_rfq_frontend_js', $wp_heka_rfq->plugin_url . 'assets/frontend/js/rfq.js', array( 'jquery' ), '1.0', true );

				$localize_quote_script_args = array(
					'ajax_url'           => admin_url( 'admin-ajax.php' ),
					'add_to_quote_nonce' => wp_create_nonce( 'wpheka-add-to-quote-shop-ajax-action' ),
				);

				wp_localize_script( 'wpheka_rfq_frontend_js', 'wpheka_rfq_shop_params', $localize_quote_script_args );
				wp_enqueue_script( 'wpheka_rfq_frontend_js' );
			}
		}

		/**
		 * Add quote button in product details page
		 *
		 * @since  1.0
		 * @author WPHEKA
		 */
		public function add_button_product_page() {
			global $product;

			wc_get_template(
				'single-product/add-to-quote.php',
				array(
					'class'      => 'add-to-quote-single',
					'product_id' => $product->get_id(),
					'wpnonce'    => wp_create_nonce( 'add-request-quote-' . $product->get_id() ),
					'label'      => wpheka_request_for_quote()->get_settings( 'button_link_text' ),
					'return_url' => '',
				),
				'',
				WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH
			);
		}

		/**
		 * Add request quote mail form after quote listing table
		 *
		 * @since  1.0
		 * @author WPHEKA
		 */
		public function add_request_quote_form() {

			$rfq_data = wpheka_request_for_quote()->get_rfq_data();

			if ( ! empty( $rfq_data ) ) {
				$data = array(
					'username' => '',
					'email'    => '',
				);

				if ( is_user_logged_in() ) {
					$current_user     = wp_get_current_user();
					$data['username'] = $current_user->display_name;
					$data['email']    = $current_user->user_email;
				}

				wc_get_template( 'request-for-quote-form.php', $data, '', WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH );
			}
		}

		/**
		 * Add parent div before quote listing table
		 *
		 * @since  1.0
		 * @author WPHEKA
		 */
		public function add_parent_div_before_rfq_list_form() {
			echo '<div class="wpheka-request-for-quote">';
		}

		/**
		 * Close parent div after request quote mail form
		 *
		 * @since  1.0
		 * @author WPHEKA
		 */
		public function close_parent_div_after_rfq_mail_form() {
			echo '</div>';
		}

		/**
		 * Add quote button in archive pages
		 *
		 * @since  1.0
		 * @author WPHEKA
		 */
		public function add_quote_button_in_loop() {
			global $product;

			if ( ! $product ) {
				return false;
			}

			if ( isset( $GLOBALS['woocommerce_loop'] ) ) {

				$exclude_loop = array(
					'cross-sells',
					'up-sells',
					'related',
				);

				if ( in_array( $GLOBALS['woocommerce_loop']['name'], $exclude_loop ) ) {
					return false;
				}
			}

			$display_for_product_type = apply_filters( 'wpheka_rfq_display_for_product_type', array( 'simple', 'subscription', 'external' ) );

			if ( ! $product->is_type( $display_for_product_type ) ) {
				return false;
			}

			if ( $product ) {
				wc_get_template(
					'single-product/add-to-quote.php',
					array(
						'class'      => 'add-to-quote-loop',
						'product_id' => $product->get_id(),
						'wpnonce'    => wp_create_nonce( 'add-request-quote-' . $product->get_id() ),
						'label'      => wpheka_request_for_quote()->get_settings( 'button_link_text' ),
						'return_url' => '',
					),
					'',
					WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH
				);
			}
		}
	}

endif;

new WPHEKA_Rfq_Frontend();
