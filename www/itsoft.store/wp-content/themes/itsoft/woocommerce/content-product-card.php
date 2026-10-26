<?php
/**
 * Custom product card for catalog grid.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product || ! $product->is_visible() || ! itsoft_is_product_available_for_sale( $product ) ) {
	return;
}

$context          = Itsoft_Product_Card_Controller::get_context( $product );
$prioritize_image = ! empty( $GLOBALS['itsoft_prioritize_next_catalog_image'] );

if ( $prioritize_image ) {
	// Consume the flag only after a visible, purchasable product reaches the
	// template. Hidden/out-of-stock products therefore cannot steal LCP priority.
	$GLOBALS['itsoft_prioritize_next_catalog_image'] = false;
}

wc_get_template(
	'partials/product-card.php',
	array(
		'product'          => $product,
		'context'          => $context,
		'prioritize_image' => $prioritize_image,
	)
);

