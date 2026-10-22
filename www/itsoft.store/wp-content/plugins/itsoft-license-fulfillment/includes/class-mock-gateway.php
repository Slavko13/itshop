<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Mock_Gateway extends WC_Payment_Gateway {
	public function __construct() {
		$this->id                 = 'itsoft_lf_mock';
		$this->method_title       = 'ITSOFT Mock Payment';
		$this->method_description = 'Локальная успешная оплата без денег и внешних запросов.';
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->title              = 'Тестовая оплата (без денег)';
		$this->description        = 'Только локальная проверка: заказ сразу получает подтверждение оплаты.';
		$this->enabled            = 'yes';
	}

	public function is_available(): bool {
		return 'local' === wp_get_environment_type() && parent::is_available();
	}

	public function process_payment( $order_id ): array {
		if ( 'local' !== wp_get_environment_type() ) {
			throw new RuntimeException( 'Mock payment is forbidden outside the local environment.' );
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
