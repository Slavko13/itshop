<?php
/**
 * Local smoke test for guest checkout and email safeguards.
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Checkout assurance smoke test is local-only.' );
}

if ( ! class_exists( 'ITSoft_LF_Checkout' ) || ! function_exists( 'wc_get_checkout_url' ) ) {
	WP_CLI::error( 'The fulfillment plugin and WooCommerce must be active.' );
}

if ( 'yes' !== get_option( 'woocommerce_enable_guest_checkout' ) ) {
	WP_CLI::error( 'Guest checkout is disabled.' );
}

$checkout = new ITSoft_LF_Checkout();
$fields   = $checkout->add_classic_confirm_field( array( 'billing' => array() ) );
if ( empty( $fields['billing']['billing_email_confirm']['required'] ) ) {
	WP_CLI::error( 'Classic checkout confirmation field is missing or optional.' );
}

if ( ! is_wp_error( $checkout->validate_email_value( 'not-an-email' ) ) ) {
	WP_CLI::error( 'Invalid repeated email passed server validation.' );
}
if ( null !== $checkout->validate_email_value( '  Buyer@Example.COM ' ) ) {
	WP_CLI::error( 'Valid repeated email failed server validation.' );
}
if ( 'buyer@example.com' !== $checkout->sanitize_email_value( '  Buyer@Example.COM ' ) ) {
	WP_CLI::error( 'Email normalization is incorrect.' );
}

$mismatch = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
$mismatch->set_param( 'billing_address', array( 'email' => 'buyer@example.com' ) );
$mismatch->set_param( 'additional_fields', array( 'itsoft/confirm-email' => 'other@example.com' ) );
$result = $checkout->validate_blocks_request( null, null, $mismatch );
if ( ! is_wp_error( $result ) || 'itsoft_checkout_email_mismatch' !== $result->get_error_code() ) {
	WP_CLI::error( 'Blocks checkout did not reject mismatching email values.' );
}

$match = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
$match->set_param( 'billing_address', array( 'email' => 'Buyer@Example.COM' ) );
$match->set_param( 'additional_fields', array( 'itsoft/confirm-email' => 'buyer@example.com' ) );
if ( null !== $checkout->validate_blocks_request( null, null, $match ) ) {
	WP_CLI::error( 'Blocks checkout rejected matching normalized email values.' );
}

$test_users = get_users( array( 'number' => 1 ) );
if ( empty( $test_users ) || ! $test_users[0] instanceof WP_User || ! is_email( $test_users[0]->user_email ) ) {
	WP_CLI::error( 'No local user with a valid account email is available for authenticated checkout checks.' );
}

$original_user_id = get_current_user_id();
$account_user     = $test_users[0];
$account_email    = strtolower( sanitize_email( $account_user->user_email ) );
wp_set_current_user( $account_user->ID );

$spoofed = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
$spoofed->set_param( 'billing_address', array( 'email' => 'changed@example.com' ) );
$spoofed->set_param( 'additional_fields', array( 'itsoft/confirm-email' => 'changed@example.com' ) );
$spoofed_result = $checkout->validate_blocks_request( null, null, $spoofed );
if ( ! is_wp_error( $spoofed_result ) || 'itsoft_checkout_email_mismatch' !== $spoofed_result->get_error_code() ) {
	WP_CLI::error( 'Authenticated Blocks checkout accepted an email different from the account email.' );
}

$authenticated = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
$authenticated->set_param( 'billing_address', array( 'email' => 'changed@example.com' ) );
$authenticated->set_param( 'additional_fields', array( 'itsoft/confirm-email' => $account_email ) );
if ( null !== $checkout->validate_blocks_request( null, null, $authenticated ) ) {
	WP_CLI::error( 'Authenticated Blocks checkout rejected the confirmed account email.' );
}

$order = new WC_Order();
$checkout->clean_blocks_order_data( $order, $authenticated );
if ( $account_email !== $order->get_billing_email() ) {
	WP_CLI::error( 'Authenticated checkout did not force the account email onto the order.' );
}

$classic_fields = $checkout->add_classic_confirm_field( array( 'billing' => array( 'billing_email' => array() ) ) );
if ( 'readonly' !== ( $classic_fields['billing']['billing_email']['custom_attributes']['readonly'] ?? '' ) ) {
	WP_CLI::error( 'Authenticated classic checkout email is not read-only.' );
}

wp_set_current_user( $original_user_id );

$checkout_fields = Automattic\WooCommerce\Blocks\Package::container()->get(
	Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class
);
$registered = $checkout_fields->get_additional_fields();
if ( empty( $registered['itsoft/confirm-email'] ) || true !== $registered['itsoft/confirm-email']['required'] ) {
	WP_CLI::error( 'Blocks checkout confirmation field is not registered as required.' );
}

WP_CLI::success( 'Checkout assurance smoke passed: guest and authenticated email confirmation, account-email locking, normalization and mismatch rejection.' );
