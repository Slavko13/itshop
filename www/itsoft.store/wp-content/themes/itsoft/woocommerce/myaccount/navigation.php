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
			<option value="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" <?php selected( wc_is_current_account_menu_item( $endpoint ) ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
</div>

<nav class="woocommerce-MyAccount-navigation account-navigation" aria-label="Разделы личного кабинета">
	<ul>
		<?php foreach ( $items as $endpoint => $label ) : ?>
			<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" <?php echo wc_is_current_account_menu_item( $endpoint ) ? 'aria-current="page"' : ''; ?>>
					<span><?php echo esc_html( $label ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
