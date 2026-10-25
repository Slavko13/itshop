<?php
/**
 * Template Name: Все статьи
 *
 * @package itsoft
 */

get_header();
?>

<div class="grid-container">
    <?php if ( function_exists( 'kama_breadcrumbs' ) ) : ?>
        <nav class="breadcrumbs" aria-label="Хлебные крошки">
            <?php kama_breadcrumbs( " " ); ?>
        </nav>
    <?php endif; ?>
</div>

<?php while ( have_posts() ) : the_post(); ?>
    <div class="category-head">
        <div class="grid-container">
            <h1 class="category__title"><?php the_title(); ?></h1>
            <div class="category__desc"><?php the_content(); ?></div>
        </div>
    </div>
<?php endwhile; ?>

<?php
$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$posts_query = new WP_Query( [
    'post_type'           => 'post',
    'paged'               => $paged,
    'posts_per_page'      => (int) get_option( 'posts_per_page' ),
    'ignore_sticky_posts' => 1,
] );
?>

<div class="grid-container">
    <div class="category-main">
        <?php if ( $posts_query->have_posts() ) : ?>
            <div class="blog-grid" data-blog-grid data-current-page="<?php echo esc_attr( $paged ); ?>" data-max-pages="<?php echo esc_attr( (int) $posts_query->max_num_pages ); ?>">
                <div class="category-posts blog-grid__list">
                    <?php while ( $posts_query->have_posts() ) : $posts_query->the_post(); ?>
                        <?php
                        $categories = get_the_category();
                        $category = $categories[0] ?? null;
                        $cat_name = $category ? esc_html( $category->name ) : '';
                        $cat_link = $category ? esc_url( get_category_link( $category->term_id ) ) : '';
                        ?>
                        <div class="item-posts__item">
                            <a class="item-posts__link" href="<?php the_permalink(); ?>">
                                <?php itsoft_article_thumbnail(); ?>
                            </a>
                            <div class="item-posts__info">
                                <div class="item-posts__status">
                                    <?php if ( $cat_link ) : ?>
                                        <a class="item-posts__cat-link" href="<?php echo $cat_link; ?>">
                                            <?php echo $cat_name; ?>
                                        </a>
                                    <?php endif; ?>
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
                </div>
                <div class="blog-grid__status" aria-live="polite"></div>
                <div class="blog-grid__sentinel" aria-hidden="true"></div>
            </div>
        <?php else : ?>
            <p>Записей нет.</p>
        <?php endif; ?>
    </div>
</div>

<?php wp_reset_postdata(); ?>

<?php
get_footer();
