<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Mock_Payment_Blocks extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
	protected $name = 'itsoft_lf_mock';

	public function initialize(): void {
		$this->settings = array(
			'enabled'     => 'yes',
			'title'       => 'Тестовая оплата — деньги не списываются',
			'description' => 'Только для тестового стенда: платёж подтверждается сразу без обращения к банку.',
		);
	}

	public function is_active(): bool {
		return ITSoft_LF_Mock_Payment::is_enabled();
	}

	public function get_payment_method_script_handles(): array {
		$handle = 'itsoft-lf-mock-payment-blocks';
		wp_register_script(
			$handle,
			plugins_url( 'assets/js/mock-payment-blocks.js', ITSOFT_LF_FILE ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			ITSOFT_LF_VERSION,
			true
		);
		return array( $handle );
	}

	public function get_payment_method_data(): array {
		return array(
			'title'       => $this->settings['title'],
			'description' => $this->settings['description'],
			'supports'    => array( 'products' ),
		);
	}
}
