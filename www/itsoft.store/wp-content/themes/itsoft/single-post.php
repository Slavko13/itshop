<?php get_header(); ?>
<?php
$post_comments_count = (int) get_comments_number();
$post_author_name    = get_the_author();
?>
<div class="grid-container">
    <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <?php kama_breadcrumbs(" "); ?>
    </nav>
</div>
<div class="article-head">
  <div class="grid-container">
    <h1 class="article-head__title"><?php the_title(); ?></h1>

    <div class="article-head__meta">
    <span class="meta-item meta-item--author">
        <span class="meta-item__icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M13.1251 5C13.1251 5.8288 12.7959 6.62366 12.2099 7.20971C11.6238 7.79576 10.8289 8.125 10.0001 8.125C9.17134 8.125 8.37649 7.79576 7.79043 7.20971C7.20438 6.62366 6.87514 5.8288 6.87514 5C6.87514 4.1712 7.20438 3.37634 7.79043 2.79029C8.37649 2.20424 9.17134 1.875 10.0001 1.875C10.8289 1.875 11.6238 2.20424 12.2099 2.79029C12.7959 3.37634 13.1251 4.1712 13.1251 5ZM3.75098 16.765C3.77776 15.1253 4.44792 13.5618 5.61696 12.4117C6.78599 11.2616 8.36022 10.6171 10.0001 10.6171C11.6401 10.6171 13.2143 11.2616 14.3833 12.4117C15.5524 13.5618 16.2225 15.1253 16.2493 16.765C14.2888 17.664 12.1569 18.1279 10.0001 18.125C7.77014 18.125 5.65348 17.6383 3.75098 16.765Z" stroke="#6C4CFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
        <span class="meta-item__text"><?php echo esc_html( $post_author_name ); ?></span>
    </span>

    <span class="meta-item meta-item--date">
        <span class="meta-item__icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 5V10H13.75M17.5 10C17.5 10.9849 17.306 11.9602 16.9291 12.8701C16.5522 13.7801 15.9997 14.6069 15.3033 15.3033C14.6069 15.9997 13.7801 16.5522 12.8701 16.9291C11.9602 17.306 10.9849 17.5 10 17.5C9.01509 17.5 8.03982 17.306 7.12987 16.9291C6.21993 16.5522 5.39314 15.9997 4.6967 15.3033C4.00026 14.6069 3.44781 13.7801 3.0709 12.8701C2.69399 11.9602 2.5 10.9849 2.5 10C2.5 8.01088 3.29018 6.10322 4.6967 4.6967C6.10322 3.29018 8.01088 2.5 10 2.5C11.9891 2.5 13.8968 3.29018 15.3033 4.6967C16.7098 6.10322 17.5 8.01088 17.5 10Z" stroke="#6C4CFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
        <time class="meta-item__text" datetime="<?php echo get_the_date( 'Y-m-d' ); ?>"><?php echo get_the_date( 'j F Y' ); ?></time>
    </span>

    <span class="meta-item meta-item--comments" aria-label="Количество комментариев">
        <span class="meta-item__icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 16.875C14.1417 16.875 17.5 13.7967 17.5 10C17.5 6.20333 14.1417 3.125 10 3.125C5.85833 3.125 2.5 6.20333 2.5 10C2.5 11.7533 3.21583 13.3525 4.39417 14.5667C4.75417 14.9392 5.01083 15.4333 4.8825 15.9342C4.74198 16.4818 4.47898 16.9904 4.11333 17.4217C4.40595 17.4742 4.7027 17.5004 5 17.5C6.06833 17.5 7.05833 17.165 7.87083 16.5942C8.54583 16.7775 9.26083 16.875 10 16.875Z" stroke="#6C4CFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
        <span class="meta-item__text"><?php echo esc_html( number_format_i18n( $post_comments_count ) ); ?></span>
    </span>
    </div>
  </div>
</div>
<div class="grid-container">
    <div class="main">
        <div class="article">
            <?php the_content(); ?>
        </div>
        <div class="comments">
           
        </div>
    </div>

    <aside class="aside">
    <?php
    $current_id  = get_the_ID();
    $categories  = get_the_category( $current_id );
    $current_cat = $categories[0] ?? false;
    $exclude_posts = [ $current_id ];

    if ( $current_cat ) :
        $args = [
            'cat'                 => $current_cat->term_id,
            'post__not_in'        => $exclude_posts,
            'posts_per_page'      => 2,
            'ignore_sticky_posts' => 1,
            'no_found_rows'       => true,
        ];

        $query = new WP_Query( $args );

        if ( $query->have_posts() ) :
            $cat_name = esc_html( $current_cat->name );
            $cat_link = esc_url( get_category_link( $current_cat->term_id ) );
            $exclude_posts[] = get_the_ID();
            ?>
            <div class="aside-posts">
                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                    <div class="item-posts__item">
                        <a class="item-posts__link" href="<?php the_permalink(); ?>">
                            <?php the_post_thumbnail( 'aside-news', [ 'class' => 'item-posts__thumb' ] ); ?>
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
            </div>
            <?php
            wp_reset_postdata();
        endif;
    endif;
    ?>
    </aside>
</div>
<div class="grid-container">
    <div class="related-posts">
        <div class="related-posts__title">Похожие статьи</div>
        <div class="related-posts__list">
            <?php
                    $args = [
                    'cat'                 => $current_cat->term_id,
                    'post__not_in'        => $exclude_posts,
                    'posts_per_page'      => 3,
                    'ignore_sticky_posts' => 1,
                    'no_found_rows'       => true,
                ];

                $query = new WP_Query( $args );

                if ( $query->have_posts() ) :
                    $cat_name = esc_html( $current_cat->name );
                    $cat_link = esc_url( get_category_link( $current_cat->term_id ) );
                    $exclude_posts[] = get_the_ID();
                    ?>
                        <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                            <div class="item-posts__item">
                                <a class="item-posts__link" href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail( 'aside-news', [ 'class' => 'item-posts__thumb' ] ); ?>
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
                    <?php
                    wp_reset_postdata();
                endif;
            ?>
        </div>
    </div>
</div>
<?php get_footer(); ?>
