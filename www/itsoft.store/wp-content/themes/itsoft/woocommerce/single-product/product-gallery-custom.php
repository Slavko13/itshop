<?php
/**
 * Custom Single Product Gallery
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/product-gallery-custom.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

// Get gallery images
$attachment_ids = $product->get_gallery_image_ids();

// Add the featured image as the first image if it exists
$post_thumbnail_id = $product->get_image_id();
if ( $post_thumbnail_id ) {
    array_unshift( $attachment_ids, $post_thumbnail_id );
}

// Remove duplicates and ensure we have at least the featured image
$attachment_ids = array_unique( $attachment_ids );

// If we still don't have any images, use the placeholder
if ( empty( $attachment_ids ) && ( $placeholder = wc_placeholder_img_src( 'product-image' ) ) ) {
    $attachment_ids[] = 0; // Use 0 to indicate placeholder
}
?>

<?php if ( ! empty( $attachment_ids ) ) : ?>
<div class="product-gallery" data-gallery-count="<?php echo esc_attr( count( $attachment_ids ) ); ?>">
    <?php if ( count( $attachment_ids ) > 1 ) : ?>
    <div class="swiper product-gallery__thumbs" aria-label="Миниатюры изображений">
        <div class="swiper-wrapper">
            <?php foreach($attachment_ids as $index => $id): ?>
                <button type="button" class="swiper-slide product-gallery__slide" aria-label="Показать изображение <?php echo esc_attr( $index + 1 ); ?>">
                    <?php 
                    if ( $id == 0 ) {
                        // Placeholder image
                        echo wc_placeholder_img( 'product-slide', array( 'loading' => 'lazy', 'decoding' => 'async' ) );
                    } else {
                        echo wp_get_attachment_image($id, 'product-slide', false, array( 'loading' => 'lazy', 'decoding' => 'async' ));
                    }
                    ?>
                </button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="swiper-button-prev" aria-label="Предыдущее изображение"></button>
        <button type="button" class="swiper-button-next" aria-label="Следующее изображение"></button>
    </div>
    <?php endif; ?>

    <div class="swiper product-gallery__main" aria-label="Галерея товара">
        <div class="swiper-wrapper">
            <?php foreach($attachment_ids as $main_image_index => $id): ?>
                <?php 
				$image_attributes = array(
					'loading'  => 0 === $main_image_index ? 'eager' : 'lazy',
					'decoding' => 'async',
					'sizes'    => '(max-width: 767px) calc(100vw - 58px), (max-width: 1199px) calc(50vw - 64px), 540px',
				);
				if ( 0 === $main_image_index ) {
					$image_attributes['fetchpriority'] = 'high';
				}

                if ( $id == 0 ) {
                    // Placeholder image
                    $full_image_url = wc_placeholder_img_src( 'full' );
                    $alt_text = __( 'Placeholder', 'woocommerce' );
					$image = wc_placeholder_img( 'product-image', $image_attributes );
                } else {
                    // Get the full image URL
                    $full_image = wp_get_attachment_image_src($id, 'full');
                    $full_image_url = $full_image ? $full_image[0] : '';

                    // Get the alt text
                    $alt_text = get_post_meta($id, '_wp_attachment_image_alt', true);
                    if (empty($alt_text)) {
                        $alt_text = get_the_title($id);
                    }
                    
					$image = wp_get_attachment_image($id, 'product-image', false, $image_attributes);
                }
                ?>
                <a href="<?php echo esc_url($full_image_url); ?>" data-fancybox="product" data-caption="<?php echo esc_attr($alt_text); ?>" class="swiper-slide" aria-label="Увеличить изображение товара">
                    <?php echo $image; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
