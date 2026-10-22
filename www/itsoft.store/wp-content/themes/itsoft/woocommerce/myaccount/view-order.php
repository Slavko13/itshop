<?php
/**
 * Single customer order.
 *
 * @package itsoft
 * @version 10.6.0
 */

defined( 'ABSPATH' ) || exit;

$notes = $order->get_customer_order_notes();
$date  = $order->get_date_created();
?>

<a class="account-order-view__back" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">← Вернуться к заказам</a>

<header class="account-order-view__header">
	<div>
		<h2>Заказ № <?php echo esc_html( $order->get_order_number() ); ?></h2>
		<p>Здесь находятся состав заказа, оплата и назначенные лицензионные ключи.</p>
	</div>
	<span class="account-order-status account-order-status--<?php echo esc_attr( sanitize_html_class( $order->get_status() ) ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
</header>

<dl class="account-order-view__summary">
	<div><dt>Дата заказа</dt><dd><?php echo esc_html( $date ? wc_format_datetime( $date, 'd.m.Y' ) : '' ); ?></dd></div>
	<div><dt>Итого</dt><dd><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd></div>
	<div><dt>Способ оплаты</dt><dd><?php echo esc_html( $order->get_payment_method_title() ?: 'Не выбран' ); ?></dd></div>
</dl>

<?php if ( $notes ) : ?>
	<section class="account-order-updates">
		<h3>Обновления заказа</h3>
		<ol>
			<?php foreach ( $notes as $note ) : ?>
				<li><time><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $note->comment_date ) ) ); ?></time><?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?></li>
			<?php endforeach; ?>
		</ol>
	</section>
<?php endif; ?>

<?php do_action( 'woocommerce_view_order', $order_id ); ?>
