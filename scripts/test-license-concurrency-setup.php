<?php
/** Set up one-key fixture for a real multi-process concurrency test. */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Concurrency test is local-only.' );
}

global $wpdb;

$old = get_option( 'itsoft_lf_concurrency_fixture' );
if ( is_array( $old ) && ! empty( $old['pool_id'] ) ) {
	$pool_id = (int) $old['pool_id'];
	$wpdb->delete( $wpdb->prefix . 'itsoft_license_assignments', array( 'pool_id' => $pool_id ), array( '%d' ) );
	$wpdb->delete( $wpdb->prefix . 'itsoft_license_keys', array( 'pool_id' => $pool_id ), array( '%d' ) );
	$wpdb->delete( $wpdb->prefix . 'itsoft_license_events', array( 'pool_id' => $pool_id ), array( '%d' ) );
	$wpdb->delete( $wpdb->prefix . 'itsoft_license_pools', array( 'id' => $pool_id ), array( '%d' ) );
}

$repository = ITSoft_LF_Plugin::instance()->repository();
$suffix     = strtolower( wp_generate_password( 10, false, false ) );
$pool_id    = $repository->create_pool( 'Concurrency smoke ' . $suffix, 0 );
$repository->import_individual_keys( $pool_id, array( 'CONCURRENCY-SMOKE-' . strtoupper( $suffix ) ), 0 );

$fixture = array(
	'pool_id'       => $pool_id,
	'order_base'    => random_int( 700000000, 799000000 ),
	'item_base'     => random_int( 800000000, 899000000 ),
	'attempt_count' => 20,
);
update_option( 'itsoft_lf_concurrency_fixture', $fixture, false );
WP_CLI::success( 'Concurrency fixture ready.' );
