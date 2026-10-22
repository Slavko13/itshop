<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Installer {
	public const SCHEMA_VERSION = '1.2.0';
	public const CAPABILITY     = 'manage_itsoft_license_inventory';

	public static function activate(): void {
		self::migrate();
		self::grant_capabilities();
	}

	public static function maybe_migrate(): void {
		if ( self::SCHEMA_VERSION !== get_option( 'itsoft_lf_schema_version' ) ) {
			self::migrate();
		}
	}

	public static function migrate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$pools           = $wpdb->prefix . 'itsoft_license_pools';
		$keys            = $wpdb->prefix . 'itsoft_license_keys';
		$product_pools   = $wpdb->prefix . 'itsoft_license_product_pools';
		$assignments     = $wpdb->prefix . 'itsoft_license_assignments';
		$events          = $wpdb->prefix . 'itsoft_license_events';
		$order_numbers   = $wpdb->prefix . 'itsoft_license_order_numbers';

		$sql = array();
		$sql[] = "CREATE TABLE {$pools} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			slug varchar(190) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			notification_level varchar(20) NOT NULL DEFAULT 'normal',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$keys} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			pool_id bigint(20) unsigned NOT NULL,
			key_ciphertext longtext NOT NULL,
			key_fingerprint char(64) NOT NULL,
			masked_value varchar(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'available',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			reserved_at datetime NULL,
			assigned_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY key_fingerprint (key_fingerprint),
			KEY pool_status (pool_id,status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$product_pools} (
			product_id bigint(20) unsigned NOT NULL,
			pool_id bigint(20) unsigned NOT NULL,
			fulfillment_mode varchar(20) NOT NULL DEFAULT 'manual',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (product_id),
			KEY pool_id (pool_id),
			KEY fulfillment_mode (fulfillment_mode)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$assignments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			key_id bigint(20) unsigned NOT NULL,
			pool_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			order_item_id bigint(20) unsigned NOT NULL,
			unit_index int(10) unsigned NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'assigned',
			assigned_by bigint(20) unsigned NOT NULL DEFAULT 0,
			assigned_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY individual_key (key_id),
			UNIQUE KEY order_item_unit (order_item_id,unit_index),
			KEY order_id (order_id),
			KEY pool_id (pool_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation varchar(64) NOT NULL,
			result varchar(20) NOT NULL,
			key_id bigint(20) unsigned NULL,
			pool_id bigint(20) unsigned NULL,
			order_id bigint(20) unsigned NULL,
			order_item_id bigint(20) unsigned NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			context_json longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY operation_created (operation,created_at),
			KEY key_id (key_id),
			KEY pool_id (pool_id),
			KEY order_id (order_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$order_numbers} (
			order_id bigint(20) unsigned NOT NULL,
			public_number char(10) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (order_id),
			UNIQUE KEY public_number (public_number)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'itsoft_lf_schema_version', self::SCHEMA_VERSION, false );
	}

	private static function grant_capabilities(): void {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( self::CAPABILITY );
			}
		}
	}
}
