<?php
/**
 * Customer profile form.
 *
 * @package itsoft
 * @version 10.5.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_edit_account_form' );
?>

<div class="account-section-head">
	<div>
		<h2>Личные данные</h2>
		<p>Эти данные используются для оформления заказов и связи с вами.</p>
	</div>
</div>

<form class="woocommerce-EditAccountForm edit-account account-profile-form" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?>>
	<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

	<section class="account-form-card">
		<header class="account-form-card__header">
			<span class="account-form-card__icon" aria-hidden="true">○</span>
			<div><h3>Контактная информация</h3><p>Имя, e-mail и телефон покупателя</p></div>
		</header>

		<div class="account-form-grid">
			<p class="woocommerce-form-row form-row form-row-first">
				<label for="account_first_name">Имя <span class="required" aria-hidden="true">*</span></label>
				<input type="text" class="woocommerce-Input input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" required aria-required="true">
			</p>
			<p class="woocommerce-form-row form-row form-row-last">
				<label for="account_last_name">Фамилия <span class="required" aria-hidden="true">*</span></label>
				<input type="text" class="woocommerce-Input input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" required aria-required="true">
			</p>
			<p class="woocommerce-form-row form-row form-row-wide">
				<label for="account_display_name">Отображаемое имя <span class="required" aria-hidden="true">*</span></label>
				<input type="text" class="woocommerce-Input input-text" name="account_display_name" id="account_display_name" aria-describedby="account_display_name_description" value="<?php echo esc_attr( $user->display_name ); ?>" required aria-required="true">
				<span class="account-field-hint" id="account_display_name_description">Так мы будем обращаться к вам в личном кабинете.</span>
			</p>
			<p class="woocommerce-form-row form-row form-row-wide">
				<label for="account_email">E-mail <span class="required" aria-hidden="true">*</span></label>
				<input type="email" class="woocommerce-Input input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" required aria-required="true">
			</p>
			<p class="woocommerce-form-row form-row form-row-wide">
				<label for="billing_phone">Телефон</label>
				<input type="tel" class="woocommerce-Input input-text" name="billing_phone" id="billing_phone" autocomplete="tel" value="<?php echo esc_attr( get_user_meta( $user->ID, 'billing_phone', true ) ); ?>" placeholder="+7 999 000-00-00">
			</p>
		</div>

		<?php do_action( 'woocommerce_edit_account_form_fields' ); ?>
	</section>

	<fieldset class="account-form-card account-form-card--security">
		<legend class="screen-reader-text">Смена пароля</legend>
		<header class="account-form-card__header">
			<span class="account-form-card__icon" aria-hidden="true">◇</span>
			<div><h3>Безопасность</h3><p>Оставьте поля пустыми, если не хотите менять пароль</p></div>
		</header>

		<div class="account-form-grid">
			<p class="woocommerce-form-row form-row form-row-wide account-form__password">
				<label for="password_current">Текущий пароль</label>
				<span class="account-form__password-control"><input type="password" class="woocommerce-Input input-text" name="password_current" id="password_current" autocomplete="current-password"><?php get_template_part( 'template-parts/account/password-toggle', null, array( 'target' => 'password_current' ) ); ?></span>
			</p>
			<p class="woocommerce-form-row form-row form-row-first account-form__password">
				<label for="password_1">Новый пароль</label>
				<span class="account-form__password-control"><input type="password" class="woocommerce-Input input-text" name="password_1" id="password_1" autocomplete="new-password"><?php get_template_part( 'template-parts/account/password-toggle', null, array( 'target' => 'password_1' ) ); ?></span>
			</p>
			<p class="woocommerce-form-row form-row form-row-last account-form__password">
				<label for="password_2">Повторите новый пароль</label>
				<span class="account-form__password-control"><input type="password" class="woocommerce-Input input-text" name="password_2" id="password_2" autocomplete="new-password"><?php get_template_part( 'template-parts/account/password-toggle', null, array( 'target' => 'password_2' ) ); ?></span>
			</p>
		</div>
	</fieldset>

	<?php do_action( 'woocommerce_edit_account_form' ); ?>

	<div class="account-profile-form__footer">
		<span>Изменения применятся сразу после сохранения.</span>
		<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
		<button type="submit" class="woocommerce-Button button" name="save_account_details" value="Сохранить изменения">Сохранить изменения</button>
		<input type="hidden" name="action" value="save_account_details">
	</div>

	<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
