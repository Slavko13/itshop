<?php
/**
 * Template Name: Корзина
 *
 * @package itsoft
 */

get_header();

$itsoft_cart_is_empty = function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty();
?>

<div class="grid-container">
	<?php if ( function_exists( 'kama_breadcrumbs' ) ) : ?>
		<nav class="breadcrumbs" aria-label="Хлебные крошки">
			<?php kama_breadcrumbs( " " ); ?>
		</nav>
	<?php endif; ?>
</div>

<?php while ( have_posts() ) : the_post(); ?>

	<main
		class="cart-page<?php echo $itsoft_cart_is_empty ? ' cart-page--empty' : ''; ?>"
		data-empty-lead="<?php echo esc_attr__( 'Выберите нужный продукт — мы сохраним его здесь и проведём вас до получения заказа.', 'itsoft' ); ?>"
		data-filled-lead="<?php echo esc_attr__( 'Проверьте состав заказа — дальше останется указать контакты и выбрать способ оплаты.', 'itsoft' ); ?>"
	>
		<header class="cart-page__hero">
			<div class="grid-container cart-page__hero-grid">
				<div class="cart-page__heading">
					<h1 class="cart-page__title"><?php the_title(); ?></h1>
					<p class="cart-page__lead">
						<?php
						echo esc_html(
							$itsoft_cart_is_empty
								? __( 'Выберите нужный продукт — мы сохраним его здесь и проведём вас до получения заказа.', 'itsoft' )
								: __( 'Проверьте состав заказа — дальше останется указать контакты и выбрать способ оплаты.', 'itsoft' )
						);
						?>
					</p>
				</div>

				<?php if ( ! $itsoft_cart_is_empty ) : ?>
				<ol class="cart-steps" aria-label="<?php esc_attr_e( 'Этапы оформления заказа', 'itsoft' ); ?>">
					<li class="cart-steps__item cart-steps__item--active" aria-current="step">
						<span class="cart-steps__number">1</span>
						<span class="cart-steps__label"><?php esc_html_e( 'Корзина', 'itsoft' ); ?></span>
					</li>
					<li class="cart-steps__item">
						<span class="cart-steps__number">2</span>
						<span class="cart-steps__label"><?php esc_html_e( 'Оформление', 'itsoft' ); ?></span>
					</li>
					<li class="cart-steps__item">
						<span class="cart-steps__number">3</span>
						<span class="cart-steps__label"><?php esc_html_e( 'Получение заказа', 'itsoft' ); ?></span>
					</li>
				</ol>
				<?php endif; ?>
			</div>
		</header>

		<div class="grid-container">
			<div class="cart-page__content">
				<div class="article cart-page__woocommerce">
					<?php echo do_shortcode( '[woocommerce_cart]' ); ?>
				</div>

				<?php if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) : ?>
					<aside class="cart-benefits" aria-label="<?php esc_attr_e( 'Информация о заказе', 'itsoft' ); ?>">
						<div class="cart-benefits__item">
							<span class="cart-benefits__icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" focusable="false"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z"/><path d="m4.5 7.8 7.5 4.3 7.5-4.3M12 12.1V21"/></svg>
							</span>
							<span><strong><?php esc_html_e( 'Электронная доставка', 'itsoft' ); ?></strong><small><?php esc_html_e( 'Без курьера и ожидания посылки', 'itsoft' ); ?></small></span>
						</div>
						<div class="cart-benefits__item">
							<span class="cart-benefits__icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 8 8 5 8-5"/></svg>
							</span>
							<span><strong><?php esc_html_e( 'Ключ придёт на e-mail', 'itsoft' ); ?></strong><small><?php esc_html_e( 'После подтверждения оплаты заказа', 'itsoft' ); ?></small></span>
						</div>
						<div class="cart-benefits__item">
							<span class="cart-benefits__icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" focusable="false"><path d="M12 3 5 6v5c0 4.6 2.9 8.4 7 10 4.1-1.6 7-5.4 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-5"/></svg>
							</span>
							<span><strong><?php esc_html_e( 'Безопасное оформление', 'itsoft' ); ?></strong><small><?php esc_html_e( 'Состав заказа виден до оплаты', 'itsoft' ); ?></small></span>
						</div>
					</aside>
				<?php endif; ?>
			</div>
		</div>

		<template id="itsoft-cart-empty-template">
			<?php get_template_part( 'template-parts/cart/empty-state' ); ?>
		</template>
	</main>

<?php endwhile; ?>

<?php
get_footer();
