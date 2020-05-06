<?php
/**
 * Template to display save changes button in the sidebar of the setting page
 *
 * @package WPHEKA_Rfq
 * @subpackage WPHEKA_Rfq_Admin
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { // If this file is called directly.
	die( 'No script kiddies please!' );
}
?>
<div class='wpheka-widget'>
	<button class='wpheka-save-changes wpheka-button wpheka-button__large wpheka-button__full plugin-loader' data-progressText='<?php echo esc_attr( __( 'Saving Changes...', 'wpheka-request-for-quote' ) ); ?>' data-completedText='<?php echo esc_attr( __( 'Changes Updated', 'wpheka-request-for-quote' ) ); ?>'><?php esc_html_e( 'Save Changes', 'wpheka-request-for-quote' ); ?></button>
</div>
