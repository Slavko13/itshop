<?php
/**
 * Product attributes block.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$attributes = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array();
?>

<div class="product-extra__attrs">
	<?php if ( ! empty( $attributes ) ) : ?>
		<h2 class="product-extra__info-title">Характеристики</h2>
		<div class="product-extra__attrs-list">
			<?php foreach ( $attributes as $attribute ) : ?>
				<?php
				$attribute_slug  = isset( $attribute['slug'] ) ? trim( (string) $attribute['slug'] ) : '';
				$attribute_label = isset( $attribute['label'] ) ? trim( (string) $attribute['label'] ) : '';
				$attribute_value = isset( $attribute['value'] ) ? trim( (string) $attribute['value'] ) : '';

				if ( '' === $attribute_label && '' === $attribute_value ) {
					continue;
				}
				?>
				<div class="product-extra__attr" <?php echo $attribute_slug ? 'data-attribute-slug="' . esc_attr( $attribute_slug ) . '"' : ''; ?>>
					<?php if ( $attribute_label ) : ?>
						<div class="product-extra__attr-title"><?php echo esc_html( $attribute_label ); ?>:</div>
					<?php endif; ?>
					<?php if ( $attribute_value ) : ?>
						<div class="product-extra__attr-value"><?php echo esc_html( $attribute_value ); ?></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
