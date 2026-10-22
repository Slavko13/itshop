<?php
/**
 * Product attributes + tags block.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$product = $args['product'] ?? null;

if ( ! $product instanceof WC_Product ) {
	return;
}

ob_start();
wc_display_product_attributes( $product );
$attributes_html = trim( ob_get_clean() );

$product_tags     = get_the_terms( $product->get_id(), 'product_tag' );
$has_product_tags = $product_tags && ! is_wp_error( $product_tags );
$has_attributes   = '' !== $attributes_html;

if ( ! $has_attributes && ! $has_product_tags ) {
	return;
}

$has_acf = function_exists( 'get_field' );
?>

<div class="product-row product-row--primary">
	<?php if ( $has_attributes ) : ?>
		<section class="product-row__section product-attributes" aria-label="<?php esc_attr_e( 'Характеристики товара', 'itsoft' ); ?>">
			<div class="product-attributes__heading">
				<p class="product-attributes__title"><?php esc_html_e( 'Характеристики:', 'itsoft' ); ?></p>
			</div>
			<div class="product-attributes__content">
				<?php echo $attributes_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $has_product_tags ) : ?>
		<section class="product-row__section product-tags" aria-label="<?php esc_attr_e( 'В данный пакет входит', 'itsoft' ); ?>">
			<div class="product-tags__heading">
				<p class="product-tags__title"><?php esc_html_e( 'В данный пакет входит:', 'itsoft' ); ?></p>
			</div>
			<ul class="product-tags__list">
				<?php foreach ( $product_tags as $tag ) : ?>
					<?php
					$tag_link = get_term_link( $tag );
					$tag_href = ! is_wp_error( $tag_link ) ? $tag_link : '';

					$tag_image     = $has_acf ? get_field( 'img', 'product_tag_' . $tag->term_id ) : null;
					$tag_image_id  = is_array( $tag_image ) && ! empty( $tag_image['ID'] ) ? (int) $tag_image['ID'] : 0;
					$tag_image_alt = is_array( $tag_image ) && ! empty( $tag_image['alt'] ) ? (string) $tag_image['alt'] : $tag->name;
					?>
					<li class="product-tags__item">
						<?php if ( $tag_href ) : ?>
							<a class="product-tags__card" href="<?php echo esc_url( $tag_href ); ?>">
						<?php else : ?>
							<div class="product-tags__card">
						<?php endif; ?>
								<?php if ( $tag_image_id ) : ?>
									<span class="product-tags__media">
										<?php
										echo wp_get_attachment_image(
											$tag_image_id,
											'thumbnail',
											false,
											array(
												'class' => 'product-tags__image',
												'alt'   => esc_attr( $tag_image_alt ),
											)
										);
										?>
									</span>
								<?php endif; ?>
								<span class="product-tags__label"><?php echo esc_html( $tag->name ); ?></span>
						<?php if ( $tag_href ) : ?>
							</a>
						<?php else : ?>
							</div>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</div>

