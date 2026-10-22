<?php
/**
 * Local account-domain smoke test.
 *
 * Run inside WordPress with:
 * wp eval-file /workspace/scripts/test-account-flow.php --allow-root
 */

defined( 'ABSPATH' ) || exit( 1 );

if ( ! function_exists( 'wc_create_new_customer' ) ) {
	echo "FAIL WooCommerce is not active.\n";
	exit( 1 );
}

$failures = array();
$user_id  = 0;
$suffix   = strtolower( wp_generate_password( 10, false, false ) );
$email    = 'codex-account-' . $suffix . '@example.test';
$username = 'codex_acc_' . $suffix;
$password = 'Local-Account-' . wp_generate_password( 14, true, false );

$assert = static function ( $condition, $label ) use ( &$failures ) {
	if ( $condition ) {
		echo 'PASS ' . $label . "\n";
		return;
	}

	$failures[] = $label;
	echo 'FAIL ' . $label . "\n";
};

try {
	$assert( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ), 'WooCommerce account registration enabled' );
	$assert( 'yes' === get_option( 'woocommerce_registration_generate_username' ), 'username generation enabled' );
	$assert( 'yes' === get_option( 'woocommerce_registration_generate_password' ), 'password setup email mode enabled' );

	$account_url = wc_get_page_permalink( 'myaccount' );
	$reset_url   = wc_lostpassword_url();
	$assert( str_starts_with( $account_url, home_url( '/' ) ), 'My Account URL is local and canonical' );
	$assert( str_contains( $reset_url, '/lost-password/' ), 'lost-password endpoint configured' );

	$user_id = wc_create_new_customer( $email, $username, $password );
	$assert( ! is_wp_error( $user_id ) && (int) $user_id > 0, 'temporary customer created' );

	if ( is_wp_error( $user_id ) ) {
		throw new RuntimeException( $user_id->get_error_message() );
	}

	$user_id = (int) $user_id;
	$user    = get_user_by( 'id', $user_id );
	$assert( $user instanceof WP_User && in_array( 'customer', $user->roles, true ), 'new account has customer role' );

	$authenticated = wp_authenticate( $username, $password );
	$assert( $authenticated instanceof WP_User && $authenticated->ID === $user_id, 'login credentials authenticate' );

	$reset_key = get_password_reset_key( $user );
	$reset_user = is_wp_error( $reset_key ) ? $reset_key : check_password_reset_key( $reset_key, $user->user_login );
	$assert( $reset_user instanceof WP_User && $reset_user->ID === $user_id, 'password reset key validates' );

	wp_set_current_user( $user_id );
	$menu_items = wc_get_account_menu_items();
	$assert( isset( $menu_items['orders'], $menu_items['licenses'], $menu_items['edit-account'], $menu_items['customer-logout'] ), 'account menu exposes orders, licenses, profile and logout' );
	$assert( ! isset( $menu_items['downloads'] ), 'irrelevant WooCommerce downloads menu is removed' );
	$assert( str_contains( wc_get_account_endpoint_url( 'licenses' ), '/licenses/' ), 'license archive endpoint URL is registered' );

	$_POST['billing_phone'] = '+7 999 111-22-33';
	do_action( 'woocommerce_save_account_details', $user_id );
	unset( $_POST['billing_phone'] );
	$assert( '+7 999 111-22-33' === get_user_meta( $user_id, 'billing_phone', true ), 'profile phone field persists safely' );

	$logout_url = wc_logout_url();
	$has_logout_action = str_contains( $logout_url, 'customer-logout' ) || str_contains( $logout_url, 'action=logout' );
	$assert( $has_logout_action && str_contains( $logout_url, '_wpnonce=' ), 'logout URL contains action and nonce' );
} catch ( Throwable $error ) {
	$failures[] = 'uncaught account test error';
	echo 'FAIL uncaught account test error: ' . $error->getMessage() . "\n";
} finally {
	wp_set_current_user( 0 );
	if ( $user_id > 0 ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
	}
	$assert( false === get_user_by( 'email', $email ), 'temporary customer removed' );
}

if ( $failures ) {
	echo "\n" . count( $failures ) . " account check(s) failed.\n";
	exit( 1 );
}

echo "\nAll account checks passed.\n";
