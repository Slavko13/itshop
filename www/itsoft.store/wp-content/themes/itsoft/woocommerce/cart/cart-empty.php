<?php
/**
 * Branded empty-cart state.
 *
 * @package itsoft
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

// Keep third-party empty-cart hooks while replacing WooCommerce's plain message.
$default_message_priority = has_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message' );
if ( false !== $default_message_priority ) {
	remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', $default_message_priority );
}

do_action( 'woocommerce_cart_is_empty' );

if ( false !== $default_message_priority ) {
	add_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', $default_message_priority );
}

get_template_part( 'template-parts/cart/empty-state' );
