<?php
/**
 * Customer account shell.
 *
 * @package itsoft
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;

$customer      = wp_get_current_user();
$display_name  = trim( $customer->first_name . ' ' . $customer->last_name );
$display_name  = $display_name ?: $customer->display_name;
$initial       = function_exists( 'mb_substr' ) ? mb_substr( $display_name, 0, 1 ) : substr( $display_name, 0, 1 );
$display_email = $customer->user_email;
?>

<div class="account-shell">
	<aside class="account-shell__sidebar">
		<div class="account-identity">
			<span class="account-identity__avatar" aria-hidden="true"><?php echo esc_html( strtoupper( $initial ) ); ?></span>
			<div class="account-identity__body">
				<strong><?php echo esc_html( $display_name ); ?></strong>
				<span><?php echo esc_html( $display_email ); ?></span>
			</div>
		</div>

		<?php do_action( 'woocommerce_account_navigation' ); ?>

		<div class="account-shell__help">
			<div>
				<strong>Нужна помощь?</strong>
				<a href="<?php echo esc_url( home_url( '/otvety-na-voprosy/' ) ); ?>">Ответы на вопросы</a>
			</div>
		</div>
	</aside>

	<div class="woocommerce-MyAccount-content account-shell__content">
		<?php do_action( 'woocommerce_account_content' ); ?>
	</div>
</div>
