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

if ( ! class_exists( 'WPHEKA_Rfq_Shortcodes', false ) ) :

	/**
	 * WPHEKA_Rfq_Shortcodes Class.
	 */
	class WPHEKA_Rfq_Shortcodes {


		/**
		 * Initialize WPHEKA_Rfq_Shortcodes.
		 */
		public static function init() {

			add_shortcode( 'wpheka_request_for_quote', array( __CLASS__, 'wpheka_request_for_quote_shortcode' ) );
		}

		/**
		 * Shortcode Wrapper.
		 *
		 * @param string[] $function Callback function.
		 * @param array    $atts     Attributes. Default to empty array.
		 * @param array    $wrapper  Customer wrapper data.
		 *
		 * @return string
		 */
		public static function shortcode_wrapper(
			$function,
			$atts = array(),
			$wrapper = array(
				'class'  => 'woocommerce',
				'before' => null,
				'after'  => null,
			)
		) {
			ob_start();

			echo empty( $wrapper['before'] ) ? '<div class="' . esc_attr( $wrapper['class'] ) . '">' : wp_kses_post( $wrapper['before'] );
			call_user_func( $function, $atts );
			echo empty( $wrapper['after'] ) ? '</div>' : wp_kses_post( $wrapper['after'] );

			return ob_get_clean();
		}

		/**
		 * Request for quote page shortcode.
		 *
		 * @return string
		 */
		public static function wpheka_request_for_quote_shortcode() {
			return self::shortcode_wrapper( array( __CLASS__, 'wpheka_request_for_quote_output' ) );
		}

		/**
		 * Output the wpheka_request_for_quote shortcode.
		 *
		 * @param array $atts Shortcode attributes.
		 */
		public static function wpheka_request_for_quote_output( $atts ) {

			// Constants.
			wc_maybe_define_constant( 'WPHEKA_RFQ_PAGE', true );

			$rfq_data = wpheka_request_for_quote()->get_rfq_data();

			$atts = shortcode_atts( array(), $atts, 'wpheka_request_for_quote' );

			// Check quote items are valid.
			do_action( 'wpheka_check_quote_items' );

			// Rfq content section start.
			do_action( 'wpheka_rfq_content_start' );

			if ( empty( $rfq_data ) ) {
				wc_get_template( 'request-for-quote-empty.php', array(), '', WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH );
			} else {
				wc_get_template(
					'request-for-quote.php',
					array(
						'rfq_data' => $rfq_data,
						'atts'     => $atts,
					),
					'',
					WPHEKA_RFQ_PLUGIN_TEMPLATE_PATH
				);
			}

			// Rfq content section start.
			do_action( 'wpheka_rfq_content_end' );
		}
	}

endif;
