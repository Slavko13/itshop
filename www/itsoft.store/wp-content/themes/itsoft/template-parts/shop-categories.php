<?php
/**
 * Template part for displaying shop categories on the front page
 *
 * @package itsoft
 */

$cats = get_field( 'home_cats' );
if( !$cats ) {
    return;
}
?>

<section class="shop-cats" id="home-categories" aria-labelledby="home-categories-title">
    <div class="home-section-heading">
        <div>
            <h2 class="shop-cats__title" id="home-categories-title"><?php echo esc_html( get_field( 'home_cats_title' ) ?: __( 'Популярные категории', 'itsoft' ) ); ?></h2>
        </div>
        <p><?php esc_html_e( 'Перейдите сразу к нужной линейке программ и версиям.', 'itsoft' ); ?></p>
    </div>
    
    <div class="shop-cats__list">
        <?php foreach( $cats as $cat ): 
            // Reset variables for each iteration to prevent data leakage
            $image_url = null;
            $image_alt = '';
            
            $cat_obj = $cat['shop_category'];
            
            // Get category thumbnail
            $thumbnail_id = get_term_meta( $cat_obj->term_id, 'thumbnail_id', true );
            if ( $thumbnail_id ) {
                $image_url = wp_get_attachment_url( $thumbnail_id );
                $image_alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
                if ( !$image_alt ) {
                    $image_alt = $cat_obj->name;
                }
            } else {
                // Explicitly set to null when no image is found
                $image_url = null;
            }
            ?>
            <?php $category_link = get_term_link( $cat_obj, 'product_cat' ); ?>
            <?php if ( is_wp_error( $category_link ) ) { continue; } ?>
            <a class="shop-cats__item" href="<?php echo esc_url( $category_link ); ?>">
                <div class="shop-cats__name">
                    <span class="shop-cats__link"><?php echo esc_html( $cat['shop_category_name'] ?: $cat_obj->name ); ?></span>
                    <small><?php echo esc_html( sprintf( _n( '%s товар', '%s товаров', (int) $cat_obj->count, 'itsoft' ), number_format_i18n( (int) $cat_obj->count ) ) ); ?></small>
                </div>
                
                <div class="shop-cats__img">
                    <?php if ( $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy" decoding="async">
                    <?php endif; ?>
                </div>
                
                <div class="shop-cats__bottom">
                    <span class="shop-cats__btn"><?php esc_html_e( 'Смотреть товары', 'itsoft' ); ?> <span aria-hidden="true">→</span></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
