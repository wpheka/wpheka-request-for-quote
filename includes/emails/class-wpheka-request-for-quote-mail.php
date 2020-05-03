<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	
	/** Customer data */
	private $customer_data;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $WPHEKA_Rfq;
		$this->id             = 'wpheka_rfq_mail';
		$this->title          = __( 'Request For Quote', $WPHEKA_Rfq->text_domain );
		$this->description    = __( 'New request for quote emails are sent to chosen recipient(s) when a new request is received.', $WPHEKA_Rfq->text_domain );
		$this->template_base = 	WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH;		
		$this->template_html  = 'emails/request-for-quote-mail-template.php';
		$this->template_plain = 'emails/request-for-quote-mail-template.php';
		$this->placeholders   = array(
			'{site_title}'   => $this->get_blogname(),
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		// Call parent constructor
		parent::__construct();

		// Other settings
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Get email subject.
	 *
	 * @since  3.1.0
	 * @return string
	 */
	public function get_default_subject() {
		global $WPHEKA_Rfq;
		return __( '[New Quote Request]', $WPHEKA_Rfq->text_domain );
	}

	/**
	 * Get email heading.
	 *
	 * @since  3.1.0
	 * @return string
	 */
	public function get_default_heading() {
		global $WPHEKA_Rfq;
		return __( 'New quote request', $WPHEKA_Rfq->text_domain );
	}

	/**
	 * Trigger the sending of this email.
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
		return wc_get_template_html( $this->template_html, array(
		        'rfq_data' => wpheka_request_for_quote()->get_rfq_data(),
				'customer_data' => $this->customer_data,
		        'email_heading' => $this->get_heading(),
		        'sent_to_admin' => true,
		        'plain_text'    => false,
		        'email'         => $this,
		), '', $this->template_base );
	}

	/**
	 * Get content plain.
	 *
	 * @access public
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, array(
			'rfq_data'	=> wpheka_request_for_quote()->get_rfq_data(),
			'customer_data' => $this->customer_data,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => true,
			'plain_text'    => true,
			'email'		=> $this,
		), '', $this->template_base );
	}

	/**
	 * Initialise settings form fields.
	 */
	public function init_form_fields() {
		global $WPHEKA_Rfq;
		$this->form_fields = array(
		'enabled'    => array(
			'title'   => __( 'Enable/Disable', $WPHEKA_Rfq->text_domain ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable this email notification', $WPHEKA_Rfq->text_domain ),
			'default' => 'yes',
		),
		'recipient'  => array(
			'title'       => __( 'Recipient(s)', $WPHEKA_Rfq->text_domain ),
			'type'        => 'text',
			/* translators: %s: WP admin email */
			'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', $WPHEKA_Rfq->text_domain ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
			'placeholder' => '',
			'default'     => '',
			'desc_tip'    => true,
		),
		'subject'    => array(
			'title'       => __( 'Subject', $WPHEKA_Rfq->text_domain ),
			'type'        => 'text',
			'desc_tip'    => true,
			/* translators: %s: list of placeholders */
			'description' => sprintf( __( 'Available placeholders: %s', $WPHEKA_Rfq->text_domain ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
			'placeholder' => $this->get_default_subject(),
			'default'     => '',
		),
		'heading'    => array(
			'title'       => __( 'Email heading', $WPHEKA_Rfq->text_domain ),
			'type'        => 'text',
			'desc_tip'    => true,
			/* translators: %s: list of placeholders */
			'description' => sprintf( __( 'Available placeholders: %s', $WPHEKA_Rfq->text_domain ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
			'placeholder' => $this->get_default_heading(),
			'default'     => '',
		),
		'email_type' => array(
			'title'       => __( 'Email type', $WPHEKA_Rfq->text_domain ),
			'type'        => 'select',
			'description' => __( 'Choose which format of email to send.', $WPHEKA_Rfq->text_domain ),
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