<?php
/**
 * Accessible show/hide password control.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$target = isset( $args['target'] ) ? sanitize_html_class( (string) $args['target'] ) : '';

if ( '' === $target ) {
	return;
}
?>
<button class="account-form__password-toggle" type="button" data-password-toggle="<?php echo esc_attr( $target ); ?>" aria-label="Показать пароль" aria-pressed="false">
	<svg class="account-form__eye account-form__eye--show" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
		<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
		<circle cx="12" cy="12" r="2.8"/>
	</svg>
	<svg class="account-form__eye account-form__eye--hide" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
		<path d="M4.2 4.2 19.8 19.8"/>
		<path d="M9.6 6.3A10 10 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-3 3.7M6.2 7.8A17.7 17.7 0 0 0 2.5 12s3.5 6 9.5 6a9.9 9.9 0 0 0 3-.5"/>
		<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
	</svg>
</button>
