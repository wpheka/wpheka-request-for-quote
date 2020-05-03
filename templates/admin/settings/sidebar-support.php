<?php
/**
 * Template to display support box in the sidebar of the setting page
 *
 * @package KinstaMUPlugins
 * @subpackage Cache
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}

?>

<div class='wpheka-box wpheka-widget'>
	<div class='wpheka-box-title-bar'>
		<h3><?php esc_html_e( 'Need Help?', wpheka_request_for_quote()->text_domain ); ?></h3>
	</div>
	<div class="wpheka-box-content wpheka-flex">
		<img class='mr22' src='<?php echo $WPHEKA_Rfq->plugin_url . 'assets/admin/images/wp-heka-logo-settings.svg'; ?>' height='66px'>
		<div>
		<?php
		// Translators: %s '<a href="https://wpheka.com/" target="_blank">Site</a>.
		$content = sprintf( __( 'If you need some help contact us through our %s', wpheka_request_for_quote()->text_domain ), '<a href="https://wpheka.com/" target="_blank">' . __( 'Site', wpheka_request_for_quote()->text_domain ) . '</a>' );

		echo wp_kses(
			$content,
			array(
				'a' => array(
					'href' => true,
					'target' => true,
				),
			)
		);
		?>
		</div>
	</div>
</div>