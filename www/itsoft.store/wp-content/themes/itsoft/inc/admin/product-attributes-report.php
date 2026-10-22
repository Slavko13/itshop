<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* На сколько кешировать выборку данных в минутах. */
if ( ! defined( 'SLDS_PRODUCT_ATTRIBUTE_CACHE_TTL' ) ) {
	define( 'SLDS_PRODUCT_ATTRIBUTE_CACHE_TTL', 15 * MINUTE_IN_SECONDS );
}

/**
 * Сколько популярных значений показываем на один ключ характеристики
 */
if ( ! defined( 'SLDS_PRODUCT_ATTRIBUTE_TOP_VALUES_COUNT' ) ) {
	define( 'SLDS_PRODUCT_ATTRIBUTE_TOP_VALUES_COUNT', 40 );
}

/**
 * Количество строк в отчетах
 */
if ( ! defined( 'SLDS_PRODUCT_ATTRIBUTES_REPORT_LIMIT' ) ) {
	define( 'SLDS_PRODUCT_ATTRIBUTES_REPORT_LIMIT', 100 );
}

/**
 * Добавление страницы отчета "Характеристики" в админку
 */
add_action( 'admin_menu', 'slds_register_product_attribute_report_page' );
function slds_register_product_attribute_report_page() {
	$capability = slds_product_attribute_report_capability();

	add_menu_page(
		esc_html__( 'Характеристики товаров', 'slds' ),
		esc_html__( 'Характеристики', 'slds' ),
		$capability,
		'slds-product-attributes',
		'slds_render_product_attribute_report_page',
		'dashicons-clipboard',
		58
	);
}

/**
 * Страница отчета доступна только для пользователей с правами 'manage_woocommerce'
 */
function slds_product_attribute_report_capability() {
	return apply_filters( 'slds_product_attribute_report_capability', 'manage_woocommerce' );
}


/**
 * Рендеринг страницы отчета по inline характеристикам товаров
 */
function slds_render_product_attribute_report_page() {
	$capability = slds_product_attribute_report_capability();

	if ( ! current_user_can( $capability ) ) {
		wp_die(
			esc_html__( 'У вас нет прав для просмотра этой страницы.', 'slds' ),
			esc_html__( 'Доступ запрещён', 'slds' ),
			[
				'response' => 403,
			]
		);
	}

	$force_refresh  = isset( $_GET['refresh'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['refresh'] ) );
	$rows           = slds_get_product_attribute_data( $force_refresh );
	$summary        = slds_process_attribute_keys_summary( $rows );
	$values_summary = slds_process_attribute_values_summary( $rows );

	if ( ! empty( $summary ) ) {
		uksort(
			$summary,
			static function ( $key_a, $key_b ) use ( $summary ) {
				$count_a = (int) $summary[ $key_a ];
				$count_b = (int) $summary[ $key_b ];

				if ( $count_a === $count_b ) {
					return strnatcasecmp( $key_a, $key_b );
				}

				return $count_b <=> $count_a;
			}
		);
	}

	if ( ! empty( $values_summary ) ) {
		uksort(
			$values_summary,
			static function ( $key_a, $key_b ) use ( $values_summary ) {
				$count_a = isset( $values_summary[ $key_a ]['values'][0]['count'] ) ? (int) $values_summary[ $key_a ]['values'][0]['count'] : 0;
				$count_b = isset( $values_summary[ $key_b ]['values'][0]['count'] ) ? (int) $values_summary[ $key_b ]['values'][0]['count'] : 0;


				if ( $count_a === $count_b ) {
					return strnatcasecmp( $key_a, $key_b );
				}

				return $count_b <=> $count_a;
			}
		);
	}

	$products_qty  = count( $rows );

	$generated_at  = current_time( 'timestamp' );
	$refresh_url = esc_url(
		add_query_arg(
			[
				'page'    => 'slds-product-attributes',
				'refresh' => 1,
			]
		)
	);

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Сводка характеристик товаров', 'slds' ); ?></h1>

		<?php 
			$message = wp_kses_post(
			sprintf(
				esc_html__( 'Данные обновлены: %s.', 'slds' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $generated_at ) )
			)
		);
		
		?>

		<?php if ( $message ) : ?>
			<div class="updated notice notice-success is-dismissible">
				<p><?php echo $message; ?></p>
			</div>
		<?php endif; ?>

		<div>
			<a class="button button-primary" href="<?php echo $refresh_url; ?>">
				<?php esc_html_e( 'Обновить данные', 'slds' ); ?>
			</a>
			<span style="margin-left:10px;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d - number of products */
						__( 'Обработано товаров: %d', 'slds' ),
						(int) $products_qty
					)
				);
				?>
			</span>
		</div>

		<h2><?php esc_html_e( 'Количество характеристик в товарах:', 'slds' ); ?></h2>
		<?php if ( empty( $summary ) ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'Характеристики товаров не найдены.', 'slds' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Ключ характеристики', 'slds' ); ?></th>
						<th style="width:180px;"><?php esc_html_e( 'Количество товаров', 'slds' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $summary, 0, SLDS_PRODUCT_ATTRIBUTES_REPORT_LIMIT, true ) as $attribute_key => $products_count ) : ?>
						<tr>
							<td><?php echo esc_html( $attribute_key ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $products_count ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Самые популярные значения у ключей характеристик:', 'slds' ); ?></h2>
		<?php if ( empty( $values_summary ) ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'Значения характеристик не найдены.', 'slds' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Ключ характеристики', 'slds' ); ?></th>
						<th><?php esc_html_e( 'Самые популярные значения', 'slds' ); ?></th>
						<th style="width:180px;"><?php esc_html_e( 'Количество товаров', 'slds' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $values_summary, 0, SLDS_PRODUCT_ATTRIBUTES_REPORT_LIMIT, true ) as $attribute_key => $attribute_data ) : ?>
						<?php $top_values = isset( $attribute_data['values'] ) ? (array) $attribute_data['values'] : []; ?>
						<tr>
							<td><?php echo esc_html( $attribute_key ); ?></td>
							<td>
								<?php if ( empty( $top_values ) ) : ?>
									<em><?php esc_html_e( 'Значения отсутствуют', 'slds' ); ?></em>
								<?php else : ?>
									<?php foreach ( $top_values as $value_row ) : ?>
										<div><?php echo esc_html( $value_row['value'] ); ?></div>
									<?php endforeach; ?>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $top_values ) ) : ?>
									<?php foreach ( $top_values as $value_row ) : ?>
										<div><?php echo esc_html( number_format_i18n( $value_row['count'] ) ); ?></div>
									<?php endforeach; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php

}

