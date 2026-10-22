<?php
/** Set up one-key fixture for a concurrent claim-versus-move test. */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Key management concurrency test is local-only.' );
}

global $wpdb;

$old = get_option( 'itsoft_lf_key_management_concurrency_fixture' );
if ( is_array( $old ) && ! empty( $old['source_pool'] ) && ! empty( $old['target_pool'] ) ) {
	$pool_ids = array( (int) $old['source_pool'], (int) $old['target_pool'] );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}itsoft_license_assignments WHERE pool_id IN (%d,%d)", ...$pool_ids ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}itsoft_license_events WHERE pool_id IN (%d,%d)", ...$pool_ids ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}itsoft_license_keys WHERE pool_id IN (%d,%d)", ...$pool_ids ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}itsoft_license_pools WHERE id IN (%d,%d)", ...$pool_ids ) );
}

$repository  = ITSoft_LF_Plugin::instance()->repository();
$suffix      = strtolower( wp_generate_password( 10, false, false ) );
$source_pool = $repository->create_pool( 'Key race source ' . $suffix, 0 );
$target_pool = $repository->create_pool( 'Key race target ' . $suffix, 0 );
$repository->import_individual_keys( $source_pool, array( 'KEY-RACE-' . strtoupper( $suffix ) ), 0 );
$key_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}itsoft_license_keys WHERE pool_id = %d", $source_pool ) );

update_option(
	'itsoft_lf_key_management_concurrency_fixture',
	array(
		'source_pool' => $source_pool,
		'target_pool' => $target_pool,
		'key_id'      => $key_id,
		'order_id'    => random_int( 930000000, 939000000 ),
		'item_id'     => random_int( 940000000, 949000000 ),
	),
	false
);

WP_CLI::success( 'Key management concurrency fixture ready.' );

