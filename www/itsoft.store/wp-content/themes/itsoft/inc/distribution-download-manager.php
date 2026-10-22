<?php
/**
 * Distribution files, link checks and protected 24-hour downloads.
 *
 * @package itsoft
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ITSOFT_DIST_DB_VERSION = '1.0.0';

/**
 * Return the distribution files table name.
 *
 * @return string
 */
function itsoft_dist_files_table() {
	global $wpdb;
	return $wpdb->prefix . 'itsoft_distribution_files';
}

/**
 * Return the temporary token table name.
 *
 * @return string
 */
function itsoft_dist_tokens_table() {
	global $wpdb;
	return $wpdb->prefix . 'itsoft_distribution_tokens';
}

/**
 * Install or upgrade custom storage tables.
 *
 * @return void
 */
function itsoft_dist_install() {
	if ( ITSOFT_DIST_DB_VERSION === get_option( 'itsoft_dist_db_version' ) ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$files   = itsoft_dist_files_table();
	$tokens  = itsoft_dist_tokens_table();

	dbDelta(
		"CREATE TABLE {$files} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			distribution_id bigint(20) unsigned NOT NULL,
			position int(10) unsigned NOT NULL DEFAULT 0,
			label varchar(255) NOT NULL DEFAULT '',
			language varchar(100) NOT NULL DEFAULT '',
			architecture varchar(32) NOT NULL DEFAULT '',
			file_format varchar(32) NOT NULL DEFAULT '',
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			checksum_sha256 char(64) NOT NULL DEFAULT '',
			source_name varchar(191) NOT NULL DEFAULT '',
			link_mode varchar(20) NOT NULL DEFAULT 'permanent',
			permanent_url text NULL,
			private_path text NULL,
			original_filename varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			download_count bigint(20) unsigned NOT NULL DEFAULT 0,
			last_checked_at datetime NULL,
			last_http_status smallint(5) unsigned NOT NULL DEFAULT 0,
			last_error text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY distribution_position (distribution_id,position),
			KEY link_mode_status (link_mode,status),
			KEY last_checked_at (last_checked_at)
		) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$tokens} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			file_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			revoked_at datetime NULL,
			download_count bigint(20) unsigned NOT NULL DEFAULT 0,
			last_download_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY file_active (file_id,expires_at,revoked_at)
		) {$charset};"
	);

	update_option( 'itsoft_dist_db_version', ITSOFT_DIST_DB_VERSION, false );
}
add_action( 'after_switch_theme', 'itsoft_dist_install' );
add_action( 'init', 'itsoft_dist_install', 5 );

/**
 * Return configured private storage directory.
 *
 * Production should define ITSOFT_PRIVATE_DISTRIBUTION_DIR outside ABSPATH.
 *
 * @return string
 */
function itsoft_dist_private_dir() {
	$directory = defined( 'ITSOFT_PRIVATE_DISTRIBUTION_DIR' )
		? (string) ITSOFT_PRIVATE_DISTRIBUTION_DIR
		: dirname( untrailingslashit( ABSPATH ) ) . '/itsoft-private-distributions';

	return (string) apply_filters( 'itsoft_distribution_private_dir', $directory );
}

/**
 * Ensure private storage exists and is not browsable on Apache.
 *
 * @return true|WP_Error
 */
function itsoft_dist_ensure_private_dir() {
	$directory = itsoft_dist_private_dir();

	if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
		return new WP_Error( 'private_dir_create', __( 'Не удалось создать закрытую директорию дистрибутивов.', 'itsoft' ) );
	}

	if ( ! is_writable( $directory ) ) {
		return new WP_Error( 'private_dir_writable', __( 'Закрытая директория дистрибутивов недоступна для записи.', 'itsoft' ) );
	}

	$deny_file = trailingslashit( $directory ) . '.htaccess';
	if ( ! file_exists( $deny_file ) ) {
		@file_put_contents( $deny_file, "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	$index_file = trailingslashit( $directory ) . 'index.php';
	if ( ! file_exists( $index_file ) ) {
		@file_put_contents( $index_file, "<?php\nhttp_response_code( 404 );\nexit;\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	return true;
}

/**
 * Return whether private storage is outside the public WordPress root.
 *
 * @return bool
 */
function itsoft_dist_private_dir_is_outside_webroot() {
	$directory = wp_normalize_path( itsoft_dist_private_dir() );
	$root      = trailingslashit( wp_normalize_path( ABSPATH ) );
	return 0 !== strpos( trailingslashit( $directory ), $root );
}

/**
 * Fetch one file row.
 *
 * @param int $file_id File ID.
 * @return array|null
 */
function itsoft_dist_get_file( $file_id ) {
	global $wpdb;
	$row = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . itsoft_dist_files_table() . ' WHERE id = %d', (int) $file_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		ARRAY_A
	);
	return is_array( $row ) ? $row : null;
}

/**
 * Fetch files attached to a distribution.
 *
 * @param int  $distribution_id Distribution post ID.
 * @param bool $active_only     Only public active rows.
 * @return array<int,array<string,mixed>>
 */
function itsoft_dist_get_files( $distribution_id, $active_only = false ) {
	global $wpdb;
	$sql = $wpdb->prepare(
		"SELECT * FROM " . itsoft_dist_files_table() . " WHERE distribution_id = %d AND status <> 'deleted'", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		(int) $distribution_id
	);
	if ( $active_only ) {
		$sql .= " AND status = 'active'";
	}
	$sql .= ' ORDER BY position ASC, id ASC';
	$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return is_array( $rows ) ? $rows : array();
}

/**
 * Sum real downloads for a distribution.
 *
 * @param int $distribution_id Distribution post ID.
 * @return int
 */
function itsoft_dist_get_download_count( $distribution_id ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COALESCE(SUM(download_count),0) FROM ' . itsoft_dist_files_table() . ' WHERE distribution_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			(int) $distribution_id
		)
	);
}

/**
 * Infer metadata from one legacy ACF row.
 *
 * @param string $label Label/version.
 * @param string $url   Legacy URL.
 * @return array<string,string>
 */
