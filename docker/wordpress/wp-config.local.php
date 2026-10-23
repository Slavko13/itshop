<?php

/**
 * Local-only WordPress configuration.
 * Production secrets must never be copied into this file.
 */

define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) ?: 'itsoft_local' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ?: 'itsoft' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ?: 'itsoft_local_only' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ?: 'db:3306' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$itsoft_salt_seed = getenv( 'ITSOFT_WP_SALT' ) ?: 'itsoft-local-salt-do-not-use-on-public-servers';
define( 'AUTH_KEY',         hash_hmac( 'sha256', 'auth-key', $itsoft_salt_seed ) );
define( 'SECURE_AUTH_KEY',  hash_hmac( 'sha256', 'secure-auth-key', $itsoft_salt_seed ) );
define( 'LOGGED_IN_KEY',    hash_hmac( 'sha256', 'logged-in-key', $itsoft_salt_seed ) );
define( 'NONCE_KEY',        hash_hmac( 'sha256', 'nonce-key', $itsoft_salt_seed ) );
define( 'AUTH_SALT',        hash_hmac( 'sha256', 'auth-salt', $itsoft_salt_seed ) );
define( 'SECURE_AUTH_SALT', hash_hmac( 'sha256', 'secure-auth-salt', $itsoft_salt_seed ) );
define( 'LOGGED_IN_SALT',   hash_hmac( 'sha256', 'logged-in-salt', $itsoft_salt_seed ) );
define( 'NONCE_SALT',       hash_hmac( 'sha256', 'nonce-salt', $itsoft_salt_seed ) );

$table_prefix = 'wpnr_';

// Caddy terminates TLS and forwards the original scheme to WordPress.
// Trust this header only because the WordPress port is bound to localhost in HTTPS mode.
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
	$itsoft_forwarded_proto = strtolower( trim( explode( ',', $_SERVER['HTTP_X_FORWARDED_PROTO'] )[0] ) );
	if ( 'https' === $itsoft_forwarded_proto ) {
		$_SERVER['HTTPS'] = 'on';
		$_SERVER['SERVER_PORT'] = 443;
	}
}

$itsoft_local_url = rtrim( getenv( 'ITSOFT_LOCAL_URL' ) ?: 'http://localhost:8080', '/' );

define( 'WP_HOME', $itsoft_local_url );
define( 'WP_SITEURL', $itsoft_local_url );
$itsoft_environment_type = getenv( 'ITSOFT_WP_ENVIRONMENT_TYPE' ) ?: 'local';
define( 'WP_ENVIRONMENT_TYPE', $itsoft_environment_type );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', false );
define( 'SCRIPT_DEBUG', true );

// Local traffic must not reach payment, Telegram, Jivo, SMTP or other production services.
define( 'ITSOFT_EXTERNAL_INTEGRATIONS_DISABLED', '0' !== getenv( 'ITSOFT_EXTERNAL_INTEGRATIONS_DISABLED' ) );
$itsoft_payment_integrations_enabled = '1' === getenv( 'ITSOFT_PAYMENT_INTEGRATIONS_ENABLED' );
define( 'ITSOFT_PAYMENT_INTEGRATIONS_ENABLED', $itsoft_payment_integrations_enabled );
// Preserve this value when restoring a backup or existing encrypted keys become unreadable.
define( 'ITSOFT_LICENSE_ENCRYPTION_KEY', getenv( 'ITSOFT_LICENSE_ENCRYPTION_KEY' ) ?: 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' );
// Persistent local fixture storage. Production should point this outside the public web-root.
define( 'ITSOFT_PRIVATE_DISTRIBUTION_DIR', '/var/www/html/wp-content/uploads/itsoft-private-distributions' );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define(
	'WP_ACCESSIBLE_HOSTS',
	$itsoft_payment_integrations_enabled
		? 'api.yookassa.ru,yookassa.ru,*.yookassa.ru,api.wordpress.org,downloads.wordpress.org'
		: ''
);
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'DISALLOW_FILE_MODS', true );
define( 'DISALLOW_FILE_EDIT', true );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
