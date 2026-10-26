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

$category_icons = array(
    'microsoft-office'     => 'microsoft-office.svg',
    'microsoft-windows'    => 'microsoft-windows.svg',
    'microsoft-365'        => 'microsoft-365.svg',
    'microsoft-visio'      => 'microsoft-visio.svg',
    'microsoft-project'    => 'microsoft-project.svg',
    'windows-server-rds'   => 'windows-server.svg',
    'microsoft-sql-server' => 'microsoft-sql-server.svg',
);

// ACF repeaters can preserve non-zero array keys after rows are reordered.
// Track the first category that is actually rendered instead of trusting the
// source key so the mobile LCP image is always discovered eagerly.
$rendered_category_index = 0;
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
            
            // Use transparent vector artwork for the featured category set.
            if ( isset( $category_icons[ $cat_obj->slug ] ) ) {
                $image_url = get_template_directory_uri() . '/assets/images/category-icons/' . $category_icons[ $cat_obj->slug ];
                $image_alt = $cat['shop_category_name'] ?: $cat_obj->name;
            } else {
                $thumbnail_id = get_term_meta( $cat_obj->term_id, 'thumbnail_id', true );
                if ( $thumbnail_id ) {
                    $image_url = wp_get_attachment_url( $thumbnail_id );
                    $image_alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ?: $cat_obj->name;
                }
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
                        <img
                            src="<?php echo esc_url( $image_url ); ?>"
                            alt="<?php echo esc_attr( $image_alt ); ?>"
                            width="160"
                            height="160"
                            loading="<?php echo 0 === $rendered_category_index ? 'eager' : 'lazy'; ?>"
                            decoding="async"
                            <?php if ( 0 === $rendered_category_index ) : ?>fetchpriority="high"<?php endif; ?>
                        >
                    <?php endif; ?>
                </div>
                
                <div class="shop-cats__bottom">
                    <span class="shop-cats__btn"><?php esc_html_e( 'Смотреть товары', 'itsoft' ); ?> <span aria-hidden="true">→</span></span>
                </div>
            </a>
            <?php $rendered_category_index++; ?>
        <?php endforeach; ?>
    </div>
</section>
