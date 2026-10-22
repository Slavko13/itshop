<?php
/**
 * Partial: Product rating block (static markup + WooCommerce data).
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

global $product;

$args = isset( $args ) && is_array( $args ) ? $args : array();

$product_card_context = $args['product_card'] ?? array();

if ( empty( $product_card_context ) && $product instanceof WC_Product ) {
	$product_card_context = Itsoft_Product_Card_Controller::get_context( $product );
}

$rating_context = $product_card_context['rating'] ?? array();

$average_display = $rating_context['average_display'] ?? '0';
$filled_stars    = isset( $rating_context['filled_stars'] ) ? (int) $rating_context['filled_stars'] : 0;
$max_stars       = isset( $rating_context['max_stars'] ) ? (int) $rating_context['max_stars'] : 5;
$review_count    = isset( $rating_context['review_count'] ) ? (int) $rating_context['review_count'] : 0;

if ( $max_stars <= 0 ) {
	$max_stars = 5;
}

$filled_stars = max( 0, min( $filled_stars, $max_stars ) );

$reviews_text = sprintf(
	_n( '%s отзыв', '%s отзывов', $review_count, 'itsoft' ),
	number_format_i18n( $review_count )
);

$star_svg_filled = <<<'SVG'
<svg width="20" height="19" viewBox="0 0 20 19" fill="none" xmlns="http://www.w3.org/2000/svg">
	<path d="M9.23888 1.08939C9.28045 0.989012 9.35125 0.903143 9.44226 0.842707C9.53328 0.782271 9.6404 0.75 9.75 0.75C9.85959 0.75 9.96671 0.782271 10.0577 0.842707C10.1487 0.903143 10.2195 0.989012 10.2611 1.08939L12.3498 6.06218C12.3889 6.15523 12.4532 6.2358 12.5356 6.29502C12.6179 6.35424 12.7152 6.38983 12.8167 6.39785L18.2404 6.8279C18.7309 6.86682 18.9295 7.47297 18.556 7.78918L14.4238 11.2938C14.3466 11.3591 14.289 11.4443 14.2575 11.5399C14.2259 11.6355 14.2216 11.7378 14.2449 11.8357L15.5079 17.0751C15.5333 17.1802 15.5267 17.2904 15.4888 17.3918C15.451 17.4932 15.3836 17.5812 15.2953 17.6447C15.2069 17.7082 15.1015 17.7443 14.9924 17.7486C14.8833 17.7529 14.7754 17.725 14.6823 17.6686L10.038 14.8616C9.95127 14.8092 9.85162 14.7814 9.75 14.7814C9.64837 14.7814 9.54872 14.8092 9.462 14.8616L4.81772 17.6696C4.72459 17.726 4.61667 17.7538 4.50757 17.7496C4.39847 17.7453 4.29309 17.7092 4.20474 17.6456C4.11639 17.5821 4.04904 17.4941 4.01119 17.3928C3.97334 17.2914 3.96668 17.1812 3.99207 17.0761L5.25512 11.8357C5.27854 11.7378 5.27423 11.6355 5.24267 11.5398C5.21111 11.4442 5.15352 11.3591 5.07623 11.2938L0.944042 7.78918C0.860831 7.71897 0.800542 7.62589 0.770812 7.52173C0.741082 7.41757 0.743247 7.30703 0.777033 7.20409C0.81082 7.10115 0.874707 7.01046 0.960605 6.94349C1.0465 6.87652 1.15055 6.83629 1.25956 6.8279L6.68329 6.39785C6.78478 6.38983 6.88206 6.35424 6.96444 6.29502C7.04681 6.2358 7.11108 6.15523 7.15018 6.06218L9.23888 1.08939Z" fill="#FDEB00" stroke="#FCE700" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
</svg>
SVG;

$star_svg_empty = <<<'SVG'
<svg width="20" height="19" viewBox="0 0 20 19" fill="none" xmlns="http://www.w3.org/2000/svg">
	<path d="M9.23888 1.08939C9.28045 0.989012 9.35125 0.903143 9.44226 0.842707C9.53328 0.782271 9.6404 0.75 9.75 0.75C9.85959 0.75 9.96671 0.782271 10.0577 0.842707C10.1487 0.903143 10.2195 0.989012 10.2611 1.08939L12.3498 6.06218C12.3889 6.15523 12.4532 6.2358 12.5356 6.29502C12.6179 6.35424 12.7152 6.38983 12.8167 6.39785L18.2404 6.8279C18.7309 6.86682 18.9295 7.47297 18.556 7.78918L14.4238 11.2938C14.3466 11.3591 14.289 11.4443 14.2575 11.5399C14.2259 11.6355 14.2216 11.7378 14.2449 11.8357L15.5079 17.0751C15.5333 17.1802 15.5267 17.2904 15.4888 17.3918C15.451 17.4932 15.3836 17.5812 15.2953 17.6447C15.2069 17.7082 15.1015 17.7443 14.9924 17.7486C14.8833 17.7529 14.7754 17.725 14.6823 17.6686L10.038 14.8616C9.95127 14.8092 9.85162 14.7814 9.75 14.7814C9.64837 14.7814 9.54872 14.8092 9.462 14.8616L4.81772 17.6696C4.72459 17.726 4.61667 17.7538 4.50757 17.7496C4.39847 17.7453 4.29309 17.7092 4.20474 17.6456C4.11639 17.5821 4.04904 17.4941 4.01119 17.3928C3.97334 17.2914 3.96668 17.1812 3.99207 17.0761L5.25512 11.8357C5.27854 11.7378 5.27423 11.6355 5.24267 11.5398C5.21111 11.4442 5.15352 11.3591 5.07623 11.2938L0.944042 7.78918C0.860831 7.71897 0.800542 7.62589 0.770812 7.52173C0.741082 7.41757 0.743247 7.30703 0.777033 7.20409C0.81082 7.10115 0.874707 7.01046 0.960605 6.94349C1.0465 6.87652 1.15055 6.83629 1.25956 6.8279L6.68329 6.39785C6.78478 6.38983 6.88206 6.35424 6.96444 6.29502C7.04681 6.2358 7.11108 6.15523 7.15018 6.06218L9.23888 1.08939Z" stroke="#FCE700" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
</svg>
SVG;
?>

<div class="product-card__rating rating">
	<div class="rating__stars" data-rating="<?php echo esc_attr( $average_display ); ?>">
		<?php for ( $i = 1; $i <= $max_stars; $i++ ) :
			$is_filled   = $i <= $filled_stars;
			$star_class  = $is_filled ? 'rating__star' : 'rating__star rating__star-empty';
			$star_markup = $is_filled ? $star_svg_filled : $star_svg_empty;
			?>
			<span class="<?php echo esc_attr( $star_class ); ?>">
				<?php echo $star_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
		<?php endfor; ?>
	</div>
	<span class="rating__count"><?php echo esc_html( $reviews_text ); ?></span>
</div>

