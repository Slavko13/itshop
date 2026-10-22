<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Cart {
	public function register_hooks(): void {
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'add_quantity_controls' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 30 );
	}

	public function add_quantity_controls( string $quantity_html, string $cart_item_key, array $cart_item ): string {
		$product = $cart_item['data'] ?? null;
		if ( ! $product instanceof WC_Product || $product->is_sold_individually() ) {
			return $quantity_html;
		}

		$minimum = 0;
		$maximum = (int) $product->get_max_purchase_quantity();
		return sprintf(
			'<div class="itsoft-cart-quantity" data-itsoft-cart-quantity data-min="%1$d" data-max="%2$d"><button type="button" class="itsoft-cart-quantity__button" data-quantity-action="decrement" aria-label="Уменьшить количество">−</button>%3$s<button type="button" class="itsoft-cart-quantity__button" data-quantity-action="increment" aria-label="Увеличить количество">+</button></div>',
			$minimum,
			$maximum,
			$quantity_html
		);
	}

	public function enqueue_assets(): void {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}
		wp_enqueue_script(
			'itsoft-lf-cart-quantity',
			plugins_url( 'assets/js/cart-quantity.js', ITSOFT_LF_FILE ),
			array( 'jquery', 'wc-cart' ),
			ITSOFT_LF_VERSION,
			true
		);
		wp_enqueue_style(
			'itsoft-lf-cart-quantity',
			plugins_url( 'assets/css/cart-quantity.css', ITSOFT_LF_FILE ),
			array(),
			ITSOFT_LF_VERSION
		);
	}
}