/**
 * Получение контента всех опубликованных товаров с кешированием на SLDS_PRODUCT_ATTRIBUTE_CACHE_TTL минут
 */
function slds_get_product_attribute_data( bool $force_refresh = false ): array {
	$cache_key   = 'slds_product_attribute_report';
	$cache_group = 'slds';

	if ( $force_refresh ) {
		delete_transient( $cache_key );
	} else {
		$cached = get_transient( $cache_key );

		if ( false !== $cached && slds_is_product_attribute_cache_fresh( $cached ) ) {
			return isset( $cached['rows'] ) ? $cached['rows'] : [];
		}
	}

	global $wpdb;

	$query = "
		SELECT ID, post_content
		FROM {$wpdb->posts}
		WHERE post_type = 'product'
			AND post_status = 'publish'
			AND post_content != ''
	";

	$rows = $wpdb->get_results( $query, ARRAY_A );

	if ( ! empty( $rows ) ) {
		$rows = array_filter(
			$rows,
			static function ( $row ) {
				return false !== strpos( $row['post_content'], '<ul' );
			}
		);
		$rows = array_values( $rows );
	}

	$data = [
		'rows'         => $rows ? $rows : [],
		'generated_at' => current_time( 'timestamp' ),
	];

	set_transient( $cache_key, $data, SLDS_PRODUCT_ATTRIBUTE_CACHE_TTL );

	return $data['rows'];
}

/**
 * Обработка данных для таблицы ключей характеристик
 */
function slds_process_attribute_keys_summary( array $rows ): array {
	$summary = [];

	if ( ! empty( $rows ) ) {
		foreach ( $rows as $row ) {
			$attributes = slds_parse_product_attributes_from_content( $row['post_content'] );

			if ( empty( $attributes ) ) {
				continue;
			}

			foreach ( array_keys( $attributes ) as $attribute_key ) {
				$attribute_key = slds_product_attribute_normalize_string( $attribute_key );

				if ( '' === $attribute_key ) {
					continue;
				}

				if ( ! isset( $summary[ $attribute_key ] ) ) {
					$summary[ $attribute_key ] = 0;
				}
				$summary[ $attribute_key ]++;
			}
		}
	}

	return $summary;
}

/**
 * Обработка данных для таблицы значений характеристик
 */
