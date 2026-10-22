<?php
/**
 * Product extra info (tags/download) block.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$product_tags = isset( $args['tags'] ) && is_array( $args['tags'] ) ? $args['tags'] : array();
$has_tags     = ! empty( $args['has_tags'] ) && ! empty( $product_tags );

$download     = isset( $args['download'] ) && is_array( $args['download'] ) ? $args['download'] : array();
$has_download = ! empty( $args['has_download'] ) && ! empty( $download );

$should_render_download_wide = ! empty( $args['should_render_download_wide'] );
?>

<?php if ( $has_tags ) : ?>
	<?php
	get_template_part(
		'woocommerce/partials/product-extra',
		'tags',
		array(
			'product_tags' => $product_tags,
		)
	);
	?>
<?php endif; ?>

<?php if ( $has_download ) : ?>
	<?php
	get_template_part(
		'woocommerce/partials/product-extra',
		'download',
		array(
			'download'    => $download,
			'extra_class' => $should_render_download_wide ? 'wide' : '',
		)
	);
	?>
<?php endif; ?>


