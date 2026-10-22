<?php
/**
 * Local WP-CLI fixture for the protected distribution route.
 * Usage: wp eval-file scripts/test-distribution-security.php setup|regenerate|revoke|expire|cleanup
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$action = isset( $args[0] ) ? (string) $args[0] : 'setup';
$key    = '_itsoft_dist_security_test';

$cleanup = static function () use ( $key ) {
	$fixture = get_option( $key );
	if ( ! is_array( $fixture ) ) {
		return;
	}
	global $wpdb;
	$file_id = absint( $fixture['file_id'] ?? 0 );
	if ( $file_id ) {
		$wpdb->delete( itsoft_dist_tokens_table(), array( 'file_id' => $file_id ), array( '%d' ) );
		$wpdb->delete( itsoft_dist_files_table(), array( 'id' => $file_id ), array( '%d' ) );
	}
	$path = isset( $fixture['path'] ) ? (string) $fixture['path'] : '';
	if ( $path && is_file( $path ) && 0 === strpos( wp_normalize_path( $path ), trailingslashit( wp_normalize_path( itsoft_dist_private_dir() ) ) ) ) {
		unlink( $path );
	}
	delete_option( $key );
};

if ( 'cleanup' === $action ) {
	$cleanup();
	WP_CLI::success( 'Distribution security fixture removed.' );
	return;
}

if ( 'setup' === $action ) {
	$cleanup();
	$ready = itsoft_dist_ensure_private_dir();
	if ( is_wp_error( $ready ) ) {
		WP_CLI::error( $ready->get_error_message() );
	}

	$post_id = (int) get_posts( array( 'post_type' => 'distribution', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0];
	$stored  = 'codex-security-fixture-' . wp_generate_uuid4() . '.zip';
	$path    = trailingslashit( itsoft_dist_private_dir() ) . $stored;
	file_put_contents( $path, 'ITSOFT-PROTECTED-TEST' );

	global $wpdb;
	$now = current_time( 'mysql', true );
	$wpdb->insert(
		itsoft_dist_files_table(),
		array(
			'distribution_id'  => $post_id,
			'position'         => 999,
			'label'            => 'Security test fixture',
			'file_format'      => 'zip',
			'file_size'        => filesize( $path ),
			'source_name'      => 'ITSOFT test',
			'link_mode'        => 'temporary',
			'private_path'     => $stored,
			'original_filename'=> 'itsoft-security-test.zip',
			'status'           => 'active',
			'created_at'       => $now,
			'updated_at'       => $now,
		)
	);
	$file_id = (int) $wpdb->insert_id;
	update_option( $key, array( 'file_id' => $file_id, 'path' => $path ), false );
} else {
	$fixture = get_option( $key );
	if ( ! is_array( $fixture ) || empty( $fixture['file_id'] ) ) {
		WP_CLI::error( 'Run setup first.' );
	}
	$file_id = (int) $fixture['file_id'];
}

if ( 'revoke' === $action ) {
	itsoft_dist_revoke_file_tokens( $file_id );
	WP_CLI::success( 'Active fixture token revoked.' );
	return;
}

$token = itsoft_dist_generate_token( $file_id, 1 );
if ( is_wp_error( $token ) ) {
	WP_CLI::error( $token->get_error_message() );
}

global $wpdb;
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . itsoft_dist_tokens_table() . ' WHERE file_id = %d ORDER BY id DESC LIMIT 1', $file_id ), ARRAY_A );
if ( 'expire' === $action ) {
	$wpdb->update( itsoft_dist_tokens_table(), array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ), array( 'id' => (int) $row['id'] ), array( '%s' ), array( '%d' ) );
}

$url_token = basename( untrailingslashit( (string) wp_parse_url( $token['url'], PHP_URL_PATH ) ) );
$diff      = strtotime( $row['expires_at'] . ' UTC' ) - strtotime( $row['created_at'] . ' UTC' );
echo 'FILE_ID=' . $file_id . PHP_EOL;
echo 'SECURE_URL=' . $token['url'] . PHP_EOL;
echo 'TOKEN_HASHED=' . ( hash( 'sha256', $url_token ) === $row['token_hash'] && $url_token !== $row['token_hash'] ? 'yes' : 'no' ) . PHP_EOL;
echo 'LIFETIME_SECONDS=' . $diff . PHP_EOL;
echo 'PRIVATE_FILE=' . ( is_file( trailingslashit( itsoft_dist_private_dir() ) . itsoft_dist_get_file( $file_id )['private_path'] ) ? 'yes' : 'no' ) . PHP_EOL;
