<?php
/**
 * Idempotent local acceptance fixture for an empty-pool Windows purchase.
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Acceptance fixtures are forbidden outside the local environment.' );
}

$product_id = 331;
$product    = wc_get_product( $product_id );
if ( ! $product || false === stripos( $product->get_name(), 'Windows' ) ) {
	WP_CLI::error( 'Expected local Windows acceptance product #331 was not found.' );
}

$repository = ITSoft_LF_Plugin::instance()->repository();
$pool_name  = 'Локальная приёмка — Windows без ключей';
$pool_id    = 0;
foreach ( $repository->list_pools() as $pool ) {
	if ( $pool_name === $pool->name ) {
		$pool_id = (int) $pool->id;
		break;
	}
}
if ( ! $pool_id ) {
	$pool_id = $repository->create_pool( $pool_name, 0 );
}

$repository->set_product_mapping( $product_id, $pool_id, 'manual', 0 );
$product = wc_get_product( $product_id );
if ( ! $product->is_in_stock() || ! $product->backorders_allowed() ) {
	WP_CLI::error( 'Mapped empty-pool Windows product is still blocked from purchase.' );
}

WP_CLI::success(
	sprintf(
		'Local acceptance fixture ready: product #%d "%s" -> empty MANUAL pool #%d; purchase remains allowed and paid order will wait for a key.',
		$product_id,
		$product->get_name(),
		$pool_id
	)
);
