<?php
/**
 * Local integration smoke test for product-to-pool mappings.
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Product mapping smoke test is local-only.' );
}

if ( ! class_exists( 'ITSoft_LF_Plugin' ) || ! function_exists( 'wc_get_product' ) ) {
	WP_CLI::error( 'The fulfillment plugin and WooCommerce must be active.' );
}

global $wpdb;

$repository = ITSoft_LF_Plugin::instance()->repository();
$suffix     = strtolower( wp_generate_password( 8, false, false ) );
$pool_id    = 0;
$product_id = 0;
$child_id   = 0;

try {
	$pool_id = $repository->create_pool( 'Mapping smoke ' . $suffix, 0 );

	$product = new WC_Product_Variable();
	$product->set_name( 'Mapping smoke parent ' . $suffix );
	$product->set_status( 'draft' );
	$product_id = $product->save();

	$variation = new WC_Product_Variation();
	$variation->set_parent_id( $product_id );
	$variation->set_status( 'publish' );
	$variation->set_regular_price( '1' );
	$child_id = $variation->save();

	$repository->set_product_mapping( $product_id, $pool_id, 'manual', 0 );
	$direct = $repository->get_product_mapping( $product_id );
	if ( ! $direct || $pool_id !== (int) $direct->pool_id || 'manual' !== $direct->fulfillment_mode ) {
		throw new RuntimeException( 'Direct mapping was not saved.' );
	}
	$events_table = $wpdb->prefix . 'itsoft_license_events';
	$event_count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE pool_id = %d AND operation = 'product.mapping_saved'", $pool_id ) );
	$repository->set_product_mapping( $product_id, $pool_id, 'manual', 0 );
	$event_count_after_repeat = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE pool_id = %d AND operation = 'product.mapping_saved'", $pool_id ) );
	if ( $event_count !== $event_count_after_repeat ) {
		throw new RuntimeException( 'Idempotent save created a duplicate audit event.' );
	}

	$inherited = $repository->resolve_product_mapping( $child_id );
	if ( ! $inherited || empty( $inherited->inherited ) || $pool_id !== (int) $inherited->pool_id ) {
		throw new RuntimeException( 'Variation did not inherit its parent mapping.' );
	}

	$repository->set_product_mapping( $child_id, $pool_id, 'auto', 0 );
	$override = $repository->resolve_product_mapping( $child_id );
	if ( ! $override || ! empty( $override->inherited ) || 'auto' !== $override->fulfillment_mode ) {
		throw new RuntimeException( 'Variation override was not resolved.' );
	}

	$invalid_rejected = false;
	try {
		$repository->set_product_mapping( $product_id, $pool_id, 'unexpected', 0 );
	} catch ( InvalidArgumentException $exception ) {
		$invalid_rejected = true;
	}
	if ( ! $invalid_rejected ) {
		throw new RuntimeException( 'Invalid fulfillment mode was accepted.' );
	}

	$repository->delete_product_mapping( $child_id, 0 );
	$after_delete = $repository->resolve_product_mapping( $child_id );
	if ( ! $after_delete || empty( $after_delete->inherited ) || 'manual' !== $after_delete->fulfillment_mode ) {
		throw new RuntimeException( 'Deleting an override did not restore inheritance.' );
	}

	WP_CLI::success( 'Product mapping smoke passed: shared pool, modes, validation and variation inheritance.' );
} finally {
	$mapping_table = $wpdb->prefix . 'itsoft_license_product_pools';
	$events_table  = $wpdb->prefix . 'itsoft_license_events';
	$pools_table   = $wpdb->prefix . 'itsoft_license_pools';
	if ( $product_id ) {
		$wpdb->delete( $mapping_table, array( 'product_id' => $product_id ), array( '%d' ) );
	}
	if ( $child_id ) {
		$wpdb->delete( $mapping_table, array( 'product_id' => $child_id ), array( '%d' ) );
		wp_delete_post( $child_id, true );
	}
	if ( $product_id ) {
		wp_delete_post( $product_id, true );
	}
	if ( $pool_id ) {
		$wpdb->delete( $events_table, array( 'pool_id' => $pool_id ), array( '%d' ) );
		$wpdb->delete( $pools_table, array( 'id' => $pool_id ), array( '%d' ) );
	}
}
