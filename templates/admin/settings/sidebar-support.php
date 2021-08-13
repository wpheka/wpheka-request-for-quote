<?php
/**
 * Template to display support box in the sidebar of the setting page
 *
 * @package WPHEKA_Rfq
 * @subpackage WPHEKA_Rfq_Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}
?>
<div class='wpheka-box wpheka-widget'>
	<div class='wpheka-box-title-bar'>
		<h3><?php esc_html_e( 'Need Help?', 'wpheka-request-for-quote' ); ?></h3>
	</div>
	<div class="wpheka-box-content wpheka-flex">
		<img class='mr22' src='<?php echo esc_url( $wp_heka_rfq->plugin_url . 'assets/admin/images/wp-heka-logo-settings.svg' ); ?>' height='66px'>
		<div>
		<?php
		// Translators: %s '<a href="https://wpheka.com/" target="_blank">Site</a>.
		$content = sprintf( __( 'If you need some help contact us through our %s', 'wpheka-request-for-quote' ), '<a href="https://www.wpheka.com/contact" target="_blank">' . __( 'Site', 'wpheka-request-for-quote' ) . '</a>' );

		echo wp_kses(
			$content,
			array(
				'a' => array(
					'href'   => true,
					'target' => true,
				),
			)
		);
		?>
		</div>
	</div>
</div>
