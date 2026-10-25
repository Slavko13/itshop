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

$context = Itsoft_Product_Card_Controller::get_context( $product );

wc_get_template(
	'partials/product-card.php',
	array(
		'product' => $product,
		'context' => $context,
	)
);

