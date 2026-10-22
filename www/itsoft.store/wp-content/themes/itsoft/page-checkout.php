<?php
/**
 * Checkout page with the shared purchase progress indicator.
 *
 * @package itsoft
 */

get_header();

$is_complete = function_exists( 'is_order_received_page' ) && is_order_received_page();
?>

<div class="grid-container">
	<?php if ( function_exists( 'kama_breadcrumbs' ) ) : ?>
		<nav class="breadcrumbs" aria-label="<?php esc_attr_e( 'Хлебные крошки', 'itsoft' ); ?>">
			<?php kama_breadcrumbs( ' ' ); ?>
		</nav>
	<?php endif; ?>
</div>

<?php while ( have_posts() ) : the_post(); ?>

	<main class="cart-page checkout-page <?php echo $is_complete ? 'checkout-page--complete' : 'checkout-page--form'; ?>">
		<header class="cart-page__hero checkout-page__hero">
			<div class="grid-container cart-page__hero-grid">
				<div class="cart-page__heading">
					<h1 class="cart-page__title">
						<?php echo esc_html( $is_complete ? __( 'Заказ оформлен', 'itsoft' ) : get_the_title() ); ?>
					</h1>
					<p class="cart-page__lead">
						<?php
						echo esc_html(
							$is_complete
								? __( 'Проверьте сведения о заказе и сохраните номер для дальнейших обращений.', 'itsoft' )
								: __( 'Укажите контактные данные и выберите удобный способ оплаты.', 'itsoft' )
						);
						?>
					</p>
				</div>

				<ol class="cart-steps" aria-label="<?php esc_attr_e( 'Этапы оформления заказа', 'itsoft' ); ?>">
					<li class="cart-steps__item cart-steps__item--complete">
						<span class="cart-steps__number">1</span>
						<span class="cart-steps__label"><?php esc_html_e( 'Корзина', 'itsoft' ); ?></span>
					</li>
					<li class="cart-steps__item <?php echo $is_complete ? 'cart-steps__item--complete' : 'cart-steps__item--active'; ?>"<?php echo $is_complete ? '' : ' aria-current="step"'; ?>>
						<span class="cart-steps__number">2</span>
						<span class="cart-steps__label"><?php esc_html_e( 'Оформление', 'itsoft' ); ?></span>
					</li>
					<li class="cart-steps__item <?php echo $is_complete ? 'cart-steps__item--active' : ''; ?>"<?php echo $is_complete ? ' aria-current="step"' : ''; ?>>
						<span class="cart-steps__number">3</span>
						<span class="cart-steps__label"><?php esc_html_e( 'Получение заказа', 'itsoft' ); ?></span>
					</li>
				</ol>
			</div>
		</header>

		<div class="grid-container">
			<div class="checkout-page__content article">
				<?php the_content(); ?>

				<?php if ( $is_complete ) : ?>
					<section class="checkout-next" aria-labelledby="checkout-next-title">
						<div class="checkout-next__icon" aria-hidden="true"></div>
						<div class="checkout-next__content">
							<h2 id="checkout-next-title"><?php esc_html_e( 'Заказ уже в обработке', 'itsoft' ); ?></h2>
							<p><?php esc_html_e( 'Сведения о заказе отправлены на указанный e-mail. Электронный ключ станет доступен после подтверждения оплаты. Сохраните номер заказа выше — он пригодится при обращении в поддержку.', 'itsoft' ); ?></p>
						</div>
						<div class="checkout-next__actions">
							<?php if ( is_user_logged_in() ) : ?>
								<a class="checkout-next__button checkout-next__button--primary" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
									<?php esc_html_e( 'Мои заказы', 'itsoft' ); ?>
								</a>
							<?php endif; ?>
							<a class="checkout-next__button <?php echo is_user_logged_in() ? 'checkout-next__button--secondary' : 'checkout-next__button--primary'; ?>" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
								<?php esc_html_e( 'Вернуться в каталог', 'itsoft' ); ?>
							</a>
						</div>
					</section>
				<?php endif; ?>
			</div>
		</div>
	</main>

<?php endwhile; ?>

<?php
get_footer();
