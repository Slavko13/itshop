<?php
/**
 * Account overview.
 *
 * @package itsoft
 * @version 4.4.0
 */

defined( 'ABSPATH' ) || exit;

$customer_id  = get_current_user_id();
$order_count  = wc_get_customer_order_count( $customer_id );
$license_count = (int) apply_filters( 'itsoft_account_license_count', 0, $customer_id );
$recent_orders = wc_get_orders(
	array(
		'customer_id' => $customer_id,
		'limit'       => 3,
		'orderby'     => 'date',
		'order'       => 'DESC',
	)
);
$first_name = trim( (string) $current_user->first_name );
$greeting   = $first_name ?: $current_user->display_name;
$address    = wc_get_account_formatted_address( 'shipping' );
if ( ! $address ) {
	$address = wc_get_account_formatted_address( 'billing' );
}
?>

<section class="account-dashboard">
	<header class="account-dashboard__welcome">
		<div>
			<h2>Здравствуйте, <?php echo esc_html( $greeting ); ?>!</h2>
			<p>Здесь собрана главная информация о ваших покупках.</p>
		</div>
		<a class="account-dashboard__catalog" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Перейти в каталог <span aria-hidden="true">→</span></a>
	</header>

	<div class="account-stats">
		<a class="account-stat account-stat--orders" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
			<span class="account-stat__value"><?php echo esc_html( $order_count ); ?></span>
			<span class="account-stat__label"><?php echo esc_html( itsoft_plural_form( $order_count, array( 'заказ', 'заказа', 'заказов' ) ) ); ?></span>
			<span class="account-stat__arrow" aria-hidden="true">→</span>
		</a>
		<a class="account-stat account-stat--licenses" href="<?php echo esc_url( wc_get_account_endpoint_url( 'licenses' ) ); ?>">
			<span class="account-stat__value"><?php echo esc_html( $license_count ); ?></span>
			<span class="account-stat__label"><?php echo esc_html( itsoft_plural_form( $license_count, array( 'ключ', 'ключа', 'ключей' ) ) ); ?></span>
			<span class="account-stat__arrow" aria-hidden="true">→</span>
		</a>
		<a class="account-stat account-stat--profile" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">
			<span class="account-stat__value account-stat__value--text">Профиль</span>
			<span class="account-stat__label">Контакты и пароль</span>
			<span class="account-stat__arrow" aria-hidden="true">→</span>
		</a>
	</div>

	<div class="account-dashboard__grid">
		<section class="account-dashboard-card account-dashboard-card--orders">
			<header class="account-dashboard-card__header">
				<div><h3>Последние заказы</h3></div>
				<?php if ( $recent_orders ) : ?><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">Все заказы</a><?php endif; ?>
			</header>

			<?php if ( $recent_orders ) : ?>
				<div class="account-recent-orders">
					<?php foreach ( $recent_orders as $order ) : ?>
						<?php
						$date       = $order->get_date_created();
						$item_names = array();
						foreach ( $order->get_items() as $item ) {
							$item_names[] = $item->get_name();
						}
						?>
						<a class="account-recent-order" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
							<span class="account-recent-order__number">№ <?php echo esc_html( $order->get_order_number() ); ?></span>
							<span class="account-recent-order__name"><?php echo esc_html( implode( ', ', array_slice( $item_names, 0, 2 ) ) ); ?></span>
							<span class="account-order-status account-order-status--<?php echo esc_attr( sanitize_html_class( $order->get_status() ) ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
							<span class="account-recent-order__meta"><?php echo esc_html( $date ? wc_format_datetime( $date, 'd.m.Y' ) : '' ); ?> · <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="account-dashboard-card__empty">
					<h4>Заказов пока нет</h4>
					<p>После первой покупки заказ появится в этом разделе.</p>
					<a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Выбрать товар</a>
				</div>
			<?php endif; ?>
		</section>

		<aside class="account-dashboard-card account-dashboard-card--details">
			<header class="account-dashboard-card__header">
				<div><h3>Данные покупателя</h3></div>
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">Изменить</a>
			</header>
			<dl class="account-profile-summary">
				<div><dt>E-mail</dt><dd><?php echo esc_html( $current_user->user_email ); ?></dd></div>
				<div><dt>Телефон</dt><dd><?php echo esc_html( get_user_meta( $customer_id, 'billing_phone', true ) ?: 'Не указан' ); ?></dd></div>
				<div><dt>Адрес доставки</dt><dd><?php echo $address ? wp_kses_post( $address ) : 'Не заполнен'; ?></dd></div>
			</dl>
			<a class="account-dashboard-card__address-link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>">Настроить адрес <span aria-hidden="true">→</span></a>
		</aside>
	</div>
</section>

<?php
do_action( 'woocommerce_account_dashboard' );
do_action( 'woocommerce_before_my_account' );
do_action( 'woocommerce_after_my_account' );
