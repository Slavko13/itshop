<?php
/**
 * Native, responsive home hero with an optional Smart Slider fallback.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$front_page_id = (int) get_option( 'page_on_front' );
$shop_url      = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$acf           = function_exists( 'get_field' );

$mode             = $acf ? (string) get_field( 'home_hero_mode', $front_page_id ) : '';
$slider_shortcode = $acf ? trim( (string) get_field( 'home_hero_shortcode', $front_page_id ) ) : '';
$use_slider       = '' !== $slider_shortcode && 'native' !== $mode;

if ( $use_slider ) :
	?>
	<div class="hero grid-container">
		<div class="hero__container hero__container--smartslider">
			<?php echo do_shortcode( $slider_shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
	<?php
	return;
endif;

$title = $acf ? trim( (string) get_field( 'home_hero_title', $front_page_id ) ) : '';
$text  = $acf ? trim( (string) get_field( 'home_hero_text', $front_page_id ) ) : '';

$title = $title ?: __( 'Лицензионное ПО без сложного оформления', 'itsoft' );
$text  = $text ?: __( 'Подберите программу, оплатите заказ и получите электронный ключ на e-mail. Для физических товаров доступна обычная доставка.', 'itsoft' );

$primary_label   = $acf ? trim( (string) get_field( 'home_hero_primary_label', $front_page_id ) ) : '';
$primary_url     = $acf ? trim( (string) get_field( 'home_hero_primary_url', $front_page_id ) ) : '';
$secondary_label = $acf ? trim( (string) get_field( 'home_hero_secondary_label', $front_page_id ) ) : '';
$secondary_url   = $acf ? trim( (string) get_field( 'home_hero_secondary_url', $front_page_id ) ) : '';
$desktop_image   = $acf ? (int) get_field( 'home_hero_desktop_image', $front_page_id ) : 0;
$mobile_image    = $acf ? (int) get_field( 'home_hero_mobile_image', $front_page_id ) : 0;

$primary_label   = $primary_label ?: __( 'Перейти в каталог', 'itsoft' );
$primary_url     = $primary_url ?: $shop_url;
$secondary_label = $secondary_label ?: __( 'Популярные категории', 'itsoft' );
$secondary_url   = $secondary_url ?: '#home-categories';

$desktop_image_url = $desktop_image ? wp_get_attachment_image_url( $desktop_image, 'large' ) : '';
$mobile_image_url  = $mobile_image ? wp_get_attachment_image_url( $mobile_image, 'large' ) : '';
?>

<section class="home-hero" aria-labelledby="home-hero-title">
	<div class="grid-container home-hero__grid">
		<div class="home-hero__content">
			<h1 class="home-hero__title" id="home-hero-title"><?php echo esc_html( $title ); ?></h1>
			<p class="home-hero__text"><?php echo esc_html( $text ); ?></p>
			<div class="home-hero__actions">
				<a class="home-hero__button home-hero__button--primary" href="<?php echo esc_url( $primary_url ); ?>">
					<?php echo esc_html( $primary_label ); ?>
					<span aria-hidden="true">→</span>
				</a>
				<a class="home-hero__button home-hero__button--secondary" href="<?php echo esc_url( $secondary_url ); ?>"><?php echo esc_html( $secondary_label ); ?></a>
			</div>
			<p class="home-hero__note"><span aria-hidden="true">✓</span> <?php esc_html_e( 'Данные заказа и статус покупки всегда доступны в личном кабинете', 'itsoft' ); ?></p>
		</div>

		<div class="home-hero__visual" aria-hidden="true">
			<?php if ( $desktop_image_url || $mobile_image_url ) : ?>
				<picture class="home-hero__picture">
					<?php if ( $mobile_image_url ) : ?><source media="(max-width: 575px)" srcset="<?php echo esc_url( $mobile_image_url ); ?>"><?php endif; ?>
					<img src="<?php echo esc_url( $desktop_image_url ?: $mobile_image_url ); ?>" alt="" loading="eager" decoding="async">
				</picture>
			<?php else : ?>
				<div class="home-hero__orb home-hero__orb--one"></div>
				<div class="home-hero__orb home-hero__orb--two"></div>
				<div class="home-license-card">
					<div class="home-license-card__top">
						<span class="home-license-card__mark">IT</span>
						<span class="home-license-card__status"><i></i><?php esc_html_e( 'Готово к выдаче', 'itsoft' ); ?></span>
					</div>
					<span class="home-license-card__caption"><?php esc_html_e( 'Электронная лицензия', 'itsoft' ); ?></span>
					<strong><?php esc_html_e( 'Программное обеспечение', 'itsoft' ); ?></strong>
					<div class="home-license-card__key">•••••-•••••-•••••-8K2P4</div>
					<div class="home-license-card__footer"><span><?php esc_html_e( 'Доставка', 'itsoft' ); ?></span><b><?php esc_html_e( 'На e-mail', 'itsoft' ); ?></b></div>
				</div>
				<span class="home-hero__floating home-hero__floating--mail">✉ <b><?php esc_html_e( 'После оплаты', 'itsoft' ); ?></b></span>
				<span class="home-hero__floating home-hero__floating--support">✓ <b><?php esc_html_e( 'Поддержка заказа', 'itsoft' ); ?></b></span>
			<?php endif; ?>
		</div>
	</div>

	<div class="grid-container home-benefits">
		<div class="home-benefits__item"><span>01</span><div><strong><?php esc_html_e( 'Быстрое оформление', 'itsoft' ); ?></strong><small><?php esc_html_e( 'Только необходимые данные', 'itsoft' ); ?></small></div></div>
		<div class="home-benefits__item"><span>02</span><div><strong><?php esc_html_e( 'Электронная доставка', 'itsoft' ); ?></strong><small><?php esc_html_e( 'Ключ и заказ приходят на e-mail', 'itsoft' ); ?></small></div></div>
		<div class="home-benefits__item"><span>03</span><div><strong><?php esc_html_e( 'История покупок', 'itsoft' ); ?></strong><small><?php esc_html_e( 'Заказы и лицензии в кабинете', 'itsoft' ); ?></small></div></div>
	</div>
</section>

