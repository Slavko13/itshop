<?php
/**
 * Local smoke test for checkout progress UI and deferred phone validation.
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Checkout progress smoke test is local-only.' );
}

$template = get_template_directory() . '/page-checkout.php';
$styles   = get_template_directory() . '/assets/css/cart.css';

if ( ! is_readable( $template ) || ! str_contains( (string) file_get_contents( $template ), 'cart-steps__item--active' ) ) {
	WP_CLI::error( 'Checkout progress template or active step markup is missing.' );
}
if ( ! is_readable( $styles ) || ! str_contains( (string) file_get_contents( $styles ), 'cart-steps__item--complete' ) ) {
	WP_CLI::error( 'Checkout progress completion styles are missing.' );
}
if ( ! function_exists( 'itsoft_validate_store_api_required_phones' ) ) {
	WP_CLI::error( 'Checkout phone validator is unavailable.' );
}

$draft_update = new WP_REST_Request( 'PUT', '/wc/store/v1/checkout' );
$draft_update->set_param( 'billing_address', array() );
$draft_update->set_param( 'shipping_address', array() );
itsoft_validate_store_api_required_phones( null, $draft_update );

$place_order = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
$place_order->set_param( 'billing_address', array() );
$place_order->set_param( 'shipping_address', array() );

try {
	itsoft_validate_store_api_required_phones( null, $place_order );
	WP_CLI::error( 'Final checkout accepted an empty billing phone.' );
} catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $exception ) {
	if ( 'itsoft_required_billing_phone' !== $exception->getErrorCode() ) {
		throw $exception;
	}
}

WP_CLI::success( 'Checkout progress smoke passed: shared steps exist, draft PUT is quiet, final POST still requires phone.' );
