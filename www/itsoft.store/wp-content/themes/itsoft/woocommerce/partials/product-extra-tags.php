<?php
/**
 * Product extra tags block.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$product_tags = $args['product_tags'] ?? array();
$image_size   = isset( $args['image_size'] ) ? (string) $args['image_size'] : 'thumbnail';
$has_acf      = function_exists( 'get_field' );

$product_tags = array_filter(
	(array) $product_tags,
	function ( $tag ) {
		return $tag instanceof WP_Term;
	}
);

$product_id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;

if ( ! $product_id ) {
	$product_id = get_the_ID();
}

if ( $product_id ) {
	$ordered_tag_ids = wp_get_object_terms(
		$product_id,
		'product_tag',
		array(
			'fields'  => 'ids',
			'orderby' => 'term_order',
			'order'   => 'ASC',
		)
	);

	if ( ! is_wp_error( $ordered_tag_ids ) && ! empty( $ordered_tag_ids ) ) {
		$tag_positions = array_flip( array_map( 'intval', $ordered_tag_ids ) );

		usort(
			$product_tags,
			static function ( WP_Term $left, WP_Term $right ) use ( $tag_positions ) : int {
				$left_position  = $tag_positions[ $left->term_id ] ?? PHP_INT_MAX;
				$right_position = $tag_positions[ $right->term_id ] ?? PHP_INT_MAX;

				if ( $left_position === $right_position ) {
					return strcmp( $left->name, $right->name );
				}

				return $left_position <=> $right_position;
			}
		);
	}
}

if ( empty( $product_tags ) ) {
	return;
}
?>

<div class="product-extra__tags">
	<div class="product-extra__info-title">В данный пакет входит:</div>
	<div class="product-extra__tags-list">
		<?php foreach ( $product_tags as $product_tag ) : ?>
			<?php
			$product_tag_link = get_term_link( $product_tag );
			$product_tag_link = is_wp_error( $product_tag_link ) ? '' : $product_tag_link;

			$product_tag_image     = $has_acf ? get_field( 'img', 'product_tag_' . $product_tag->term_id ) : null;
			$product_tag_image_url = '';
			$product_tag_image_alt = '';

			if ( is_array( $product_tag_image ) ) {
				$product_tag_image_url = isset( $product_tag_image['sizes'][ $image_size ] ) ? $product_tag_image['sizes'][ $image_size ] : ( $product_tag_image['url'] ?? '' );
				$product_tag_image_alt = $product_tag_image['alt'] ?? '';
			}

			if ( ! $product_tag_image_alt ) {
				$product_tag_image_alt = $product_tag->name;
			}
			?>
			<div class="product-extra__tag">
				<div class="product-extra__tag-icon" aria-hidden="true">
					<?php if ( $product_tag_image_url ) : ?>
						<img src="<?php echo esc_url( $product_tag_image_url ); ?>" alt="<?php echo esc_attr( $product_tag_image_alt ); ?>">
					<?php endif; ?>
				</div>
				<div class="product-extra__tag-name">
					<?php if ( $product_tag_link ) : ?>
						<a href="<?php echo esc_url( $product_tag_link ); ?>">
							<?php echo esc_html( $product_tag->name ); ?>
						</a>
					<?php else : ?>
						<?php echo esc_html( $product_tag->name ); ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>

