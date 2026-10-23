<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Mock_Gateway extends WC_Payment_Gateway {
	public function __construct() {
		$this->id                 = 'itsoft_lf_mock';
		$this->method_title       = 'ITSOFT Mock Payment';
		$this->method_description = 'Тестовая успешная оплата без денег и внешних запросов. В production всегда заблокирована.';
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->title              = 'Тестовая оплата — деньги не списываются';
		$this->description        = 'Только для тестового стенда: заказ сразу получает подтверждение оплаты без обращения к банку.';
		$this->enabled            = 'yes';
	}

	public function is_available(): bool {
		return ITSoft_LF_Mock_Payment::is_enabled() && parent::is_available();
	}

	public function process_payment( $order_id ): array {
		if ( ! ITSoft_LF_Mock_Payment::is_enabled() ) {
			throw new RuntimeException( 'Mock payment is forbidden in this environment.' );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new InvalidArgumentException( 'Order does not exist.' );
		}

		$order->payment_complete( 'MOCK-' . strtoupper( wp_generate_uuid4() ) );
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
