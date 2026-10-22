<?php
/** Local integration smoke test for individual key management. */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Key management smoke test is local-only.' );
}

global $wpdb;

$repository  = ITSoft_LF_Plugin::instance()->repository();
$suffix      = strtolower( wp_generate_password( 10, false, false ) );
$pool_a      = $repository->create_pool( 'Key management A ' . $suffix, 0 );
$pool_b      = $repository->create_pool( 'Key management B ' . $suffix, 0 );
$keys_table  = $wpdb->prefix . 'itsoft_license_keys';
$events      = $wpdb->prefix . 'itsoft_license_events';
$assignments = $wpdb->prefix . 'itsoft_license_assignments';
$pools       = $wpdb->prefix . 'itsoft_license_pools';
$key_values  = array(
	'KEY-MANAGEMENT-' . strtoupper( $suffix ) . '-0001',
	'KEY-MANAGEMENT-' . strtoupper( $suffix ) . '-0002',
	'KEY-MANAGEMENT-' . strtoupper( $suffix ) . '-0003',
);
$pagination_values = array();
for ( $index = 1; $index <= 21; ++$index ) {
	$pagination_values[] = 'KEY-PAGINATION-' . strtoupper( $suffix ) . '-' . str_pad( (string) $index, 4, '0', STR_PAD_LEFT );
}

try {
	$import = $repository->import_individual_keys( $pool_a, $key_values, 0 );
	if ( 3 !== $import['added'] ) {
		throw new RuntimeException( 'Fixture import failed.' );
	}
	$pagination_import = $repository->import_individual_keys( $pool_b, $pagination_values, 0 );
	if ( 21 !== $pagination_import['added'] ) {
		throw new RuntimeException( 'Pagination fixture import failed.' );
	}

	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$keys_table} WHERE pool_id = %d ORDER BY id ASC", $pool_a ) );
	if ( 3 !== count( $rows ) ) {
		throw new RuntimeException( 'Expected three imported keys.' );
	}

	$list = $repository->query_keys( array( 'pool_id' => $pool_a, 'status' => 'available', 'per_page' => 20 ) );
	if ( 3 !== $list['total'] || 3 !== count( $list['items'] ) ) {
		throw new RuntimeException( 'Pool/status filtering failed.' );
	}
	$page_one = $repository->query_keys( array( 'pool_id' => $pool_b, 'page' => 1, 'per_page' => 20 ) );
	$page_two = $repository->query_keys( array( 'pool_id' => $pool_b, 'page' => 2, 'per_page' => 20 ) );
	if ( 21 !== $page_one['total'] || 2 !== $page_one['total_pages'] || 20 !== count( $page_one['items'] ) || 1 !== count( $page_two['items'] ) ) {
		throw new RuntimeException( 'Pagination failed.' );
	}
	$search = $repository->query_keys( array( 'pool_id' => $pool_a, 'search' => '0001', 'per_page' => 20 ) );
	if ( 1 !== $search['total'] ) {
		throw new RuntimeException( 'Masked suffix search failed.' );
	}

	$key_one_ciphertext = $rows[0]->key_ciphertext;
	$move               = $repository->manage_keys( array( (int) $rows[0]->id ), 'move', $pool_b, 0 );
	if ( 1 !== $move['changed'] || 0 !== $move['errors'] ) {
		throw new RuntimeException( 'Available key move failed.' );
	}
	$moved = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$keys_table} WHERE id = %d", (int) $rows[0]->id ) );
	if ( $pool_b !== (int) $moved->pool_id || $key_one_ciphertext !== $moved->key_ciphertext ) {
		throw new RuntimeException( 'Move changed encrypted data or selected the wrong pool.' );
	}

	$disable = $repository->manage_keys( array( (int) $rows[1]->id ), 'disable', 0, 0 );
	if ( 1 !== $disable['changed'] ) {
		throw new RuntimeException( 'Available key disable failed.' );
	}
	$disabled = $repository->query_keys( array( 'pool_id' => $pool_a, 'status' => 'disabled', 'per_page' => 20 ) );
	if ( 1 !== $disabled['total'] ) {
		throw new RuntimeException( 'Disabled key filter failed.' );
	}

	$order_base = random_int( 910000000, 919000000 );
	$item_base  = random_int( 920000000, 929000000 );
	$assignment = $repository->claim_key_for_unit( $pool_a, $order_base, $item_base, 1, 0 );
	if ( ! $assignment || (int) $rows[2]->id !== (int) $assignment->key_id ) {
		throw new RuntimeException( 'Disabled key was issued or available key was not issued.' );
	}

	$empty = $repository->claim_key_for_unit( $pool_a, $order_base + 1, $item_base + 1, 1, 0 );
	if ( null !== $empty ) {
		throw new RuntimeException( 'Disabled key participated in fulfillment.' );
	}

	$immutable = $repository->manage_keys( array( (int) $rows[2]->id ), 'move', $pool_b, 0 );
	if ( 0 !== $immutable['changed'] || 1 !== $immutable['skipped'] ) {
		throw new RuntimeException( 'Assigned key was not protected from moving.' );
	}
	$assigned_row = $wpdb->get_row( $wpdb->prepare( "SELECT pool_id,status FROM {$keys_table} WHERE id = %d", (int) $rows[2]->id ) );
	if ( $pool_a !== (int) $assigned_row->pool_id || 'assigned' !== $assigned_row->status ) {
		throw new RuntimeException( 'Assigned key changed after rejected move.' );
	}

	$restore = $repository->manage_keys( array( (int) $rows[1]->id ), 'restore', 0, 0 );
	if ( 1 !== $restore['changed'] ) {
		throw new RuntimeException( 'Disabled key restore failed.' );
	}
	$restored_assignment = $repository->claim_key_for_unit( $pool_a, $order_base + 2, $item_base + 2, 1, 0 );
	if ( ! $restored_assignment || (int) $rows[1]->id !== (int) $restored_assignment->key_id ) {
		throw new RuntimeException( 'Restored key did not return to fulfillment.' );
	}

	$success_events = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$events}
			WHERE key_id IN (%d,%d,%d) AND operation IN ('key.moved','key.disabled','key.restored') AND result = 'success'",
			(int) $rows[0]->id,
			(int) $rows[1]->id,
			(int) $rows[2]->id
		)
	);
	$conflicts = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$events} WHERE key_id = %d AND operation = 'key.moved' AND result = 'conflict'",
			(int) $rows[2]->id
		)
	);
	if ( 3 !== $success_events || 1 !== $conflicts ) {
		throw new RuntimeException( 'Expected management audit events were not recorded.' );
	}

	WP_CLI::success( 'Key management smoke passed: list, search, move, disable, restore, immutable assignments and audit.' );
} finally {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$assignments} WHERE pool_id IN (%d,%d)", $pool_a, $pool_b ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$events} WHERE pool_id IN (%d,%d)", $pool_a, $pool_b ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$keys_table} WHERE pool_id IN (%d,%d)", $pool_a, $pool_b ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$pools} WHERE id IN (%d,%d)", $pool_a, $pool_b ) );
}

