<?php
/**
 * Customer login and registration.
 *
 * @package itsoft
 * @version 9.9.0
 */

defined( 'ABSPATH' ) || exit;

$registration_enabled = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
$register_active      = isset( $_POST['register'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

do_action( 'woocommerce_before_customer_login_form' );
?>

<div class="account-auth" data-account-auth data-initial-tab="<?php echo $register_active ? 'register' : 'login'; ?>">
	<section class="account-auth__card" aria-labelledby="account-auth-title">
		<div class="account-auth__heading">
			<h2 id="account-auth-title">Управляйте покупками без лишних писем</h2>
			<p>Все заказы и полученные лицензии будут доступны после входа.</p>
		</div>

		<?php if ( $registration_enabled ) : ?>
			<div class="account-auth__tabs" role="tablist" aria-label="Вход или регистрация">
				<button class="account-auth__tab<?php echo $register_active ? '' : ' is-active'; ?>" type="button" role="tab" id="account-tab-login" aria-controls="account-panel-login" aria-selected="<?php echo $register_active ? 'false' : 'true'; ?>" data-auth-tab="login">Вход</button>
				<button class="account-auth__tab<?php echo $register_active ? ' is-active' : ''; ?>" type="button" role="tab" id="account-tab-register" aria-controls="account-panel-register" aria-selected="<?php echo $register_active ? 'true' : 'false'; ?>" data-auth-tab="register">Регистрация</button>
			</div>
		<?php endif; ?>

		<div class="account-auth__panels">
			<div class="account-auth__panel<?php echo $register_active ? '' : ' is-active'; ?>" id="account-panel-login" role="tabpanel" aria-labelledby="account-tab-login" data-auth-panel="login">
				<form class="woocommerce-form woocommerce-form-login login account-form" method="post" novalidate>
					<?php do_action( 'woocommerce_login_form_start' ); ?>

					<p class="woocommerce-form-row form-row form-row-wide">
						<label for="username">E-mail или имя пользователя <span class="required" aria-hidden="true">*</span><span class="screen-reader-text">Обязательное поле</span></label>
						<input type="text" class="woocommerce-Input input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" placeholder="name@example.ru" required aria-required="true">
					</p>
					<p class="woocommerce-form-row form-row form-row-wide account-form__password">
						<label for="password">Пароль <span class="required" aria-hidden="true">*</span><span class="screen-reader-text">Обязательное поле</span></label>
						<span class="account-form__password-control">
							<input class="woocommerce-Input input-text" type="password" name="password" id="password" autocomplete="current-password" placeholder="Введите пароль" required aria-required="true">
							<?php get_template_part( 'template-parts/account/password-toggle', null, array( 'target' => 'password' ) ); ?>
						</span>
					</p>

					<?php do_action( 'woocommerce_login_form' ); ?>

					<div class="account-form__options woocommerce-LostPassword">
						<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme account-check">
							<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever">
							<span>Запомнить меня</span>
						</label>
						<a href="<?php echo esc_url( wc_lostpassword_url() ); ?>">Забыли пароль?</a>
					</div>

					<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
					<button type="submit" class="woocommerce-button button account-form__submit" name="login" value="Войти">Войти в кабинет</button>

					<?php do_action( 'woocommerce_login_form_end' ); ?>
				</form>
			</div>

			<?php if ( $registration_enabled ) : ?>
				<div class="account-auth__panel<?php echo $register_active ? ' is-active' : ''; ?>" id="account-panel-register" role="tabpanel" aria-labelledby="account-tab-register" data-auth-panel="register">
					<form method="post" class="woocommerce-form woocommerce-form-register register account-form" <?php do_action( 'woocommerce_register_form_tag' ); ?>>
						<?php do_action( 'woocommerce_register_form_start' ); ?>

						<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
							<p class="woocommerce-form-row form-row form-row-wide">
								<label for="reg_username">Имя пользователя <span class="required" aria-hidden="true">*</span><span class="screen-reader-text">Обязательное поле</span></label>
								<input type="text" class="woocommerce-Input input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ! empty( $_POST['username'] ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true">
							</p>
						<?php endif; ?>

						<p class="woocommerce-form-row form-row form-row-wide">
							<label for="reg_email">E-mail <span class="required" aria-hidden="true">*</span><span class="screen-reader-text">Обязательное поле</span></label>
							<input type="email" class="woocommerce-Input input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ! empty( $_POST['email'] ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" placeholder="name@example.ru" required aria-required="true">
						</p>

						<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
							<p class="woocommerce-form-row form-row form-row-wide account-form__password">
								<label for="reg_password">Пароль <span class="required" aria-hidden="true">*</span><span class="screen-reader-text">Обязательное поле</span></label>
								<span class="account-form__password-control">
									<input type="password" class="woocommerce-Input input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true">
									<?php get_template_part( 'template-parts/account/password-toggle', null, array( 'target' => 'reg_password' ) ); ?>
								</span>
							</p>
						<?php else : ?>
							<div class="account-form__notice">
								<span class="account-form__notice-icon" aria-hidden="true">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<rect x="3.5" y="5.5" width="17" height="13" rx="2.5" stroke="currentColor" stroke-width="1.7" />
										<path d="M5 7L12 12.5L19 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
									</svg>
								</span>
								<p><strong>Пароль задаётся по ссылке</strong><br>После регистрации мы отправим письмо на указанный адрес.</p>
							</div>
						<?php endif; ?>

						<?php do_action( 'woocommerce_register_form' ); ?>

						<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
						<button type="submit" class="woocommerce-Button woocommerce-button button account-form__submit" name="register" value="Регистрация">Создать аккаунт</button>
						<?php do_action( 'woocommerce_register_form_end' ); ?>
					</form>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<aside class="account-auth__aside" aria-label="Преимущества личного кабинета">
		<div class="account-auth__aside-content">
			<h2>Всё важное после покупки — под рукой</h2>
			<ul class="account-auth__benefits">
				<li><span class="account-auth__benefit-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.5 8.2L6.5 11L12.5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg></span><div><strong>История заказов</strong><small>Статусы, суммы и состав покупок</small></div></li>
				<li><span class="account-auth__benefit-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.5 8.2L6.5 11L12.5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg></span><div><strong>Лицензионные ключи</strong><small>Повторный доступ без поиска письма</small></div></li>
				<li><span class="account-auth__benefit-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.5 8.2L6.5 11L12.5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg></span><div><strong>Быстрое оформление</strong><small>Контактные данные сохраняются безопасно</small></div></li>
			</ul>
		</div>
		<p class="account-auth__aside-help">Нужна помощь? <a href="<?php echo esc_url( home_url( '/otvety-na-voprosy/' ) ); ?>">Ответы на вопросы</a></p>
	</aside>
</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
