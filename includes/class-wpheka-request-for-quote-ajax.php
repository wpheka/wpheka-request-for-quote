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

            // Save plugin data
            add_action( 'wp_ajax_save_wpheka_rfq_plugin_data', array( $this, 'action_save_wpheka_rfq_plugin_data' ) );

            // Save product to add to quote list
            add_action( 'wp_ajax_wpheka_add_to_quote', array( $this, 'action_wpheka_add_to_quote' ) );
            add_action( 'wp_ajax_nopriv_wpheka_add_to_quote', array( $this, 'action_wpheka_add_to_quote' ) );
			
            add_action( 'wp_ajax_wpheka_add_to_quote_shop', array( $this, 'action_wpheka_add_to_quote_shop' ) );
            add_action( 'wp_ajax_nopriv_wpheka_add_to_quote_shop', array( $this, 'action_wpheka_add_to_quote_shop' ) );

            // Update quote product list
            add_action( 'wp_ajax_update_rfq_list', array( $this, 'action_update_rfq_list' ) );
            add_action( 'wp_ajax_nopriv_update_rfq_list', array( $this, 'action_update_rfq_list' ) );

            // Remove item from rfq list
            add_action( 'wp_ajax_remove_item_from_rfq_list', array( $this, 'action_remove_item_from_rfq_list' ) );
            add_action( 'wp_ajax_nopriv_remove_item_from_rfq_list', array( $this, 'action_remove_item_from_rfq_list' ) );

            // Mail quote product list
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
            update_option('wpheka_rfq_general_settings', $_POST);
            update_option( 'wpheka_request_for_quote_page_id', $_POST['wpheka_request_for_quote_page_id'] );
            wp_send_json_success();
            wp_die();
        }

        /**
         * AJAX Action to add product to quote list
         *
         * @return void
         */
        public function action_wpheka_add_to_quote() {
            
            check_ajax_referer( 'wpheka-add-to-quote-ajax-action', 'security', false );

            if( !empty($_POST) ) {

                if ( isset( wpheka_request_for_quote()->session ) ) {

                    $rfq_data = wpheka_request_for_quote()->get_rfq_data();

                    $wpheka_rfq_product_already_in_list_message = apply_filters( 'wpheka_rfq_product_already_in_list_message', __( 'Product already in the quote list.', wpheka_request_for_quote()->text_domain ) );
                    $wpheka_rfq_product_added_view_browse_list = apply_filters( 'wpheka_rfq_product_added_view_browse_list', __( 'Browse the list.', wpheka_request_for_quote()->text_domain ) );
                    $wpheka_rfq_product_added_to_list_message = apply_filters( 'wpheka_rfq_product_added_to_list_message', __( 'Product added to quote list.', wpheka_request_for_quote()->text_domain ) );

                    $result = 'false';

                    if ( isset( $_POST['variation_id'] ) ) {

                        $rfq = array(
                            'product_id'   => $_POST['product_id'],
                            'variation_id' => $_POST['variation_id'],
                            'quantity'     => ( isset( $_POST['quantity'] ) ) ? (int) $_POST['quantity'] : 1,
                        );

                        $variations = array();

                        foreach ( $_POST as $key => $value ) {

                            if ( stripos( $key, 'attribute' ) !== false ) {
                                $variations[ $key ] = $value;
                            }
                        }

                        $rfq ['variations'] = $variations;

                        $rfq_item_key = $_POST['product_id'] . '-' . $_POST['variation_id'];

                        if ( array_key_exists( $rfq_item_key, $rfq_data) ) {
                            // product already exists
                            $result = 'exists';
                        }else{
                            $rfq_data[ $rfq_item_key ] = $rfq;
                            $result = 'true';
                        }

                    }else{

                        $rfq = array(
                            'product_id' => $_POST['product_id'],
                            'quantity'   => ( isset( $_POST['quantity'] ) ) ? (int) $_POST['quantity'] : 1,
                        );

                        $rfq_item_key = $_POST['product_id'];

                        if ( array_key_exists( $rfq_item_key, $rfq_data) ) {
                            // product already exists
                            $result = 'exists';
                        }else{
                            $result = 'true';
                            $rfq_data[ $_POST['product_id'] ] = $rfq;
                        }

                    }

                    if( $result == 'exists' ) {

                        wp_send_json(
                            array(
                                'result'       => $result,
                                'message'      => $wpheka_rfq_product_already_in_list_message,
                                'label_browse' => $wpheka_rfq_product_added_view_browse_list,
                                'rfq_page_url'      => wpheka_request_for_quote()->get_rfq_page_url(),
                            )
                        );

                    } elseif( $result == 'true' ) {
                        
                        wpheka_request_for_quote()->session->set( 'rfq', $rfq_data);

                        wp_send_json(
                            array(
                                'result'       => $result,
                                'message'      => $wpheka_rfq_product_added_to_list_message,
                                'label_browse' => $wpheka_rfq_product_added_view_browse_list,
                                'rfq_page_url'  => wpheka_request_for_quote()->get_rfq_page_url(),
                            )
                        );

                    } elseif( $result == 'false' ) {
                        wp_send_json(
                            array(
                                'result'       => $result,
                                'message'      => apply_filters( 'wpheka_rfq_product_error_message', __( 'Error occurred while adding product to the quote list.', wpheka_request_for_quote()->text_domain ) ),
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
            if(!empty($_POST)) {
                $nonce_value = wc_get_var( $_REQUEST['wpheka-rfq-nonce'] ); // @codingStandardsIgnoreLine.
                if ( wp_verify_nonce( $nonce_value, 'wpheka-rfq' ) ) {
                    $posted_data = $_POST['rfq'];
                    $rfq_data = wpheka_request_for_quote()->get_rfq_data();
                    if( !empty($posted_data) ) {
                        foreach ( $posted_data as $rfq_item_key => $rfq_item ) {
                            $quantity = (int) $rfq_item['quantity'];
                            if( !empty($rfq_data) ) {
                                if ( array_key_exists( $rfq_item_key, $rfq_data) ) {
                                    $rfq_data[$rfq_item_key]['quantity'] = $quantity;
                                }
                            }

                        }
                        wpheka_request_for_quote()->session->set( 'rfq', $rfq_data);

                        $response = array();
                        // Get rfq fragment.
                        ob_start();
                        wc_get_template( 'request-for-quote.php', array( 'rfq_data' => wpheka_request_for_quote()->get_rfq_data(), 'atts' => array()), '', WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH );
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
            if(!empty($_POST)) {
                $nonce_value = wc_get_var( $_REQUEST['wpheka-send-quote-request-nonce'] ); // @codingStandardsIgnoreLine.
                if ( wp_verify_nonce( $nonce_value, 'rfq-send-request' ) ) {

                    $name = sanitize_text_field( $_REQUEST['rfq_display_name'] );
                    $email = sanitize_text_field( $_REQUEST['rfq_email'] );
                    $message = sanitize_text_field( $_REQUEST['rfq_message'] );
                    $rfq_data = wpheka_request_for_quote()->get_rfq_data();
					
					if( !empty( $rfq_data ) ) {
						$customer_data = array(
										  'name'	=> $name,
										  'email'	=> $email,
										  'message'	=> $message
										  );
	
						$mail_success = WC()->mailer()->emails['WPHEKA_Rfq_Mail']->trigger( $customer_data );
						
						if( $mail_success ) {
							// Clear request quote list
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

            check_ajax_referer( 'wpheka-add-to-quote-ajax-action', 'security', false );

            if(!empty($_POST['rfq_item_key'])) {

                $rfq_item_key = trim($_POST['rfq_item_key']);

                $rfq_data = wpheka_request_for_quote()->get_rfq_data();
				
				if( array_key_exists( $rfq_item_key, $rfq_data) ) {
					unset( $rfq_data[$rfq_item_key] );
					
					if( empty( $rfq_data ) ) {
						wpheka_request_for_quote()->session->set( 'rfq', array() );
					}else{
						wpheka_request_for_quote()->session->set( 'rfq', $rfq_data);
					}
					$rfq_data = wpheka_request_for_quote()->get_rfq_data();
				}

                $response = array();             

                // Get rfq fragment.
                ob_start();
                if( empty($rfq_data) ) {
                    wc_get_template( 'request-for-quote-empty.php', array( 'rfq_data' => array(), 'atts' => array()), '', WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH );
                }else{
                    wc_get_template( 'request-for-quote.php', array( 'rfq_data' => $rfq_data, 'atts' => array()), '', WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH );
                }
                $response['html'] = ob_get_clean();
				$response['hide_message_form'] = empty($rfq_data) ? true : false;

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
			
			$result = 'false';
			$wpheka_rfq_product_already_in_list_message = apply_filters( 'wpheka_rfq_product_already_in_list_message', __( 'Product already in the quote list.', wpheka_request_for_quote()->text_domain ) );
			$wpheka_rfq_product_added_view_browse_list = apply_filters( 'wpheka_rfq_product_added_view_browse_list', __( 'Browse the list.', wpheka_request_for_quote()->text_domain ) );
            $wpheka_rfq_product_added_to_list_message = apply_filters( 'wpheka_rfq_product_added_to_list_message', __( 'Product added to quote list.', wpheka_request_for_quote()->text_domain ) );
			
			if ( isset( wpheka_request_for_quote()->session ) ) {
			
				$rfq_data = wpheka_request_for_quote()->get_rfq_data();
				
				$rfq = array(
					'product_id' => $_POST['product_id'],
					'quantity'   => ( isset( $_POST['quantity'] ) ) ? (int) $_POST['quantity'] : 1,
				);
				
				$rfq_item_key = $_POST['product_id'];
				
				if ( array_key_exists( $rfq_item_key, $rfq_data) ) {
					// product already exists
					$result = 'exists';
				}else{
					$result = 'true';
					$rfq_data[ $_POST['product_id'] ] = $rfq;
				}
				
			}
			
			if( $result == 'exists' ) {

				wp_send_json(
					array(
						'result'       => $result,
						'message'      => $wpheka_rfq_product_already_in_list_message,
						'label_browse' => $wpheka_rfq_product_added_view_browse_list,
						'rfq_page_url'	=> wpheka_request_for_quote()->get_rfq_page_url(),
					)
				);

			} elseif( $result == 'true' ) {
				
				wpheka_request_for_quote()->session->set( 'rfq', $rfq_data);

				wp_send_json(
					array(
						'result'       => $result,
						'message'      => $wpheka_rfq_product_added_to_list_message,
						'label_browse' => $wpheka_rfq_product_added_view_browse_list,
						'rfq_page_url'	=> wpheka_request_for_quote()->get_rfq_page_url(),
					)
				);

			} elseif( $result == 'false' ) {
				wp_send_json(
					array(
						'result'       => $result,
						'message'      => apply_filters( 'wpheka_rfq_product_error_message', __( 'Error occurred while adding product to the quote list.', wpheka_request_for_quote()->text_domain ) ),
					)
				);
			}
			
		}

	}

endif;

new WPHEKA_Rfq_Ajax();