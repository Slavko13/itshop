<?php
/**
 * Plugin Name: IT Софт — Яндекс Метрика
 * Description: Consent-aware Яндекс Метрика, e-commerce and business goals for IT Софт.
 * Version: 1.0.0
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

final class ITSoft_Metrika {
	private const VERSION = '1.0.0';
	private const OPTION  = 'itsoft_metrika_settings';
	private const COOKIE_PENDING_GOALS  = 'itsoft_ym_goals';
	private const COOKIE_PENDING_ADD    = 'itsoft_ym_cart_add';
	private const COOKIE_PENDING_REMOVE = 'itsoft_ym_cart_remove';

	private static array $displayed_products = array();

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_post_itsoft_metrika_save', array( __CLASS__, 'save_settings' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ), 100 );
		add_action( 'wp_footer', array( __CLASS__, 'attach_frontend_config' ), 5 );
		add_action( 'the_post', array( __CLASS__, 'collect_product_impression' ), 20 );
		add_action( 'wp_login', array( __CLASS__, 'queue_login_goal' ), 10, 2 );
		add_action( 'user_register', array( __CLASS__, 'queue_register_goal' ) );
		add_action( 'comment_post', array( __CLASS__, 'queue_review_goal' ), 10, 3 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'queue_cart_add' ), 10, 6 );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'queue_cart_remove' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_remove_link', array( __CLASS__, 'decorate_remove_link' ), 20, 2 );
	}

	private static function defaults(): array {
		return array(
			'production_id'    => '',
			'test_id'          => '',
			'production_hosts' => "itsoft.store\nwww.itsoft.store",
			'test_hosts'       => "test.itsoft.store\nlocalhost\n127.0.0.1\n45.151.70.104\n176.98.184.23",
			'require_consent'  => '1',
			'debug'            => '0',
		);
	}

	private static function settings(): array {
		$value = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $value ) ? $value : array(), self::defaults() );
	}

	private static function sanitize_counter_id( $value ): string {
		return preg_replace( '/\D+/', '', (string) $value );
	}

	private static function hosts( string $value ): array {
		$hosts = preg_split( '/[\s,;]+/', strtolower( $value ) );
		return array_values( array_unique( array_filter( array_map( 'trim', $hosts ) ) ) );
	}

	private static function current_counter(): array {
		$settings = self::settings();
		$host     = strtolower( preg_replace( '/:\d+$/', '', (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) );
		$is_test  = in_array( $host, self::hosts( (string) $settings['test_hosts'] ), true );
		$is_prod  = in_array( $host, self::hosts( (string) $settings['production_hosts'] ), true );

		$production_id = defined( 'ITSOFT_METRIKA_PRODUCTION_ID' )
			? self::sanitize_counter_id( constant( 'ITSOFT_METRIKA_PRODUCTION_ID' ) )
			: self::sanitize_counter_id( $settings['production_id'] );
		$test_id = defined( 'ITSOFT_METRIKA_TEST_ID' )
			? self::sanitize_counter_id( constant( 'ITSOFT_METRIKA_TEST_ID' ) )
			: self::sanitize_counter_id( $settings['test_id'] );

		if ( $is_test ) {
			return array( 'id' => $test_id, 'environment' => 'test' );
		}

		if ( $is_prod ) {
			return array( 'id' => $production_id, 'environment' => 'production' );
		}

		return array( 'id' => '', 'environment' => 'unknown' );
	}

	public static function register_settings_page(): void {
		add_options_page(
			'IT Софт — Яндекс Метрика',
			'IT Софт — Метрика',
			'manage_options',
			'itsoft-metrika',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'itsoft' ) );
		}

		check_admin_referer( 'itsoft_metrika_save' );
		$input = isset( $_POST['itsoft_metrika'] ) && is_array( $_POST['itsoft_metrika'] )
			? wp_unslash( $_POST['itsoft_metrika'] )
			: array();

		$settings = array(
			'production_id'    => self::sanitize_counter_id( $input['production_id'] ?? '' ),
			'test_id'          => self::sanitize_counter_id( $input['test_id'] ?? '' ),
			'production_hosts' => implode( "\n", self::hosts( sanitize_textarea_field( $input['production_hosts'] ?? '' ) ) ),
			'test_hosts'       => implode( "\n", self::hosts( sanitize_textarea_field( $input['test_hosts'] ?? '' ) ) ),
			'require_consent'  => empty( $input['require_consent'] ) ? '0' : '1',
			'debug'            => empty( $input['debug'] ) ? '0' : '1',
		);

		update_option( self::OPTION, $settings, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'itsoft-metrika', 'updated' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::settings();
		$counter  = self::current_counter();
		$goals    = self::goal_labels();
		?>
		<div class="wrap">
			<h1>IT Софт — Яндекс Метрика</h1>
			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p>Настройки сохранены.</p></div>
			<?php endif; ?>
			<p>Код загружается только на перечисленных доменах и только после согласия на cookies. Тестовый и боевой трафик разделены.</p>
			<p><strong>Текущий режим:</strong> <?php echo esc_html( $counter['environment'] ); ?>; <strong>счётчик:</strong> <?php echo esc_html( $counter['id'] ?: 'не задан' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="itsoft_metrika_save">
				<?php wp_nonce_field( 'itsoft_metrika_save' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="itsoft-metrika-production-id">ID боевого счётчика</label></th><td><input id="itsoft-metrika-production-id" class="regular-text" inputmode="numeric" name="itsoft_metrika[production_id]" value="<?php echo esc_attr( $settings['production_id'] ); ?>"><p class="description">Для itsoft.store. Если ID пустой, запросы в Метрику не отправляются.</p></td></tr>
					<tr><th scope="row"><label for="itsoft-metrika-test-id">ID тестового счётчика</label></th><td><input id="itsoft-metrika-test-id" class="regular-text" inputmode="numeric" name="itsoft_metrika[test_id]" value="<?php echo esc_attr( $settings['test_id'] ); ?>"><p class="description">Для test.itsoft.store и тестовых IP.</p></td></tr>
					<tr><th scope="row"><label for="itsoft-metrika-production-hosts">Боевые домены</label></th><td><textarea id="itsoft-metrika-production-hosts" class="large-text code" rows="4" name="itsoft_metrika[production_hosts]"><?php echo esc_textarea( $settings['production_hosts'] ); ?></textarea></td></tr>
					<tr><th scope="row"><label for="itsoft-metrika-test-hosts">Тестовые домены и IP</label></th><td><textarea id="itsoft-metrika-test-hosts" class="large-text code" rows="7" name="itsoft_metrika[test_hosts]"><?php echo esc_textarea( $settings['test_hosts'] ); ?></textarea></td></tr>
					<tr><th scope="row">Приватность</th><td><label><input type="checkbox" name="itsoft_metrika[require_consent]" value="1" <?php checked( '1', $settings['require_consent'] ); ?>> Запускать Метрику только после принятия cookies</label></td></tr>
					<tr><th scope="row">Диагностика</th><td><label><input type="checkbox" name="itsoft_metrika[debug]" value="1" <?php checked( '1', $settings['debug'] ); ?>> Показывать отправляемые события в консоли браузера</label></td></tr>
				</table>
				<?php submit_button( 'Сохранить настройки' ); ?>
			</form>

			<h2>Цели для создания в кабинете Метрики</h2>
			<p>Создайте цели типа «JavaScript-событие» с точными идентификаторами:</p>
			<table class="widefat striped" style="max-width:900px"><thead><tr><th>Идентификатор</th><th>Что означает</th></tr></thead><tbody>
			<?php foreach ( $goals as $goal_id => $label ) : ?>
				<tr><td><code><?php echo esc_html( $goal_id ); ?></code></td><td><?php echo esc_html( $label ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p>Также включите в счётчиках Вебвизор, карту кликов, карту ссылок, аналитику форм и электронную коммерцию. Имя контейнера электронной коммерции: <code>dataLayer</code>.</p>
		</div>
		<?php
	}

	private static function goal_labels(): array {
		return array(
			'ym-product-view'                => 'Просмотр карточки товара',
			'ym-add-to-cart'                 => 'Успешное добавление в корзину',
			'ym-begin-checkout'              => 'Переход к оформлению заказа',
			'ym-add-payment-info'            => 'Выбор способа оплаты',
			'ym-purchase'                    => 'Успешная оплаченная покупка',
			'ym-register'                    => 'Успешная регистрация',
			'ym-login'                       => 'Успешный вход',
			'ym-site-search'                 => 'Поиск по сайту',
			'itsoft-distribution-download'   => 'Скачивание дистрибутива',
			'itsoft-license-copy'            => 'Копирование лицензионного ключа',
			'itsoft-review-submit'           => 'Успешная отправка отзыва',
			'itsoft-checkout-error'          => 'Ошибка при оформлении заказа',
		);
	}

	public static function collect_product_impression( WP_Post $post ): void {
		if ( is_admin() || 'product' !== $post->post_type || is_singular( 'product' ) || isset( self::$displayed_products[ $post->ID ] ) ) {
			return;
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
		if ( $product instanceof WC_Product && $product->is_visible() && $product->is_purchasable() && $product->is_in_stock() ) {
			self::$displayed_products[ $post->ID ] = self::product_data( $product, 1, self::page_list_name(), count( self::$displayed_products ) + 1 );
		}
	}

	private static function page_list_name(): string {
		if ( is_search() ) {
			return 'Результаты поиска';
		}
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return 'Категория товаров';
		}
		if ( is_front_page() ) {
			return 'Главная';
		}
		return 'Каталог';
	}

	public static function enqueue_frontend(): void {
		if ( is_admin() ) {
			return;
		}

		$counter = self::current_counter();
		if ( empty( $counter['id'] ) ) {
			return;
		}

		$asset_path = __DIR__ . '/itsoft-metrika-assets/metrika.js';
		wp_enqueue_script(
			'itsoft-metrika',
			plugins_url( 'itsoft-metrika-assets/metrika.js', __FILE__ ),
			array(),
			file_exists( $asset_path ) ? (string) filemtime( $asset_path ) : self::VERSION,
			true
		);

	}

	public static function attach_frontend_config(): void {
		$counter = self::current_counter();
		if ( empty( $counter['id'] ) || ! wp_script_is( 'itsoft-metrika', 'enqueued' ) ) {
			return;
		}

		wp_add_inline_script(
			'itsoft-metrika',
			'window.ITSOFT_METRIKA = ' . wp_json_encode( self::frontend_config( $counter ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';',
			'before'
		);
	}

	private static function frontend_config( array $counter ): array {
		$settings       = self::settings();
		$initial_events = array();
		$current_product = null;
		$purchase        = null;

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product instanceof WC_Product ) {
				$current_product  = self::product_data( $product, 1, 'Карточка товара', 1 );
				$initial_events[] = array( 'type' => 'detail', 'products' => array( $current_product ) );
			}
		}

		if ( self::$displayed_products ) {
			$initial_events[] = array( 'type' => 'impressions', 'products' => array_values( self::$displayed_products ) );
		}

		$order = self::thankyou_order();
		if ( $order instanceof WC_Order && self::is_trackable_purchase( $order, $counter['environment'] ) ) {
			$products = array();
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( $product instanceof WC_Product ) {
					$products[] = self::product_data( $product, (float) $item->get_quantity(), 'Заказ', count( $products ) + 1, (float) $order->get_item_total( $item ) );
				}
			}
			$purchase = array(
				'orderId'  => (string) $order->get_order_number(),
				'revenue'  => (float) $order->get_total(),
				'coupon'   => implode( ', ', $order->get_coupon_codes() ),
				'currency' => (string) $order->get_currency(),
				'products' => $products,
			);
		}

		$pending_add    = self::pending_cart_product( self::COOKIE_PENDING_ADD );
		$pending_remove = self::pending_cart_product( self::COOKIE_PENDING_REMOVE );
		$search         = null;
		if ( is_search() ) {
			global $wp_query;
			$search = array(
				'queryLength' => function_exists( 'mb_strlen' ) ? mb_strlen( get_search_query( false ) ) : strlen( get_search_query( false ) ),
				'resultCount' => isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : 0,
			);
		}

		return array(
			'counterId'       => (int) $counter['id'],
			'environment'     => $counter['environment'],
			'visitorType'     => current_user_can( 'manage_options' ) ? 'staff' : ( is_user_logged_in() ? 'customer' : 'guest' ),
			'requireConsent'  => '1' === $settings['require_consent'],
			'debug'           => '1' === $settings['debug'],
			'currency'        => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'RUB',
			'page'            => array(
				'isProduct' => function_exists( 'is_product' ) && is_product(),
				'isCheckout' => function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ),
				'isCart' => function_exists( 'is_cart' ) && is_cart(),
				'distributionId' => is_singular( 'distribution' ) ? get_queried_object_id() : 0,
			),
			'currentProduct'  => $current_product,
			'initialEvents'   => $initial_events,
			'purchase'        => $purchase,
			'pendingAdd'      => $pending_add,
			'pendingRemove'   => $pending_remove,
			'pendingGoals'    => self::pending_goals(),
			'search'          => $search,
			'cookieNames'     => array(
				'goals'  => self::COOKIE_PENDING_GOALS,
				'add'    => self::COOKIE_PENDING_ADD,
				'remove' => self::COOKIE_PENDING_REMOVE,
			),
		);
	}

	private static function product_data( WC_Product $product, float $quantity = 1, string $list = '', int $position = 0, ?float $price = null ): array {
		$categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		$data = array(
			'id'       => (string) ( $product->get_sku() ?: $product->get_id() ),
			'name'     => wp_strip_all_tags( $product->get_name() ),
			'brand'    => 'Microsoft',
			'category' => is_wp_error( $categories ) ? '' : implode( '/', $categories ),
			'price'    => null === $price ? (float) wc_get_price_to_display( $product ) : $price,
			'quantity' => $quantity,
		);
		if ( $list ) {
			$data['list'] = $list;
		}
		if ( $position > 0 ) {
			$data['position'] = $position;
		}
		return array_filter( $data, static fn( $value ) => '' !== $value && null !== $value );
	}

	private static function thankyou_order() {
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) {
			return null;
		}
		$order_id = absint( get_query_var( 'order-received' ) );
		return $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
	}

	private static function is_trackable_purchase( WC_Order $order, string $environment ): bool {
		if ( ! $order->is_paid() && ! $order->has_status( array( 'processing', 'completed' ) ) ) {
			return false;
		}
		$method = strtolower( (string) $order->get_payment_method() );
		if ( 'production' === $environment && ( str_contains( $method, 'mock' ) || str_contains( $method, 'test' ) ) ) {
			return false;
		}
		return true;
	}

	public static function queue_login_goal( string $user_login, WP_User $user ): void {
		$redirect = isset( $_REQUEST['redirect_to'] ) ? (string) wp_unslash( $_REQUEST['redirect_to'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( str_contains( $redirect, '/wp-admin' ) ) {
			return;
		}
		self::queue_goal_cookie( 'ym-login' );
	}

	public static function queue_register_goal( int $user_id ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		self::queue_goal_cookie( 'ym-register' );
	}

	public static function queue_review_goal( int $comment_id, $comment_approved, array $commentdata ): void {
		$post_id = absint( $commentdata['comment_post_ID'] ?? 0 );
		$type    = get_post_type( $post_id );
		if ( in_array( $type, array( 'product', 'distribution' ), true ) ) {
			self::queue_goal_cookie( 'itsoft-review-submit' );
		}
	}

	private static function queue_goal_cookie( string $goal ): void {
		$goals = self::pending_goals();
		if ( ! in_array( $goal, $goals, true ) ) {
			$goals[] = $goal;
		}
		self::set_json_cookie( self::COOKIE_PENDING_GOALS, $goals, DAY_IN_SECONDS );
	}

	private static function pending_goals(): array {
		$raw   = isset( $_COOKIE[ self::COOKIE_PENDING_GOALS ] ) ? rawurldecode( wp_unslash( $_COOKIE[ self::COOKIE_PENDING_GOALS ] ) ) : '';
		$value = json_decode( $raw, true );
		return is_array( $value ) ? array_values( array_filter( array_map( 'sanitize_key', $value ) ) ) : array();
	}

	public static function queue_cart_add( string $cart_item_key, int $product_id, int $quantity, int $variation_id, array $variation, array $cart_item_data ): void {
		self::set_json_cookie( self::COOKIE_PENDING_ADD, array( 'productId' => $variation_id ?: $product_id, 'quantity' => $quantity ), 15 * MINUTE_IN_SECONDS );
	}

	public static function queue_cart_remove( string $cart_item_key, WC_Cart $cart ): void {
		$item = $cart->removed_cart_contents[ $cart_item_key ] ?? null;
		if ( ! is_array( $item ) ) {
			return;
		}
		self::set_json_cookie(
			self::COOKIE_PENDING_REMOVE,
			array(
				'productId' => absint( $item['variation_id'] ?? 0 ) ?: absint( $item['product_id'] ?? 0 ),
				'quantity'  => max( 1, absint( $item['quantity'] ?? 1 ) ),
			),
			15 * MINUTE_IN_SECONDS
		);
	}

	private static function pending_cart_product( string $cookie_name ): ?array {
		$raw   = isset( $_COOKIE[ $cookie_name ] ) ? rawurldecode( wp_unslash( $_COOKIE[ $cookie_name ] ) ) : '';
		$value = json_decode( $raw, true );
		if ( ! is_array( $value ) || empty( $value['productId'] ) || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( absint( $value['productId'] ) );
		return $product instanceof WC_Product ? self::product_data( $product, max( 1, (float) ( $value['quantity'] ?? 1 ) ), 'Корзина' ) : null;
	}

	private static function set_json_cookie( string $name, array $value, int $ttl ): void {
		if ( headers_sent() ) {
			return;
		}
		$encoded = rawurlencode( wp_json_encode( $value ) );
		setcookie(
			$name,
			$encoded,
			array(
				'expires'  => time() + $ttl,
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ $name ] = $encoded;
	}

	public static function decorate_remove_link( string $link, string $cart_item_key ): string {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $link;
		}
		$item = WC()->cart->get_cart_item( $cart_item_key );
		if ( ! is_array( $item ) ) {
			return $link;
		}
		$product = $item['data'] ?? null;
		if ( ! $product instanceof WC_Product ) {
			return $link;
		}
		$data = self::product_data( $product, max( 1, (float) ( $item['quantity'] ?? 1 ) ), 'Корзина' );
		return preg_replace( '/<a\s/', '<a data-metrika-product="' . esc_attr( wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ) . '" ', $link, 1 ) ?: $link;
	}
}

ITSoft_Metrika::init();
