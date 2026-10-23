<?php
// mail-enabled.php yes|no -- turn the quote email on or off, as WooCommerce's email screen does.
$settings            = (array) get_option( 'woocommerce_wpheka_rfq_mail_settings', array() );
$settings['enabled'] = 'no' === ( $args[0] ?? 'yes' ) ? 'no' : 'yes';
update_option( 'woocommerce_wpheka_rfq_mail_settings', $settings );
echo 'ok';
