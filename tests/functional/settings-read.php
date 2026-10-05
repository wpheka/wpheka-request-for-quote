<?php
// settings-read.php <key> -- what the plugin reads for one setting, as JSON.
echo wp_json_encode( wpheka_request_for_quote()->get_settings( $args[0] ) );
