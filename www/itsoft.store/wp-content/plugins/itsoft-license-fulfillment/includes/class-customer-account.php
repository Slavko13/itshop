<?php

defined( 'ABSPATH' ) || exit;

/**
 * Customer-facing license archive in WooCommerce My Account.
 */
final class ITSoft_LF_Customer_Account {
	private const ENDPOINT = 'licenses';
	private const ENDPOINT_SCHEMA = '1';

	private ITSoft_LF_Inventory_Repository $repository;

	public function __construct( ITSoft_LF_Inventory_Repository $repository ) {
		$this->repository = $repository;
	}

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_endpoint' ), 9 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'filter_menu_items' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_endpoint' ) );
		add_filter( 'itsoft_account_license_count', array( $this, 'filter_license_count' ), 10, 2 );
	}

	public function register_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );

		if ( self::ENDPOINT_SCHEMA !== get_option( 'itsoft_lf_account_endpoint_schema' ) ) {
			flush_rewrite_rules( false );
			update_option( 'itsoft_lf_account_endpoint_schema', self::ENDPOINT_SCHEMA, false );
		}
	}

	public function filter_menu_items( array $items ): array {
		unset( $items['downloads'] );

		$labels = array(
			'dashboard'       => 'Обзор',
			'orders'          => 'Мои заказы',
			self::ENDPOINT     => 'Лицензионные ключи',
			'edit-address'    => 'Адрес доставки',
			'edit-account'    => 'Личные данные',
			'customer-logout' => 'Выйти',
		);
		$result = array();

		foreach ( $items as $endpoint => $label ) {
			$result[ $endpoint ] = $labels[ $endpoint ] ?? $label;
			if ( 'orders' === $endpoint ) {
				$result[ self::ENDPOINT ] = $labels[ self::ENDPOINT ];
			}
		}

		if ( ! isset( $result[ self::ENDPOINT ] ) ) {
			$result[ self::ENDPOINT ] = $labels[ self::ENDPOINT ];
		}

		return $result;
	}

	public function filter_license_count( int $count, int $customer_id ): int {
		if ( $customer_id < 1 ) {
			return 0;
		}

		$order_ids = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'limit'       => -1,
				'return'      => 'ids',
			)
		);

		return $this->repository->count_assignments_for_orders( $order_ids );
	}

	public function render_endpoint(): void {
		$customer_id = get_current_user_id();
		if ( $customer_id < 1 ) {
			return;
		}

		$entries = $this->get_customer_entries( $customer_id, true );
		?>
		<div class="account-section-head">
			<div>
				<span class="account-section-head__eyebrow">Ваши покупки</span>
				<h2>Лицензионные ключи</h2>
				<p>Ключи доступны только владельцу аккаунта. Нажмите «Показать», чтобы открыть значение.</p>
			</div>
			<span class="account-section-head__count"><?php echo esc_html( count( $entries ) ); ?></span>
		</div>

		<?php if ( ! $entries ) : ?>
			<div class="account-empty-state">
				<span class="account-empty-state__icon" aria-hidden="true">◇</span>
				<h3>Ключей пока нет</h3>
				<p>После подтверждения оплаты и выдачи лицензия появится здесь и придёт на e-mail.</p>
				<a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Перейти в каталог</a>
			</div>
		<?php else : ?>
			<div class="account-license-list">
				<?php foreach ( $entries as $entry ) : ?>
					<article class="account-license-card account-license-card--<?php echo esc_attr( $entry['state'] ); ?>">
						<div class="account-license-card__product">
							<?php if ( $entry['image'] ) : ?>
								<?php echo wp_kses_post( $entry['image'] ); ?>
							<?php else : ?>
								<span class="account-license-card__fallback" aria-hidden="true">K</span>
							<?php endif; ?>
							<div>
								<span class="account-license-card__state"><?php echo 'issued' === $entry['state'] ? 'Ключ выдан' : 'Ожидает выдачи'; ?></span>
								<h3><?php echo esc_html( $entry['product_name'] ); ?></h3>
								<p>Заказ <a href="<?php echo esc_url( $entry['order_url'] ); ?>">№ <?php echo esc_html( $entry['order_number'] ); ?></a> · <?php echo esc_html( $entry['date'] ); ?></p>
							</div>
						</div>

						<?php if ( 'issued' === $entry['state'] ) : ?>
							<div class="account-license-card__key" data-license-control>
								<span class="account-license-card__key-label">Лицензионный ключ</span>
								<code data-license-value data-masked="<?php echo esc_attr( $entry['masked'] ); ?>" data-value="<?php echo esc_attr( $entry['value'] ); ?>"><?php echo esc_html( $entry['masked'] ); ?></code>
								<div class="account-license-card__actions">
									<button type="button" data-license-reveal aria-pressed="false">Показать</button>
									<button type="button" data-license-copy>Скопировать</button>
								</div>
								<span class="account-license-card__feedback" data-license-feedback role="status" aria-live="polite"></span>
							</div>
						<?php else : ?>
							<div class="account-license-card__pending">
								<strong>Ключ ещё не назначен</strong>
								<span>После подтверждённой оплаты он появится здесь автоматически или будет выдан менеджером.</span>
							</div>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Returns license rows only for orders owned by the requested customer.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_customer_entries( int $customer_id, bool $decrypt ): array {
		$orders = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'limit'       => 100,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		$entries = array();

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order || (int) $order->get_customer_id() !== $customer_id ) {
				continue;
			}

			foreach ( $order->get_items() as $item_id => $item ) {
				$product     = $item->get_product();
				$product_id  = $product ? (int) $product->get_id() : (int) $item->get_product_id();
				$assignments = $this->repository->get_assignments_for_item( (int) $item_id );
				$image       = $product ? $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) : '';

				foreach ( $assignments as $assignment ) {
					$value = '';
					if ( $decrypt ) {
						try {
							$value = $this->repository->decrypt_assignment( $assignment );
						} catch ( Throwable $exception ) {
							continue;
						}
					}

					$entries[] = $this->make_entry( $order, $item, $image, 'issued', (string) $assignment->masked_value, $value );
				}

				if ( ! $assignments && $product_id > 0 && $this->repository->resolve_product_mapping( $product_id ) ) {
					$entries[] = $this->make_entry( $order, $item, $image, 'pending', '', '' );
				}
			}
		}

		return $entries;
	}

	private function make_entry( WC_Order $order, WC_Order_Item_Product $item, string $image, string $state, string $masked, string $value ): array {
		$date = $order->get_date_created();

		return array(
			'state'        => $state,
			'product_name' => $item->get_name(),
			'image'        => $image,
			'order_number' => $order->get_order_number(),
			'order_url'    => $order->get_view_order_url(),
			'date'         => $date ? wc_format_datetime( $date, 'd.m.Y' ) : '',
			'masked'       => $masked,
			'value'        => $value,
		);
	}
}
