<?php
/**
 * Partial: flexible product card for catalog grid.
 *
 * @package itsoft
 *
 * @var WC_Product $product
 * @var array      $context
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $product ) || ! $product instanceof WC_Product ) {
	return;
}

$permalink = get_permalink( $product->get_id() );

$badges      = array();
$badge_terms = get_the_terms( $product->get_id(), 'product_cat' );

if ( $badge_terms && ! is_wp_error( $badge_terms ) ) {
	$top_level = array();
	$nested    = array();

	foreach ( $badge_terms as $term ) {
		if ( 0 === (int) $term->parent ) {
			$top_level[] = $term;
		} else {
			$nested[] = $term;
		}
	}

	$ordered_terms = array_merge( $top_level, $nested );
	$normalized_badge_names = array();

	foreach ( $ordered_terms as $term ) {
		$term_name = get_field( 'short_name', $term );
		if ( ! $term_name ) {
			$term_name = $term->name;
		}

		if ( 'microsoft-office' === $term->slug ) {
			$term_name = 'Microsoft Office';
		} elseif ( 'microsoft-windows' === $term->slug ) {
			$term_name = 'Microsoft Windows';
		}

		$term_name = preg_replace( '/^Office\s+(20\d{2})$/ui', '$1', trim( (string) $term_name ) );
		$term_name = preg_replace( '/^Microsoft\s+Office$/ui', 'Microsoft Office', trim( (string) $term_name ) );
		$normalized_name = strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', '', (string) $term_name ) );

		if ( '' === $normalized_name || in_array( $normalized_name, $normalized_badge_names, true ) ) {
			continue;
		}

		$normalized_badge_names[] = $normalized_name;

		$badges[] = array(
			'name' => $term_name,
		);

		if ( count( $badges ) >= 2 ) {
			break;
		}
	}
}

$img_size = isset( $image_size ) ? $image_size : 'product-image';

$image = $product->get_image(
	$img_size,
	array(
		'class'    => 'catalog-card__image',
		'loading'  => 'lazy',
		'decoding' => 'async',
	)
);

if ( empty( $image ) ) {
	$image = wc_placeholder_img(
		$img_size,
		array(
			'class' => 'catalog-card__image',
		)
	);
}

$price_current = $context['prices']['current_display'] ?? '';
$price_regular = $context['prices']['regular_display'] ?? '';
$has_discount  = ! empty( $context['prices']['has_discount'] );
$discount      = $context['prices']['discount_percent'] ?? 0;
$price_raw     = isset( $context['prices']['current_raw'] ) ? (float) $context['prices']['current_raw'] : 0.0;

$rating_average = $context['rating']['average'] ?? 0;
$rating_display = $context['rating']['average_display'] ?? '';
$review_count   = $context['rating']['review_count'] ?? 0;

$rating_aria = $rating_average > 0
	? sprintf(
		/* translators: %s - product rating */
		esc_html__( 'Средняя оценка %s из 5', 'itsoft' ),
		$rating_display
	)
	: esc_html__( 'Отзывов пока нет', 'itsoft' );

$button_classes = array(
	'catalog-card__buy',
	'btn',
	'button',
	'product_type_' . $product->get_type(),
);

if ( $product->is_purchasable() && $product->is_in_stock() ) {
	$button_classes[] = 'add_to_cart_button';
}

if ( $product->supports( 'ajax_add_to_cart' ) ) {
	$button_classes[] = 'ajax_add_to_cart';
}

$button_classes = implode( ' ', array_filter( array_unique( $button_classes ) ) );
$button_url     = $product->add_to_cart_url();
$button_text    = $product->add_to_cart_text();
$button_aria    = $product->add_to_cart_description();
$is_available   = $product->is_purchasable() && $product->is_in_stock();
$quick_buy_url  = ! is_product() && function_exists( 'itsoft_get_quick_buy_url' )
	? itsoft_get_quick_buy_url( $product )
	: '';

$card_classes = 'catalog-grid__item catalog-card';
if ( ! empty( $extra_class ) ) {
	$card_classes .= ' ' . $extra_class;
}

$metrika_categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
$metrika_product    = array(
	'id'       => (string) ( $product->get_sku() ?: $product->get_id() ),
	'name'     => wp_strip_all_tags( $product->get_name() ),
	'brand'    => 'Microsoft',
	'category' => is_wp_error( $metrika_categories ) ? '' : implode( '/', $metrika_categories ),
	'price'    => $price_raw,
	'quantity' => 1,
);
?>

