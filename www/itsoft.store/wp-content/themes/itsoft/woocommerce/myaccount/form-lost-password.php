<?php
/**
 * Lost password form.
 *
 * @package itsoft
 * @version 9.2.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_lost_password_form' );
?>

<div class="account-recovery">
	<section class="account-recovery__card">
		<h2>Получить ссылку для входа</h2>
		<p class="account-recovery__intro"><?php echo apply_filters( 'woocommerce_lost_password_message', 'Введите e-mail или имя пользователя. Ссылка для установки нового пароля придёт в письме.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

		<form method="post" class="woocommerce-ResetPassword lost_reset_password account-form">
			<p class="woocommerce-form-row form-row form-row-wide">
				<label for="user_login">E-mail или имя пользователя <span class="required" aria-hidden="true">*</span><span class="screen-reader-text">Обязательное поле</span></label>
				<input class="woocommerce-Input input-text" type="text" name="user_login" id="user_login" autocomplete="username" placeholder="name@example.ru" required aria-required="true">
			</p>

			<?php do_action( 'woocommerce_lostpassword_form' ); ?>

			<input type="hidden" name="wc_reset_password" value="true">
			<?php wp_nonce_field( 'lost_password', 'woocommerce-lost-password-nonce' ); ?>
			<button type="submit" class="woocommerce-Button button account-form__submit" value="Отправить ссылку">Отправить ссылку</button>
			<a class="account-recovery__back" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">Вернуться ко входу</a>
		</form>
	</section>

	<aside class="account-recovery__tip">
		<strong>Письмо не пришло?</strong>
		<p>Проверьте папку «Спам» и убедитесь, что адрес введён без опечаток. Повторная отправка письма может занять несколько минут.</p>
	</aside>
</div>

<?php do_action( 'woocommerce_after_lost_password_form' ); ?>
