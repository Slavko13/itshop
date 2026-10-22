<?php
/**
 * Local end-to-end smoke test for the fake payment gateway.
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Mock gateway smoke test is local-only.' );
}

if ( ! class_exists( 'ITSoft_LF_Plugin' ) || ! function_exists( 'wc_create_order' ) ) {
	WP_CLI::error( 'The fulfillment plugin and WooCommerce must be active.' );
}

global $wpdb;

$plugin        = ITSoft_LF_Plugin::instance();
$repository    = $plugin->repository();
$suffix        = strtolower( wp_generate_password( 8, false, false ) );
$pool_id       = 0;
$product_id    = 0;
$order_id      = 0;
$previous_auto = get_option( 'itsoft_lf_auto_fulfillment_enabled', 'no' );

try {
	$gateways = WC()->payment_gateways()->payment_gateways();
	if ( empty( $gateways['itsoft_lf_mock'] ) || ! $gateways['itsoft_lf_mock'] instanceof ITSoft_LF_Mock_Gateway ) {
		throw new RuntimeException( 'The local mock gateway is not registered.' );
	}
	$gateway = $gateways['itsoft_lf_mock'];
	if ( ! $gateway->is_available() ) {
		throw new RuntimeException( 'The local mock gateway is not available.' );
	}

	$pool_id = $repository->create_pool( 'Mock payment smoke ' . $suffix, 0 );
	$repository->import_individual_keys(
		$pool_id,
		array( 'MOCK-PAYMENT-' . strtoupper( $suffix ) . '-KEY' ),
		0
	);

	$product = new WC_Product_Simple();
	$product->set_name( 'Mock payment product ' . $suffix );
	$product->set_status( 'publish' );
	$product->set_regular_price( '100' );
	$product_id = $product->save();
	$repository->set_product_mapping( $product_id, $pool_id, 'auto', 0 );
	update_option( 'itsoft_lf_auto_fulfillment_enabled', 'yes', false );

	$order = wc_create_order();
	$order->add_product( wc_get_product( $product_id ), 1 );
	$order->set_billing_email( 'mock-payment@example.test' );
	$order->set_payment_method( $gateway );
	$order->calculate_totals();
	$order->save();
	$order_id = $order->get_id();
	$plugin->orders()->initialize_order( $order );

	$result = $gateway->process_payment( $order_id );
	if ( 'success' !== ( $result['result'] ?? '' ) || empty( $result['redirect'] ) ) {
		throw new RuntimeException( 'The mock gateway did not return a successful checkout result.' );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order->is_paid() ) {
		throw new RuntimeException( 'The mock gateway did not mark the order paid.' );
	}
	if ( ! str_starts_with( (string) $order->get_transaction_id(), 'MOCK-' ) ) {
		throw new RuntimeException( 'The mock transaction marker is missing.' );
	}
	if ( 'issued' !== $order->get_meta( ITSoft_LF_Order_Service::ORDER_STATUS_META, true ) ) {
		throw new RuntimeException( 'Paid mock order did not reach the issued state.' );
	}

	$items       = $order->get_items( 'line_item' );
	$item_id     = (int) array_key_first( $items );
	$assignments = $repository->get_assignments_for_item( $item_id );
	if ( 1 !== count( $assignments ) ) {
		throw new RuntimeException( 'Mock payment did not allocate exactly one key.' );
	}

	$plugin->orders()->handle_payment_complete( $order_id );
	if ( 1 !== count( $repository->get_assignments_for_item( $item_id ) ) ) {
		throw new RuntimeException( 'Repeated payment processing allocated another key.' );
	}

	WP_CLI::success( 'Mock payment smoke passed: checkout result, paid order, MOCK transaction, AUTO key issue and callback idempotency.' );
} finally {
	$assignments = $wpdb->prefix . 'itsoft_license_assignments';
	$keys        = $wpdb->prefix . 'itsoft_license_keys';
	$events      = $wpdb->prefix . 'itsoft_license_events';
	$mappings    = $wpdb->prefix . 'itsoft_license_product_pools';
	$pools       = $wpdb->prefix . 'itsoft_license_pools';
	$numbers     = $wpdb->prefix . 'itsoft_license_order_numbers';

	if ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->delete( true );
		}
		$wpdb->delete( $numbers, array( 'order_id' => $order_id ), array( '%d' ) );
	}
	if ( $pool_id ) {
		$wpdb->delete( $assignments, array( 'pool_id' => $pool_id ), array( '%d' ) );
		$wpdb->delete( $keys, array( 'pool_id' => $pool_id ), array( '%d' ) );
		$wpdb->delete( $events, array( 'pool_id' => $pool_id ), array( '%d' ) );
	}
	if ( $product_id ) {
		$wpdb->delete( $mappings, array( 'product_id' => $product_id ), array( '%d' ) );
		wp_delete_post( $product_id, true );
	}
	if ( $pool_id ) {
		$wpdb->delete( $pools, array( 'id' => $pool_id ), array( '%d' ) );
	}
	update_option( 'itsoft_lf_auto_fulfillment_enabled', $previous_auto, false );
}
