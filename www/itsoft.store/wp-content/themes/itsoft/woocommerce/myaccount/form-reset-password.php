<?php
/**
 * Password reset form.
 *
 * @package itsoft
 * @version 9.2.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_reset_password_form' );
?>

<div class="account-recovery">
	<section class="account-recovery__card">
		<div class="account-recovery__icon" aria-hidden="true">✓</div>
		<h2>Придумайте новый пароль</h2>
		<p class="account-recovery__intro"><?php echo apply_filters( 'woocommerce_reset_password_message', 'Используйте новый пароль, которого нет на других сайтах.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>

		<form method="post" class="woocommerce-ResetPassword lost_reset_password account-form">
			<p class="woocommerce-form-row form-row form-row-wide account-form__password">
				<label for="password_1">Новый пароль <span class="required" aria-hidden="true">*</span></label>
				<span class="account-form__password-control">
					<input type="password" class="woocommerce-Input input-text" name="password_1" id="password_1" autocomplete="new-password" required aria-required="true">
					<?php get_template_part( 'template-parts/account/password-toggle', null, array( 'target' => 'password_1' ) ); ?>
				</span>
			</p>
			<p class="woocommerce-form-row form-row form-row-wide account-form__password">
				<label for="password_2">Повторите пароль <span class="required" aria-hidden="true">*</span></label>
				<span class="account-form__password-control">
					<input type="password" class="woocommerce-Input input-text" name="password_2" id="password_2" autocomplete="new-password" required aria-required="true">
					<?php get_template_part( 'template-parts/account/password-toggle', null, array( 'target' => 'password_2' ) ); ?>
				</span>
			</p>

			<input type="hidden" name="reset_key" value="<?php echo esc_attr( $args['key'] ); ?>">
			<input type="hidden" name="reset_login" value="<?php echo esc_attr( $args['login'] ); ?>">
			<?php do_action( 'woocommerce_resetpassword_form' ); ?>
			<input type="hidden" name="wc_reset_password" value="true">
			<?php wp_nonce_field( 'reset_password', 'woocommerce-reset-password-nonce' ); ?>
			<button type="submit" class="woocommerce-Button button account-form__submit" value="Сохранить пароль">Сохранить пароль</button>
		</form>
	</section>
</div>

<?php do_action( 'woocommerce_after_reset_password_form' ); ?>
