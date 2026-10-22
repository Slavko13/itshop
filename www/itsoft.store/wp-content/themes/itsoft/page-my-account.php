<?php
/**
 * Customer account page.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

get_header();

$is_logged_in = is_user_logged_in();
$is_recovery  = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'lost-password' );
$page_title   = $is_logged_in ? 'Личный кабинет' : 'Вход и регистрация';
$page_lead    = $is_logged_in
	? 'Заказы, лицензионные ключи и личные данные собраны в одном месте.'
	: 'Войдите, чтобы видеть свои заказы и ключи, или создайте новый аккаунт.';

if ( $is_recovery ) {
	$page_title = 'Восстановление доступа';
	$page_lead  = 'Укажите e-mail аккаунта — мы отправим безопасную ссылку для установки нового пароля.';
}
?>

<main class="account-page<?php echo $is_logged_in ? ' account-page--customer' : ' account-page--guest'; ?>">
	<div class="grid-container">
		<nav class="breadcrumbs account-page__breadcrumbs" aria-label="Хлебные крошки">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
			<span aria-hidden="true">/</span>
			<span><?php echo esc_html( $page_title ); ?></span>
		</nav>

		<header class="account-page__header">
			<h1 class="account-page__title"><?php echo esc_html( $page_title ); ?></h1>
			<p class="account-page__lead"><?php echo esc_html( $page_lead ); ?></p>
		</header>

		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<div class="account-page__content">
				<?php the_content(); ?>
			</div>
		<?php endwhile; ?>
	</div>
</main>

<?php get_footer(); ?>
