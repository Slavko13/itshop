<?php
/** Verify and clean the concurrent claim-versus-move fixture. */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Key management concurrency verification is local-only.' );
}

global $wpdb;

$fixture = get_option( 'itsoft_lf_key_management_concurrency_fixture' );
if ( ! is_array( $fixture ) || empty( $fixture['key_id'] ) ) {
	WP_CLI::error( 'Key management concurrency fixture is missing.' );
}

$source_pool = (int) $fixture['source_pool'];
$target_pool = (int) $fixture['target_pool'];
$key_id      = (int) $fixture['key_id'];
$assignments = $wpdb->prefix . 'itsoft_license_assignments';
$keys        = $wpdb->prefix . 'itsoft_license_keys';
$events      = $wpdb->prefix . 'itsoft_license_events';
$pools       = $wpdb->prefix . 'itsoft_license_pools';

try {
	$key              = $wpdb->get_row( $wpdb->prepare( "SELECT pool_id,status FROM {$keys} WHERE id = %d", $key_id ) );
	$assignment_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$assignments} WHERE key_id = %d", $key_id ) );
	$assign_success   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events} WHERE key_id = %d AND operation = 'key.assigned' AND result = 'success'", $key_id ) );
	$move_success     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events} WHERE key_id = %d AND operation = 'key.moved' AND result = 'success'", $key_id ) );
	$claimed          = $key && 1 === $assignment_count && 1 === $assign_success && 0 === $move_success && $source_pool === (int) $key->pool_id && 'assigned' === $key->status;
	$moved            = $key && 0 === $assignment_count && 0 === $assign_success && 1 === $move_success && $target_pool === (int) $key->pool_id && 'available' === $key->status;

	if ( ! $claimed && ! $moved ) {
		throw new RuntimeException(
			sprintf(
				'Claim/move invariant failed: assignments=%d assigned_events=%d moved_events=%d pool=%d status=%s.',
				$assignment_count,
				$assign_success,
				$move_success,
				$key ? (int) $key->pool_id : 0,
				$key ? $key->status : 'missing'
			)
		);
	}

	WP_CLI::success( 'Key management concurrency passed: claim and move could not both win.' );
} finally {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$assignments} WHERE pool_id IN (%d,%d)", $source_pool, $target_pool ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$events} WHERE pool_id IN (%d,%d)", $source_pool, $target_pool ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$keys} WHERE pool_id IN (%d,%d)", $source_pool, $target_pool ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pools} WHERE id IN (%d,%d)", $source_pool, $target_pool ) );
	delete_option( 'itsoft_lf_key_management_concurrency_fixture' );
}