function itsoft_dist_infer_legacy_metadata( $label, $url ) {
	$language = '';
	if ( false !== stripos( $url, '/ru-ru/' ) ) {
		$language = 'Русский';
	} elseif ( false !== stripos( $url, '/en-us/' ) ) {
		$language = 'English';
	}

	$architecture = '';
	if ( preg_match( '/(?:x|х)\s*64|64-bit/ui', $label ) ) {
		$architecture = 'x64';
	} elseif ( preg_match( '/(?:x|х)\s*32|32-bit|x86/ui', $label ) ) {
		$architecture = 'x86';
	}

	$path        = (string) wp_parse_url( $url, PHP_URL_PATH );
	$extension   = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
	$source_name = (string) wp_parse_url( $url, PHP_URL_HOST );

	return array(
		'language'     => $language,
		'architecture' => $architecture,
		'file_format'  => $extension,
		'source_name'  => $source_name,
	);
}

/**
 * Migrate legacy ACF rows once without deleting their source values.
 *
 * @return void
 */
function itsoft_dist_migrate_legacy_rows() {
	if ( get_option( 'itsoft_dist_legacy_migrated' ) || ! function_exists( 'get_field' ) ) {
		return;
	}

	global $wpdb;
	$post_ids = get_posts(
		array(
			'post_type'      => 'distribution',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	$now = current_time( 'mysql', true );
	foreach ( $post_ids as $post_id ) {
		if ( itsoft_dist_get_files( $post_id ) ) {
			continue;
		}

		$legacy_rows = get_field( 'links', $post_id );
		if ( ! is_array( $legacy_rows ) ) {
			continue;
		}

		foreach ( array_values( $legacy_rows ) as $position => $legacy ) {
			$label = isset( $legacy['version'] ) ? sanitize_text_field( (string) $legacy['version'] ) : '';
			$url   = isset( $legacy['link'] ) ? esc_url_raw( (string) $legacy['link'] ) : '';
			if ( '' === $url ) {
				continue;
			}

			$meta   = itsoft_dist_infer_legacy_metadata( $label, $url );
			$status = 'active';
			$error  = '';
			$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			$title  = strtolower( get_the_title( $post_id ) );

			if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
				$status = 'disabled';
				$error  = 'Отключено при миграции: локальная/тестовая ссылка.';
			} elseif ( false !== strpos( $title, 'office' ) && false !== strpos( $url, '/rufus-' ) ) {
				$status = 'disabled';
				$error  = 'Отключено при миграции: ссылка Rufus не соответствует дистрибутиву Office.';
			}

			$wpdb->insert(
				itsoft_dist_files_table(),
				array(
					'distribution_id' => (int) $post_id,
					'position'        => (int) $position,
					'label'           => $label,
					'language'        => $meta['language'],
					'architecture'    => $meta['architecture'],
					'file_format'     => $meta['file_format'],
					'source_name'     => $meta['source_name'],
					'link_mode'       => 'permanent',
					'permanent_url'   => $url,
					'status'          => $status,
					'last_error'      => $error,
					'created_at'      => $now,
					'updated_at'      => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	update_option( 'itsoft_dist_legacy_migrated', gmdate( 'c' ), false );
}
add_action( 'init', 'itsoft_dist_migrate_legacy_rows', 30 );

/**
 * Hide the legacy ACF links repeater after non-destructive migration.
 *
 * @param array<string,mixed> $field Field definition.
 * @return array<string,mixed>|false
 */
function itsoft_dist_hide_legacy_links_field( $field ) {
	if ( is_admin() && ITSOFT_DIST_DB_VERSION === get_option( 'itsoft_dist_db_version' ) ) {
		return false;
	}
	return $field;
}
add_filter( 'acf/prepare_field/name=links', 'itsoft_dist_hide_legacy_links_field' );

/**
 * Convert the repetitive legacy article markup into editable instruction steps.
 * Source post content is intentionally preserved as a non-destructive backup.
 *
 * @return void
 */
function itsoft_dist_migrate_legacy_instructions() {
	if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
		return;
	}

	$post_ids = get_posts(
		array(
			'post_type'      => 'distribution',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $post_ids as $post_id ) {
		if ( get_post_meta( $post_id, '_itsoft_dist_steps_migrated', true ) ) {
			continue;
		}
		if ( get_field( 'installation_steps', $post_id ) ) {
			update_post_meta( $post_id, '_itsoft_dist_steps_migrated', 'existing' );
			continue;
		}

		$content = (string) get_post_field( 'post_content', $post_id );
		$steps   = array();
		if ( preg_match_all( '/<strong\b[^>]*>(.*?)<\/strong>(.*?)(?=<strong\b|$)/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$title = trim( preg_replace( '/^\s*\d+[\.)]\s*/u', '', wp_strip_all_tags( $match[1] ) ) );
				if ( ! $title || false !== mb_stripos( $title, 'инструкция по установке' ) ) {
					continue;
				}
				$image_id = 0;
				if ( preg_match( '/wp-image-(\d+)/', $match[2], $image_match ) ) {
					$image_id = absint( $image_match[1] );
				}
				$steps[] = array(
					'title'     => $title,
					'text'      => '',
					'image'     => $image_id,
					'note'      => '',
					'note_type' => 'info',
				);
			}
		}

		if ( count( $steps ) >= 2 ) {
			update_field( 'field_itsoft_dist_install_steps', $steps, $post_id );
			update_post_meta( $post_id, '_itsoft_dist_steps_migrated', (string) count( $steps ) );
		}
	}
}
add_action( 'init', 'itsoft_dist_migrate_legacy_instructions', 35 );

/**
 * Add distribution file manager meta box.
 *
 * @return void
 */
function itsoft_dist_add_meta_box() {
	add_meta_box(
		'itsoft-distribution-files',
		__( 'Файлы и ссылки дистрибутива', 'itsoft' ),
		'itsoft_dist_render_meta_box',
		'distribution',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_distribution', 'itsoft_dist_add_meta_box' );

/**
 * Render a select option set.
 *
 * @param array<string,string> $options Options.
 * @param string               $current Current value.
 * @return void
 */
function itsoft_dist_render_options( $options, $current ) {
	foreach ( $options as $value => $label ) {
		printf(
			'<option value="%1$s"%2$s>%3$s</option>',
			esc_attr( $value ),
			selected( $current, $value, false ),
			esc_html( $label )
		);
	}
}

/**
 * Render fields for an existing/new file row.
 *
 * @param array<string,mixed> $file File data.
 * @param string              $key  Form key.
 * @return void
 */
function itsoft_dist_render_admin_file_row( $file, $key ) {
	$is_new    = 'new' === $key;
	$file_id   = $is_new ? 0 : (int) $file['id'];
	$mode      = isset( $file['link_mode'] ) ? (string) $file['link_mode'] : 'permanent';
	$status    = isset( $file['status'] ) ? (string) $file['status'] : 'active';
	$last_code = isset( $file['last_http_status'] ) ? (int) $file['last_http_status'] : 0;
	?>
	<fieldset class="itsoft-dist-admin-file<?php echo $is_new ? ' itsoft-dist-admin-file--new' : ''; ?>">
		<legend>
			<?php echo $is_new ? esc_html__( 'Новая позиция', 'itsoft' ) : esc_html( sprintf( __( 'Позиция #%d', 'itsoft' ), $file_id ) ); ?>
			<?php if ( ! $is_new ) : ?>
				<span class="itsoft-dist-admin-file__state itsoft-dist-admin-file__state--<?php echo esc_attr( $status ); ?>">
					<?php echo 'active' === $status ? esc_html__( 'Опубликована', 'itsoft' ) : esc_html__( 'Отключена', 'itsoft' ); ?>
				</span>
			<?php endif; ?>
		</legend>

		<input type="hidden" name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][id]" value="<?php echo esc_attr( $file_id ); ?>">

		<div class="itsoft-dist-admin-grid">
			<label>
				<span><?php esc_html_e( 'Название варианта', 'itsoft' ); ?></span>
				<input type="text" name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $file['label'] ?? '' ); ?>" placeholder="Версия x64">
			</label>
			<label>
				<span><?php esc_html_e( 'Язык', 'itsoft' ); ?></span>
				<input type="text" name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][language]" value="<?php echo esc_attr( $file['language'] ?? '' ); ?>" placeholder="Русский">
			</label>
			<label>
				<span><?php esc_html_e( 'Архитектура', 'itsoft' ); ?></span>
				<select name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][architecture]">
					<?php itsoft_dist_render_options( array( '' => '—', 'x64' => 'x64', 'x86' => 'x86 / x32', 'arm64' => 'ARM64', 'universal' => __( 'Универсальная', 'itsoft' ) ), (string) ( $file['architecture'] ?? '' ) ); ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Формат', 'itsoft' ); ?></span>
				<input type="text" name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][file_format]" value="<?php echo esc_attr( $file['file_format'] ?? '' ); ?>" placeholder="img / iso / exe">
			</label>
			<label>
				<span><?php esc_html_e( 'Источник', 'itsoft' ); ?></span>
				<input type="text" name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][source_name]" value="<?php echo esc_attr( $file['source_name'] ?? '' ); ?>" placeholder="Microsoft">
			</label>
			<label>
				<span><?php esc_html_e( 'Размер, байт', 'itsoft' ); ?></span>
				<input type="number" min="0" name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][file_size]" value="<?php echo esc_attr( (int) ( $file['file_size'] ?? 0 ) ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'Режим ссылки', 'itsoft' ); ?></span>
				<select name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][link_mode]">
					<?php itsoft_dist_render_options( array( 'permanent' => __( 'Постоянная', 'itsoft' ), 'temporary' => __( 'Защищённая, 24 часа', 'itsoft' ) ), $mode ); ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Публикация', 'itsoft' ); ?></span>
				<select name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][status]">
					<?php itsoft_dist_render_options( array( 'active' => __( 'Доступна', 'itsoft' ), 'disabled' => __( 'Временно недоступна', 'itsoft' ) ), $status ); ?>
				</select>
			</label>
		</div>

		<label class="itsoft-dist-admin-wide">
			<span><?php esc_html_e( 'Постоянный URL', 'itsoft' ); ?></span>
			<input type="url" name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][permanent_url]" value="<?php echo esc_attr( $file['permanent_url'] ?? '' ); ?>" placeholder="https://...">
		</label>

		<label class="itsoft-dist-admin-wide">
			<span><?php esc_html_e( 'SHA-256 (необязательно)', 'itsoft' ); ?></span>
			<input type="text" maxlength="64" name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][checksum_sha256]" value="<?php echo esc_attr( $file['checksum_sha256'] ?? '' ); ?>" pattern="[a-fA-F0-9]{64}">
		</label>

		<label class="itsoft-dist-admin-wide">
			<span><?php esc_html_e( 'Закрытый файл', 'itsoft' ); ?></span>
			<input type="file" name="itsoft_private_file_<?php echo esc_attr( $key ); ?>">
			<?php if ( ! empty( $file['original_filename'] ) ) : ?>
				<small><?php echo esc_html( sprintf( __( 'Загружен: %s', 'itsoft' ), $file['original_filename'] ) ); ?></small>
			<?php else : ?>
				<small><?php esc_html_e( 'Нужен только для защищённого режима. Файл сохраняется вне публичной медиатеки.', 'itsoft' ); ?></small>
			<?php endif; ?>
		</label>

		<?php if ( ! $is_new ) : ?>
			<div class="itsoft-dist-admin-actions">
				<?php if ( 'permanent' === $mode ) : ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=itsoft_dist_check_file&file_id=' . $file_id ), 'itsoft_dist_check_' . $file_id ) ); ?>"><?php esc_html_e( 'Проверить ссылку', 'itsoft' ); ?></a>
				<?php else : ?>
					<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=itsoft_dist_generate_token&file_id=' . $file_id ), 'itsoft_dist_generate_' . $file_id ) ); ?>"><?php esc_html_e( 'Сформировать новую ссылку на 24 часа', 'itsoft' ); ?></a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=itsoft_dist_revoke_tokens&file_id=' . $file_id ), 'itsoft_dist_revoke_' . $file_id ) ); ?>"><?php esc_html_e( 'Отозвать активные ссылки', 'itsoft' ); ?></a>
				<?php endif; ?>
				<label class="itsoft-dist-admin-delete"><input type="checkbox" name="itsoft_dist_files[<?php echo esc_attr( $key ); ?>][delete]" value="1"> <?php esc_html_e( 'Удалить позицию', 'itsoft' ); ?></label>
			</div>
			<?php if ( $file['last_checked_at'] || $file['last_error'] ) : ?>
				<p class="itsoft-dist-admin-check <?php echo ( $last_code >= 200 && $last_code < 400 ) ? 'is-ok' : 'is-error'; ?>">
					<?php echo esc_html( sprintf( __( 'Последняя проверка: %1$s; HTTP: %2$d. %3$s', 'itsoft' ), $file['last_checked_at'] ?: '—', $last_code, $file['last_error'] ?: '' ) ); ?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</fieldset>
	<?php
}

