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
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'wpheka_rfq_mail';
			$this->title          = __( 'Request For Quote', 'wpheka-request-for-quote' );
			$this->description    = __( 'New request for quote emails are sent to chosen recipient(s) when a new request is received.', 'wpheka-request-for-quote' );
			$this->template_base  = WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH;
			$this->template_html  = 'emails/request-for-quote-mail-template.php';
			$this->template_plain = 'emails/request-for-quote-mail-template.php';
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

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$return = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();

			return $return;
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
					'rfq_data'      => wpheka_request_for_quote()->get_rfq_data(),
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
					'rfq_data'      => wpheka_request_for_quote()->get_rfq_data(),
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
