<?php
/**
 * Customer orders as responsive cards.
 *
 * @package itsoft
 * @version 9.5.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders );
?>

<div class="account-section-head">
	<div>
		<h2>Мои заказы</h2>
		<p>Откройте заказ, чтобы посмотреть состав, статус оплаты и выданные ключи.</p>
	</div>
	<?php if ( $has_orders ) : ?><span class="account-section-head__count"><?php echo esc_html( count( $customer_orders->orders ) ); ?></span><?php endif; ?>
</div>

<?php if ( $has_orders ) : ?>
	<div class="account-orders-list">
		<?php foreach ( $customer_orders->orders as $customer_order ) : ?>
			<?php
			$order      = wc_get_order( $customer_order );
			$item_count = $order->get_item_count() - $order->get_item_count_refunded();
			$date       = $order->get_date_created();
			$items      = $order->get_items();
			$first_item = reset( $items );
			$product    = $first_item ? $first_item->get_product() : false;
			$actions    = wc_get_account_orders_actions( $order );
			?>
			<article class="account-order-card">
				<header class="account-order-card__header">
					<div>
						<span class="account-order-card__label">Заказ</span>
						<a class="account-order-card__number" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">№ <?php echo esc_html( $order->get_order_number() ); ?></a>
					</div>
					<span class="account-order-status account-order-status--<?php echo esc_attr( sanitize_html_class( $order->get_status() ) ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
				</header>

				<div class="account-order-card__body">
					<div class="account-order-card__product">
						<?php if ( $product ) : ?>
							<div class="account-order-card__image"><?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?></div>
						<?php endif; ?>
						<div>
							<?php foreach ( array_slice( $items, 0, 2, true ) as $item ) : ?>
								<strong><?php echo esc_html( $item->get_name() ); ?></strong>
							<?php endforeach; ?>
							<?php if ( count( $items ) > 2 ) : ?><small>и ещё <?php echo esc_html( count( $items ) - 2 ); ?></small><?php endif; ?>
						</div>
					</div>

					<dl class="account-order-card__meta">
						<div><dt>Дата</dt><dd><?php echo esc_html( $date ? wc_format_datetime( $date, 'd.m.Y' ) : '' ); ?></dd></div>
						<div><dt>Товаров</dt><dd><?php echo esc_html( $item_count ); ?></dd></div>
						<div><dt>Сумма</dt><dd><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd></div>
					</dl>
				</div>

				<footer class="account-order-card__footer">
					<span>Подробности заказа и ключи доступны внутри</span>
					<div class="account-order-card__actions">
						<?php foreach ( $actions as $key => $action ) : ?>
							<?php $action_names = array( 'view' => 'Подробнее', 'pay' => 'Оплатить', 'cancel' => 'Отменить' ); ?>
							<a class="button account-order-card__action account-order-card__action--<?php echo esc_attr( sanitize_html_class( $key ) ); ?> <?php echo esc_attr( sanitize_html_class( $key ) ); ?>" href="<?php echo esc_url( $action['url'] ); ?>" aria-label="<?php echo esc_attr( $action['aria-label'] ?? $action['name'] ); ?>">
								<span><?php echo esc_html( $action_names[ $key ] ?? $action['name'] ); ?></span>
								<?php if ( in_array( $key, array( 'view', 'pay' ), true ) ) : ?><span class="account-order-card__action-arrow" aria-hidden="true">→</span><?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
				</footer>
			</article>
		<?php endforeach; ?>
	</div>

	<?php do_action( 'woocommerce_before_account_orders_pagination' ); ?>
	<?php if ( 1 < $customer_orders->max_num_pages ) : ?>
		<nav class="woocommerce-pagination account-pagination" aria-label="Страницы заказов">
			<?php if ( 1 !== $current_page ) : ?><a class="button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ); ?>">← Назад</a><?php endif; ?>
			<span>Страница <?php echo esc_html( $current_page ); ?> из <?php echo esc_html( $customer_orders->max_num_pages ); ?></span>
			<?php if ( (int) $customer_orders->max_num_pages !== $current_page ) : ?><a class="button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ); ?>">Далее →</a><?php endif; ?>
		</nav>
	<?php endif; ?>
<?php else : ?>
	<div class="account-empty-state">
		<h3>Заказов пока нет</h3>
		<p>Выберите нужный продукт — после оформления заказ появится здесь.</p>
		<a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Перейти в каталог</a>
	</div>
<?php endif; ?>

<?php do_action( 'woocommerce_after_account_orders', $has_orders ); ?>
