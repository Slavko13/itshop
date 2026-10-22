<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Admin {
	private ITSoft_LF_Inventory_Repository $repository;
	private ITSoft_LF_Crypto $crypto;

	public function __construct( ITSoft_LF_Inventory_Repository $repository, ITSoft_LF_Crypto $crypto ) {
		$this->repository = $repository;
		$this->crypto     = $crypto;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_itsoft_lf_create_pool', array( $this, 'handle_create_pool' ) );
		add_action( 'admin_post_itsoft_lf_import_keys', array( $this, 'handle_import_keys' ) );
		add_action( 'admin_post_itsoft_lf_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_notices', array( $this, 'render_configuration_notice' ) );
	}

	public function register_menu(): void {
		add_menu_page(
			'Лицензионные ключи',
			'Лицензионные ключи',
			ITSoft_LF_Installer::CAPABILITY,
			'itsoft-license-inventory',
			array( $this, 'render_page' ),
			'dashicons-lock',
			56
		);

		add_submenu_page(
			'itsoft-license-inventory',
			'Обзор лицензий и пулов',
			'Обзор и пулы',
			ITSoft_LF_Installer::CAPABILITY,
			'itsoft-license-inventory',
			array( $this, 'render_page' )
		);
	}

	public function render_configuration_notice(): void {
		if ( current_user_can( ITSoft_LF_Installer::CAPABILITY ) && ! $this->crypto->is_configured() ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'ITSOFT License Fulfillment: encryption key is not configured. Import is disabled.', 'itsoft-license-fulfillment' );
			echo '</p></div>';
		}
	}

	public function render_page(): void {
		$this->assert_capability();
		$pools                    = $this->repository->list_pools();
		$keys                     = $this->repository->list_recent_keys();
		$mappings                 = $this->repository->list_product_mappings();
		$import_button_attributes = $this->crypto->is_configured() ? array() : array( 'disabled' => 'disabled' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Обзор лицензий и пулов', 'itsoft-license-fulfillment' ); ?></h1>
			<?php $this->render_result_notice(); ?>

			<h2><?php esc_html_e( 'Автоматическая выдача', 'itsoft-license-fulfillment' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="itsoft_lf_save_settings">
				<?php wp_nonce_field( 'itsoft_lf_save_settings' ); ?>
				<label><input type="checkbox" name="auto_fulfillment_enabled" value="yes" <?php checked( 'yes', get_option( 'itsoft_lf_auto_fulfillment_enabled', 'no' ) ); ?>>
					<?php esc_html_e( 'Выдавать ключи товарам AUTO после WooCommerce payment_complete', 'itsoft-license-fulfillment' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Не включать на production до проверки тестового платежа и повторного callback выбранного платёжного модуля.', 'itsoft-license-fulfillment' ); ?></p>
				<?php submit_button( __( 'Сохранить настройку', 'itsoft-license-fulfillment' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Пулы', 'itsoft-license-fulfillment' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th>ID</th><th>Название</th><th>Статус</th><th>Товаров</th><th>Всего</th><th>Доступно</th><th>Исключено из выдачи</th><th>Выдано</th></tr></thead>
				<tbody>
				<?php if ( ! $pools ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'Пулы ещё не созданы.', 'itsoft-license-fulfillment' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $pools as $pool ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $pool->id ); ?></td>
							<td><?php echo esc_html( $pool->name ); ?></td>
							<td><?php echo esc_html( $pool->status ); ?></td>
							<td><?php echo esc_html( (string) $pool->mapped_products ); ?></td>
							<td><?php echo esc_html( (string) $pool->total_keys ); ?></td>
							<td><?php echo esc_html( (string) $pool->available_keys ); ?></td>
							<td><?php echo esc_html( (string) $pool->disabled_keys ); ?></td>
							<td><?php echo esc_html( (string) $pool->assigned_keys ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Связи товаров с пулами', 'itsoft-license-fulfillment' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Связь и режим AUTO/MANUAL настраиваются в карточке товара, раздел «Запасы». Для AUTO также включите глобальную автоматическую выдачу выше.', 'itsoft-license-fulfillment' ); ?></p>
			<table class="widefat striped">
				<thead><tr><th>Товар / SKU</th><th>Пул</th><th>Режим</th><th>Обновлено</th></tr></thead>
				<tbody>
				<?php if ( ! $mappings ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Связи ещё не настроены.', 'itsoft-license-fulfillment' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $mappings as $mapping ) : ?>
						<?php $product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $mapping->product_id ) : false; ?>
						<tr>
							<td>
								<?php if ( $product ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( (int) $mapping->product_id ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
									<?php if ( $product->get_sku() ) : ?><code><?php echo esc_html( $product->get_sku() ); ?></code><?php endif; ?>
								<?php else : ?>
									<?php echo esc_html( '#' . (string) $mapping->product_id . ' (удалён)' ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $mapping->pool_name ); ?></td>
							<td><code><?php echo esc_html( strtoupper( $mapping->fulfillment_mode ) ); ?></code></td>
							<td><?php echo esc_html( $mapping->updated_at ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Создать пул', 'itsoft-license-fulfillment' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="itsoft_lf_create_pool">
				<?php wp_nonce_field( 'itsoft_lf_create_pool' ); ?>
				<input type="text" name="pool_name" class="regular-text" maxlength="190" required>
				<?php submit_button( __( 'Создать пул', 'itsoft-license-fulfillment' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Импорт individual keys', 'itsoft-license-fulfillment' ); ?></h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="itsoft_lf_import_keys">
				<?php wp_nonce_field( 'itsoft_lf_import_keys' ); ?>
				<p><select name="pool_id" required>
					<option value=""><?php esc_html_e( 'Выберите пул', 'itsoft-license-fulfillment' ); ?></option>
					<?php foreach ( $pools as $pool ) : ?>
						<option value="<?php echo esc_attr( (string) $pool->id ); ?>"><?php echo esc_html( $pool->name ); ?></option>
					<?php endforeach; ?>
				</select></p>
				<p><textarea name="keys" rows="8" cols="80" placeholder="Один ключ на строку"></textarea></p>
				<p><label>TXT/CSV: <input type="file" name="key_file" accept=".txt,.csv,text/plain,text/csv"></label></p>
				<p class="description"><?php esc_html_e( 'До 5000 строк и 2 МБ. Полные ключи после импорта не выводятся.', 'itsoft-license-fulfillment' ); ?></p>
				<?php submit_button( __( 'Импортировать', 'itsoft-license-fulfillment' ), 'primary', 'submit', false, $import_button_attributes ); ?>
			</form>

			<h2><?php esc_html_e( 'Последние ключи', 'itsoft-license-fulfillment' ); ?></h2>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=itsoft-license-keys' ) ); ?>"><?php esc_html_e( 'Открыть все ключи', 'itsoft-license-fulfillment' ); ?></a></p>
			<table class="widefat striped">
				<thead><tr><th>ID</th><th>Пул</th><th>Маска</th><th>Статус</th><th>Создан</th></tr></thead>
				<tbody>
				<?php if ( ! $keys ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Ключи отсутствуют.', 'itsoft-license-fulfillment' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $keys as $key ) : ?>
						<tr><td><?php echo esc_html( (string) $key->id ); ?></td><td><?php echo esc_html( $key->pool_name ); ?></td><td><code><?php echo esc_html( $key->masked_value ); ?></code></td><td><?php echo esc_html( $key->status ); ?></td><td><?php echo esc_html( $key->created_at ); ?></td></tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function handle_create_pool(): void {
		$this->assert_capability();
		check_admin_referer( 'itsoft_lf_create_pool' );

		try {
			$name = isset( $_POST['pool_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pool_name'] ) ) : '';
			$this->repository->create_pool( $name, get_current_user_id() );
			$this->redirect( 'pool_created' );
		} catch ( Throwable $exception ) {
			$this->redirect( 'error' );
		}
	}

	public function handle_import_keys(): void {
		$this->assert_capability();
		check_admin_referer( 'itsoft_lf_import_keys' );

		if ( ! $this->crypto->is_configured() ) {
			$this->redirect( 'crypto_missing' );
		}

		$pool_id = isset( $_POST['pool_id'] ) ? absint( $_POST['pool_id'] ) : 0;
		$keys    = $this->parse_textarea();

		try {
			$keys   = array_merge( $keys, $this->parse_upload() );
			$keys   = array_slice( $keys, 0, 5000 );
			$result = $this->repository->import_individual_keys( $pool_id, $keys, get_current_user_id() );
			$this->redirect( 'imported', $result );
		} catch ( Throwable $exception ) {
			$this->redirect( 'error' );
		}
	}

	public function handle_save_settings(): void {
		$this->assert_capability();
		check_admin_referer( 'itsoft_lf_save_settings' );
		update_option( 'itsoft_lf_auto_fulfillment_enabled', isset( $_POST['auto_fulfillment_enabled'] ) ? 'yes' : 'no', false );
		$this->redirect( 'settings_saved' );
	}

	private function parse_textarea(): array {
		$text = isset( $_POST['keys'] ) ? (string) wp_unslash( $_POST['keys'] ) : '';
		return preg_split( '/\R/u', $text ) ?: array();
	}

	private function parse_upload(): array {
		if ( empty( $_FILES['key_file']['tmp_name'] ) || UPLOAD_ERR_NO_FILE === (int) $_FILES['key_file']['error'] ) {
			return array();
		}
		if ( UPLOAD_ERR_OK !== (int) $_FILES['key_file']['error'] || (int) $_FILES['key_file']['size'] > 2 * MB_IN_BYTES ) {
			throw new RuntimeException( 'Invalid upload.' );
		}
		if ( ! is_uploaded_file( (string) $_FILES['key_file']['tmp_name'] ) ) {
			throw new RuntimeException( 'Upload source is invalid.' );
		}

		$filename  = sanitize_file_name( (string) $_FILES['key_file']['name'] );
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'txt', 'csv' ), true ) ) {
			throw new RuntimeException( 'Unsupported file type.' );
		}

		$content = file_get_contents( (string) $_FILES['key_file']['tmp_name'] );
		if ( false === $content ) {
			throw new RuntimeException( 'Unable to read upload.' );
		}

		$lines = preg_split( '/\R/u', $content ) ?: array();
		if ( 'csv' === $extension ) {
			return array_map(
				static function ( string $line ): string {
					$columns = str_getcsv( $line );
					return isset( $columns[0] ) ? (string) $columns[0] : '';
				},
				$lines
			);
		}

		return $lines;
	}

	private function render_result_notice(): void {
		$result = isset( $_GET['itsoft_lf_result'] ) ? sanitize_key( wp_unslash( $_GET['itsoft_lf_result'] ) ) : '';
		if ( '' === $result ) {
			return;
		}

		if ( 'imported' === $result ) {
			$added      = isset( $_GET['added'] ) ? absint( $_GET['added'] ) : 0;
			$duplicates = isset( $_GET['duplicates'] ) ? absint( $_GET['duplicates'] ) : 0;
			$invalid    = isset( $_GET['invalid'] ) ? absint( $_GET['invalid'] ) : 0;
			$errors     = isset( $_GET['errors'] ) ? absint( $_GET['errors'] ) : 0;
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( 'Добавлено: %d; дубликаты: %d; некорректные: %d; ошибки: %d.', $added, $duplicates, $invalid, $errors ) ) . '</p></div>';
			return;
		}

		$messages = array(
			'pool_created'   => 'Пул создан.',
			'settings_saved' => 'Настройки сохранены.',
			'crypto_missing' => 'Ключ шифрования не настроен.',
			'error'          => 'Операция не выполнена. Проверьте входные данные.',
		);
		if ( isset( $messages[ $result ] ) ) {
			echo '<div class="notice ' . ( 'error' === $result ? 'notice-error' : 'notice-success' ) . '"><p>' . esc_html( $messages[ $result ] ) . '</p></div>';
		}
	}

	private function redirect( string $result, array $summary = array() ): void {
		$args = array_merge( array( 'page' => 'itsoft-license-inventory', 'itsoft_lf_result' => $result ), $summary );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private function assert_capability(): void {
		if ( ! current_user_can( ITSoft_LF_Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage license inventory.', 'itsoft-license-fulfillment' ), 403 );
		}
	}
}
