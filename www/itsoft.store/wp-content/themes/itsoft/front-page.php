<?php get_header(); ?>
    <div class="grid-container">
        <div class="home-main">

            <?php get_template_part( 'template-parts/shop-categories' ); ?>

            <?php get_template_part( 'template-parts/popular-products' ); ?>

            <?php get_template_part( 'template-parts/recommend-products' ); ?>

            <?php get_template_part( 'template-parts/useful-articles' ); ?>
        </div>
        <div class="article home__article">
            <h2 class="home__title"><?php the_title(); ?></h2>
            <div class="home__content"><?php the_content(); ?></div>
        </div>
    </div>
<?php get_footer(); ?>