<article class="<?php echo esc_attr( $card_classes ); ?>" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-metrika-product="<?php echo esc_attr( wp_json_encode( $metrika_product, JSON_UNESCAPED_UNICODE ) ); ?>" itemscope itemtype="http://schema.org/Product">
	<div class="catalog-card__media">
		<?php if ( $badges || ( $has_discount && $discount ) ) : ?>
			<div class="catalog-card__labels">
				<?php if ( $badges ) : ?>
				<div class="catalog-card__badges">
					<?php foreach ( $badges as $badge ) : ?>
						<span class="catalog-card__badge"><?php echo esc_html( $badge['name'] ); ?></span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php if ( $has_discount && $discount ) : ?>
					<span class="catalog-card__discount">-<?php echo esc_html( $discount ); ?>%</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<a class="catalog-card__image-link" href="<?php echo esc_url( $permalink ); ?>">
			<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
	</div>

	<div class="catalog-card__body">
		<div class="catalog-card__title" itemprop="name">
			<a class="catalog-card__link" href="<?php echo esc_url( $permalink ); ?>">
				<?php echo esc_html( get_the_title( $product->get_id() ) ); ?>
			</a>
		</div>

		<div class="catalog-card__meta">
			<div class="catalog-card__rating<?php echo $rating_average > 0 ? '' : ' catalog-card__rating--empty'; ?>" aria-label="<?php echo esc_attr( $rating_aria ); ?>">
				<span class="catalog-card__rating-icon">
					<?php echo itsoft_catalog_get_icon( 'star' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="catalog-card__rating-value"><?php echo esc_html( $rating_display ?: __( 'Нет оценок', 'itsoft' ) ); ?></span>
			</div>

			<div class="catalog-card__reviews" aria-label="<?php esc_attr_e( 'Количество отзывов', 'itsoft' ); ?>">
				<span class="catalog-card__reviews-icon">
					<?php echo itsoft_catalog_get_icon( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="catalog-card__reviews-count"><?php echo esc_html( number_format_i18n( (int) $review_count ) ); ?></span>
			</div>
		</div>

		<div class="catalog-card__footer">
			<div class="catalog-card__pricing" itemprop="offers" itemscope itemtype="http://schema.org/Offer">
				<?php
				$price_current_classes = 'catalog-card__price-current';
				if ( empty( $price_regular ) ) {
					$price_current_classes .= ' catalog-card__price-current--regular';
				}
				?>
				<span class="<?php echo esc_attr( $price_current_classes ); ?>" itemprop="price"><?php echo esc_html( $price_current ); ?></span>
				<?php if ( $price_regular ) : ?>
					<span class="catalog-card__price-old"><?php echo esc_html( $price_regular ); ?></span>
				<?php endif; ?>
				<meta itemprop="price" content="<?php echo esc_attr( wc_format_decimal( $price_raw, wc_get_price_decimals() ) ); ?>" />
				<meta itemprop="priceCurrency" content="<?php echo esc_attr( get_woocommerce_currency() ); ?>" />
				<link itemprop="availability" href="<?php echo esc_url( $product->is_in_stock() ? 'http://schema.org/InStock' : 'http://schema.org/OutOfStock' ); ?>" />
			</div>

			<div class="catalog-card__actions">
				<a
					class="<?php echo esc_attr( $button_classes ); ?>"
					href="<?php echo esc_url( $button_url ); ?>"
					data-quantity="1"
					data-product_id="<?php echo esc_attr( $product->get_id() ); ?>"
					data-product_sku="<?php echo esc_attr( $product->get_sku() ); ?>"
					aria-label="<?php echo esc_attr( $button_aria ); ?>"
					rel="nofollow"
				>
					<span class="catalog-card__buy-label"><?php echo esc_html( $button_text ); ?></span>
					<span class="catalog-card__buy-icon" aria-hidden="true">→</span>
				</a>
				<?php if ( $quick_buy_url ) : ?>
					<a
						class="catalog-card__quick-buy button"
						href="<?php echo esc_url( $quick_buy_url ); ?>"
						aria-label="<?php echo esc_attr( sprintf( __( 'Купить «%s» в 1 клик', 'itsoft' ), get_the_title( $product->get_id() ) ) ); ?>"
						rel="nofollow"
					>
						<?php esc_html_e( 'Купить в 1 клик', 'itsoft' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( ! $is_available ) : ?>
					<span class="catalog-card__stock-note">
						<?php esc_html_e( 'Сейчас недоступен для покупки', 'itsoft' ); ?>
					</span>
				<?php endif; ?>
			</div>
		</div>
	</div>
</article>

