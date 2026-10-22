<?php
/** Local diagnostic: list registered WooCommerce payment gateways without secrets. */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Gateway diagnostic is local-only.' );
}

$rows = array();
foreach ( WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway ) {
	$rows[] = array(
		'id'      => (string) $gateway_id,
		'enabled' => (string) $gateway->enabled,
		'title'   => wp_strip_all_tags( (string) $gateway->get_title() ),
	);
}
WP_CLI::log( wp_json_encode( $rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
