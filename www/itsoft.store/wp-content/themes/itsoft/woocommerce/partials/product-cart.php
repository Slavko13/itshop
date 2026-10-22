<?php
/**
 * Partial: Product add-to-cart block (static markup + WP data hooks).
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

$cart_context       = $product_card_context['cart'] ?? array();
$variations_context = $product_card_context['variations'] ?? array();
$variation_items    = $variations_context['items'] ?? array();
$active_variation_id = isset( $variations_context['active_id'] ) ? (int) $variations_context['active_id'] : 0;

if ( empty( $cart_context ) ) {
	return;
}

$min_quantity     = isset( $cart_context['min_quantity'] ) ? (int) $cart_context['min_quantity'] : 1;
$max_quantity     = $cart_context['max_quantity'] ?? '';
$step             = isset( $cart_context['step'] ) ? (float) $cart_context['step'] : 1;
$default_quantity = isset( $cart_context['default_quantity'] ) ? (float) $cart_context['default_quantity'] : $min_quantity;

$button_text         = $cart_context['button_text'] ?? esc_html__( 'Добавить в корзину', 'itsoft' );
$button_in_cart_text = $cart_context['button_in_cart_text'] ?? esc_html__( 'В корзине', 'itsoft' );
$button_url          = $cart_context['button_url'] ?? '';
$button_default_ui_text = esc_html__( 'Добавить в корзину', 'itsoft' );

$is_purchasable = ! empty( $cart_context['is_purchasable'] );
$is_in_stock    = ! empty( $cart_context['is_in_stock'] );

if ( ! $is_in_stock ) {
	$button_default_ui_text = esc_html__( 'Временно нет в наличии', 'itsoft' );
} elseif ( ! $is_purchasable ) {
	$button_default_ui_text = esc_html__( 'Недоступно для покупки', 'itsoft' );
}

$button_disabled_attr = ( ! $is_purchasable || ! $is_in_stock ) ? ' disabled' : '';

$product_id         = isset( $cart_context['product_id'] ) ? (int) $cart_context['product_id'] : 0;
$product_type       = $cart_context['product_type'] ?? 'simple';
$form_action        = $cart_context['button_url'] ?? '';
$default_attributes = array();

if ( $product instanceof WC_Product_Variable ) {
	$default_attributes = $product->get_default_attributes();
}

$attribute_slugs = array();

foreach ( $variation_items as $variation_item ) {
	if ( empty( $variation_item['attributes'] ) || ! is_array( $variation_item['attributes'] ) ) {
		continue;
	}

	foreach ( $variation_item['attributes'] as $attribute_slug => $_value ) {
		$attribute_slugs[ $attribute_slug ] = true;
	}
}

$wrapper_data_attributes = array(
	'data-product-id'      => $cart_context['product_id'] ?? '',
	'data-product-type'    => $cart_context['product_type'] ?? '',
	'data-product-sku'     => $cart_context['sku'] ?? '',
	'data-add-to-cart-url' => $button_url,
	'data-cart-url'        => $cart_context['cart_url'] ?? '',
	'data-ajax-url'        => $cart_context['ajax_url'] ?? '',
);

$wrapper_data_attr_string = '';

foreach ( $wrapper_data_attributes as $attr_name => $attr_value ) {
	if ( '' === $attr_value ) {
		continue;
	}

	$wrapper_data_attr_string .= sprintf(
		' %1$s="%2$s"',
		esc_attr( $attr_name ),
		esc_attr( $attr_value )
	);
}
?>

<div class="product-card__cart cart" data-product-cart<?php echo $wrapper_data_attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<form class="cart__form" data-cart-form action="<?php echo esc_url( $form_action ); ?>" method="post">
		<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product_id ); ?>" data-form-field="add-to-cart" />
		<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" />
		<?php if ( 'variable' === $product_type ) : ?>
			<input type="hidden" name="variation_id" value="<?php echo esc_attr( $active_variation_id ); ?>" data-form-field="variation-id" data-form-default-value="<?php echo esc_attr( $active_variation_id ); ?>" />
			<?php foreach ( array_keys( $attribute_slugs ) as $attribute_slug ) :
				$attribute_name   = 'attribute_' . $attribute_slug;
				$default_value    = $default_attributes[ $attribute_slug ] ?? '';
				?>
				<input
					type="hidden"
					name="<?php echo esc_attr( $attribute_name ); ?>"
					value="<?php echo esc_attr( $default_value ); ?>"
					data-form-attribute="<?php echo esc_attr( $attribute_slug ); ?>"
					<?php echo '' !== $default_value ? 'data-form-default-value="' . esc_attr( $default_value ) . '"' : ''; ?>
				/>
			<?php endforeach; ?>
		<?php endif; ?>

		<!-- product__quantity hidden по умолчанию, появляется active после первого клика -->
		<div class="product__quantity" data-quantity-controls data-quantity-visible="false" data-min="<?php echo esc_attr( $min_quantity ); ?>" data-step="<?php echo esc_attr( $step ); ?>" data-sold-individually="<?php echo ! empty( $cart_context['is_sold_individually'] ) ? '1' : '0'; ?>">
			<button class="quantity-btn minus" type="button" data-quantity-btn="decrement" aria-label="<?php esc_attr_e( 'Уменьшить количество', 'itsoft' ); ?>">
				<svg width="35" height="20" viewBox="0 0 35 20" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M10 10H25" stroke="#6C4CFF" stroke-width="2" />
				</svg>
			</button>
			<input
				type="text"
				name="quantity"
				class="cart__quantity"
				data-quantity-input
				value="<?php echo esc_attr( $default_quantity ); ?>"
				dat-min="<?php echo esc_attr( $min_quantity ); ?>"
				data-min="<?php echo esc_attr( $min_quantity ); ?>"
				data-step="<?php echo esc_attr( $step ); ?>"
				data-max="<?php echo '' === $max_quantity ? '' : esc_attr( $max_quantity ); ?>"
				data-sold-individually="<?php echo ! empty( $cart_context['is_sold_individually'] ) ? '1' : '0'; ?>"
				inputmode="decimal"
			/>
			<button class="quantity-btn plus" type="button" data-quantity-btn="increment" aria-label="<?php esc_attr_e( 'Увеличить количество', 'itsoft' ); ?>">
				<svg width="35" height="35" viewBox="0 0 35 35" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M10 17.6744H17.5M25 17.6744H17.5M17.5 17.6744V10V25" stroke="#6C4CFF" stroke-width="2" />
				</svg>
			</button>
		</div>

		<button
			class="cart__btn btn"
			type="submit"
			data-cart-action="add"
			data-cart-state="idle"
			data-cart-button-default="<?php echo esc_attr( $button_default_ui_text ); ?>"
			data-cart-button-in-cart-top="<?php echo esc_attr( $button_in_cart_text ); ?>"
			data-cart-button-in-cart-bottom="<?php esc_attr_e( 'Перейти', 'itsoft' ); ?>"
			data-product-id="<?php echo esc_attr( $cart_context['product_id'] ?? 0 ); ?>"
			data-product-type="<?php echo esc_attr( $cart_context['product_type'] ?? '' ); ?>"
			data-add-to-cart-url="<?php echo esc_url( $button_url ); ?>"
			data-sold-individually="<?php echo ! empty( $cart_context['is_sold_individually'] ) ? '1' : '0'; ?>"
		<?php echo $button_disabled_attr; ?>
		>
			<span class="cart__btn-default" data-cart-button-default-view>
				<svg class="btn__icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M1.875 2.5H3.03C3.455 2.5 3.82583 2.78583 3.93583 3.19583L4.255 4.39333M4.255 4.39333C8.8972 4.26324 13.5349 4.77929 18.035 5.92667C17.3483 7.97167 16.5325 9.95833 15.5983 11.875H6.25M4.255 4.39333L6.25 11.875M6.25 11.875C5.58696 11.875 4.95107 12.1384 4.48223 12.6072C4.01339 13.0761 3.75 13.712 3.75 14.375H16.875M5 16.875C5 17.0408 4.93415 17.1997 4.81694 17.3169C4.69973 17.4342 4.54076 17.5 4.375 17.5C4.20924 17.5 4.05027 17.4342 3.93306 17.3169C3.81585 17.1997 3.75 17.0408 3.75 16.875C3.75 16.7092 3.81585 16.5503 3.93306 16.4331C4.05027 16.3158 4.20924 16.25 4.375 16.25C4.54076 16.25 4.69973 16.3158 4.81694 16.4331C4.93415 16.5503 5 16.7092 5 16.875ZM15.625 16.875C15.625 17.0408 15.5592 17.1997 15.4419 17.3169C15.3247 17.4342 15.1658 17.5 15 17.5C14.8342 17.5 14.6753 17.4342 14.5581 17.3169C14.4408 17.1997 14.375 17.0408 14.375 16.875C14.375 16.7092 14.4408 16.5503 14.5581 16.4331C14.6753 16.3158 14.8342 16.25 15 16.25C15.1658 16.25 15.3247 16.3158 15.4419 16.4331C15.5592 16.5503 15.625 16.7092 15.625 16.875Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
				<span class="btn__text"><?php echo esc_html( $button_default_ui_text ); ?></span>
			</span>
			<span class="cart__btn-in-cart" data-cart-button-in-cart-view hidden>
				<span class="cart__btn-top"><?php echo esc_html( $button_in_cart_text ); ?></span>
				<span class="cart__btn-bottom"><?php esc_html_e( 'Перейти', 'itsoft' ); ?></span>
			</span>
		</button>
	</form>
</div>
<span
	class="cart__notice"
	data-cart-notice
	data-cart-notice-for="<?php echo esc_attr( $product_id ); ?>"
	data-state="hidden"
	aria-live="polite"
></span>


