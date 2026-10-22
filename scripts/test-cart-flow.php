<?php
/**
 * Local smoke test for the classic cart actually rendered by the theme.
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Cart flow smoke test is local-only.' );
}

if ( ! did_action( 'woocommerce_init' ) ) {
	do_action( 'woocommerce_init' );
}
if ( ! WC()->cart ) {
	wc_load_cart();
}

$product = wc_get_product( 331 );
if ( ! $product || ! $product->is_in_stock() || ! $product->backorders_allowed() ) {
	WP_CLI::error( 'Mapped empty-pool Windows acceptance product is not purchasable.' );
}

WC()->cart->empty_cart();
$cart_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
if ( ! $cart_key ) {
	WP_CLI::error( 'Windows product could not be added to the cart.' );
}
if ( WC()->cart->needs_shipping() ) {
	WP_CLI::error( 'Mapped license product incorrectly requires physical shipping.' );
}

WC()->cart->set_quantity( $cart_key, 2, true );
$item = WC()->cart->get_cart_item( $cart_key );
if ( 2 !== (int) $item['quantity'] ) {
	WP_CLI::error( 'Cart increment to two failed.' );
}

WC()->cart->set_quantity( $cart_key, 1, true );
$item = WC()->cart->get_cart_item( $cart_key );
if ( empty( $item ) || 1 !== (int) $item['quantity'] ) {
	WP_CLI::error( 'Cart decrement removed the line instead of changing two to one.' );
}

$input = woocommerce_quantity_input(
	array(
		'input_name'  => "cart[{$cart_key}][qty]",
		'input_value' => 1,
		'min_value'   => 0,
	),
	$product,
	false
);
$html = apply_filters( 'woocommerce_cart_item_quantity', $input, $cart_key, $item );
if ( ! str_contains( $html, 'data-quantity-action="decrement"' ) || ! str_contains( $html, 'data-quantity-action="increment"' ) ) {
	WP_CLI::error( 'Plus/minus controls are missing from classic cart markup.' );
}

WC()->cart->set_quantity( $cart_key, 0, true );
if ( WC()->cart->get_cart_item( $cart_key ) ) {
	WP_CLI::error( 'Explicit transition to the allowed zero did not remove the cart line.' );
}

WP_CLI::success( 'Cart flow smoke passed: mapped Windows add-to-cart, no physical shipping, 1->2->1 quantity, plus/minus markup and explicit zero removal.' );
