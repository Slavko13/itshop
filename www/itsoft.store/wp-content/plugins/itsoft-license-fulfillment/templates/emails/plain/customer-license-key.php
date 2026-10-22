<?php
defined( 'ABSPATH' ) || exit;

echo esc_html( $email_heading ) . "\n\n";
echo 'Заказ №' . esc_html( $order->get_order_number() ) . "\n\n";
foreach ( $license_rows as $row ) {
	echo esc_html( $row['product_name'] ) . ': ' . esc_html( $row['key'] ) . "\n";
}
echo "\nСохраните ключ в безопасном месте. Не передавайте его третьим лицам.\n";