function slds_process_attribute_values_summary( array $rows ): array {
	$key_value_counts = [];

	if ( empty( $rows ) ) {
		return [];
	}

	foreach ( $rows as $row ) {
		$attributes = slds_parse_product_attributes_from_content( $row['post_content'] );

		if ( empty( $attributes ) ) {
			continue;
		}

		foreach ( $attributes as $attribute_key => $attribute_values ) {
			$attribute_key = slds_product_attribute_normalize_string( $attribute_key );

			if ( '' === $attribute_key ) {
				continue;
			}

			$values = is_array( $attribute_values ) ? $attribute_values : [ $attribute_values ];

			$values = array_filter(
				array_map(
					static function ( $value ) {
						$value = slds_product_attribute_normalize_string( $value );

						return '' === $value ? null : $value;
					},
					$values
				)
			);

			if ( empty( $values ) ) {
				$values = [ 'Да' ];
			}

			$values = array_values( array_unique( $values ) );

			foreach ( $values as $value ) {

				if ( ! isset( $key_value_counts[ $attribute_key ] ) ) {
					$key_value_counts[ $attribute_key ] = [];
				}

				if ( ! isset( $key_value_counts[ $attribute_key ][ $value ] ) ) {
					$key_value_counts[ $attribute_key ][ $value ] = 0;
				}

				$key_value_counts[ $attribute_key ][ $value ]++;
			}
		}
	}

	$values_summary = [];

	foreach ( $key_value_counts as $attribute_key => $value_counts ) {
		if ( empty( $value_counts ) ) {
			continue;
		}

		uksort(
			$value_counts,
			static function ( $value_a, $value_b ) use ( $value_counts ) {
				$count_a = (int) $value_counts[ $value_a ];
				$count_b = (int) $value_counts[ $value_b ];

				if ( $count_a === $count_b ) {
					return strnatcasecmp( $value_a, $value_b );
				}

				return $count_b <=> $count_a;
			}
		);

		$top_values_limit = max( 1, (int) SLDS_PRODUCT_ATTRIBUTE_TOP_VALUES_COUNT );
		$top_values       = array_slice( $value_counts, 0, $top_values_limit, true );

		if ( empty( $top_values ) ) {
			continue;
		}

		$values_summary[ $attribute_key ] = [
			'values' => array_map(
				static function ( $value, $count ) {
					return [
						'value' => $value,
						'count' => (int) $count,
					];
				},
				array_keys( $top_values ),
				$top_values
			),
		];
	}

	return $values_summary;
}

/**
 * Проверка не просрочился ли кеш на SLDS_PRODUCT_ATTRIBUTE_CACHE_TTL
 */
function slds_is_product_attribute_cache_fresh( $cached ): bool {
	if ( ! is_array( $cached ) ) {
		return false;
	}

	if ( empty( $cached['generated_at'] ) ) {
		return false;
	}

	$generated_at = (int) $cached['generated_at'];

	return ( current_time( 'timestamp' ) - $generated_at ) < SLDS_PRODUCT_ATTRIBUTE_CACHE_TTL;
}


/**
 * Парсинг характеристик из контента товара
 */
function slds_parse_product_attributes_from_content( string $content, bool $use_extended_value_delimiters = false ): array {

	if ( '' === trim( $content ) ) {
		return [];
	}

	$attributes = [];
	$delimiters = [ '/' ];

	if ( $use_extended_value_delimiters ) {
		$delimiters = array_merge(
			$delimiters,
			[
				', ',
				'; ',
			]
		);
	}

	$value_split_pattern = '/(?:' . implode(
		'|',
		array_map(
			static function ( $delimiter ) {
				return preg_quote( $delimiter, '/' );
			},
			$delimiters
		)
	) . ')/';

	if ( ! preg_match_all( '/<li\b[^>]*>(.*?)<\/li>/is', $content, $matches ) ) {
		return [];
	}

	foreach ( $matches[1] as $raw_item ) {
		$text = trim( wp_strip_all_tags( wp_kses_decode_entities( $raw_item ) ) );

		if ( '' === $text ) {
			continue;
		}

		if ( false !== strpos( $text, ':' ) ) {
			[ $key, $value ] = array_map(
				'slds_product_attribute_normalize_string',
				explode( ':', $text, 2 )
			);
		} else {
			$key   = slds_product_attribute_normalize_string( $text );
			$value = 'Да';
		}

		if ( '' === $key ) {
			continue;
		}

		if ( '' === $value ) {
			$value = 'Да';
		}

		$values = [ $value ];

		if ( '' !== $value && preg_match( $value_split_pattern, $value ) ) {
			$split_values = preg_split( $value_split_pattern, $value );

			if ( false !== $split_values ) {
				$values = array_filter( array_map( 'slds_product_attribute_normalize_string', $split_values ) );
			}
		}

		if ( empty( $values ) ) {
			$values = [ $value ];
		}

		if ( isset( $attributes[ $key ] ) ) {
			$attributes[ $key ] = array_merge( $attributes[ $key ], $values );
		} else {
			$attributes[ $key ] = $values;
		}
	}
	
	return $attributes;
}

/**
 * Приводим строку характеристики к единому виду, убирая неразрывные пробелы.
 */
function slds_product_attribute_normalize_string( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = (string) $value;

	static $nb_spaces = null;

	if ( null === $nb_spaces ) {
		$nb_spaces = [
			'&nbsp;',
			html_entity_decode( '&nbsp;', ENT_NOQUOTES, 'UTF-8' ),
			"\xC2\xA0",
		];
	}

	$value = str_replace( $nb_spaces, ' ', $value );

	return trim( $value );
}