/**
 * Render distribution file manager.
 *
 * @param WP_Post $post Distribution.
 * @return void
 */
function itsoft_dist_render_meta_box( $post ) {
	wp_nonce_field( 'itsoft_dist_save_files', 'itsoft_dist_files_nonce' );
	$files   = itsoft_dist_get_files( $post->ID );
	$flash   = get_transient( 'itsoft_dist_admin_flash_' . get_current_user_id() );
	$outside = itsoft_dist_private_dir_is_outside_webroot();
	delete_transient( 'itsoft_dist_admin_flash_' . get_current_user_id() );
	?>
	<div class="itsoft-dist-admin">
		<?php if ( is_array( $flash ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $flash['type'] ?? 'success' ); ?> inline"><p><?php echo wp_kses_post( $flash['message'] ?? '' ); ?></p></div>
		<?php endif; ?>
		<div class="notice notice-<?php echo $outside ? 'success' : 'warning'; ?> inline">
			<p>
				<strong><?php esc_html_e( 'Закрытое хранилище:', 'itsoft' ); ?></strong>
				<code><?php echo esc_html( itsoft_dist_private_dir() ); ?></code><br>
				<?php echo $outside ? esc_html__( 'Директория расположена вне web-root.', 'itsoft' ) : esc_html__( 'Директория находится внутри web-root. Перед production необходимо подтвердить запрет прямого доступа на уровне сервера.', 'itsoft' ); ?>
			</p>
		</div>
		<p><?php esc_html_e( 'Постоянные ссылки перенаправляют на сохранённый официальный URL. Защищённая ссылка создаётся администратором, действует 24 часа и выдаёт только загруженный закрытый файл.', 'itsoft' ); ?></p>
		<?php foreach ( $files as $file ) : ?>
			<?php itsoft_dist_render_admin_file_row( $file, (string) $file['id'] ); ?>
		<?php endforeach; ?>
		<?php itsoft_dist_render_admin_file_row( array(), 'new' ); ?>
	</div>
	<style>
		.itsoft-dist-admin-file{border:1px solid #dcdcde;border-radius:10px;padding:16px;margin:16px 0;background:#fff}.itsoft-dist-admin-file legend{padding:0 8px;font-weight:700}.itsoft-dist-admin-grid{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr));gap:12px}.itsoft-dist-admin label span{display:block;font-weight:600;margin-bottom:5px}.itsoft-dist-admin input,.itsoft-dist-admin select{width:100%}.itsoft-dist-admin-wide{display:block;margin-top:12px}.itsoft-dist-admin-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:14px}.itsoft-dist-admin-delete{margin-left:auto;color:#b32d2e}.itsoft-dist-admin-delete input{width:auto}.itsoft-dist-admin-file__state{display:inline-block;margin-left:8px;padding:3px 8px;border-radius:99px;font-size:11px;background:#dff5e7;color:#116b36}.itsoft-dist-admin-file__state--disabled{background:#fce8e8;color:#9d2424}.itsoft-dist-admin-check{padding:8px 10px;background:#f6f7f7}.itsoft-dist-admin-check.is-error{border-left:4px solid #d63638}.itsoft-dist-admin-check.is-ok{border-left:4px solid #00a32a}@media(max-width:900px){.itsoft-dist-admin-grid{grid-template-columns:repeat(2,1fr)}}
	</style>
	<script>document.addEventListener('DOMContentLoaded',function(){var form=document.getElementById('post');if(form){form.enctype='multipart/form-data';}});</script>
	<?php
}

/**
 * Sanitize a file form row.
 *
 * @param array<string,mixed> $input Raw row.
 * @return array<string,mixed>
 */
function itsoft_dist_sanitize_file_row( $input ) {
	$mode   = isset( $input['link_mode'] ) && 'temporary' === $input['link_mode'] ? 'temporary' : 'permanent';
	$status = isset( $input['status'] ) && 'disabled' === $input['status'] ? 'disabled' : 'active';
	$sha    = isset( $input['checksum_sha256'] ) ? strtolower( sanitize_text_field( $input['checksum_sha256'] ) ) : '';
	if ( $sha && ! preg_match( '/^[a-f0-9]{64}$/', $sha ) ) {
		$sha = '';
	}

	return array(
		'label'           => sanitize_text_field( $input['label'] ?? '' ),
		'language'        => sanitize_text_field( $input['language'] ?? '' ),
		'architecture'    => in_array( $input['architecture'] ?? '', array( '', 'x64', 'x86', 'arm64', 'universal' ), true ) ? $input['architecture'] : '',
		'file_format'     => strtolower( sanitize_key( $input['file_format'] ?? '' ) ),
		'file_size'       => absint( $input['file_size'] ?? 0 ),
		'checksum_sha256' => $sha,
		'source_name'     => sanitize_text_field( $input['source_name'] ?? '' ),
		'link_mode'       => $mode,
		'permanent_url'   => esc_url_raw( $input['permanent_url'] ?? '' ),
		'status'          => $status,
	);
}

/**
 * Save an uploaded file outside the media library.
 *
 * @param array<string,mixed> $upload One $_FILES entry.
 * @return array<string,mixed>|WP_Error
 */
function itsoft_dist_store_private_upload( $upload ) {
	if ( empty( $upload['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $upload['error'] ) {
		return new WP_Error( 'upload_missing', __( 'Закрытый файл не был загружен.', 'itsoft' ) );
	}

	$ready = itsoft_dist_ensure_private_dir();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}

	$original  = sanitize_file_name( (string) $upload['name'] );
	$extension = strtolower( (string) pathinfo( $original, PATHINFO_EXTENSION ) );
	$allowed   = array( 'zip', 'rar', '7z', 'exe', 'msi', 'iso', 'img', 'dmg', 'pkg', 'apk', 'deb', 'rpm', 'bin', 'tar', 'gz' );
	if ( ! in_array( $extension, $allowed, true ) ) {
		return new WP_Error( 'upload_type', __( 'Этот формат файла не разрешён для закрытого хранилища.', 'itsoft' ) );
	}

	$stored_name = wp_generate_uuid4() . '.' . $extension;
	$destination = trailingslashit( itsoft_dist_private_dir() ) . $stored_name;
	if ( ! @move_uploaded_file( $upload['tmp_name'], $destination ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return new WP_Error( 'upload_move', __( 'Не удалось переместить файл в закрытое хранилище.', 'itsoft' ) );
	}
	@chmod( $destination, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	return array(
		'private_path'      => $stored_name,
		'original_filename' => $original,
		'file_size'         => (int) filesize( $destination ),
		'file_format'       => $extension,
	);
}

/**
 * Save distribution file manager values.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function itsoft_dist_save_files( $post_id ) {
	if ( ! isset( $_POST['itsoft_dist_files_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['itsoft_dist_files_nonce'] ) ), 'itsoft_dist_save_files' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$rows = isset( $_POST['itsoft_dist_files'] ) && is_array( $_POST['itsoft_dist_files'] )
		? wp_unslash( $_POST['itsoft_dist_files'] )
		: array();

	global $wpdb;
	$now      = current_time( 'mysql', true );
	$position = 0;
	$messages = array();

	foreach ( $rows as $key => $raw ) {
		if ( ! is_array( $raw ) ) {
			continue;
		}
		$file_id = 'new' === (string) $key ? 0 : absint( $raw['id'] ?? $key );
		$existing = $file_id ? itsoft_dist_get_file( $file_id ) : null;
		if ( $existing && (int) $existing['distribution_id'] !== (int) $post_id ) {
			continue;
		}

		if ( ! empty( $raw['delete'] ) && $existing ) {
			$wpdb->update( itsoft_dist_files_table(), array( 'status' => 'deleted', 'updated_at' => $now ), array( 'id' => $file_id ), array( '%s', '%s' ), array( '%d' ) );
			itsoft_dist_revoke_file_tokens( $file_id );
			continue;
		}

		$data = itsoft_dist_sanitize_file_row( $raw );
		$upload_key = 'itsoft_private_file_' . $key;
		$has_upload = isset( $_FILES[ $upload_key ] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES[ $upload_key ]['error']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! $existing && '' === $data['label'] && '' === $data['permanent_url'] && ! $has_upload ) {
			continue;
		}

		$data['distribution_id'] = (int) $post_id;
		$data['position']        = $position++;
		$data['updated_at']      = $now;

		if ( $existing ) {
			$data['private_path']      = $existing['private_path'];
			$data['original_filename'] = $existing['original_filename'];
		}

		if ( $has_upload ) {
			$stored = itsoft_dist_store_private_upload( $_FILES[ $upload_key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_wp_error( $stored ) ) {
				$messages[] = $stored->get_error_message();
			} else {
				$data = array_merge( $data, $stored );
			}
		}

		if ( 'temporary' === $data['link_mode'] && empty( $data['private_path'] ) ) {
			$data['status']     = 'disabled';
			$data['last_error'] = __( 'Защищённый режим отключён: сначала загрузите закрытый файл.', 'itsoft' );
			$messages[]         = sprintf( __( '«%s»: защищённый режим сохранён как недоступный, потому что файл не загружен.', 'itsoft' ), $data['label'] ?: __( 'Новая позиция', 'itsoft' ) );
		} elseif ( 'permanent' === $data['link_mode'] && '' === $data['permanent_url'] ) {
			$data['status']     = 'disabled';
			$data['last_error'] = __( 'Постоянный режим отключён: URL не заполнен.', 'itsoft' );
		}

		if ( $existing ) {
			$wpdb->update( itsoft_dist_files_table(), $data, array( 'id' => $file_id ), null, array( '%d' ) );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( itsoft_dist_files_table(), $data );
		}
	}

	if ( $messages ) {
		set_transient(
			'itsoft_dist_admin_flash_' . get_current_user_id(),
			array( 'type' => 'warning', 'message' => implode( '<br>', array_map( 'esc_html', $messages ) ) ),
			MINUTE_IN_SECONDS
		);
	}
}
add_action( 'save_post_distribution', 'itsoft_dist_save_files', 20 );

/**
 * Add useful file state columns to the distributions admin list.
 *
 * @param array<string,string> $columns Existing columns.
 * @return array<string,string>
 */
function itsoft_dist_admin_columns( $columns ) {
	$columns['itsoft_dist_files']     = __( 'Файлы', 'itsoft' );
	$columns['itsoft_dist_link_mode'] = __( 'Режим выдачи', 'itsoft' );
	$columns['itsoft_dist_downloads'] = __( 'Скачивания', 'itsoft' );
	return $columns;
}
add_filter( 'manage_distribution_posts_columns', 'itsoft_dist_admin_columns' );

/**
 * Render distribution file state in the admin list.
 *
 * @param string $column  Column key.
 * @param int    $post_id Distribution post ID.
 * @return void
 */
function itsoft_dist_admin_column_content( $column, $post_id ) {
	if ( ! in_array( $column, array( 'itsoft_dist_files', 'itsoft_dist_link_mode', 'itsoft_dist_downloads' ), true ) ) {
		return;
	}
	$files    = itsoft_dist_get_files( $post_id );
	$active   = array_filter( $files, static function ( $file ) { return 'active' === $file['status']; } );
	$disabled = count( $files ) - count( $active );

	if ( 'itsoft_dist_files' === $column ) {
		echo esc_html( sprintf( __( '%1$d доступно / %2$d всего', 'itsoft' ), count( $active ), count( $files ) ) );
		if ( $disabled ) {
			echo '<br><span style="color:#996800">' . esc_html( sprintf( __( '%d отключено', 'itsoft' ), $disabled ) ) . '</span>';
		}
		return;
	}

	if ( 'itsoft_dist_link_mode' === $column ) {
		$modes = array_unique( wp_list_pluck( $files, 'link_mode' ) );
		$labels = array_map(
			static function ( $mode ) {
				return 'temporary' === $mode ? __( '24 часа', 'itsoft' ) : __( 'Постоянная', 'itsoft' );
			},
			$modes
		);
		echo $labels ? esc_html( implode( ', ', $labels ) ) : '—';
		return;
	}

	echo esc_html( number_format_i18n( itsoft_dist_get_download_count( $post_id ) ) );
}
add_action( 'manage_distribution_posts_custom_column', 'itsoft_dist_admin_column_content', 10, 2 );

/**
 * Revoke active tokens for a file.
 *
 * @param int $file_id File ID.
 * @return int|false
 */
function itsoft_dist_revoke_file_tokens( $file_id ) {
	global $wpdb;
	return $wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . itsoft_dist_tokens_table() . ' SET revoked_at = %s WHERE file_id = %d AND revoked_at IS NULL', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			current_time( 'mysql', true ),
			(int) $file_id
		)
	);
}

/**
 * Generate a 24-hour bearer token. Plain token is returned once only.
 *
 * @param int $file_id File ID.
 * @param int $user_id Creator.
 * @return array<string,string>|WP_Error
 */
function itsoft_dist_generate_token( $file_id, $user_id ) {
	$file = itsoft_dist_get_file( $file_id );
	if ( ! $file || 'temporary' !== $file['link_mode'] || 'active' !== $file['status'] ) {
		return new WP_Error( 'token_file', __( 'Позиция не готова к защищённой выдаче.', 'itsoft' ) );
	}

	$path = trailingslashit( itsoft_dist_private_dir() ) . ltrim( (string) $file['private_path'], '/\\' );
	if ( ! $file['private_path'] || ! is_file( $path ) ) {
		return new WP_Error( 'token_path', __( 'Закрытый файл не найден.', 'itsoft' ) );
	}

	itsoft_dist_revoke_file_tokens( $file_id );
	$token      = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
	$created_at = time();
	$expires_at = $created_at + DAY_IN_SECONDS;

	global $wpdb;
	$inserted = $wpdb->insert(
		itsoft_dist_tokens_table(),
		array(
			'file_id'    => (int) $file_id,
			'token_hash' => hash( 'sha256', $token ),
			'created_by' => (int) $user_id,
			'created_at' => gmdate( 'Y-m-d H:i:s', $created_at ),
			'expires_at' => gmdate( 'Y-m-d H:i:s', $expires_at ),
		),
		array( '%d', '%s', '%d', '%s', '%s' )
	);
	if ( ! $inserted ) {
		return new WP_Error( 'token_insert', __( 'Не удалось сохранить защищённую ссылку.', 'itsoft' ) );
	}

	return array(
		'url'        => home_url( '/secure-distribution/' . rawurlencode( $token ) . '/' ),
		'expires_at' => wp_date( 'd.m.Y H:i', $expires_at ),
	);
}

/**
 * Redirect back to distribution edit screen.
 *
 * @param int $distribution_id Distribution ID.
 * @return never
 */
function itsoft_dist_redirect_to_edit( $distribution_id ) {
	wp_safe_redirect( admin_url( 'post.php?post=' . (int) $distribution_id . '&action=edit#itsoft-distribution-files' ) );
	exit;
}

/**
 * Handle admin token generation.
 *
 * @return never
 */
function itsoft_dist_admin_generate_token() {
	$file_id = absint( $_GET['file_id'] ?? 0 );
	$file    = itsoft_dist_get_file( $file_id );
	if ( ! $file || ! current_user_can( 'edit_post', (int) $file['distribution_id'] ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'itsoft' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'itsoft_dist_generate_' . $file_id );
	$result = itsoft_dist_generate_token( $file_id, get_current_user_id() );
	if ( is_wp_error( $result ) ) {
		$flash = array( 'type' => 'error', 'message' => esc_html( $result->get_error_message() ) );
	} else {
		$flash = array(
			'type'    => 'success',
			'message' => sprintf(
				/* translators: 1: URL, 2: expiry date. */
				__( 'Новая ссылка (показывается только сейчас): <input type="text" readonly value="%1$s" onclick="this.select()" style="width:100%%;margin:8px 0"> Действует до %2$s.', 'itsoft' ),
				esc_attr( $result['url'] ),
				esc_html( $result['expires_at'] )
			),
		);
	}
	set_transient( 'itsoft_dist_admin_flash_' . get_current_user_id(), $flash, 10 * MINUTE_IN_SECONDS );
	itsoft_dist_redirect_to_edit( (int) $file['distribution_id'] );
}
add_action( 'admin_post_itsoft_dist_generate_token', 'itsoft_dist_admin_generate_token' );

/**
 * Handle token revocation.
 *
 * @return never
 */
function itsoft_dist_admin_revoke_tokens() {
	$file_id = absint( $_GET['file_id'] ?? 0 );
	$file    = itsoft_dist_get_file( $file_id );
	if ( ! $file || ! current_user_can( 'edit_post', (int) $file['distribution_id'] ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'itsoft' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'itsoft_dist_revoke_' . $file_id );
	itsoft_dist_revoke_file_tokens( $file_id );
	set_transient( 'itsoft_dist_admin_flash_' . get_current_user_id(), array( 'type' => 'success', 'message' => __( 'Активные ссылки отозваны.', 'itsoft' ) ), MINUTE_IN_SECONDS );
	itsoft_dist_redirect_to_edit( (int) $file['distribution_id'] );
}
add_action( 'admin_post_itsoft_dist_revoke_tokens', 'itsoft_dist_admin_revoke_tokens' );

/**
 * Check one permanent URL and save diagnostics.
 *
 * @param int $file_id File ID.
 * @return array<string,mixed>|WP_Error
 */
function itsoft_dist_check_file( $file_id ) {
	$file = itsoft_dist_get_file( $file_id );
	if ( ! $file || 'permanent' !== $file['link_mode'] || ! $file['permanent_url'] ) {
		return new WP_Error( 'check_file', __( 'У позиции нет постоянного URL.', 'itsoft' ) );
	}

	$response = wp_remote_head(
		$file['permanent_url'],
		array(
			'timeout'     => 12,
			'redirection' => 5,
			'user-agent'  => 'ITSOFT-LinkChecker/1.0; ' . home_url( '/' ),
		)
	);
	$code  = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$error = is_wp_error( $response ) ? $response->get_error_message() : '';
	if ( ! $error && ( $code < 200 || $code >= 400 ) ) {
		$error = sprintf( __( 'Удалённый сервер вернул HTTP %d.', 'itsoft' ), $code );
	}

	global $wpdb;
	$wpdb->update(
		itsoft_dist_files_table(),
		array(
			'last_checked_at'  => current_time( 'mysql', true ),
			'last_http_status' => $code,
			'last_error'       => $error,
			'updated_at'       => current_time( 'mysql', true ),
		),
		array( 'id' => (int) $file_id ),
		array( '%s', '%d', '%s', '%s' ),
		array( '%d' )
	);

	return array( 'code' => $code, 'error' => $error );
}

/**
 * Handle manual URL check.
 *
 * @return never
 */
function itsoft_dist_admin_check_file() {
	$file_id = absint( $_GET['file_id'] ?? 0 );
	$file    = itsoft_dist_get_file( $file_id );
	if ( ! $file || ! current_user_can( 'edit_post', (int) $file['distribution_id'] ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'itsoft' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'itsoft_dist_check_' . $file_id );
	$result = itsoft_dist_check_file( $file_id );
	$ok     = ! is_wp_error( $result ) && empty( $result['error'] );
	$message = is_wp_error( $result ) ? $result->get_error_message() : ( $ok ? sprintf( __( 'Ссылка доступна, HTTP %d.', 'itsoft' ), $result['code'] ) : $result['error'] );
	set_transient( 'itsoft_dist_admin_flash_' . get_current_user_id(), array( 'type' => $ok ? 'success' : 'error', 'message' => esc_html( $message ) ), MINUTE_IN_SECONDS );
	itsoft_dist_redirect_to_edit( (int) $file['distribution_id'] );
}
add_action( 'admin_post_itsoft_dist_check_file', 'itsoft_dist_admin_check_file' );

/**
 * Register daily link audit.
 *
 * @return void
 */
function itsoft_dist_schedule_link_audit() {
	if ( ! wp_next_scheduled( 'itsoft_dist_daily_link_audit' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'itsoft_dist_daily_link_audit' );
	}
}
add_action( 'init', 'itsoft_dist_schedule_link_audit', 40 );

/**
 * Check a small batch to avoid loading the hosting worker for too long.
 *
 * @return void
 */
function itsoft_dist_run_link_audit() {
	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT id FROM " . itsoft_dist_files_table() . " WHERE link_mode = 'permanent' AND status = 'active' ORDER BY COALESCE(last_checked_at,'1970-01-01') ASC LIMIT 5" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	);
	foreach ( $ids as $id ) {
		itsoft_dist_check_file( (int) $id );
	}
}
add_action( 'itsoft_dist_daily_link_audit', 'itsoft_dist_run_link_audit' );

/**
 * Register public download routes.
 *
 * @return void
 */
function itsoft_dist_add_rewrite_rules() {
	add_rewrite_rule( '^get-distribution/([0-9]+)/?$', 'index.php?itsoft_dist_file=$matches[1]', 'top' );
	add_rewrite_rule( '^secure-distribution/([A-Za-z0-9_-]+)/?$', 'index.php?itsoft_dist_token=$matches[1]', 'top' );
	if ( get_option( 'itsoft_dist_rewrite_version' ) !== ITSOFT_DIST_DB_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'itsoft_dist_rewrite_version', ITSOFT_DIST_DB_VERSION, false );
	}
}
add_action( 'init', 'itsoft_dist_add_rewrite_rules', 20 );

/**
 * Add public download query vars.
 *
 * @param array<int,string> $vars Query vars.
 * @return array<int,string>
 */
function itsoft_dist_query_vars( $vars ) {
	$vars[] = 'itsoft_dist_file';
	$vars[] = 'itsoft_dist_token';
	return $vars;
}
add_filter( 'query_vars', 'itsoft_dist_query_vars' );

/**
 * Public stable URL for a permanent row.
 *
 * @param array<string,mixed>|int $file File row or ID.
 * @return string
 */
function itsoft_dist_get_public_url( $file ) {
	$file_id = is_array( $file ) ? (int) $file['id'] : (int) $file;
	return home_url( '/get-distribution/' . $file_id . '/' );
}

/**
 * Atomically increment real download counters.
 *
 * @param int      $file_id  File ID.
 * @param int|null $token_id Optional token ID.
 * @return void
 */
function itsoft_dist_increment_download( $file_id, $token_id = null ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . itsoft_dist_files_table() . ' SET download_count = download_count + 1 WHERE id = %d', (int) $file_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( $token_id ) {
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . itsoft_dist_tokens_table() . ' SET download_count = download_count + 1, last_download_at = %s WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				current_time( 'mysql', true ),
				(int) $token_id
			)
		);
	}
}

/**
 * Render a controlled download error using the site shell.
 *
 * @param int    $status  HTTP status.
 * @param string $title   Heading.
 * @param string $message Message.
 * @return never
 */
function itsoft_dist_render_download_error( $status, $title, $message ) {
	status_header( (int) $status );
	nocache_headers();
	$style_path = get_template_directory() . '/assets/css/distribution-redesign.css';
	wp_enqueue_style(
		'itsoft-distribution-redesign',
		get_template_directory_uri() . '/assets/css/distribution-redesign.css',
		array( 'itsoft-style' ),
		file_exists( $style_path ) ? (string) filemtime( $style_path ) : ITSOFT_DIST_DB_VERSION
	);
	get_header();
	?>
	<main class="distribution-download-error grid-container">
		<div class="distribution-download-error__card">
			<span class="distribution-download-error__code"><?php echo esc_html( (string) $status ); ?></span>
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $message ); ?></p>
			<a class="distribution-download-error__button" href="<?php echo esc_url( home_url( '/download/' ) ); ?>"><?php esc_html_e( 'Вернуться к дистрибутивам', 'itsoft' ); ?></a>
		</div>
	</main>
	<?php
	get_footer();
	exit;
}

/**
 * Stream a private file with basic HTTP Range support.
 *
 * @param string $path     Absolute path.
 * @param string $filename Download filename.
 * @return never
 */
function itsoft_dist_stream_private_file( $path, $filename ) {
	$size  = (int) filesize( $path );
	$start = 0;
	$end   = max( 0, $size - 1 );
	$status = 200;

	if ( isset( $_SERVER['HTTP_RANGE'] ) && preg_match( '/bytes=(\d*)-(\d*)/i', sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ), $matches ) ) {
		if ( '' !== $matches[1] ) {
			$start = min( $end, max( 0, (int) $matches[1] ) );
		}
		if ( '' !== $matches[2] ) {
			$end = min( $end, max( $start, (int) $matches[2] ) );
		}
		$status = 206;
	}

	$length = max( 0, $end - $start + 1 );
	status_header( $status );
	nocache_headers();
	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="' . rawurlencode( sanitize_file_name( $filename ) ) . '"' );
	header( 'Accept-Ranges: bytes' );
	header( 'Content-Length: ' . $length );
	if ( 206 === $status ) {
		header( "Content-Range: bytes {$start}-{$end}/{$size}" );
	}
	header( 'X-Content-Type-Options: nosniff' );

	@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	ignore_user_abort( true );
	$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( false === $handle ) {
		itsoft_dist_render_download_error( 500, __( 'Ошибка скачивания', 'itsoft' ), __( 'Файл временно недоступен.', 'itsoft' ) );
	}
	fseek( $handle, $start );
	$remaining = $length;
	while ( $remaining > 0 && ! feof( $handle ) ) {
		$chunk = fread( $handle, min( 1024 * 1024, $remaining ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		if ( false === $chunk ) {
			break;
		}
		echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$remaining -= strlen( $chunk );
		flush();
	}
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}

/**
 * Resolve permanent and temporary download routes.
 *
 * @return void
 */
function itsoft_dist_handle_download_request() {
	$file_id = absint( get_query_var( 'itsoft_dist_file' ) );
	$token   = (string) get_query_var( 'itsoft_dist_token' );

	if ( $file_id ) {
		$file = itsoft_dist_get_file( $file_id );
		if ( ! $file || 'active' !== $file['status'] || 'permanent' !== $file['link_mode'] || ! $file['permanent_url'] ) {
			itsoft_dist_render_download_error( 404, __( 'Ссылка недоступна', 'itsoft' ), __( 'Эта загрузка отключена или больше не существует.', 'itsoft' ) );
		}
		itsoft_dist_increment_download( $file_id );
		nocache_headers();
		wp_redirect( esc_url_raw( $file['permanent_url'] ), 302, 'ITSOFT Distribution' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	if ( '' === $token ) {
		return;
	}
	if ( ! preg_match( '/^[A-Za-z0-9_-]{40,60}$/', $token ) ) {
		itsoft_dist_render_download_error( 404, __( 'Ссылка недоступна', 'itsoft' ), __( 'Адрес защищённой загрузки некорректен.', 'itsoft' ) );
	}

	global $wpdb;
	$token_row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . itsoft_dist_tokens_table() . ' WHERE token_hash = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			hash( 'sha256', $token )
		),
		ARRAY_A
	);
	if ( ! is_array( $token_row ) ) {
		itsoft_dist_render_download_error( 404, __( 'Ссылка недоступна', 'itsoft' ), __( 'Защищённая ссылка не найдена.', 'itsoft' ) );
	}
	if ( ! empty( $token_row['revoked_at'] ) ) {
		itsoft_dist_render_download_error( 410, __( 'Ссылка отозвана', 'itsoft' ), __( 'Администратор сформировал новую ссылку или закрыл доступ.', 'itsoft' ) );
	}
	if ( strtotime( $token_row['expires_at'] . ' UTC' ) <= time() ) {
		itsoft_dist_render_download_error( 410, __( 'Срок ссылки истёк', 'itsoft' ), __( 'Эта ссылка действовала 24 часа. Запросите новую ссылку у администратора.', 'itsoft' ) );
	}

	$file = itsoft_dist_get_file( (int) $token_row['file_id'] );
	if ( ! $file || 'active' !== $file['status'] || 'temporary' !== $file['link_mode'] || ! $file['private_path'] ) {
		itsoft_dist_render_download_error( 404, __( 'Файл недоступен', 'itsoft' ), __( 'Позиция временно отключена.', 'itsoft' ) );
	}

	$base = realpath( itsoft_dist_private_dir() );
	$path = realpath( trailingslashit( itsoft_dist_private_dir() ) . ltrim( $file['private_path'], '/\\' ) );
	if ( ! $base || ! $path || 0 !== strpos( wp_normalize_path( $path ), trailingslashit( wp_normalize_path( $base ) ) ) || ! is_file( $path ) ) {
		itsoft_dist_render_download_error( 404, __( 'Файл недоступен', 'itsoft' ), __( 'Закрытый файл не найден в хранилище.', 'itsoft' ) );
	}

	itsoft_dist_increment_download( (int) $file['id'], (int) $token_row['id'] );
	itsoft_dist_stream_private_file( $path, $file['original_filename'] ?: basename( $path ) );
}
add_action( 'template_redirect', 'itsoft_dist_handle_download_request', 0 );

/**
 * Human readable file size.
 *
 * @param int $bytes Bytes.
 * @return string
 */
function itsoft_dist_format_size( $bytes ) {
	return $bytes > 0 ? size_format( (int) $bytes, 1 ) : '';
}

/**
 * Aggregate public metadata for a distribution card.
 *
 * @param int $distribution_id Post ID.
 * @return array<string,mixed>
 */
function itsoft_dist_get_summary( $distribution_id ) {
	$files         = itsoft_dist_get_files( $distribution_id, true );
	$languages     = array();
	$architectures = array();
	$formats       = array();
	$sources       = array();
	$temporary     = false;

	foreach ( $files as $file ) {
		if ( $file['language'] ) {
			$languages[] = $file['language'];
		}
		if ( $file['architecture'] ) {
			$architectures[] = $file['architecture'];
		}
		if ( $file['file_format'] ) {
			$formats[] = strtoupper( $file['file_format'] );
		}
		if ( $file['source_name'] ) {
			$sources[] = $file['source_name'];
		}
		$temporary = $temporary || 'temporary' === $file['link_mode'];
	}

	return array(
		'files'         => $files,
		'file_count'    => count( $files ),
		'languages'     => array_values( array_unique( $languages ) ),
		'architectures' => array_values( array_unique( $architectures ) ),
		'formats'       => array_values( array_unique( $formats ) ),
		'sources'       => array_values( array_unique( $sources ) ),
		'temporary'     => $temporary,
		'downloads'     => itsoft_dist_get_download_count( $distribution_id ),
	);
}

/**
 * Count distributions in a download term including descendants.
 *
 * @param int $term_id Term ID.
 * @return int
 */
function itsoft_dist_get_term_count( $term_id ) {
	$query = new WP_Query(
		array(
			'post_type'              => 'distribution',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy'         => 'download',
					'field'            => 'term_id',
					'terms'            => array( (int) $term_id ),
					'include_children' => true,
				),
			),
		)
	);
	$count = (int) $query->found_posts;
	wp_reset_postdata();
	return $count;
}

/**
 * Return a download taxonomy icon URL.
 *
 * @param int $term_id Term ID.
 * @return string
 */
function itsoft_dist_get_term_icon_url( $term_id ) {
	$thumbnail_id = (int) get_term_meta( (int) $term_id, 'thumbnail_id', true );
	if ( $thumbnail_id ) {
		$url = wp_get_attachment_image_url( $thumbnail_id, 'medium' );
		if ( $url ) {
			return $url;
		}
	}
	if ( function_exists( 'get_field' ) ) {
		$image = get_field( 'img', 'download_' . (int) $term_id );
		if ( is_array( $image ) && ! empty( $image['url'] ) ) {
			return (string) $image['url'];
		}
	}
	return '';
}






