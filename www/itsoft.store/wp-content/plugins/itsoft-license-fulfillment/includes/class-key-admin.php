<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Key_Admin {
	private ITSoft_LF_Inventory_Repository $repository;

	public function __construct( ITSoft_LF_Inventory_Repository $repository ) {
		$this->repository = $repository;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_post_itsoft_lf_manage_keys', array( $this, 'handle_manage_keys' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu(): void {
		add_submenu_page(
			'itsoft-license-inventory',
			'Управление лицензионными ключами',
			'Управление ключами',
			ITSoft_LF_Installer::CAPABILITY,
			'itsoft-license-keys',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'склад-ключей_page_itsoft-license-keys' !== $hook_suffix && false === strpos( $hook_suffix, 'itsoft-license-keys' ) ) {
			return;
		}
		wp_enqueue_script(
			'itsoft-lf-key-admin',
			plugins_url( 'assets/js/key-admin.js', ITSOFT_LF_FILE ),
			array(),
			ITSOFT_LF_VERSION,
			true
		);
	}

	public function render_page(): void {
		$this->assert_capability();
		$filters = $this->current_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$result  = $this->repository->query_keys( $filters );
		$pools   = $this->repository->list_pools();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Управление лицензионными ключами', 'itsoft-license-fulfillment' ); ?></h1>
			<p><?php esc_html_e( 'Полные значения не выводятся. Свободные и исключённые из выдачи ключи можно перемещать; выданные ключи защищены от изменений.', 'itsoft-license-fulfillment' ); ?></p>
			<?php $this->render_notice(); ?>

			<form method="get">
				<input type="hidden" name="page" value="itsoft-license-keys">
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Пул', 'itsoft-license-fulfillment' ); ?></span>
					<select name="pool_id">
						<option value="0"><?php esc_html_e( 'Все пулы', 'itsoft-license-fulfillment' ); ?></option>
						<?php foreach ( $pools as $pool ) : ?>
							<option value="<?php echo esc_attr( (string) $pool->id ); ?>" <?php selected( $filters['pool_id'], (int) $pool->id ); ?>><?php echo esc_html( $pool->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Статус', 'itsoft-license-fulfillment' ); ?></span>
					<select name="key_status">
						<option value=""><?php esc_html_e( 'Все статусы', 'itsoft-license-fulfillment' ); ?></option>
						<option value="available" <?php selected( $filters['status'], 'available' ); ?>><?php esc_html_e( 'Доступен', 'itsoft-license-fulfillment' ); ?></option>
						<option value="disabled" <?php selected( $filters['status'], 'disabled' ); ?>><?php esc_html_e( 'Исключён из выдачи', 'itsoft-license-fulfillment' ); ?></option>
						<option value="assigned" <?php selected( $filters['status'], 'assigned' ); ?>><?php esc_html_e( 'Выдан', 'itsoft-license-fulfillment' ); ?></option>
					</select>
				</label>
				<label><?php esc_html_e( 'С', 'itsoft-license-fulfillment' ); ?> <input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>"></label>
				<label><?php esc_html_e( 'по', 'itsoft-license-fulfillment' ); ?> <input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>"></label>
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Поиск', 'itsoft-license-fulfillment' ); ?></span>
					<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="ID или часть маски">
				</label>
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Строк на странице', 'itsoft-license-fulfillment' ); ?></span>
					<select name="per_page">
						<?php foreach ( array( 20, 50, 100, 200 ) as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( $filters['per_page'], $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php submit_button( __( 'Фильтровать', 'itsoft-license-fulfillment' ), 'secondary', 'submit', false ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=itsoft-license-keys' ) ); ?>"><?php esc_html_e( 'Сбросить', 'itsoft-license-fulfillment' ); ?></a>
			</form>

			<p><?php echo esc_html( sprintf( 'Найдено ключей: %d.', $result['total'] ) ); ?></p>

			<form id="itsoft-lf-key-management" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="itsoft_lf_manage_keys">
				<?php wp_nonce_field( 'itsoft_lf_manage_keys' ); ?>
				<?php $this->render_preserved_filters( $filters ); ?>

				<div class="tablenav top">
					<div class="alignleft actions">
						<label class="screen-reader-text" for="itsoft-lf-key-action"><?php esc_html_e( 'Действие', 'itsoft-license-fulfillment' ); ?></label>
						<select id="itsoft-lf-key-action" name="key_operation" required>
							<option value=""><?php esc_html_e( 'Выберите действие', 'itsoft-license-fulfillment' ); ?></option>
							<option value="move"><?php esc_html_e( 'Переместить в другой пул', 'itsoft-license-fulfillment' ); ?></option>
							<option value="disable"><?php esc_html_e( 'Исключить из выдачи', 'itsoft-license-fulfillment' ); ?></option>
							<option value="restore"><?php esc_html_e( 'Вернуть в выдачу', 'itsoft-license-fulfillment' ); ?></option>
						</select>
						<span id="itsoft-lf-target-pool-wrap" hidden>
							<label class="screen-reader-text" for="itsoft-lf-target-pool"><?php esc_html_e( 'Целевой пул', 'itsoft-license-fulfillment' ); ?></label>
							<select id="itsoft-lf-target-pool" name="target_pool_id">
								<option value="0"><?php esc_html_e( 'Выберите целевой пул', 'itsoft-license-fulfillment' ); ?></option>
								<?php foreach ( $pools as $pool ) : ?>
									<?php if ( 'active' === $pool->status ) : ?>
										<option value="<?php echo esc_attr( (string) $pool->id ); ?>"><?php echo esc_html( $pool->name ); ?></option>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
						</span>
						<?php submit_button( __( 'Применить', 'itsoft-license-fulfillment' ), 'secondary', 'submit', false ); ?>
					</div>
					<br class="clear">
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="manage-column check-column"><input id="itsoft-lf-select-all" type="checkbox"><label for="itsoft-lf-select-all" class="screen-reader-text"><?php esc_html_e( 'Выбрать все', 'itsoft-license-fulfillment' ); ?></label></td>
							<th>ID</th><th><?php esc_html_e( 'Пул', 'itsoft-license-fulfillment' ); ?></th><th><?php esc_html_e( 'Маска', 'itsoft-license-fulfillment' ); ?></th><th><?php esc_html_e( 'Статус', 'itsoft-license-fulfillment' ); ?></th><th><?php esc_html_e( 'Заказ', 'itsoft-license-fulfillment' ); ?></th><th><?php esc_html_e( 'Импортировал', 'itsoft-license-fulfillment' ); ?></th><th><?php esc_html_e( 'Создан', 'itsoft-license-fulfillment' ); ?></th><th><?php esc_html_e( 'Изменён', 'itsoft-license-fulfillment' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( ! $result['items'] ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'Ключи по выбранным условиям не найдены.', 'itsoft-license-fulfillment' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $result['items'] as $key ) : ?>
							<tr>
								<th class="check-column"><input class="itsoft-lf-key-checkbox" type="checkbox" name="key_ids[]" value="<?php echo esc_attr( (string) $key->id ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Выбрать ключ %d', $key->id ) ); ?>"></th>
								<td><?php echo esc_html( (string) $key->id ); ?></td>
								<td><?php echo esc_html( $key->pool_name ); ?> <code>#<?php echo esc_html( (string) $key->pool_id ); ?></code></td>
								<td><code><?php echo esc_html( $key->masked_value ); ?></code></td>
								<td><?php echo esc_html( $this->status_label( (string) $key->status ) ); ?></td>
								<td><?php $this->render_order_link( $key ); ?></td>
								<td><?php echo esc_html( $key->created_by_name ?: ( '#' . (string) $key->created_by ) ); ?></td>
								<td><?php echo esc_html( get_date_from_gmt( $key->created_at, 'd.m.Y H:i' ) ); ?></td>
								<td><?php echo esc_html( get_date_from_gmt( $key->updated_at, 'd.m.Y H:i' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>

				<p id="itsoft-lf-selected-count" aria-live="polite"><?php esc_html_e( 'Выбрано: 0', 'itsoft-license-fulfillment' ); ?></p>
				<p><label><input type="checkbox" name="confirm_operation" value="yes" required> <?php esc_html_e( 'Подтверждаю изменение выбранных ключей.', 'itsoft-license-fulfillment' ); ?></label></p>
			</form>

			<?php $this->render_pagination( $result, $filters ); ?>
		</div>
		<?php
	}

	public function handle_manage_keys(): void {
		$this->assert_capability();
		check_admin_referer( 'itsoft_lf_manage_keys' );
		$filters = $this->current_filters( $_POST );
		if ( 'yes' !== ( $_POST['confirm_operation'] ?? '' ) ) {
			$this->redirect( 'confirmation_missing', array(), $filters );
		}

		$key_ids = isset( $_POST['key_ids'] ) && is_array( $_POST['key_ids'] ) ? wp_unslash( $_POST['key_ids'] ) : array();
		$operation = isset( $_POST['key_operation'] ) ? sanitize_key( wp_unslash( $_POST['key_operation'] ) ) : '';
		$target    = isset( $_POST['target_pool_id'] ) ? absint( $_POST['target_pool_id'] ) : 0;
		try {
			$result = $this->repository->manage_keys( $key_ids, $operation, $target, get_current_user_id() );
			$this->redirect( 'managed', $result, $filters );
		} catch ( Throwable $exception ) {
			$this->redirect( 'error', array(), $filters );
		}
	}

	private function current_filters( array $source ): array {
		$status   = isset( $source['key_status'] ) ? sanitize_key( wp_unslash( $source['key_status'] ) ) : '';
		$allowed  = array( '', 'available', 'disabled', 'assigned' );
		$per_page = isset( $source['per_page'] ) ? absint( $source['per_page'] ) : 50;
		return array(
			'pool_id'   => isset( $source['pool_id'] ) ? absint( $source['pool_id'] ) : 0,
			'status'    => in_array( $status, $allowed, true ) ? $status : '',
			'search'    => isset( $source['s'] ) ? sanitize_text_field( wp_unslash( $source['s'] ) ) : '',
			'date_from' => isset( $source['date_from'] ) ? sanitize_text_field( wp_unslash( $source['date_from'] ) ) : '',
			'date_to'   => isset( $source['date_to'] ) ? sanitize_text_field( wp_unslash( $source['date_to'] ) ) : '',
			'page'      => isset( $source['paged'] ) ? max( 1, absint( $source['paged'] ) ) : 1,
			'per_page'  => in_array( $per_page, array( 20, 50, 100, 200 ), true ) ? $per_page : 50,
		);
	}

	private function render_preserved_filters( array $filters ): void {
		$fields = array(
			'pool_id'   => $filters['pool_id'],
			'key_status' => $filters['status'],
			's'         => $filters['search'],
			'date_from' => $filters['date_from'],
			'date_to'   => $filters['date_to'],
			'paged'     => $filters['page'],
			'per_page'  => $filters['per_page'],
		);
		foreach ( $fields as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
	}

	private function render_order_link( object $key ): void {
		if ( empty( $key->order_id ) ) {
			echo '&mdash;';
			return;
		}
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $key->order_id ) : false;
		$url   = $order ? $order->get_edit_order_url() : admin_url( 'admin.php?page=wc-orders&action=edit&id=' . (int) $key->order_id );
		$label = $key->public_number ?: ( '#' . (string) $key->order_id );
		echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	private function render_pagination( array $result, array $filters ): void {
		if ( $result['total_pages'] < 2 ) {
			return;
		}
		$args = array(
			'page'       => 'itsoft-license-keys',
			'pool_id'    => $filters['pool_id'],
			'key_status' => $filters['status'],
			's'          => $filters['search'],
			'date_from'  => $filters['date_from'],
			'date_to'    => $filters['date_to'],
			'per_page'   => $filters['per_page'],
			'paged'      => '%#%',
		);
		$links = paginate_links(
			array(
				'base'      => add_query_arg( $args, admin_url( 'admin.php' ) ),
				'format'    => '',
				'current'   => $result['page'],
				'total'     => $result['total_pages'],
				'type'      => 'list',
				'prev_text' => '‹',
				'next_text' => '›',
			)
		);
		if ( $links ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
		}
	}

	private function render_notice(): void {
		$result = isset( $_GET['itsoft_lf_key_result'] ) ? sanitize_key( wp_unslash( $_GET['itsoft_lf_key_result'] ) ) : '';
		if ( 'managed' === $result ) {
			$processed = isset( $_GET['processed'] ) ? absint( $_GET['processed'] ) : 0;
			$changed   = isset( $_GET['changed'] ) ? absint( $_GET['changed'] ) : 0;
			$skipped   = isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0;
			$errors    = isset( $_GET['errors'] ) ? absint( $_GET['errors'] ) : 0;
			$class     = $errors > 0 ? 'notice-error' : ( $skipped > 0 ? 'notice-warning' : 'notice-success' );
			echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( sprintf( 'Обработано: %d; изменено: %d; пропущено: %d; ошибок: %d.', $processed, $changed, $skipped, $errors ) ) . '</p></div>';
			return;
		}
		if ( 'confirmation_missing' === $result ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Подтвердите изменение выбранных ключей.', 'itsoft-license-fulfillment' ) . '</p></div>';
		} elseif ( 'error' === $result ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Операция не выполнена. Выберите ключи, допустимое действие и целевой пул.', 'itsoft-license-fulfillment' ) . '</p></div>';
		}
	}

	private function redirect( string $result, array $summary, array $filters ): void {
		$args = array_merge(
			array(
				'page'                 => 'itsoft-license-keys',
				'itsoft_lf_key_result' => $result,
				'pool_id'              => $filters['pool_id'],
				'key_status'           => $filters['status'],
				's'                    => $filters['search'],
				'date_from'            => $filters['date_from'],
				'date_to'              => $filters['date_to'],
				'paged'                => $filters['page'],
				'per_page'             => $filters['per_page'],
			),
			$summary
		);
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private function status_label( string $status ): string {
		$labels = array(
			'available' => 'Доступен',
			'disabled'  => 'Исключён из выдачи',
			'assigned'  => 'Выдан',
		);
		return $labels[ $status ] ?? $status;
	}

	private function assert_capability(): void {
		if ( ! current_user_can( ITSoft_LF_Installer::CAPABILITY ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage license inventory.', 'itsoft-license-fulfillment' ), 403 );
		}
	}
}
