<?php
/**
 * Upsells and category fallback.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product ) {
	return;
}

$limit = 4;
$upsell_ids = $product->get_upsell_ids();
// Ensure IDs are integers
$upsell_ids = itsoft_filter_available_product_ids( array_map( 'absint', $upsell_ids ) );
$upsell_count = count( $upsell_ids );

$final_ids = $upsell_ids;

if ( $upsell_count < $limit ) {
	$needed = $limit - $upsell_count;
	$category_ids = $product->get_category_ids();

	if ( ! empty( $category_ids ) ) {
		$exclude_ids = array_merge( array( $product->get_id() ), $upsell_ids );
		
		// wc_get_products 'category' argument expects slugs, not IDs.
		$category_slugs = array();
		foreach ( $category_ids as $cat_id ) {
			$term = get_term( $cat_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$category_slugs[] = $term->slug;
			}
		}

		$related_products = array();
		if ( ! empty( $category_slugs ) ) {
			$related_products = wc_get_products( array(
				'category' => $category_slugs,
				'limit'    => $needed,
				'include'  => itsoft_get_available_product_ids() ?: array( 0 ),
				'exclude'  => $exclude_ids,
				'orderby'  => 'rand',
				'return'   => 'ids',
				'status'   => 'publish',
			) );
		}
		
		if ( ! empty( $related_products ) ) {
			$final_ids = array_merge( $final_ids, $related_products );
		}
	}
}

if ( empty( $final_ids ) ) {
	return;
}

// Ensure unique and limit
$final_ids = array_unique( $final_ids );
$final_ids = itsoft_filter_available_product_ids( $final_ids );
$final_ids = array_slice( $final_ids, 0, $limit );

if ( empty( $final_ids ) ) {
	return;
}

$args = array(
	'post_type'           => 'product',
	'ignore_sticky_posts' => 1,
	'no_found_rows'       => 1,
	'posts_per_page'      => $limit,
	'post__in'            => $final_ids,
	'orderby'             => 'post__in',
);

$products = new WP_Query( $args );

if ( $products->have_posts() ) : ?>

	<div class="product-upsells">
		<h2><?php esc_html_e( 'С этим товаром покупают также', 'itsoft' ); ?></h2>

		<div class="product-upsells-grid">
			<?php while ( $products->have_posts() ) : $products->the_post(); ?>
				<?php wc_get_template_part( 'content', 'product-card' ); ?>
			<?php endwhile; ?>
		</div>
	</div>

	<?php wp_reset_postdata(); ?>

<?php endif; ?>

