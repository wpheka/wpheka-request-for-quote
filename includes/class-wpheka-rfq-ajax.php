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

if ( ! class_exists( 'WPHEKA_Rfq_Ajax', false ) ) :

	/**
	 * WPHEKA_Rfq_Ajax Class.
	 */
	class WPHEKA_Rfq_Ajax {


		/**
		 * WPHEKA_Rfq_Ajax Constructor.
		 */
		public function __construct() {

			// Save plugin data.
			add_action( 'wp_ajax_save_wpheka_rfq_plugin_data', array( $this, 'action_save_wpheka_rfq_plugin_data' ) );

			// Save product to add to quote list.
			add_action( 'wp_ajax_wpheka_add_to_quote', array( $this, 'action_wpheka_add_to_quote' ) );
			add_action( 'wp_ajax_nopriv_wpheka_add_to_quote', array( $this, 'action_wpheka_add_to_quote' ) );

			add_action( 'wp_ajax_wpheka_add_to_quote_shop', array( $this, 'action_wpheka_add_to_quote_shop' ) );
			add_action( 'wp_ajax_nopriv_wpheka_add_to_quote_shop', array( $this, 'action_wpheka_add_to_quote_shop' ) );

			// Update quote product list.
			add_action( 'wp_ajax_update_rfq_list', array( $this, 'action_update_rfq_list' ) );
			add_action( 'wp_ajax_nopriv_update_rfq_list', array( $this, 'action_update_rfq_list' ) );

			// Remove item from rfq list.
			add_action( 'wp_ajax_remove_item_from_rfq_list', array( $this, 'action_remove_item_from_rfq_list' ) );
			add_action( 'wp_ajax_nopriv_remove_item_from_rfq_list', array( $this, 'action_remove_item_from_rfq_list' ) );

			// Mail quote product list.
			add_action( 'wp_ajax_send_rfq_list', array( $this, 'action_send_rfq_list' ) );
			add_action( 'wp_ajax_nopriv_send_rfq_list', array( $this, 'action_send_rfq_list' ) );
		}

		/**
		 * AJAX Action to save all plugin data
		 *
		 * @return void
		 */
		public function action_save_wpheka_rfq_plugin_data() {

			check_ajax_referer( 'save-plugin-data', 'wpheka_nonce' );

			if ( empty( $_POST['wpheka_request_for_quote_page_id'] ) ) { // PHPCS: input var ok.
				wp_send_json_error( array( 'message' => __( 'Request for quote page is not set.', 'wpheka-request-for-quote' ) ) );
				wp_die();
			}

			$wpheka_rfq_general_settings = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );

			update_option( 'wpheka_rfq_general_settings', $wpheka_rfq_general_settings );
			update_option( 'wpheka_request_for_quote_page_id', sanitize_text_field( wp_unslash( $_POST['wpheka_request_for_quote_page_id'] ) ) );

			wp_send_json_success();
			wp_die();
		}

		/**
		 * AJAX Action to add product to quote list
		 *
		 * @return void
		 */
		public function action_wpheka_add_to_quote() {
			check_ajax_referer( 'wpheka-add-to-quote-ajax-action', 'security' );

			if ( ! empty( $_POST['product_id'] ) ) {
				if ( isset( wpheka_request_for_quote()->session ) ) {
					$rfq_data = wpheka_request_for_quote()->get_rfq_data();

					$wpheka_rfq_product_already_in_list_message = apply_filters( 'wpheka_rfq_product_already_in_list_message', __( 'Product already in the quote list.', 'wpheka-request-for-quote' ) );
					$wpheka_rfq_product_added_view_browse_list  = apply_filters( 'wpheka_rfq_product_added_view_browse_list', __( 'Browse the list.', 'wpheka-request-for-quote' ) );
					$wpheka_rfq_product_added_to_list_message   = apply_filters( 'wpheka_rfq_product_added_to_list_message', __( 'Product added to quote list.', 'wpheka-request-for-quote' ) );

					$result = 'false';

					$product_id = absint( $_POST['product_id'] );

					$variation_id = empty( $_POST['variation_id'] ) ? 0 : absint( $_POST['variation_id'] );

					$quantity = empty( $_POST['quantity'] ) ? 1 : absint( $_POST['quantity'] );

					if ( ! empty( $variation_id ) ) {
						$rfq = array(
							'product_id'   => $product_id,
							'variation_id' => $variation_id,
							'quantity'     => $quantity,
						);

						$variations = array();

						$raw_posted_data = wp_unslash( $_POST );

						foreach ( $raw_posted_data as $key => $value ) {
							if ( stripos( $key, 'attribute' ) !== false ) {
								$key = sanitize_key( wp_unslash( $key ) );
								$value = sanitize_text_field( wp_unslash( $value ) );
								$variations[ $key ] = $value;
							}
						}

						$rfq ['variations'] = $variations;

						$rfq_item_key = $product_id . '-' . $variation_id;

						if ( array_key_exists( $rfq_item_key, $rfq_data ) ) {
							// product already exists.
							$result = 'exists';
						} else {
							$rfq_data[ $rfq_item_key ] = $rfq;
							$result                    = 'true';
						}
					} else {
						$rfq = array(
							'product_id' => $product_id,
							'quantity'   => $quantity,
						);

						$rfq_item_key = $product_id;

						if ( array_key_exists( $rfq_item_key, $rfq_data ) ) {
							// product already exists.
							$result = 'exists';
						} else {
							$result                  = 'true';
							$rfq_data[ $product_id ] = $rfq;
						}
					}

					if ( 'exists' == $result ) {
						wp_send_json(
							array(
								'result'       => $result,
								'message'      => $wpheka_rfq_product_already_in_list_message,
								'label_browse' => $wpheka_rfq_product_added_view_browse_list,
								'rfq_page_url' => wpheka_request_for_quote()->get_rfq_page_url(),
							)
						);
					} elseif ( 'true' == $result ) {
						wpheka_request_for_quote()->session->set( 'rfq', $rfq_data );

						wp_send_json(
							array(
								'result'       => $result,
								'message'      => $wpheka_rfq_product_added_to_list_message,
								'label_browse' => $wpheka_rfq_product_added_view_browse_list,
								'rfq_page_url' => wpheka_request_for_quote()->get_rfq_page_url(),
							)
						);
					} elseif ( 'false' == $result ) {
						wp_send_json(
							array(
								'result'  => $result,
								'message' => apply_filters( 'wpheka_rfq_product_error_message', __( 'Error occurred while adding product to the quote list.', 'wpheka-request-for-quote' ) ),
							)
						);
					}
				}
			}

			wp_die();
		}

		/**
		 * Update request quote list table data
		 *
		 * @since  1.0
		 * @author WPHEKA
		 */
		public function action_update_rfq_list() {
			if ( ! empty( $_POST['rfq'] ) ) {
                $nonce_value = wc_get_var($_REQUEST['wpheka-rfq-nonce']); // @codingStandardsIgnoreLine.
				if ( wp_verify_nonce( $nonce_value, 'wpheka-rfq' ) ) {

					$posted_data = wp_unslash( $_POST['rfq'] );
					$rfq_data = wpheka_request_for_quote()->get_rfq_data();
					if ( ! empty( $posted_data ) ) {
						foreach ( $posted_data as $rfq_item_key => $rfq_item ) {
							$quantity = absint( $rfq_item['quantity'] );
							$rfq_item_key = sanitize_key( wp_unslash( $rfq_item_key ) );
							if ( ! empty( $rfq_data ) ) {
								if ( array_key_exists( $rfq_item_key, $rfq_data ) ) {
									$rfq_data[ $rfq_item_key ]['quantity'] = $quantity;
								}
							}
						}
						wpheka_request_for_quote()->session->set( 'rfq', $rfq_data );

						$response = array();
						// Get rfq fragment.
						ob_start();
						wc_get_template(
							'request-for-quote.php',
							array(
								'rfq_data' => wpheka_request_for_quote()->get_rfq_data(),
								'atts'     => array(),
							),
							'',
							WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH
						);
						$response['html'] = ob_get_clean();

						wp_send_json_success( $response );
					}
				}
			}
			wp_die();
		}

		/**
		 * Send request quote product list by email
		 *
		 * @since  1.0
		 * @author WPHEKA
		 */
		public function action_send_rfq_list() {
			if ( ! empty( $_POST['rfq_email'] ) ) {
                $nonce_value = wc_get_var($_POST['wpheka-send-quote-request-nonce']); // @codingStandardsIgnoreLine.
				if ( wp_verify_nonce( $nonce_value, 'rfq-send-request' ) ) {
					$name = empty( $_POST['rfq_display_name'] ) ? '' : sanitize_text_field( wp_unslash( $_POST['rfq_display_name'] ) );
					$email    = sanitize_email( wp_unslash( $_POST['rfq_email'] ) );
					$message = empty( $_POST['rfq_message'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['rfq_message'] ) );
					$rfq_data = wpheka_request_for_quote()->get_rfq_data();

					if ( ! empty( $rfq_data ) ) {
						$customer_data = array(
							'name'    => $name,
							'email'   => $email,
							'message' => $message,
						);

						$mail_success = WC()->mailer()->emails['WPHEKA_Rfq_Mail']->trigger( $customer_data );

						if ( $mail_success ) {
							// Clear request quote list.
							wpheka_request_for_quote()->clear_rfq_data();

							wp_send_json_success( array( 'message' => 'Your request has been sent successfully.' ) );
						}
					}
				}
				wp_send_json_error( array( 'message' => 'Something went wrong. Please try again later.' ) );
			}
			wp_die();
		}

		/**
		 * Remove quote product from list
		 *
		 * @since  1.0
		 * @author WPHEKA
		 */
		public function action_remove_item_from_rfq_list() {
			check_ajax_referer( 'wpheka-add-to-quote-ajax-action', 'security' );

			if ( ! empty( $_POST['rfq_item_key'] ) ) {
				$rfq_item_key = sanitize_key( $_POST['rfq_item_key'] );

				$rfq_data = wpheka_request_for_quote()->get_rfq_data();

				if ( array_key_exists( $rfq_item_key, $rfq_data ) ) {
					unset( $rfq_data[ $rfq_item_key ] );

					if ( empty( $rfq_data ) ) {
						wpheka_request_for_quote()->session->set( 'rfq', array() );
					} else {
						wpheka_request_for_quote()->session->set( 'rfq', $rfq_data );
					}
					$rfq_data = wpheka_request_for_quote()->get_rfq_data();
				}

				$response = array();

				// Get rfq fragment.
				ob_start();
				if ( empty( $rfq_data ) ) {
					wc_get_template(
						'request-for-quote-empty.php',
						array(
							'rfq_data' => array(),
							'atts'     => array(),
						),
						'',
						WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH
					);
				} else {
					wc_get_template(
						'request-for-quote.php',
						array(
							'rfq_data' => $rfq_data,
							'atts'     => array(),
						),
						'',
						WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH
					);
				}
				$response['html']              = ob_get_clean();
				$response['hide_message_form'] = empty( $rfq_data ) ? true : false;

				wp_send_json_success( $response );
			}
			wp_die();
		}

		/**
		 * Add to quote in archive pages
		 *
		 * @since  1.0
		 * @author WPHEKA
		 */
		public function action_wpheka_add_to_quote_shop() {
			ob_start();

            // phpcs:disable WordPress.Security.NonceVerification.Missing
			if ( empty( $_POST['product_id'] ) ) {
				wp_die();
			}

			$product_id = absint( $_POST['product_id'] );
			$quantity = empty( $_POST['quantity'] ) ? 1 : absint( $_POST['quantity'] );

			$result                                     = 'false';
			$wpheka_rfq_product_already_in_list_message = apply_filters( 'wpheka_rfq_product_already_in_list_message', __( 'Product already in the quote list.', 'wpheka-request-for-quote' ) );
			$wpheka_rfq_product_added_view_browse_list  = apply_filters( 'wpheka_rfq_product_added_view_browse_list', __( 'Browse the list.', 'wpheka-request-for-quote' ) );
			$wpheka_rfq_product_added_to_list_message   = apply_filters( 'wpheka_rfq_product_added_to_list_message', __( 'Product added to quote list.', 'wpheka-request-for-quote' ) );

			if ( isset( wpheka_request_for_quote()->session ) ) {
				$rfq_data = wpheka_request_for_quote()->get_rfq_data();

				$rfq = array(
					'product_id' => $product_id,
					'quantity'   => $quantity,
				);

				$rfq_item_key = $product_id;

				if ( array_key_exists( $rfq_item_key, $rfq_data ) ) {
					// product already exists.
					$result = 'exists';
				} else {
					$result                  = 'true';
					$rfq_data[ $product_id ] = $rfq;
				}
			}

			if ( 'exists' == $result ) {
				wp_send_json(
					array(
						'result'       => $result,
						'message'      => $wpheka_rfq_product_already_in_list_message,
						'label_browse' => $wpheka_rfq_product_added_view_browse_list,
						'rfq_page_url' => wpheka_request_for_quote()->get_rfq_page_url(),
					)
				);
			} elseif ( 'true' == $result ) {
				wpheka_request_for_quote()->session->set( 'rfq', $rfq_data );

				wp_send_json(
					array(
						'result'       => $result,
						'message'      => $wpheka_rfq_product_added_to_list_message,
						'label_browse' => $wpheka_rfq_product_added_view_browse_list,
						'rfq_page_url' => wpheka_request_for_quote()->get_rfq_page_url(),
					)
				);
			} elseif ( 'false' == $result ) {
				wp_send_json(
					array(
						'result'  => $result,
						'message' => apply_filters( 'wpheka_rfq_product_error_message', __( 'Error occurred while adding product to the quote list.', 'wpheka-request-for-quote' ) ),
					)
				);
			}
		}
	}

endif;

new WPHEKA_Rfq_Ajax();
