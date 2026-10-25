<?php
/**
 * Popular products block
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$manual_products = function_exists( 'get_field' ) ? get_field( 'home_popular_products', (int) get_option( 'page_on_front' ) ) : array();
$manual_products = array_values( array_filter( array_map( 'absint', (array) $manual_products ) ) );
$available_products = itsoft_get_available_product_ids();
$manual_products    = array_values( array_intersect( $manual_products, $available_products ) );

$args = array(
    'post_type'           => 'product',
    'posts_per_page'      => 4,
    'meta_key'            => '_wc_review_count',
    'orderby'             => 'meta_value_num',
    'order'               => 'DESC',
    'post_status'         => 'publish',
    'ignore_sticky_posts' => 1,
	'post__in'            => $available_products ?: array( 0 ),
);

if ( $manual_products ) {
    $args['post__in'] = $manual_products;
    $args['orderby']  = 'post__in';
    unset( $args['meta_key'], $args['order'] );
}

$popular_products = new WP_Query( $args );
$GLOBALS['itsoft_home_popular_product_ids'] = wp_list_pluck( $popular_products->posts, 'ID' );

if ( $popular_products->have_posts() ) : ?>

    <section class="popular-products">
        <div class="products-block">
            <div class="products-block__header">
                <div>
                    <h2 class="products-block__title"><?php esc_html_e( 'Популярные товары', 'itsoft' ); ?></h2>
                </div>
                <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" class="btn-show-all"><?php esc_html_e( 'Весь каталог', 'itsoft' ); ?> <span aria-hidden="true">→</span></a>
            </div>

            <div class="products-block__grid">
                <?php while ( $popular_products->have_posts() ) : $popular_products->the_post(); ?>
                    <?php wc_get_template_part( 'content', 'product-card' ); ?>
                <?php endwhile; ?>
            </div>

        </div>
    </section>

    <?php wp_reset_postdata(); ?>

<?php endif; ?>
