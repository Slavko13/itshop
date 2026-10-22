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

define( 'AUTH_KEY',         'local-auth-key-do-not-use-in-production' );
define( 'SECURE_AUTH_KEY',  'local-secure-auth-key-do-not-use-in-production' );
define( 'LOGGED_IN_KEY',    'local-logged-in-key-do-not-use-in-production' );
define( 'NONCE_KEY',        'local-nonce-key-do-not-use-in-production' );
define( 'AUTH_SALT',        'local-auth-salt-do-not-use-in-production' );
define( 'SECURE_AUTH_SALT', 'local-secure-auth-salt-do-not-use-in-production' );
define( 'LOGGED_IN_SALT',   'local-logged-in-salt-do-not-use-in-production' );
define( 'NONCE_SALT',       'local-nonce-salt-do-not-use-in-production' );

$table_prefix = 'wpnr_';

$itsoft_local_url = rtrim( getenv( 'ITSOFT_LOCAL_URL' ) ?: 'http://localhost:8080', '/' );

define( 'WP_HOME', $itsoft_local_url );
define( 'WP_SITEURL', $itsoft_local_url );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', false );
define( 'SCRIPT_DEBUG', true );

// Local traffic must not reach payment, Telegram, Jivo, SMTP or other production services.
define( 'ITSOFT_EXTERNAL_INTEGRATIONS_DISABLED', true );
// Fixed local-only key. Production must supply an independent random 32-byte base64 key.
define( 'ITSOFT_LICENSE_ENCRYPTION_KEY', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' );
// Persistent local fixture storage. Production should point this outside the public web-root.
define( 'ITSOFT_PRIVATE_DISTRIBUTION_DIR', '/var/www/html/wp-content/uploads/itsoft-private-distributions' );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'WP_ACCESSIBLE_HOSTS', '' );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'DISALLOW_FILE_MODS', true );
define( 'DISALLOW_FILE_EDIT', true );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
