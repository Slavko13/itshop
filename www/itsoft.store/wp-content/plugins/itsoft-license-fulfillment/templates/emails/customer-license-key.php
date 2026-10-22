<?php
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<p>Оплата заказа №<?php echo esc_html( $order->get_order_number() ); ?> подтверждена. Назначенные лицензионные ключи:</p>
<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5" border="1">
	<thead><tr><th style="text-align:left">Товар</th><th style="text-align:left">Лицензионный ключ</th></tr></thead>
	<tbody>
	<?php foreach ( $license_rows as $row ) : ?>
		<tr><td><?php echo esc_html( $row['product_name'] ); ?></td><td><code><?php echo esc_html( $row['key'] ); ?></code></td></tr>
	<?php endforeach; ?>
	</tbody>
</table>
<p>Сохраните ключ в безопасном месте. Не передавайте его третьим лицам.</p>
<?php
do_action( 'woocommerce_email_footer', $email );
