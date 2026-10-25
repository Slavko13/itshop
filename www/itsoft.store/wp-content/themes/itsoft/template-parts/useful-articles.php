<?php
/**
 * Template part for displaying useful articles on the front page
 *
 * @package itsoft
 */

$front_page_id = (int) get_option( 'page_on_front' );
$configured_category = function_exists( 'get_field' ) ? absint( get_field( 'home_articles_category', $front_page_id ) ) : 0;
$category_id = $configured_category ?: (int) get_option( 'default_category' );
$current_cat = get_category( $category_id );

if ( !$current_cat ) {
    return;
}

$args = [
    'cat'                 => $current_cat->term_id,
    'posts_per_page'      => 3,
    'ignore_sticky_posts' => 1,
    'no_found_rows'       => true,
];

$query = new WP_Query( $args );

if ( !$query->have_posts() ) {
    return;
}

$cat_name = esc_html( $current_cat->name );
$cat_link = esc_url( get_category_link( $current_cat->term_id ) );
?>

<section class="useful-articles" aria-labelledby="home-articles-title">
    <div class="home-section-heading">
        <div>
            <h2 class="useful-articles__title" id="home-articles-title"><?php esc_html_e( 'Полезные статьи', 'itsoft' ); ?></h2>
        </div>
        <a href="<?php echo $cat_link; ?>" class="btn-show-all"><?php esc_html_e( 'Все статьи', 'itsoft' ); ?> <span aria-hidden="true">→</span></a>
    </div>
    <div class="useful-articles__list">
        <?php while ( $query->have_posts() ) : $query->the_post(); ?>
            <div class="item-posts__item">
                <a class="item-posts__link" href="<?php the_permalink(); ?>">
                    <?php itsoft_article_thumbnail( 0, 'item-posts__thumb', 'eager' ); ?>
                </a>
                <div class="item-posts__info">
                    <div class="item-posts__status">
                        <a class="item-posts__cat-link" href="<?php echo $cat_link; ?>">
                            <?php echo $cat_name; ?>
                        </a>
                        <time class="item-posts__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                            <?php echo get_the_date( 'j F Y' ); ?>
                        </time>
                    </div>
                    <a class="item-posts__title" href="<?php the_permalink(); ?>">
                        <?php the_title(); ?>
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
        <?php wp_reset_postdata(); ?>
    </div>
    <div class="useful-articles__more">
        <a href="<?php echo $cat_link; ?>" class="useful-articles__show-more"><?php esc_html_e( 'Показать все', 'itsoft' ); ?></a>
    </div>
</section>
