<?php
/** Verify and clean the multi-process concurrency fixture. */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Concurrency verification is local-only.' );
}

global $wpdb;

$fixture = get_option( 'itsoft_lf_concurrency_fixture' );
if ( ! is_array( $fixture ) || empty( $fixture['pool_id'] ) ) {
	WP_CLI::error( 'Concurrency fixture is missing.' );
}

$pool_id    = (int) $fixture['pool_id'];
$assignments = $wpdb->prefix . 'itsoft_license_assignments';
$keys        = $wpdb->prefix . 'itsoft_license_keys';
$events      = $wpdb->prefix . 'itsoft_license_events';
$pools       = $wpdb->prefix . 'itsoft_license_pools';

try {
	$assignment_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$assignments} WHERE pool_id = %d", $pool_id ) );
	$assigned_keys    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$keys} WHERE pool_id = %d AND status = 'assigned'", $pool_id ) );
	$available_keys   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$keys} WHERE pool_id = %d AND status = 'available'", $pool_id ) );
	$success_events   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events} WHERE pool_id = %d AND operation = 'key.assigned' AND result = 'success'", $pool_id ) );

	if ( 1 !== $assignment_count || 1 !== $assigned_keys || 0 !== $available_keys || 1 !== $success_events ) {
		throw new RuntimeException(
			sprintf(
				'Concurrency invariant failed: assignments=%d assigned=%d available=%d success_events=%d.',
				$assignment_count,
				$assigned_keys,
				$available_keys,
				$success_events
			)
		);
	}

	WP_CLI::success( 'Concurrency smoke passed: 20 independent attempts, one key, exactly one assignment.' );
} finally {
	$wpdb->delete( $assignments, array( 'pool_id' => $pool_id ), array( '%d' ) );
	$wpdb->delete( $keys, array( 'pool_id' => $pool_id ), array( '%d' ) );
	$wpdb->delete( $events, array( 'pool_id' => $pool_id ), array( '%d' ) );
	$wpdb->delete( $pools, array( 'id' => $pool_id ), array( '%d' ) );
	delete_option( 'itsoft_lf_concurrency_fixture' );
}
