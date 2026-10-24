<?php
/**
 * Local integration smoke test for order numbers, issue states and manual assignment.
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Order flow smoke test is local-only.' );
}

if ( ! class_exists( 'ITSoft_LF_Plugin' ) || ! function_exists( 'wc_create_order' ) ) {
	WP_CLI::error( 'The fulfillment plugin and WooCommerce must be active.' );
}

global $wpdb;

$plugin       = ITSoft_LF_Plugin::instance();
$repository   = $plugin->repository();
$order_service = $plugin->orders();
$suffix       = strtolower( wp_generate_password( 8, false, false ) );
$pool_id      = 0;
$product_id   = 0;
$order_ids    = array();
$temporary_user_id = 0;

try {
	$pool_id = $repository->create_pool( 'Order smoke ' . $suffix, 0 );
	$key_one = 'ORDER-SMOKE-' . strtoupper( $suffix ) . '-0001';
	$key_two = 'ORDER-SMOKE-' . strtoupper( $suffix ) . '-0002';
	$repository->import_individual_keys(
		$pool_id,
		array( $key_one, $key_two ),
		0
	);

	$product = new WC_Product_Simple();
	$product->set_name( 'Order smoke product ' . $suffix );
	$product->set_status( 'publish' );
	$product->set_regular_price( '100' );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 0 );
	$product->set_stock_status( 'outofstock' );
	$product_id = $product->save();
	$repository->set_product_mapping( $product_id, $pool_id, 'manual', 0 );

	$product = wc_get_product( $product_id );
	if ( ! $product->is_in_stock() || ! $product->backorders_allowed() || ! $product->has_enough_stock( 2 ) ) {
		throw new RuntimeException( 'Mapped product is still blocked by WooCommerce stock.' );
	}

	$order = wc_create_order();
	$order->add_product( $product, 2 );
	$owner = get_user_by( 'login', 'itsoft_admin' );
	if ( ! $owner ) {
		throw new RuntimeException( 'Local test administrator is missing.' );
	}
	$order->set_customer_id( $owner->ID );
	$order->set_billing_email( 'order-smoke@example.test' );
	$order->calculate_totals();
	$order->save();
	$order_ids[] = $order->get_id();
	$order_service->initialize_order( $order );

	$public_number = $order->get_order_number();
	if ( ! preg_match( '/^\d{10}$/', $public_number ) ) {
		throw new RuntimeException( 'Public order number is not 10 digits.' );
	}
	if ( 'pending_payment' !== $order->get_meta( ITSoft_LF_Order_Service::ORDER_STATUS_META, true ) ) {
		throw new RuntimeException( 'Unpaid order did not receive pending_payment status.' );
	}

	$order->update_status( 'processing' );
	$order = wc_get_order( $order->get_id() );
	if ( 'waiting_key' !== $order->get_meta( ITSoft_LF_Order_Service::ORDER_STATUS_META, true ) ) {
		throw new RuntimeException( 'Paid order did not receive waiting_key status.' );
	}

	$items   = $order->get_items( 'line_item' );
	$item_id = (int) array_key_first( $items );
	$result  = $order_service->manually_assign_item( $order->get_id(), $item_id, 0 );
	if ( 2 !== $result['assigned'] || 0 !== $result['missing'] || 'issued' !== $result['status'] ) {
		throw new RuntimeException( 'Manual assignment did not allocate one key per unit.' );
	}
	$events_table = $wpdb->prefix . 'itsoft_license_events';
	$low_events   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE pool_id = %d AND operation = 'inventory.low'", $pool_id ) );
	$empty_events = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE pool_id = %d AND operation = 'inventory.empty'", $pool_id ) );
	if ( 1 !== $low_events || 1 !== $empty_events ) {
		throw new RuntimeException( 'Inventory threshold notifications were not edge-triggered exactly once.' );
	}
	$order = wc_get_order( $order->get_id() );
	if ( 'sent' !== $order->get_meta( '_itsoft_lf_key_email_status', true ) ) {
		throw new RuntimeException( 'Assignment email was not accepted by the local mail transport.' );
	}
	if ( ! $order->has_status( 'completed' ) ) {
		throw new RuntimeException( 'Fully issued manual order was not completed.' );
	}

	$assignment_count_before_resend = count( $repository->get_assignments_for_order( $order->get_id() ) );
	$order->set_billing_email( 'order-smoke-resend@example.test' );
	$order->save();
	if ( ! $plugin->communication()->send_order_keys( $order->get_id(), true ) ) {
		throw new RuntimeException( 'Resend to the corrected email failed.' );
	}
	if ( $assignment_count_before_resend !== count( $repository->get_assignments_for_order( $order->get_id() ) ) ) {
		throw new RuntimeException( 'Resend allocated a new key.' );
	}

	wp_set_current_user( $owner->ID );
	ob_start();
	do_action( 'woocommerce_order_details_after_order_table', $order );
	$owner_html = (string) ob_get_clean();
	if ( ! str_contains( $owner_html, $key_one ) || ! str_contains( $owner_html, $key_two ) ) {
		throw new RuntimeException( 'Order owner cannot see assigned keys in My Account output.' );
	}
	if ( ! str_contains( $owner_html, 'data-license-reveal' ) || ! str_contains( $owner_html, 'data-license-copy' ) || ! str_contains( $owner_html, 'data-masked=' ) ) {
		throw new RuntimeException( 'Order key output is missing secure reveal/copy controls.' );
	}
	ob_start();
	do_action( 'woocommerce_account_licenses_endpoint' );
	$license_archive_html = (string) ob_get_clean();
	if ( ! str_contains( $license_archive_html, $key_one ) || ! str_contains( $license_archive_html, $key_two ) || ! str_contains( $license_archive_html, $public_number ) ) {
		throw new RuntimeException( 'The customer license archive does not contain the assigned keys and order reference.' );
	}
	if ( 2 !== (int) apply_filters( 'itsoft_account_license_count', 0, $owner->ID ) ) {
		throw new RuntimeException( 'The account dashboard license counter is incorrect.' );
	}

	$temporary_user_id = wp_create_user( 'order_smoke_' . $suffix, wp_generate_password( 24 ), 'other-' . $suffix . '@example.test' );
	if ( is_wp_error( $temporary_user_id ) ) {
		throw new RuntimeException( 'Unable to create access-control test user.' );
	}
	wp_set_current_user( $temporary_user_id );
	ob_start();
	do_action( 'woocommerce_order_details_after_order_table', $order );
	$other_html = (string) ob_get_clean();
	if ( str_contains( $other_html, $key_one ) || str_contains( $other_html, $key_two ) ) {
		throw new RuntimeException( 'Another user can see order license keys.' );
	}
	ob_start();
	do_action( 'woocommerce_account_licenses_endpoint' );
	$other_archive_html = (string) ob_get_clean();
	if ( str_contains( $other_archive_html, $key_one ) || str_contains( $other_archive_html, $key_two ) ) {
		throw new RuntimeException( 'Another user can see keys in the customer license archive.' );
	}
	wp_set_current_user( 0 );

	$repeat = $order_service->manually_assign_item( $order->get_id(), $item_id, 0 );
	if ( 0 !== $repeat['assigned'] || 2 !== $repeat['existing'] || 'issued' !== $repeat['status'] ) {
		throw new RuntimeException( 'Repeated manual assignment was not idempotent.' );
	}
	$low_events_after_repeat   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE pool_id = %d AND operation = 'inventory.low'", $pool_id ) );
	$empty_events_after_repeat = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE pool_id = %d AND operation = 'inventory.empty'", $pool_id ) );
	if ( 1 !== $low_events_after_repeat || 1 !== $empty_events_after_repeat ) {
		throw new RuntimeException( 'Repeated action duplicated inventory threshold events.' );
	}

	$order_two = wc_create_order();
	$order_two->add_product( $product, 1 );
	$order_two->set_billing_email( 'order-smoke-two@example.test' );
	$order_two->calculate_totals();
	$order_two->save();
	$order_ids[] = $order_two->get_id();
	$order_service->initialize_order( $order_two );
	$public_number_two = $order_two->get_order_number();
	if ( $public_number === $public_number_two || ! preg_match( '/^\d{10}$/', $public_number_two ) ) {
		throw new RuntimeException( 'Public order numbers are not unique.' );
	}
	$order_two->update_status( 'processing' );
	$order_two = wc_get_order( $order_two->get_id() );
	$items_two = $order_two->get_items( 'line_item' );
	$item_two  = (int) array_key_first( $items_two );
	$empty     = $order_service->manually_assign_item( $order_two->get_id(), $item_two, 0 );
	if ( 1 !== $empty['missing'] || 'waiting_key' !== $empty['status'] ) {
		throw new RuntimeException( 'Empty pool did not preserve waiting_key status.' );
	}

	$repository->set_product_mapping( $product_id, $pool_id, 'auto', 0 );
	$repository->import_individual_keys( $pool_id, array( 'ORDER-SMOKE-' . strtoupper( $suffix ) . '-AUTO' ), 0 );
	update_option( 'itsoft_lf_auto_fulfillment_enabled', 'yes', false );
	$auto_order = wc_create_order();
	$auto_order->add_product( $product, 1 );
	$auto_order->set_customer_id( $owner->ID );
	$auto_order->set_billing_email( 'order-smoke-auto@example.test' );
	$auto_order->calculate_totals();
	$auto_order->save();
	$order_ids[] = $auto_order->get_id();
	$order_service->initialize_order( $auto_order );
	$auto_order->payment_complete( 'LOCAL-AUTO-' . strtoupper( $suffix ) );
	$auto_order = wc_get_order( $auto_order->get_id() );
	$auto_items = $auto_order->get_items( 'line_item' );
	$auto_item_id = (int) array_key_first( $auto_items );
	if ( 'issued' !== $auto_order->get_meta( ITSoft_LF_Order_Service::ORDER_STATUS_META, true ) || 1 !== count( $repository->get_assignments_for_item( $auto_item_id ) ) ) {
		throw new RuntimeException( 'AUTO order was not fulfilled after payment_complete.' );
	}
	if ( ! $auto_order->has_status( 'completed' ) ) {
		throw new RuntimeException( 'Fully issued AUTO order was not completed.' );
	}
	$order_service->handle_payment_complete( $auto_order->get_id() );
	if ( 1 !== count( $repository->get_assignments_for_item( $auto_item_id ) ) ) {
		throw new RuntimeException( 'Repeated payment callback allocated a second key.' );
	}

	$auto_empty_order = wc_create_order();
	$auto_empty_order->add_product( $product, 1 );
	$auto_empty_order->set_customer_id( $owner->ID );
	$auto_empty_order->set_billing_email( 'order-smoke-auto-empty@example.test' );
	$auto_empty_order->calculate_totals();
	$auto_empty_order->save();
	$order_ids[] = $auto_empty_order->get_id();
	$order_service->initialize_order( $auto_empty_order );
	$auto_empty_order->payment_complete( 'LOCAL-AUTO-EMPTY-' . strtoupper( $suffix ) );
	$auto_empty_order = wc_get_order( $auto_empty_order->get_id() );
	if ( 'waiting_key' !== $auto_empty_order->get_meta( ITSoft_LF_Order_Service::ORDER_STATUS_META, true ) ) {
		throw new RuntimeException( 'AUTO order with an empty pool did not remain waiting_key.' );
	}

	$search_by_number = apply_filters( 'woocommerce_cot_shop_order_search_results', array(), $public_number );
	$search_by_product = apply_filters( 'woocommerce_cot_shop_order_search_results', array(), 'Order smoke product ' . $suffix );
	if ( ! in_array( $order->get_id(), $search_by_number, true ) || ! in_array( $order->get_id(), $search_by_product, true ) ) {
		throw new RuntimeException( 'Extended HPOS order search did not find the test order.' );
	}

	WP_CLI::success( 'Order flow smoke passed: manual/AUTO fulfillment, repeated callback, public numbers, states, stock independence, email/resend, My Account access control, empty pool and HPOS search.' );
} finally {
	$assignments = $wpdb->prefix . 'itsoft_license_assignments';
	$keys        = $wpdb->prefix . 'itsoft_license_keys';
	$events      = $wpdb->prefix . 'itsoft_license_events';
	$mappings    = $wpdb->prefix . 'itsoft_license_product_pools';
	$pools       = $wpdb->prefix . 'itsoft_license_pools';
	$numbers     = $wpdb->prefix . 'itsoft_license_order_numbers';

	foreach ( $order_ids as $order_id ) {
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
	update_option( 'itsoft_lf_auto_fulfillment_enabled', 'no', false );
	if ( $temporary_user_id && ! is_wp_error( $temporary_user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $temporary_user_id );
	}
}
