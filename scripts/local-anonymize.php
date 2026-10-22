<?php
/**
 * Anonymize an imported production database for local development.
 * Run with: wp eval-file /workspace/scripts/local-anonymize.php --allow-root
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Anonymization is allowed only when WP_ENVIRONMENT_TYPE=local.' );
}

global $wpdb;

$table_exists = static function ( string $table ) use ( $wpdb ): bool {
	return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
};

$truncate = static function ( string $table ) use ( $wpdb, $table_exists ): void {
	if ( $table_exists( $table ) ) {
		$wpdb->query( "TRUNCATE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
};

// Remove obvious spam first, then anonymize retained reviews/comments.
$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
$wpdb->query(
	"DELETE cm FROM {$wpdb->commentmeta} cm
	LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
	WHERE c.comment_ID IS NULL OR cm.meta_key LIKE 'akismet_%'"
);
$wpdb->query(
	"UPDATE {$wpdb->comments}
	SET comment_author = CONCAT('Reviewer ', comment_ID),
		comment_author_email = CONCAT('reviewer+', comment_ID, '@example.test'),
		comment_author_url = '',
		comment_author_IP = '0.0.0.0',
		comment_agent = ''"
);

// Preserve roles/order relations while making all imported accounts unusable and anonymous.
$user_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->users}" );
foreach ( $user_ids as $user_id_raw ) {
	$user_id = (int) $user_id_raw;
	$wpdb->update(
		$wpdb->users,
		array(
			'user_login'          => 'local_user_' . $user_id,
			'user_nicename'       => 'local-user-' . $user_id,
			'user_email'          => 'user+' . $user_id . '@example.test',
			'user_url'            => '',
			'user_activation_key' => '',
			'display_name'        => 'Local User ' . $user_id,
			'user_pass'           => wp_hash_password( wp_generate_password( 64, true, true ) ),
		),
		array( 'ID' => $user_id )
	);

	$meta_replacements = array(
		'first_name'       => 'Local',
		'last_name'        => 'User ' . $user_id,
		'nickname'         => 'local_user_' . $user_id,
		'description'      => '',
		'billing_first_name'  => 'Local',
		'billing_last_name'   => 'User ' . $user_id,
		'billing_company'     => '',
		'billing_address_1'   => 'Test address',
		'billing_address_2'   => '',
		'billing_city'        => 'Moscow',
		'billing_state'       => '',
		'billing_postcode'    => '101000',
		'billing_country'     => 'RU',
		'billing_email'       => 'user+' . $user_id . '@example.test',
		'billing_phone'       => '+70000000000',
		'shipping_first_name' => 'Local',
		'shipping_last_name'  => 'User ' . $user_id,
		'shipping_company'    => '',
		'shipping_address_1'  => 'Test address',
		'shipping_address_2'  => '',
		'shipping_city'       => 'Moscow',
		'shipping_state'      => '',
		'shipping_postcode'   => '101000',
		'shipping_country'    => 'RU',
	);

	foreach ( $meta_replacements as $meta_key => $meta_value ) {
		if ( metadata_exists( 'user', $user_id, $meta_key ) ) {
			update_user_meta( $user_id, $meta_key, $meta_value );
		}
	}

	delete_user_meta( $user_id, 'session_tokens' );
	delete_user_meta( $user_id, '_application_passwords' );
}

// HPOS order data.
$orders_table = $wpdb->prefix . 'wc_orders';
if ( $table_exists( $orders_table ) ) {
	$wpdb->query(
		"UPDATE `{$orders_table}`
		SET billing_email = CONCAT('order+', id, '@example.test'),
			transaction_id = '', ip_address = '0.0.0.0', user_agent = '', customer_note = ''"
	); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$addresses_table = $wpdb->prefix . 'wc_order_addresses';
if ( $table_exists( $addresses_table ) ) {
	$wpdb->query(
		"UPDATE `{$addresses_table}`
		SET first_name = 'Local', last_name = CONCAT('Order ', order_id), company = '',
			address_1 = 'Test address', address_2 = '', city = 'Moscow', state = '',
			postcode = '101000', country = 'RU',
			email = CONCAT('order+', order_id, '@example.test'), phone = '+70000000000'"
	); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$customer_lookup_table = $wpdb->prefix . 'wc_customer_lookup';
if ( $table_exists( $customer_lookup_table ) ) {
	$wpdb->query(
		"UPDATE `{$customer_lookup_table}`
		SET username = CONCAT('local_customer_', customer_id), first_name = 'Local',
			last_name = CONCAT('Customer ', customer_id),
			email = CONCAT('customer+', customer_id, '@example.test'),
			postcode = '101000', city = 'Moscow', state = ''"
	); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Legacy order/customer metadata.
$legacy_meta = array(
	'_billing_first_name'  => 'Local',
	'_billing_last_name'   => 'Customer',
	'_billing_company'     => '',
	'_billing_address_1'   => 'Test address',
	'_billing_address_2'   => '',
	'_billing_city'        => 'Moscow',
	'_billing_state'       => '',
	'_billing_postcode'    => '101000',
	'_billing_country'     => 'RU',
	'_billing_phone'       => '+70000000000',
	'_shipping_first_name' => 'Local',
	'_shipping_last_name'  => 'Customer',
	'_shipping_company'    => '',
	'_shipping_address_1'  => 'Test address',
	'_shipping_address_2'  => '',
	'_shipping_city'       => 'Moscow',
	'_shipping_state'      => '',
	'_shipping_postcode'   => '101000',
	'_shipping_country'    => 'RU',
);
foreach ( $legacy_meta as $meta_key => $meta_value ) {
	$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $meta_value ), array( 'meta_key' => $meta_key ) );
}
$wpdb->query(
	"UPDATE {$wpdb->postmeta}
	SET meta_value = CONCAT('order+', post_id, '@example.test')
	WHERE meta_key = '_billing_email'"
);

$wpdb->query(
	"UPDATE {$wpdb->posts}
	SET post_excerpt = '', post_password = ''
	WHERE post_type IN ('shop_order', 'shop_order_refund')"
);

// Remove imported sessions, remote credentials, webhooks, payment tokens and queued jobs.
foreach (
	array(
		'woocommerce_sessions',
		'woocommerce_api_keys',
		'woocommerce_payment_tokenmeta',
		'woocommerce_payment_tokens',
		'wc_webhooks',
		'woocommerce_log',
		'yookassa_payment',
		'sa_captured_wc_fields',
		'smsalert_booking_reminder',
		'smsalert_renewal_reminders',
		'actionscheduler_logs',
		'actionscheduler_actions',
		'actionscheduler_claims',
		'actionscheduler_groups',
	) as $table_suffix
) {
	$truncate( $wpdb->prefix . $table_suffix );
}

$download_log_table = $wpdb->prefix . 'wc_download_log';
if ( $table_exists( $download_log_table ) ) {
	$wpdb->query( "UPDATE `{$download_log_table}` SET user_ip_address = '0.0.0.0'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$permissions_table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
if ( $table_exists( $permissions_table ) ) {
	$wpdb->query(
		"UPDATE `{$permissions_table}`
		SET user_email = CONCAT('download+', permission_id, '@example.test'), order_key = ''"
	); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete provider credentials/settings from the local copy and force every gateway off except local COD.
$credential_option_patterns = array(
	'wordpress_api_key',
	'smsalert_%',
	'%smtp%',
	'%telegram%',
	'woocommerce_yookassa_%',
);
foreach ( $credential_option_patterns as $pattern ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
}

$gateway_options = $wpdb->get_results(
	"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce\_%\_settings'",
	ARRAY_A
);
foreach ( $gateway_options as $gateway_option ) {
	$settings = maybe_unserialize( $gateway_option['option_value'] );
	if ( ! is_array( $settings ) ) {
		continue;
	}

	$is_cod              = 'woocommerce_cod_settings' === $gateway_option['option_name'];
	$settings['enabled'] = $is_cod ? 'yes' : 'no';

	foreach ( array_keys( $settings ) as $setting_key ) {
		if ( preg_match( '/(secret|token|password|api[_-]?key|shop[_-]?id|merchant)/i', (string) $setting_key ) ) {
			$settings[ $setting_key ] = '';
		}
	}

	update_option( $gateway_option['option_name'], $settings, false );
}

delete_option( 'cron' );

// Create one known local administrator after all imported accounts have been locked.
$local_admin = get_user_by( 'login', 'itsoft_admin' );
if ( $local_admin ) {
	wp_update_user(
		array(
			'ID'           => $local_admin->ID,
			'user_email'   => 'admin@itsoft.local',
			'display_name' => 'ITSOFT Local Admin',
			'user_pass'    => 'local-only-change-me',
			'role'         => 'administrator',
		)
	);
} else {
	$local_admin_id = wp_create_user( 'itsoft_admin', 'local-only-change-me', 'admin@itsoft.local' );
	if ( is_wp_error( $local_admin_id ) ) {
		WP_CLI::error( $local_admin_id->get_error_message() );
	}
	( new WP_User( $local_admin_id ) )->set_role( 'administrator' );
}

update_option( 'admin_email', 'admin@itsoft.local' );
update_option( 'woocommerce_email_from_address', 'store@itsoft.local' );
update_option( 'woocommerce_email_from_name', 'ITSOFT Local' );
update_option( 'itsoft_local_database_anonymized_at', gmdate( 'c' ) );

wp_cache_flush();
WP_CLI::success( 'Imported database anonymized and external credentials removed.' );
