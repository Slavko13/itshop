<?php
/**
 * Local integration smoke test for the inventory foundation.
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Inventory smoke test is local-only.' );
}

if ( ! class_exists( 'ITSoft_LF_Plugin' ) ) {
	WP_CLI::error( 'ITSOFT License Fulfillment plugin is not active.' );
}

global $wpdb;

$plugin     = ITSoft_LF_Plugin::instance();
$crypto     = $plugin->crypto();
$repository = $plugin->repository();
$suffix     = strtolower( wp_generate_password( 8, false, false ) );
$pool_id    = $repository->create_pool( 'Smoke ' . $suffix, get_current_user_id() );
$key_one    = 'LOCAL-SMOKE-' . strtoupper( $suffix ) . '-0001';
$key_two    = 'LOCAL-SMOKE-' . strtoupper( $suffix ) . '-0002';

$result = $repository->import_individual_keys( $pool_id, array( $key_one, $key_two, $key_one, '' ), get_current_user_id() );
if ( 2 !== $result['added'] || 1 !== $result['duplicates'] || 1 !== $result['invalid'] || 0 !== $result['errors'] ) {
	WP_CLI::error( 'Unexpected import result: ' . wp_json_encode( $result ) );
}

$keys_table = $wpdb->prefix . 'itsoft_license_keys';
$row        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$keys_table} WHERE pool_id = %d ORDER BY id ASC LIMIT 1", $pool_id ) );
if ( ! $row || str_contains( $row->key_ciphertext, $key_one ) ) {
	WP_CLI::error( 'Plaintext key was stored or test row is missing.' );
}
if ( $key_one !== $crypto->decrypt( $row->key_ciphertext ) ) {
	WP_CLI::error( 'Encrypted key cannot be decrypted with the configured local key.' );
}
if ( ! str_ends_with( $row->masked_value, '0001' ) ) {
	WP_CLI::error( 'Masked value does not contain the expected safe suffix.' );
}

$events_table = $wpdb->prefix . 'itsoft_license_events';
$event_count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE pool_id = %d", $pool_id ) );
if ( $event_count < 2 ) {
	WP_CLI::error( 'Expected audit events were not recorded.' );
}

$wpdb->delete( $events_table, array( 'pool_id' => $pool_id ), array( '%d' ) );
$wpdb->delete( $keys_table, array( 'pool_id' => $pool_id ), array( '%d' ) );
$wpdb->delete( $wpdb->prefix . 'itsoft_license_pools', array( 'id' => $pool_id ), array( '%d' ) );

WP_CLI::success( 'Inventory smoke passed: encryption, masking, dedupe and audit events.' );
