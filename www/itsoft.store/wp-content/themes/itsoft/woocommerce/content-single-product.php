<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * Override this template by copying it to yourtheme/woocommerce/content-single-product.php
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // WPCS: XSS ok.
	return;
}

$single_product_context = array(
	'product_id'   => get_the_ID(),
	'product_card' => array(),
	'extra'        => array(),
	'content'      => array(),
	'faq'          => array(),
);

if ( $product instanceof WC_Product ) {
	$single_product_context = wp_parse_args(
		Itsoft_Product_Single_Controller::get_context( $product ),
		$single_product_context
	);
}

$product_card_context = $single_product_context['product_card'];

$product_purchase_context = wp_parse_args(
	$single_product_context['purchase'] ?? array(),
	array(
		'category'          => '',
		'sku'               => '',
		'is_virtual'        => false,
		'fulfilment_label'  => '',
		'fulfilment_detail' => '',
		'stock_label'       => '',
		'stock_state'       => 'unavailable',
		'short_description' => '',
		'steps'             => array(),
	)
);

$product_extra_context = wp_parse_args(
	$single_product_context['extra'],
	array(
		'attributes'                  => array(),
		'tags'                        => array(),
		'download'                    => array(),
		'has_tags'                    => false,
		'has_download'                => false,
		'should_render_download_wide' => false,
		'show_secondary_download'     => false,
		'content_wrapper_class'       => 'product-extra__content',
	)
);

$product_content_context = wp_parse_args(
	$single_product_context['content'],
	array(
		'has_content' => false,
		'rendered'    => '',
	)
);

$product_faq_context = wp_parse_args(
	$single_product_context['faq'],
	array(
		'items'     => array(),
		'has_items' => false,
	)
);

$product_id = (int) $single_product_context['product_id'];
$cart_context = isset( $product_card_context['cart'] ) && is_array( $product_card_context['cart'] )
	? $product_card_context['cart']
	: array();
$has_extra_content = ! empty( $product_extra_context['attributes'] )
	|| ! empty( $product_extra_context['has_tags'] )
	|| ! empty( $product_extra_context['has_download'] )
	|| ! empty( $product_content_context['has_content'] )
	|| ! empty( $product_faq_context['has_items'] );
$has_about_content = ! empty( $product_extra_context['attributes'] )
	|| ! empty( $product_content_context['has_content'] )
	|| ! empty( $product_faq_context['has_items'] );
?>

