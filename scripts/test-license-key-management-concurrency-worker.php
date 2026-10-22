<?php
/** One worker for a concurrent claim-versus-move test. */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Key management concurrency worker is local-only.' );
}

$fixture = get_option( 'itsoft_lf_key_management_concurrency_fixture' );
$mode    = isset( $args[0] ) ? sanitize_key( $args[0] ) : '';
if ( ! is_array( $fixture ) || ! in_array( $mode, array( 'claim', 'move' ), true ) ) {
	WP_CLI::error( 'Invalid key management concurrency fixture or worker mode.' );
}

$repository = ITSoft_LF_Plugin::instance()->repository();
if ( 'claim' === $mode ) {
	$assignment = $repository->claim_key_for_unit(
		(int) $fixture['source_pool'],
		(int) $fixture['order_id'],
		(int) $fixture['item_id'],
		1,
		0
	);
	WP_CLI::log( $assignment ? 'claimed' : 'empty' );
} else {
	$result = $repository->manage_keys( array( (int) $fixture['key_id'] ), 'move', (int) $fixture['target_pool'], 0 );
	WP_CLI::log( 1 === $result['changed'] ? 'moved' : 'skipped' );
}

