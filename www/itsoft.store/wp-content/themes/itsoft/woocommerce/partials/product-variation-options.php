<?php
/**
 * Partial: Product variation options block (static HTML for SEO + data hooks for JS)
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

$variations_context   = $product_card_context['variations'] ?? array();
$variation_items      = $variations_context['items'] ?? array();
$active_variation_id  = $variations_context['active_id'] ?? null;
$has_linked_options   = ! empty( $variations_context['has_linked'] );

if ( empty( $variation_items ) ) {
	return;
}

$var_desc = get_field( 'var_desc', $product->get_id() );
?>

<div class="product-card__options options" data-product-options>
	<?php if ( $var_desc ) : ?>
		<div class="options__title"><?php echo esc_html( $var_desc ); ?></div>
	<?php endif; ?>

	<div class="options__items" data-product-variations<?php echo $has_linked_options ? ' data-linked-options="1"' : ''; ?>>
		<?php foreach ( $variation_items as $variation_item ) :
			$variation_id        = isset( $variation_item['id'] ) ? (int) $variation_item['id'] : 0;
			$option_label        = $variation_item['label'] ?? '';
			$attributes          = $variation_item['attributes'] ?? array();
			$attributes_display  = $variation_item['attributes_display'] ?? array();
			$prices              = $variation_item['prices'] ?? array();
			$price_display       = $prices['current'] ?? '';
			$raw_prices          = $prices['raw'] ?? array();
			$discount_percent    = isset( $prices['discount_percent'] ) ? (int) $prices['discount_percent'] : 0;
			$has_discount        = ! empty( $prices['has_discount'] );
			$is_linked           = ! empty( $variation_item['is_linked'] );
			$is_active           = array_key_exists( 'is_active', $variation_item )
				? (bool) $variation_item['is_active']
				: ( $variation_id && $variation_id === (int) $active_variation_id );
			$linked_url          = $is_linked ? ( $variation_item['linked_url'] ?? '' ) : '';
			$data_attributes_str = '';

			if ( ! $is_linked ) {
				foreach ( $attributes as $attribute_slug => $attribute_value ) {
					$data_attributes_str .= sprintf(
						' data-attribute-%1$s="%2$s"',
						esc_attr( $attribute_slug ),
						esc_attr( $attribute_value )
					);
				}

				foreach ( $attributes_display as $attribute_slug => $display_value ) {
					$data_attributes_str .= sprintf(
						' data-attribute-display-%1$s="%2$s"',
						esc_attr( $attribute_slug ),
						esc_attr( $display_value )
					);
				}

				$data_attributes_str .= sprintf(
					' data-price="%1$s" data-regular-price="%2$s" data-sale-price="%3$s"',
					esc_attr( $prices['current'] ?? '' ),
					esc_attr( $prices['regular'] ?? '' ),
					esc_attr( $prices['sale'] ?? '' )
				);

				$data_attributes_str .= sprintf(
					' data-raw-price="%1$s" data-raw-regular-price="%2$s" data-raw-sale-price="%3$s" data-discount-percent="%4$s" data-has-discount="%5$s"',
					esc_attr( $raw_prices['current'] ?? '' ),
					esc_attr( $raw_prices['regular'] ?? '' ),
					esc_attr( $raw_prices['sale'] ?? '' ),
					esc_attr( $discount_percent ),
					esc_attr( $has_discount ? '1' : '0' )
				);
			}
		?>
		<?php if ( $is_linked && $linked_url ) : ?>
			<a
				class="options__item <?php echo $is_active ? 'option_active' : ''; ?>"
				href="<?php echo esc_url( $linked_url ); ?>"
				data-linked-url="<?php echo esc_url( $linked_url ); ?>">
				<span class="option__label"><?php echo esc_html( $option_label ); ?></span>
				<?php if ( $price_display ) : ?>
					<span class="option__price"><?php echo esc_html( $price_display ); ?></span>
				<?php endif; ?>
			</a>
		<?php else : ?>
			<button
				type="button"
				class="options__item <?php echo $is_active ? 'option_active' : ''; ?>"
				data-variation-option
				data-variation-id="<?php echo esc_attr( $variation_id ); ?>"
				data-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
				aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"<?php echo $data_attributes_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<span class="option__label"><?php echo esc_html( $option_label ); ?></span>
				<?php if ( $price_display ) : ?>
					<span class="option__price"><?php echo esc_html( $price_display ); ?></span>
				<?php endif; ?>
			</button>
		<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<a href="#variations-diff-<?php echo esc_attr( $product->get_id() ); ?>" class="options__diff-link" data-fancybox><?php esc_html_e( 'В чем различия?', 'itsoft' ); ?></a>

	<div id="variations-diff-<?php echo esc_attr( $product->get_id() ); ?>" class="variations-diff-popup" style="display: none;">
		<div class="variations-diff-popup__tabs">
			<?php foreach ( $variation_items as $index => $item ) : ?>
				<button class="variations-diff-popup__tab <?php echo 0 === $index ? 'active' : ''; ?>" data-tab-target="<?php echo esc_attr( $item['id'] ); ?>">
					<?php echo esc_html( $item['label'] ); ?>
				</button>
			<?php endforeach; ?>
		</div>
		<div class="variations-diff-popup__content">
			<?php foreach ( $variation_items as $index => $item ) : ?>
				<div data-tab-content="<?php echo esc_attr( $item['id'] ); ?>" class="variations-diff-popup__pane <?php echo 0 === $index ? 'active' : ''; ?>">
					<?php echo wp_kses_post( $item['description'] ?? '' ); ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

