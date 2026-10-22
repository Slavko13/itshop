<?php
/** One worker process for the concurrency test. */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Concurrency worker is local-only.' );
}

$fixture = get_option( 'itsoft_lf_concurrency_fixture' );
$index   = isset( $args[0] ) ? (int) $args[0] : -1;
if ( ! is_array( $fixture ) || $index < 0 || $index >= (int) $fixture['attempt_count'] ) {
	WP_CLI::error( 'Invalid concurrency fixture or worker index.' );
}

$assignment = ITSoft_LF_Plugin::instance()->repository()->claim_key_for_unit(
	(int) $fixture['pool_id'],
	(int) $fixture['order_base'] + $index,
	(int) $fixture['item_base'] + $index,
	1,
	0
);

WP_CLI::log( $assignment ? 'won' : 'empty' );
