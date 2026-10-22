<?php
/**
 * Local smoke test for electronic, physical and mixed carts.
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Mixed delivery smoke test is local-only.' );
}

if ( ! did_action( 'woocommerce_init' ) ) {
	do_action( 'woocommerce_init' );
}
if ( ! WC()->cart ) {
	wc_load_cart();
}

$electronic_ids = array( 331, 333 );
$physical_ids   = array( 350, 344 );

try {
	foreach ( $electronic_ids as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_virtual() ) {
			WP_CLI::error( "Electronic acceptance product #{$product_id} is missing or not virtual." );
		}
	}

	foreach ( $physical_ids as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || $product->is_virtual() ) {
			WP_CLI::error( "Physical acceptance variation #{$product_id} is missing or virtual." );
		}
	}

	WC()->cart->empty_cart();
	foreach ( $electronic_ids as $product_id ) {
		if ( ! WC()->cart->add_to_cart( $product_id, 1 ) ) {
			WP_CLI::error( "Electronic product #{$product_id} could not be added to the cart." );
		}
	}
	if ( WC()->cart->needs_shipping() ) {
		WP_CLI::error( 'Electronic-only cart incorrectly requires shipping.' );
	}
	$electronic_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	if ( isset( $electronic_gateways['cod'] ) ) {
		WP_CLI::error( 'Cash on delivery is available for an electronic-only cart.' );
	}

	WC()->cart->empty_cart();
	if ( ! WC()->cart->add_to_cart( $physical_ids[0], 1 ) ) {
		WP_CLI::error( "Physical variation #{$physical_ids[0]} could not be added to the cart." );
	}
	if ( ! WC()->cart->needs_shipping() ) {
		WP_CLI::error( 'Physical-only cart does not require shipping.' );
	}
	$physical_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	if ( ! isset( $physical_gateways['cod'] ) ) {
		WP_CLI::error( 'Cash on delivery is missing from a physical cart.' );
	}

	if ( ! WC()->cart->add_to_cart( $electronic_ids[0], 1 ) ) {
		WP_CLI::error( "Electronic product #{$electronic_ids[0]} could not be added to the mixed cart." );
	}
	if ( ! WC()->cart->needs_shipping() ) {
		WP_CLI::error( 'Mixed cart does not require shipping.' );
	}

	WP_CLI::success( 'Mixed delivery smoke passed: electronic cart has no shipping/COD; physical and mixed carts require shipping and physical COD remains available.' );
} finally {
	WC()->cart->empty_cart();
}
