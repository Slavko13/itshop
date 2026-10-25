<?php
/**
 * Reusable branded empty-cart content.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$shop_url = function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0
	? wc_get_page_permalink( 'shop' )
	: home_url( '/' );
?>

<section class="cart-empty cart-empty-state" aria-labelledby="cart-empty-title">
	<div class="cart-empty-state__visual" aria-hidden="true">
		<span class="cart-empty-state__glow cart-empty-state__glow--one"></span>
		<span class="cart-empty-state__glow cart-empty-state__glow--two"></span>
		<svg width="58" height="58" viewBox="0 0 96 96" focusable="false">
			<path d="M24 31h7l6 34h35l8-25H34"/>
			<path d="M42 75a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm34 0a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/>
			<path d="M49 24h17M57.5 15.5v17" class="cart-empty-state__spark"/>
		</svg>
	</div>

	<h2 id="cart-empty-title"><?php esc_html_e( 'Добавим что-нибудь полезное?', 'itsoft' ); ?></h2>
	<p><?php esc_html_e( 'В каталоге собраны лицензии, программы и другие продукты. Выберите нужное — оформление займёт всего несколько минут.', 'itsoft' ); ?></p>

	<a class="cart-empty-state__button" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', $shop_url ) ); ?>">
		<span><?php esc_html_e( 'Перейти в каталог', 'itsoft' ); ?></span>
		<svg width="18" height="18" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="m7 4 6 6-6 6"/></svg>
	</a>

	<ul class="cart-empty-state__benefits" aria-label="<?php esc_attr_e( 'Преимущества магазина', 'itsoft' ); ?>">
		<li><span class="cart-empty-state__benefit-icon" aria-hidden="true">✓</span><span class="cart-empty-state__benefit-text"><?php esc_html_e( 'Быстрое оформление', 'itsoft' ); ?></span></li>
		<li><span class="cart-empty-state__benefit-icon" aria-hidden="true">✓</span><span class="cart-empty-state__benefit-text"><?php esc_html_e( 'Безопасная оплата', 'itsoft' ); ?></span></li>
		<li><span class="cart-empty-state__benefit-icon" aria-hidden="true">✓</span><span class="cart-empty-state__benefit-text"><?php esc_html_e( 'Заказ и ключи на e-mail', 'itsoft' ); ?></span></li>
	</ul>
</section>
