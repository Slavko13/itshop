<?php
/**
 * Reproducible, local-only WooCommerce seed.
 * Run with: wp eval-file /workspace/scripts/local-seed.php --allow-root
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Local seed is allowed only when WP_ENVIRONMENT_TYPE=local.' );
}

if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
	WP_CLI::error( 'WooCommerce must be active before running the local seed.' );
}

if ( class_exists( 'WC_Install' ) ) {
	WC_Install::create_pages();
}

update_option( 'permalink_structure', '/%postname%/' );
update_option( 'woocommerce_currency', 'RUB' );
update_option( 'woocommerce_default_country', 'RU' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );

$cod_settings            = get_option( 'woocommerce_cod_settings', array() );
$cod_settings            = is_array( $cod_settings ) ? $cod_settings : array();
$cod_settings['enabled'] = 'yes';
$cod_settings['title']   = 'Локальная тестовая оплата';
update_option( 'woocommerce_cod_settings', $cod_settings );

$product_id = (int) get_option( 'itsoft_local_test_product_id', 0 );
$product    = $product_id ? wc_get_product( $product_id ) : false;

if ( ! $product instanceof WC_Product_Simple ) {
	$product = new WC_Product_Simple();
}

$product->set_name( 'ITSOFT Local Test Product' );
$product->set_slug( 'itsoft-local-test-product' );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'visible' );
$product->set_description( 'Синтетический виртуальный товар только для локальной smoke-проверки.' );
$product->set_short_description( 'Не является реальным товаром и не содержит лицензионного ключа.' );
$product->set_sku( 'ITSOFT-LOCAL-TEST' );
$product->set_regular_price( '100' );
$product->set_virtual( true );
$product->set_manage_stock( false );
$product->set_stock_status( 'instock' );
$product_id = $product->save();

update_option( 'itsoft_local_test_product_id', $product_id );
flush_rewrite_rules();

WP_CLI::success( sprintf( 'Local WooCommerce seed is ready. Test product ID: %d', $product_id ) );
