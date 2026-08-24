<?php
/**
 * WPHEKA_Rfq_Mail
 *
 * @package WPHEKA_Rfq
 * @author      WPHEKA
 * @link        https://wpheka.com/
 * @since       1.0
 * @version     1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPHEKA_Rfq_Mail' ) ) :

	/**
	 * New Order Email.
	 *
	 * An email sent to the warehouse when a new order is assigned for.
	 *
	 * @class       WPHEKA_Rfq_Mail
	 * @extends     WC_Email
	 */
	class WPHEKA_Rfq_Mail extends WC_Email {


		/**
		 * Customer data.
		 *
		 * @var array cookie name
		 */
		private $customer_data;

		/**
		 * Quote items to render instead of the visitor's session, or null.
		 *
		 * Only set for WooCommerce's email preview. A real send reads the
		 * session, which is where the actual quote lives.
		 *
		 * @since 1.8.2
		 * @var array|null
		 */
		private $rfq_data_override = null;

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'wpheka_rfq_mail';
			$this->title          = __( 'Request For Quote', 'wpheka-request-for-quote' );
			$this->description    = __( 'New request for quote emails are sent to chosen recipient(s) when a new request is received.', 'wpheka-request-for-quote' );
			$this->template_base  = WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH;
			$this->template_html = 'emails/request-for-quote-mail-template.php';
			/*
			 * A real plain-text template, not the HTML one. Pointing both at the
			 * same file meant "Email type: Plain text" mailed a full
			 * <!DOCTYPE html> document as text/plain, and the recipient read the
			 * markup.
			 */
			$this->template_plain = 'emails/plain/request-for-quote-mail-template.php';
			$this->placeholders   = array(
				'{site_title}'   => $this->get_blogname(),
				'{order_date}'   => '',
				'{order_number}' => '',
			);

			// Call parent constructor.
			parent::__construct();

			// Other settings.
			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		}

		/**
		 * Get email subject.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[New Quote Request]', 'wpheka-request-for-quote' );
		}

		/**
		 * Get email heading.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'New quote request', 'wpheka-request-for-quote' );
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * @param  array $customer_data Array of customer data.
		 */
		public function trigger( $customer_data ) {

			$this->customer_data = $customer_data;
			$this->setup_locale();

			/*
			 * Initialised, because the branch below does not always run. With the
			 * email disabled or no recipient set, this returned an undefined
			 * variable -- a PHP warning, and a null the AJAX handler then read as
			 * "sending failed".
			 */
			$return = false;

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$return = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();

			return $return;
		}

		/**
		 * Fill this email with sample content for WooCommerce's email preview.
		 *
		 * Called from the `woocommerce_prepare_email_for_preview` filter, which
		 * exists for exactly this. Without it the preview rendered an email with
		 * no customer and no products -- technically correct, and useless for
		 * judging what the email looks like.
		 *
		 * @since 1.8.2
		 * @return void
		 */
		public function set_preview_data() {
			$this->customer_data = array(
				'name'    => __( 'John Doe', 'wpheka-request-for-quote' ),
				'email'   => 'john.doe@example.com',
				'phone'   => '+1 555 0100',
				'company' => __( 'Example Ltd', 'wpheka-request-for-quote' ),
				'message' => __( 'Could you quote for these quantities, and let me know your lead time?', 'wpheka-request-for-quote' ),
			);

			$this->rfq_data_override = $this->get_preview_rfq_data();
		}

		/**
		 * Sample quote items for the preview.
		 *
		 * Real products from the store, because the template calls
		 * wc_get_product() on each id -- a made-up id would render an empty
		 * table and look like a bug in the template.
		 *
		 * @since 1.8.2
		 * @return array
		 */
		private function get_preview_rfq_data() {
			if ( ! function_exists( 'wc_get_products' ) ) {
				return array();
			}

			$product_ids = wc_get_products(
				array(
					'limit'   => 2,
					'status'  => 'publish',
					'return'  => 'ids',
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);

			$rfq_data = array();
			$quantity = 2;

			foreach ( (array) $product_ids as $product_id ) {
				$rfq_data[ $product_id ] = array(
					'product_id' => $product_id,
					'quantity'   => $quantity,
				);

				$quantity += 3;
			}

			return $rfq_data;
		}

		/**
		 * The quote items this email should render.
		 *
		 * @since 1.8.2
		 * @return array
		 */
		private function get_rfq_items() {
			if ( null !== $this->rfq_data_override ) {
				return $this->rfq_data_override;
			}

			return wpheka_request_for_quote()->get_rfq_data();
		}

		/**
		 * Get content html.
		 *
		 * @access public
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'rfq_data'      => $this->get_rfq_items(),
					'customer_data' => $this->customer_data,
					'email_heading' => $this->get_heading(),
					'sent_to_admin' => true,
					'plain_text'    => false,
					'email'         => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Get content plain.
		 *
		 * @access public
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'rfq_data'      => $this->get_rfq_items(),
					'customer_data' => $this->customer_data,
					'email_heading' => $this->get_heading(),
					'sent_to_admin' => true,
					'plain_text'    => true,
					'email'         => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Initialise settings form fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'    => array(
					'title'   => __( 'Enable/Disable', 'wpheka-request-for-quote' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'wpheka-request-for-quote' ),
					'default' => 'yes',
				),
				'recipient'  => array(
					'title'       => __( 'Recipient(s)', 'wpheka-request-for-quote' ),
					'type'        => 'text',
					/* translators: %s: WP admin email */
					'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'wpheka-request-for-quote' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
					'placeholder' => '',
					'default'     => '',
					'desc_tip'    => true,
				),
				'subject'    => array(
					'title'       => __( 'Subject', 'wpheka-request-for-quote' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: list of placeholders */
					'description' => sprintf( __( 'Available placeholders: %s', 'wpheka-request-for-quote' ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'    => array(
					'title'       => __( 'Email heading', 'wpheka-request-for-quote' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: list of placeholders */
					'description' => sprintf( __( 'Available placeholders: %s', 'wpheka-request-for-quote' ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'email_type' => array(
					'title'       => __( 'Email type', 'wpheka-request-for-quote' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'wpheka-request-for-quote' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}
	}

endif;

return new WPHEKA_Rfq_Mail();
