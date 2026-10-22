<?php
/**
 * Статичный вывод блока цен для карточки товара.
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

$prices = $product_card_context['prices'] ?? array();

$current_price_display = $prices['current_display'] ?? '';

if ( ! $current_price_display ) {
	return;
}

$regular_price_display = $prices['regular_display'] ?? '';
$has_discount          = ! empty( $prices['has_discount'] );
$discount_percent      = isset( $prices['discount_percent'] ) ? (int) $prices['discount_percent'] : 0;
$current_classes       = 'prices__current' . ( $has_discount ? '' : ' prices__current--regular' );
$regular_classes       = 'prices__old' . ( $has_discount ? '' : ' is-hidden' );
$discount_classes      = 'prices__discount' . ( $has_discount ? '' : ' is-hidden' );
?>
<?php
$discount_label = $has_discount ? sprintf( esc_html__( '-%s%%', 'itsoft' ), esc_html( $discount_percent ) ) : '';
?>
<div class="product-card__prices prices" data-product-prices>
	<span class="<?php echo esc_attr( $current_classes ); ?>" data-price-field="current"><?php echo esc_html( $current_price_display ); ?></span>
	<span class="<?php echo esc_attr( $regular_classes ); ?>" data-price-field="regular"><?php echo esc_html( $regular_price_display ); ?></span>
	<span class="<?php echo esc_attr( $discount_classes ); ?>" data-price-field="discount"><?php echo esc_html( $discount_label ); ?></span>
</div>

