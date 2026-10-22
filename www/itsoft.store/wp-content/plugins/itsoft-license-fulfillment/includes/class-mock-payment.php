<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Mock_Payment {
	public static function register_hooks(): void {
		if ( 'local' !== wp_get_environment_type() ) {
			return;
		}
		add_filter( 'woocommerce_payment_gateways', array( self::class, 'register_gateway' ) );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::register_blocks_support();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( self::class, 'register_blocks_support' ) );
		}
	}

	public static function register_gateway( array $gateways ): array {
		if ( ! class_exists( 'ITSoft_LF_Mock_Gateway' ) ) {
			require_once ITSOFT_LF_DIR . 'includes/class-mock-gateway.php';
		}
		$gateways[] = 'ITSoft_LF_Mock_Gateway';
		return $gateways;
	}

	public static function register_blocks_support(): void {
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}
		require_once ITSOFT_LF_DIR . 'includes/class-mock-payment-blocks.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ): void {
				$registry->register( new ITSoft_LF_Mock_Payment_Blocks() );
			}
		);
	}
}
