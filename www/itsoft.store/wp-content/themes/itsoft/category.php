<?php get_header(); ?>
<div class="grid-container">
    <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <?php kama_breadcrumbs(" "); ?>
    </nav>
</div>
<div class="category-head">
    <div class="grid-container">
        <h1 class="category__title"><?php echo single_cat_title(); ?></h1>
        <div class="category__desc"><?php the_field('short_description', get_queried_object()); ?></div>
    </div>
</div>
<div class="grid-container">
    <div class="category-main">
        <?php if ( have_posts() ) : ?>
            <div class="category-posts">
                <?php while ( have_posts() ) : the_post(); ?>
                    <div class="item-posts__item">
                        <a class="item-posts__link" href="<?php the_permalink(); ?>">
                            <?php the_post_thumbnail( 'category-post', [ 'class' => 'item-posts__thumb' ] ); ?>
                        </a>
                        <div class="item-posts__info">
                            <div class="item-posts__status">
                                <a class="item-posts__cat-link" href="">
                                    <?php echo single_cat_title(); ?>
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
            </div>
            <?php else : ?>
                <p>Записей в этой категории нет.</p>
            <?php endif; ?>
    </div>
</div>
<div class="grid-container">
    <div class="category-bottom"><?php echo category_description(); ?></div>
</div>
<?php get_footer(); ?>