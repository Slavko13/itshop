<?php
/**
 * Customer addresses overview.
 *
 * @package itsoft
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$customer_id = get_current_user_id();
$addresses   = array(
	'billing'  => 'Контактный адрес',
	'shipping' => 'Адрес доставки',
);
?>

<div class="account-section-head">
	<div>
		<h2>Адрес доставки</h2>
		<p>Адрес нужен только для товаров с физической доставкой. Для электронных ключей он не используется.</p>
	</div>
</div>

<div class="account-addresses">
	<?php foreach ( $addresses as $name => $title ) : ?>
		<?php $address = wc_get_account_formatted_address( $name ); ?>
		<article class="account-address-card">
			<header>
				<div><span><?php echo 'shipping' === $name ? 'Для посылок' : 'Данные покупателя'; ?></span><h3><?php echo esc_html( $title ); ?></h3></div>
			</header>
			<address><?php echo $address ? wp_kses_post( $address ) : '<span>Адрес пока не заполнен</span>'; ?></address>
			<a class="button" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', $name ) ); ?>"><?php echo $address ? 'Изменить' : 'Добавить адрес'; ?></a>
			<?php do_action( 'woocommerce_my_account_after_my_address', $name ); ?>
		</article>
	<?php endforeach; ?>
</div>