<div class="product-page" id="product-<?php the_ID(); ?>" <?php wc_product_class( '', $product ); ?>>

	<!-- Breadcrumbs -->
	<nav class="breadcrumbs" aria-label="Хлебные крошки">
		<?php kama_breadcrumbs(" "); ?>
	</nav>

	<section class="product-top" aria-labelledby="product-title-<?php echo esc_attr( $product_id ); ?>">
		<div class="product-images" aria-label="Изображения товара">
			<?php
			/**
			 * Hook: woocommerce_before_single_product_summary.
			 *
			 * @hooked woocommerce_show_product_sale_flash - 10
			 * @hooked woocommerce_show_product_images - 20
			 */
			do_action( 'woocommerce_before_single_product_summary' );
			?>
		</div>

		<div class="product-details">
			<div class="summary entry-summary product-purchase">
				<div class="product-purchase__labels">
					<?php if ( $product_purchase_context['category'] ) : ?>
						<span class="product-purchase__category"><?php echo esc_html( $product_purchase_context['category'] ); ?></span>
					<?php endif; ?>
					<span class="product-purchase__type product-purchase__type--<?php echo $product_purchase_context['is_virtual'] ? 'digital' : 'physical'; ?>">
						<?php echo esc_html( $product_purchase_context['fulfilment_label'] ); ?>
					</span>
				</div>

				<h1 class="product-title" id="product-title-<?php echo esc_attr( $product_id ); ?>"><?php the_title(); ?></h1>

				<div class="product-purchase__meta">
				<?php
				get_template_part(
					'woocommerce/partials/product',
					'rating',
					array(
						'product_card' => $product_card_context,
					)
				);
				?>
					<span class="product-stock product-stock--<?php echo esc_attr( $product_purchase_context['stock_state'] ); ?>">
						<span class="product-stock__dot" aria-hidden="true"></span>
						<?php echo esc_html( $product_purchase_context['stock_label'] ); ?>
					</span>
					<?php if ( $product_purchase_context['sku'] ) : ?>
						<span class="product-purchase__sku">Артикул: <?php echo esc_html( $product_purchase_context['sku'] ); ?></span>
					<?php endif; ?>
				</div>

				<?php if ( $product_purchase_context['short_description'] ) : ?>
					<div class="product-purchase__lead"><?php echo wp_kses_post( $product_purchase_context['short_description'] ); ?></div>
				<?php endif; ?>

				<?php
				get_template_part(
					'woocommerce/partials/product',
					'variation-options',
					array(
						'product_card' => $product_card_context,
					)
				);
				?>
				<div class="product-purchase__price-block">
					<span class="product-purchase__price-label">Стоимость</span>
					<?php
				get_template_part(
					'woocommerce/partials/product',
					'price-line',
					array(
						'product_card' => $product_card_context,
					)
				);
					?>
				</div>

				<div class="product-fulfilment product-fulfilment--digital">
					<span class="product-fulfilment__icon" aria-hidden="true">
						✉
					</span>
					<span class="product-fulfilment__copy">
						<strong><?php echo esc_html( $product_purchase_context['fulfilment_label'] ); ?></strong>
						<span><?php echo esc_html( $product_purchase_context['fulfilment_detail'] ); ?></span>
					</span>
				</div>

				<?php
				get_template_part(
					'woocommerce/partials/product',
					'cart',
					array(
						'product_card' => $product_card_context,
					)
				);
				?>

				<ul class="product-assurances" aria-label="Преимущества покупки">
					<li><span aria-hidden="true">✓</span> Безопасное оформление</li>
					<li><span aria-hidden="true">✓</span> Поддержка по заказу</li>
					<li><span aria-hidden="true">✓</span> Данные доступны в кабинете</li>
				</ul>
			</div>
		</div>
	</section>

	<div class="product-benefits" aria-label="Условия покупки">
		<div class="product-benefit"><span class="product-benefit__icon" aria-hidden="true">01</span><span><strong>Получение на e-mail</strong><small>После подтверждения оплаты</small></span></div>
		<div class="product-benefit"><span class="product-benefit__icon" aria-hidden="true">02</span><span><strong>Понятный статус</strong><small>Заказ виден в личном кабинете</small></span></div>
		<div class="product-benefit"><span class="product-benefit__icon" aria-hidden="true">03</span><span><strong>Помощь специалиста</strong><small>Подскажем по покупке и активации</small></span></div>
	</div>

	<?php if ( $has_extra_content || ! empty( $product_purchase_context['steps'] ) ) : ?>
	<div class="product-extra" id="product-information">
		<?php if ( $has_about_content ) : ?>
		<div class="product-extra__layout">
			<?php if ( ! empty( $product_extra_context['attributes'] ) ) : ?>
			<aside class="product-extra__info" aria-label="Характеристики товара">
				<?php
				get_template_part(
					'woocommerce/partials/product-extra',
					'attrs',
					array( 'attributes' => $product_extra_context['attributes'] )
				);
				?>
			</aside>
			<?php endif; ?>

			<div class="product-extra__about">
			<?php
			get_template_part(
				'woocommerce/partials/product-extra',
				'about',
				array(
					'product_id' => $product_id,
					'content'    => $product_content_context,
					'faq'        => $product_faq_context,
					'extra'      => $product_extra_context,
				)
			);
			?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $product_purchase_context['steps'] ) ) : ?>
		<section class="product-steps" aria-labelledby="product-steps-title">
			<div class="product-section-heading">
				<span>ПОСЛЕ ПОКУПКИ</span>
				<h2 id="product-steps-title"><?php echo $product_purchase_context['is_virtual'] ? 'Как получить и активировать' : 'Как получить заказ'; ?></h2>
			</div>
			<ol class="product-steps__list">
				<?php foreach ( $product_purchase_context['steps'] as $index => $step ) : ?>
					<li class="product-step"><span class="product-step__number"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span><span><strong><?php echo esc_html( $step['title'] ); ?></strong><small><?php echo esc_html( $step['text'] ); ?></small></span></li>
				<?php endforeach; ?>
			</ol>
		</section>
		<?php endif; ?>

		<?php if ( $product_extra_context['has_tags'] || $product_extra_context['has_download'] ) : ?>
		<div class="product-extra__resources">
			<?php
			get_template_part(
				'woocommerce/partials/product-extra',
				'info',
				array(
					'tags'                        => $product_extra_context['tags'],
					'has_tags'                    => $product_extra_context['has_tags'],
					'download'                    => $product_extra_context['download'],
					'has_download'                => $product_extra_context['has_download'],
					'should_render_download_wide' => $product_extra_context['should_render_download_wide'],
				)
			);
			?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="product-bottom">
		<?php get_template_part( 'woocommerce/partials/product', 'reviews' ); ?>
		<?php get_template_part( 'woocommerce/partials/product', 'upsells' ); ?>
	</div>

	<div class="product-sticky-buy" data-product-sticky hidden>
		<div class="product-sticky-buy__copy">
			<span class="product-sticky-buy__label"><?php echo esc_html( $product_purchase_context['fulfilment_label'] ); ?></span>
			<strong class="product-sticky-buy__price" data-sticky-price></strong>
		</div>
		<button class="product-sticky-buy__button" type="button" data-product-sticky-action <?php echo empty( $cart_context['is_purchasable'] ) || empty( $cart_context['is_in_stock'] ) ? 'disabled' : ''; ?>>
			<?php echo ! empty( $cart_context['is_in_stock'] ) ? 'В корзину' : 'Нет в наличии'; ?>
		</button>
	</div>

</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
