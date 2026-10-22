<?php
$front_page_id        = (int) get_option( 'page_on_front' );
$home_hero_mode       = function_exists( 'get_field' ) ? (string) get_field( 'home_hero_mode', $front_page_id ) : '';
$home_hero_shortcode  = function_exists( 'get_field' ) ? trim( (string) get_field( 'home_hero_shortcode', $front_page_id ) ) : '';
$home_uses_slider     = '' !== $home_hero_shortcode && 'native' !== $home_hero_mode;

get_header();
?>
    <div class="grid-container">
        <div class="home-main">

            <?php get_template_part( 'template-parts/shop-categories' ); ?>

            <?php get_template_part( 'template-parts/popular-products' ); ?>

            <?php get_template_part( 'template-parts/recommend-products' ); ?>

            <?php get_template_part( 'template-parts/useful-articles' ); ?>
        </div>
        <div class="article home__article">
            <?php if ( $home_uses_slider ) : ?>
                <h1 class="home__title"><?php the_title(); ?></h1>
            <?php else : ?>
                <h2 class="home__title"><?php the_title(); ?></h2>
            <?php endif; ?>
            <div class="home__content"><?php the_content(); ?></div>
        </div>
    </div>
<?php get_footer(); ?>
