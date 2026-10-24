<?php
/**
 * Local smoke test for the store-wide electronic fulfilment policy.
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

$electronic_ids       = array( 331, 333 );
$legacy_physical_ids  = array( 350, 344 );

try {
	foreach ( $electronic_ids as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_virtual() ) {
			WP_CLI::error( "Electronic acceptance product #{$product_id} is missing or not virtual." );
		}
	}

	foreach ( $legacy_physical_ids as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			WP_CLI::error( "Legacy imported variation #{$product_id} is missing." );
		}
		if ( $product->needs_shipping() ) {
			WP_CLI::error( "Legacy imported variation #{$product_id} still requires shipping." );
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
	if ( ! WC()->cart->add_to_cart( $legacy_physical_ids[0], 1 ) ) {
		WP_CLI::error( "Legacy imported variation #{$legacy_physical_ids[0]} could not be added to the cart." );
	}
	if ( WC()->cart->needs_shipping() ) {
		WP_CLI::error( 'A legacy imported product incorrectly requires shipping.' );
	}
	$legacy_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	if ( isset( $legacy_gateways['cod'] ) ) {
		WP_CLI::error( 'Cash on delivery is available for a legacy imported product.' );
	}

	if ( ! WC()->cart->add_to_cart( $electronic_ids[0], 1 ) ) {
		WP_CLI::error( "Electronic product #{$electronic_ids[0]} could not be added to the mixed cart." );
	}
	if ( WC()->cart->needs_shipping() ) {
		WP_CLI::error( 'A mixed legacy cart incorrectly requires shipping.' );
	}

	WP_CLI::success( 'Electronic fulfilment smoke passed: current and legacy products never require shipping or cash on delivery.' );
} finally {
	WC()->cart->empty_cart();
}
