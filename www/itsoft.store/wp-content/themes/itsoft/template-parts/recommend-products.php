<?php
/**
 * Recommend products block
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$manual_products = function_exists( 'get_field' ) ? get_field( 'home_recommended_products', (int) get_option( 'page_on_front' ) ) : array();
$manual_products = array_values( array_filter( array_map( 'absint', (array) $manual_products ) ) );
$available_products = itsoft_get_available_product_ids();
$manual_products = array_values( array_intersect( $manual_products, $available_products ) );
$excluded_products = array_values( array_filter( array_map( 'absint', (array) ( $GLOBALS['itsoft_home_popular_product_ids'] ?? array() ) ) ) );
$manual_products = array_values( array_diff( $manual_products, $excluded_products ) );
$recommended_products = array_values( array_diff( $available_products, $excluded_products ) );

$args = array(
    'post_type'           => 'product',
    'posts_per_page'      => 4,
    'meta_key'            => '_wc_average_rating',
    'orderby'             => 'meta_value_num',
    'order'               => 'DESC',
    'post_status'         => 'publish',
    'ignore_sticky_posts' => 1,
	'post__in'             => $recommended_products ?: array( 0 ),
);

if ( $manual_products ) {
    $args['post__in'] = $manual_products;
    $args['orderby']  = 'post__in';
    unset( $args['meta_key'], $args['order'] );
}

$recommend_products = new WP_Query( $args );

if ( $recommend_products->have_posts() ) : ?>

    <section class="recommend-products popular-products">
        <div class="products-block">
            <div class="products-block__header">
                <div>
                    <h2 class="products-block__title"><?php esc_html_e( 'Рекомендуем приобрести', 'itsoft' ); ?></h2>
                </div>
            </div>

            <div class="products-block__grid recommend-products__grid">
                <?php while ( $recommend_products->have_posts() ) : $recommend_products->the_post(); ?>
                    <?php wc_get_template_part( 'content', 'product-card' ); ?>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <?php wp_reset_postdata(); ?>

<?php endif; ?>
