<?php
/**
 * Customer account navigation.
 *
 * @package itsoft
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$items = wc_get_account_menu_items();

do_action( 'woocommerce_before_account_navigation' );
?>

<div class="account-nav-mobile">
	<label for="account-nav-select">Раздел кабинета</label>
	<select id="account-nav-select" data-account-nav-select>
		<?php foreach ( $items as $endpoint => $label ) : ?>
			<option
				value="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"
				<?php selected( wc_is_current_account_menu_item( $endpoint ) ); ?>
				<?php echo 'customer-logout' === $endpoint ? 'data-account-logout-option' : ''; ?>
			><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
</div>

<nav class="woocommerce-MyAccount-navigation account-navigation" aria-label="Разделы личного кабинета">
	<ul>
		<?php foreach ( $items as $endpoint => $label ) : ?>
			<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>">
				<a
					href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"
					<?php echo wc_is_current_account_menu_item( $endpoint ) ? 'aria-current="page"' : ''; ?>
					<?php echo 'customer-logout' === $endpoint ? 'data-account-logout' : ''; ?>
				>
					<span><?php echo esc_html( $label ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<div class="account-logout-modal" data-account-logout-dialog hidden>
	<div class="account-logout-modal__backdrop" data-account-logout-cancel></div>
	<section
		class="account-logout-modal__dialog"
		role="dialog"
		aria-modal="true"
		aria-labelledby="account-logout-title"
		aria-describedby="account-logout-description"
		tabindex="-1"
	>
		<button class="account-logout-modal__close" type="button" data-account-logout-cancel aria-label="Закрыть">×</button>
		<span class="account-logout-modal__kicker">Личный кабинет</span>
		<h2 id="account-logout-title">Выйти из аккаунта?</h2>
		<p id="account-logout-description">Для просмотра заказов и лицензионных ключей потребуется снова войти.</p>
		<div class="account-logout-modal__actions">
			<button class="account-logout-modal__stay" type="button" data-account-logout-cancel>Остаться</button>
			<a class="account-logout-modal__confirm" href="#" data-account-logout-confirm>Выйти</a>
		</div>
	</section>
</div>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
