<?php
/**
 * Native, responsive home hero.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$front_page_id = (int) get_option( 'page_on_front' );
$shop_url      = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$acf           = function_exists( 'get_field' );

$title = $acf ? trim( (string) get_field( 'home_hero_title', $front_page_id ) ) : '';
$text  = $acf ? trim( (string) get_field( 'home_hero_text', $front_page_id ) ) : '';

$title = $title ?: __( 'Лицензионное ПО без переплат', 'itsoft' );
$text  = $text ?: __( 'Подберите программу, оплатите заказ и получите электронный ключ на e-mail.', 'itsoft' );
$text  = preg_replace( '/\s*Для физических товаров[^.]*\.?/iu', '', $text );

$primary_label = $acf ? trim( (string) get_field( 'home_hero_primary_label', $front_page_id ) ) : '';
$primary_url   = $acf ? trim( (string) get_field( 'home_hero_primary_url', $front_page_id ) ) : '';
$desktop_image = $acf ? (int) get_field( 'home_hero_desktop_image', $front_page_id ) : 0;
$mobile_image  = $acf ? (int) get_field( 'home_hero_mobile_image', $front_page_id ) : 0;

$primary_label = $primary_label ?: __( 'Выбрать лицензию', 'itsoft' );
$primary_url   = $primary_url ?: $shop_url;

$desktop_image_url = $desktop_image
	? wp_get_attachment_image_url( $desktop_image, 'large' )
	: get_template_directory_uri() . '/assets/images/home-hero-license-v2.png';
$mobile_image_url  = $mobile_image ? wp_get_attachment_image_url( $mobile_image, 'large' ) : '';
?>

<section class="home-hero" aria-labelledby="home-hero-title">
	<div class="grid-container home-hero__grid">
		<div class="home-hero__content">
			<span class="home-hero__eyebrow"><?php esc_html_e( 'Электронные лицензии', 'itsoft' ); ?></span>
			<h1 class="home-hero__title" id="home-hero-title"><?php echo esc_html( $title ); ?></h1>
			<p class="home-hero__text"><?php echo esc_html( $text ); ?></p>
			<div class="home-hero__actions">
				<a class="home-hero__button home-hero__button--primary" href="<?php echo esc_url( $primary_url ); ?>">
					<span><?php echo esc_html( $primary_label ); ?></span>
					<i aria-hidden="true">→</i>
				</a>
			</div>
			<p class="home-hero__note"><span aria-hidden="true">✓</span> <?php esc_html_e( 'Данные доступны в личном кабинете', 'itsoft' ); ?></p>
		</div>

		<div class="home-hero__visual" aria-hidden="true">
			<picture class="home-hero__picture">
				<?php if ( $mobile_image_url ) : ?><source media="(max-width: 575px)" srcset="<?php echo esc_url( $mobile_image_url ); ?>"><?php endif; ?>
				<img src="<?php echo esc_url( $desktop_image_url ); ?>" alt="" width="1536" height="1024" loading="eager" decoding="async" fetchpriority="high">
			</picture>
		</div>
	</div>

	<div class="grid-container home-benefits">
		<div class="home-benefits__item"><span>01</span><div><strong><?php esc_html_e( 'Быстрое оформление', 'itsoft' ); ?></strong><small><?php esc_html_e( 'Только необходимые данные', 'itsoft' ); ?></small></div></div>
		<div class="home-benefits__item"><span>02</span><div><strong><?php esc_html_e( 'Получение ключа', 'itsoft' ); ?></strong><small><?php esc_html_e( 'Ключ и заказ приходят на e-mail', 'itsoft' ); ?></small></div></div>
		<div class="home-benefits__item"><span>03</span><div><strong><?php esc_html_e( 'История покупок', 'itsoft' ); ?></strong><small><?php esc_html_e( 'Заказы и лицензии в кабинете', 'itsoft' ); ?></small></div></div>
	</div>
</section>

