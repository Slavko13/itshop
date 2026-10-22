<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Checkout {
	private const BLOCK_CONFIRM_FIELD = 'itsoft/confirm-email';
	private const CLASSIC_CONFIRM_FIELD = 'billing_email_confirm';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_blocks_field' ), 20 );
		add_action( 'woocommerce_blocks_checkout_enqueue_data', array( $this, 'configure_blocks_address_fields' ), 5 );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'configure_address_fields' ), 1000 );
		add_filter( 'woocommerce_get_country_locale_base', array( $this, 'configure_address_fields' ), 1000 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_classic_confirm_field' ), 1000 );
		add_filter( 'option_woocommerce_cod_settings', array( $this, 'configure_cod_settings' ), 1000 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_classic_checkout' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'sanitize_classic_order_email' ), 20, 2 );
		add_filter( 'rest_request_before_callbacks', array( $this, 'validate_blocks_request' ), 20, 3 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'clean_blocks_order_data' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
	}

	public function configure_cod_settings( $settings ): array {
		$settings = is_array( $settings ) ? $settings : array();
		// COD has no business meaning for an order containing only virtual goods.
		$settings['enable_for_virtual'] = 'no';
		return $settings;
	}

	public function configure_blocks_address_fields(): void {
		if (
			! $this->is_digital_only_cart()
			|| ! class_exists( '\\Automattic\\WooCommerce\\Blocks\\Package' )
			|| ! class_exists( '\\Automattic\\WooCommerce\\Blocks\\Assets\\AssetDataRegistry' )
			|| ! class_exists( '\\Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields' )
		) {
			return;
		}

		$container       = \Automattic\WooCommerce\Blocks\Package::container();
		$checkout_fields = $container->get( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class );
		$asset_registry  = $container->get( \Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry::class );
		$fields          = array_merge( $checkout_fields->get_core_fields(), $checkout_fields->get_additional_fields() );

		foreach ( array( 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $field_key ) {
			if ( ! isset( $fields[ $field_key ] ) ) {
				continue;
			}
			$fields[ $field_key ]['required'] = false;
			$fields[ $field_key ]['hidden']   = true;
		}

		// Register before WooCommerce adds its defaultFields value at priority 10.
		$asset_registry->add( 'defaultFields', $fields );
	}

	public function configure_address_fields( array $fields ): array {
		if ( ! $this->is_digital_only_cart() ) {
			return $fields;
		}

		foreach ( array( 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $field_key ) {
			if ( ! isset( $fields[ $field_key ] ) ) {
				continue;
			}
			$fields[ $field_key ]['required'] = false;
			$fields[ $field_key ]['hidden']   = true;
		}

		return $fields;
	}

	public function register_blocks_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'                         => self::BLOCK_CONFIRM_FIELD,
				'label'                      => 'Повторите e-mail',
				'location'                   => 'contact',
				'type'                       => 'text',
				'required'                   => true,
				'show_in_order_confirmation' => false,
				'attributes'                 => array(
					'autocomplete'   => 'email',
					'autocapitalize' => 'none',
					'maxLength'      => '254',
				),
				'sanitize_callback'          => array( $this, 'sanitize_email_value' ),
				'validate_callback'          => array( $this, 'validate_email_value' ),
			)
		);
	}

	public function sanitize_email_value( $value ): string {
		return strtolower( sanitize_email( trim( (string) $value ) ) );
	}

	public function validate_email_value( $value ): ?WP_Error {
		$value = $this->sanitize_email_value( $value );
		if ( '' === $value || ! is_email( $value ) ) {
			return new WP_Error( 'itsoft_invalid_confirm_email', 'Введите корректный e-mail повторно.' );
		}
		return null;
	}

	public function add_classic_confirm_field( array $fields ): array {
		$priority = isset( $fields['billing']['billing_email']['priority'] )
			? (int) $fields['billing']['billing_email']['priority'] + 1
			: 111;
		$fields['billing'][ self::CLASSIC_CONFIRM_FIELD ] = array(
			'type'         => 'email',
			'label'        => 'Повторите e-mail',
			'required'     => true,
			'priority'     => $priority,
			'autocomplete' => 'email',
			'class'        => array( 'form-row-wide' ),
		);
		return $fields;
	}

	public function validate_classic_checkout( array $data, WP_Error $errors ): void {
		$email   = $this->sanitize_email_value( $data['billing_email'] ?? '' );
		$confirm = $this->sanitize_email_value( $data[ self::CLASSIC_CONFIRM_FIELD ] ?? '' );
		if ( '' === $email || ! is_email( $email ) ) {
			$errors->add( 'itsoft_invalid_billing_email', 'Введите корректный e-mail.' );
		}
		if ( '' === $confirm || ! is_email( $confirm ) ) {
			$errors->add( 'itsoft_invalid_confirm_email', 'Введите корректный e-mail повторно.' );
		} elseif ( $email !== $confirm ) {
			$errors->add( 'itsoft_checkout_email_mismatch', 'Адреса электронной почты не совпадают.' );
		}
	}

	public function sanitize_classic_order_email( WC_Order $order, array $data ): void {
		$email = $this->sanitize_email_value( $data['billing_email'] ?? '' );
		if ( '' !== $email ) {
			$order->set_billing_email( $email );
		}
	}

	public function validate_blocks_request( $response, $handler, WP_REST_Request $request ) {
		if ( 'POST' !== $request->get_method() || ! preg_match( '#^/wc/store(?:/v\d+)?/checkout$#', $request->get_route() ) ) {
			return $response;
		}

		$billing    = (array) $request->get_param( 'billing_address' );
		$additional = (array) $request->get_param( 'additional_fields' );
		$email      = $this->sanitize_email_value( $billing['email'] ?? '' );
		$confirm    = $this->sanitize_email_value( $additional[ self::BLOCK_CONFIRM_FIELD ] ?? '' );

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error(
				'itsoft_invalid_billing_email',
				'Введите корректный e-mail.',
				array( 'status' => 400 )
			);
		}
		if ( '' === $confirm || ! is_email( $confirm ) ) {
			return new WP_Error(
				'itsoft_invalid_confirm_email',
				'Введите корректный e-mail повторно.',
				array( 'status' => 400 )
			);
		}
		if ( $email !== $confirm ) {
			return new WP_Error(
				'itsoft_checkout_email_mismatch',
				'Адреса электронной почты не совпадают.',
				array( 'status' => 400 )
			);
		}

		return $response;
	}

	public function clean_blocks_order_data( WC_Order $order, WP_REST_Request $request ): void {
		$billing = (array) $request->get_param( 'billing_address' );
		$email   = $this->sanitize_email_value( $billing['email'] ?? '' );
		if ( '' !== $email ) {
			$order->set_billing_email( $email );
		}
		if ( $this->is_digital_only_cart() && '' === $order->get_billing_country() && function_exists( 'WC' ) && WC()->countries ) {
			$order->set_billing_country( WC()->countries->get_base_country() );
		}
		// The repeated address is needed only for validation and must not be stored as duplicate PII.
		$order->delete_meta_data( '_wc_other/' . self::BLOCK_CONFIRM_FIELD );
	}

	public function enqueue_assets(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		wp_enqueue_script(
			'itsoft-lf-checkout-email',
			plugins_url( 'assets/js/checkout-email.js', ITSOFT_LF_FILE ),
			array(),
			ITSOFT_LF_VERSION,
			true
		);
		wp_localize_script(
			'itsoft-lf-checkout-email',
			'ITSOFT_CHECKOUT',
			array(
				'digitalOnly' => $this->is_digital_only_cart(),
			)
		);
		wp_enqueue_style(
			'itsoft-lf-checkout-email',
			plugins_url( 'assets/css/checkout-email.css', ITSOFT_LF_FILE ),
			array(),
			ITSOFT_LF_VERSION
		);
	}

	private function is_digital_only_cart(): bool {
		return function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() && ! WC()->cart->needs_shipping();
	}
}
