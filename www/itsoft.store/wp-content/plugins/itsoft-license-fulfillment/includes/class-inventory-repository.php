<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Inventory_Repository {
	private ITSoft_LF_Crypto $crypto;

	public function __construct( ITSoft_LF_Crypto $crypto ) {
		$this->crypto = $crypto;
	}

	public function create_pool( string $name, int $actor_user_id ): int {
		global $wpdb;

		$name = sanitize_text_field( $name );
		$slug = sanitize_title( $name );
		if ( '' === $name || '' === $slug ) {
			throw new InvalidArgumentException( 'Pool name is required.' );
		}

		$table = $wpdb->prefix . 'itsoft_license_pools';
		$now   = current_time( 'mysql', true );
		$ok    = $wpdb->insert(
			$table,
			array(
				'name'       => $name,
				'slug'       => $slug,
				'status'     => 'active',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			throw new RuntimeException( 'Unable to create pool. Its name may already exist.' );
		}

		$pool_id = (int) $wpdb->insert_id;
		$this->record_event( 'pool.created', 'success', $actor_user_id, array( 'pool_id' => $pool_id ) );
		return $pool_id;
	}

	/**
	 * @return array{added:int,duplicates:int,invalid:int,errors:int}
	 */
	public function import_individual_keys( int $pool_id, array $raw_keys, int $actor_user_id ): array {
		global $wpdb;

		if ( ! $this->get_pool( $pool_id ) ) {
			throw new InvalidArgumentException( 'Pool does not exist.' );
		}

		$table  = $wpdb->prefix . 'itsoft_license_keys';
		$result = array( 'added' => 0, 'duplicates' => 0, 'invalid' => 0, 'errors' => 0 );
		$seen   = array();
		$now    = current_time( 'mysql', true );

		foreach ( $raw_keys as $raw_key ) {
			$key = $this->crypto->normalize( (string) $raw_key );
			if ( '' === $key || strlen( $key ) > 4096 ) {
				++$result['invalid'];
				continue;
			}

			$fingerprint = $this->crypto->fingerprint( $key );
			if ( isset( $seen[ $fingerprint ] ) ) {
				++$result['duplicates'];
				continue;
			}
			$seen[ $fingerprint ] = true;

			$inserted = $wpdb->insert(
				$table,
				array(
					'pool_id'         => $pool_id,
					'key_ciphertext'  => $this->crypto->encrypt( $key ),
					'key_fingerprint' => $fingerprint,
					'masked_value'    => $this->crypto->mask( $key ),
					'status'          => 'available',
					'created_by'      => $actor_user_id,
					'created_at'      => $now,
					'updated_at'      => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);

			if ( false !== $inserted ) {
				++$result['added'];
			} elseif ( str_contains( strtolower( $wpdb->last_error ), 'duplicate' ) ) {
				++$result['duplicates'];
			} else {
				++$result['errors'];
			}
		}

		$this->record_event(
			'keys.imported',
			$result['errors'] ? 'partial' : 'success',
			$actor_user_id,
			array( 'pool_id' => $pool_id, 'summary' => $result )
		);
		if ( $result['added'] > 0 ) {
			$this->evaluate_pool_stock_level( $pool_id );
		}

		return $result;
	}

	public function get_pool( int $pool_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'itsoft_license_pools';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $pool_id ) );
		return $row ?: null;
	}

	public function list_pools(): array {
		global $wpdb;
		$pools = $wpdb->prefix . 'itsoft_license_pools';
		$keys  = $wpdb->prefix . 'itsoft_license_keys';

		return $wpdb->get_results(
			"SELECT p.*,
				COUNT(k.id) AS total_keys,
				SUM(CASE WHEN k.status = 'available' THEN 1 ELSE 0 END) AS available_keys,
				SUM(CASE WHEN k.status = 'disabled' THEN 1 ELSE 0 END) AS disabled_keys,
				SUM(CASE WHEN k.status = 'assigned' THEN 1 ELSE 0 END) AS assigned_keys,
				(SELECT COUNT(*) FROM {$wpdb->prefix}itsoft_license_product_pools pp WHERE pp.pool_id = p.id) AS mapped_products
			FROM {$pools} p
			LEFT JOIN {$keys} k ON k.pool_id = p.id
			GROUP BY p.id
			ORDER BY p.id DESC"
		);
	}

	public function set_product_mapping( int $product_id, int $pool_id, string $mode, int $actor_user_id ): void {
		global $wpdb;

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		if ( ! $product ) {
			throw new InvalidArgumentException( 'Product does not exist.' );
		}

		$pool = $this->get_pool( $pool_id );
		if ( ! $pool || 'active' !== $pool->status ) {
			throw new InvalidArgumentException( 'An active pool is required.' );
		}

		if ( ! in_array( $mode, array( 'auto', 'manual' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported fulfillment mode.' );
		}

		$table    = $wpdb->prefix . 'itsoft_license_product_pools';
		$existing = $this->get_product_mapping( $product_id );
		if ( $existing && $pool_id === (int) $existing->pool_id && $mode === $existing->fulfillment_mode ) {
			return;
		}
		$now      = current_time( 'mysql', true );
		$data     = array(
			'product_id'       => $product_id,
			'pool_id'          => $pool_id,
			'fulfillment_mode' => $mode,
			'updated_at'       => $now,
		);

		if ( $existing ) {
			$ok = $wpdb->update(
				$table,
				$data,
				array( 'product_id' => $product_id ),
				array( '%d', '%d', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$data['created_at'] = $now;
			$ok                 = $wpdb->insert( $table, $data, array( '%d', '%d', '%s', '%s', '%s' ) );
		}

		if ( false === $ok ) {
			throw new RuntimeException( 'Unable to save product mapping.' );
		}

		$this->record_event(
			'product.mapping_saved',
			'success',
			$actor_user_id,
			array(
				'pool_id' => $pool_id,
				'summary' => array(
					'product_id'    => $product_id,
					'mode'          => $mode,
					'previous_pool' => $existing ? (int) $existing->pool_id : null,
					'previous_mode' => $existing ? $existing->fulfillment_mode : null,
				),
			)
		);
	}

	public function delete_product_mapping( int $product_id, int $actor_user_id ): void {
		global $wpdb;

		$existing = $this->get_product_mapping( $product_id );
		if ( ! $existing ) {
			return;
		}

		$table = $wpdb->prefix . 'itsoft_license_product_pools';
		$ok    = $wpdb->delete( $table, array( 'product_id' => $product_id ), array( '%d' ) );
		if ( false === $ok ) {
			throw new RuntimeException( 'Unable to remove product mapping.' );
		}

		$this->record_event(
			'product.mapping_removed',
			'success',
			$actor_user_id,
			array(
				'pool_id' => (int) $existing->pool_id,
				'summary' => array(
					'product_id' => $product_id,
					'mode'       => $existing->fulfillment_mode,
				),
			)
		);
	}

	public function get_product_mapping( int $product_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'itsoft_license_product_pools';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $product_id ) );
		return $row ?: null;
	}

	/**
	 * Variations inherit their parent mapping unless they have an explicit mapping.
	 */
	public function resolve_product_mapping( int $product_id ): ?object {
		$mapping = $this->get_product_mapping( $product_id );
		if ( $mapping ) {
			$mapping->inherited = false;
			return $mapping;
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		if ( $product && $product->is_type( 'variation' ) ) {
			$mapping = $this->get_product_mapping( (int) $product->get_parent_id() );
			if ( $mapping ) {
				$mapping->inherited = true;
				return $mapping;
			}
		}

		return null;
	}

	public function list_product_mappings(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'itsoft_license_product_pools';
		$pools = $wpdb->prefix . 'itsoft_license_pools';

		return $wpdb->get_results(
			"SELECT pp.product_id, pp.pool_id, pp.fulfillment_mode, pp.updated_at, p.name AS pool_name
			FROM {$table} pp
			INNER JOIN {$pools} p ON p.id = pp.pool_id
			ORDER BY pp.updated_at DESC, pp.product_id DESC"
		);
	}

	/**
	 * Atomically assigns one available key to a specific order item unit.
	 *
	 * @return object|null Existing/new assignment with encrypted key data, or null when the pool is empty.
	 */
	public function claim_key_for_unit( int $pool_id, int $order_id, int $order_item_id, int $unit_index, int $actor_user_id ): ?object {
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			try {
				return $this->claim_key_for_unit_transaction( $pool_id, $order_id, $order_item_id, $unit_index, $actor_user_id );
			} catch ( RuntimeException $exception ) {
				if ( 2 === $attempt || ! $this->is_retryable_transaction_error( $exception ) ) {
					throw $exception;
				}
				usleep( 20000 * ( $attempt + 1 ) + random_int( 0, 20000 ) );
			}
		}
		return null;
	}

	private function claim_key_for_unit_transaction( int $pool_id, int $order_id, int $order_item_id, int $unit_index, int $actor_user_id ): ?object {
		global $wpdb;

		if ( $pool_id < 1 || $order_id < 1 || $order_item_id < 1 || $unit_index < 1 ) {
			throw new InvalidArgumentException( 'Invalid assignment identifiers.' );
		}

		$keys        = $wpdb->prefix . 'itsoft_license_keys';
		$assignments = $wpdb->prefix . 'itsoft_license_assignments';
		$now         = current_time( 'mysql', true );

		$previous_suppress = $wpdb->suppress_errors( true );
		$wpdb->query( 'START TRANSACTION' );
		try {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT a.*, k.key_ciphertext, k.masked_value
					FROM {$assignments} a INNER JOIN {$keys} k ON k.id = a.key_id
					WHERE a.order_item_id = %d AND a.unit_index = %d FOR UPDATE",
					$order_item_id,
					$unit_index
				)
			);
			$this->throw_on_database_error();
			if ( $existing ) {
				$existing->was_existing = true;
				$wpdb->query( 'COMMIT' );
				$wpdb->suppress_errors( $previous_suppress );
				return $existing;
			}

			$key = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, key_ciphertext, masked_value FROM {$keys}
					WHERE pool_id = %d AND status = 'available'
					ORDER BY id ASC LIMIT 1 FOR UPDATE",
					$pool_id
				)
			);
			$this->throw_on_database_error();

			if ( ! $key ) {
				$wpdb->query( 'ROLLBACK' );
				$wpdb->suppress_errors( $previous_suppress );
				$this->record_event(
					'key.claim_failed',
					'empty',
					$actor_user_id,
					array(
						'pool_id'       => $pool_id,
						'order_id'      => $order_id,
						'order_item_id' => $order_item_id,
						'summary'       => array( 'unit_index' => $unit_index ),
					)
				);
				return null;
			}

			$inserted = $wpdb->insert(
				$assignments,
				array(
					'key_id'        => (int) $key->id,
					'pool_id'       => $pool_id,
					'order_id'      => $order_id,
					'order_item_id' => $order_item_id,
					'unit_index'    => $unit_index,
					'status'        => 'assigned',
					'assigned_by'   => $actor_user_id,
					'assigned_at'   => $now,
				),
				array( '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s' )
			);
			if ( false === $inserted ) {
				$this->throw_on_database_error();
				throw new RuntimeException( 'Unable to create key assignment.' );
			}

			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$keys} SET status = 'assigned', assigned_at = %s, updated_at = %s
					WHERE id = %d AND status = 'available'",
					$now,
					$now,
					(int) $key->id
				)
			);
			if ( 1 !== $updated ) {
				$this->throw_on_database_error();
				throw new RuntimeException( 'Unable to claim the selected key.' );
			}

			$assignment_id = (int) $wpdb->insert_id;
			$this->record_event(
				'key.assigned',
				'success',
				$actor_user_id,
				array(
					'key_id'        => (int) $key->id,
					'pool_id'       => $pool_id,
					'order_id'      => $order_id,
					'order_item_id' => $order_item_id,
					'summary'       => array( 'unit_index' => $unit_index ),
				)
			);
			$wpdb->query( 'COMMIT' );
			$wpdb->suppress_errors( $previous_suppress );
			$this->evaluate_pool_stock_level( $pool_id );

			return (object) array(
				'id'             => $assignment_id,
				'key_id'         => (int) $key->id,
				'pool_id'        => $pool_id,
				'order_id'       => $order_id,
				'order_item_id'  => $order_item_id,
				'unit_index'     => $unit_index,
				'status'         => 'assigned',
				'key_ciphertext' => $key->key_ciphertext,
				'masked_value'   => $key->masked_value,
				'was_existing'   => false,
			);
		} catch ( Throwable $exception ) {
			$wpdb->query( 'ROLLBACK' );
			$wpdb->suppress_errors( $previous_suppress );
			throw $exception;
		}
	}

	public function get_assignments_for_item( int $order_item_id ): array {
		global $wpdb;
		$keys        = $wpdb->prefix . 'itsoft_license_keys';
		$assignments = $wpdb->prefix . 'itsoft_license_assignments';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, k.masked_value, k.key_ciphertext
				FROM {$assignments} a INNER JOIN {$keys} k ON k.id = a.key_id
				WHERE a.order_item_id = %d ORDER BY a.unit_index ASC",
				$order_item_id
			)
		);
	}

	public function get_assignments_for_order( int $order_id ): array {
		global $wpdb;
		$keys        = $wpdb->prefix . 'itsoft_license_keys';
		$assignments = $wpdb->prefix . 'itsoft_license_assignments';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, k.masked_value, k.key_ciphertext
				FROM {$assignments} a INNER JOIN {$keys} k ON k.id = a.key_id
				WHERE a.order_id = %d ORDER BY a.order_item_id ASC, a.unit_index ASC",
				$order_id
			)
		);
	}

	public function count_assignments_for_orders( array $order_ids ): int {
		global $wpdb;

		$order_ids = array_values( array_unique( array_filter( array_map( 'absint', $order_ids ) ) ) );
		if ( ! $order_ids ) {
			return 0;
		}

		$assignments  = $wpdb->prefix . 'itsoft_license_assignments';
		$placeholders = implode( ', ', array_fill( 0, count( $order_ids ), '%d' ) );
		$query        = $wpdb->prepare( "SELECT COUNT(*) FROM {$assignments} WHERE order_id IN ({$placeholders})", ...$order_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $wpdb->get_var( $query );
	}

	public function decrypt_assignment( object $assignment ): string {
		if ( empty( $assignment->key_ciphertext ) ) {
			throw new InvalidArgumentException( 'Assignment has no encrypted key.' );
		}
		return $this->crypto->decrypt( $assignment->key_ciphertext );
	}

	public function evaluate_pool_stock_level( int $pool_id ): void {
		global $wpdb;

		$pools = $wpdb->prefix . 'itsoft_license_pools';
		$keys  = $wpdb->prefix . 'itsoft_license_keys';
		$pool  = $this->get_pool( $pool_id );
		if ( ! $pool ) {
			return;
		}

		$available = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$keys} WHERE pool_id = %d AND status = 'available'", $pool_id )
		);
		$level = 0 === $available ? 'empty' : ( 1 === $available ? 'low' : 'normal' );
		if ( $level === $pool->notification_level ) {
			return;
		}

		$changed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$pools} SET notification_level = %s, updated_at = %s WHERE id = %d AND notification_level <> %s",
				$level,
				current_time( 'mysql', true ),
				$pool_id,
				$level
			)
		);
		if ( 1 !== $changed || 'normal' === $level ) {
			return;
		}

		$this->record_event(
			'inventory.' . $level,
			'success',
			0,
			array(
				'pool_id' => $pool_id,
				'summary' => array( 'available' => $available ),
			)
		);
		do_action( 'itsoft_lf_inventory_threshold', $pool_id, $available, $level );
	}

	public function list_recent_keys( int $limit = 50 ): array {
		global $wpdb;
		$keys  = $wpdb->prefix . 'itsoft_license_keys';
		$pools = $wpdb->prefix . 'itsoft_license_pools';
		$limit = max( 1, min( 200, $limit ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT k.id, k.masked_value, k.status, k.created_at, p.name AS pool_name
				FROM {$keys} k INNER JOIN {$pools} p ON p.id = k.pool_id
				ORDER BY k.id DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Returns a safe, masked and paginated inventory view.
	 *
	 * @return array{items:array,total:int,page:int,per_page:int,total_pages:int}
	 */
	public function query_keys( array $filters = array() ): array {
		global $wpdb;

		$keys          = $wpdb->prefix . 'itsoft_license_keys';
		$pools         = $wpdb->prefix . 'itsoft_license_pools';
		$assignments   = $wpdb->prefix . 'itsoft_license_assignments';
		$order_numbers = $wpdb->prefix . 'itsoft_license_order_numbers';
		$pool_id       = isset( $filters['pool_id'] ) ? absint( $filters['pool_id'] ) : 0;
		$status        = isset( $filters['status'] ) ? sanitize_key( (string) $filters['status'] ) : '';
		$search        = isset( $filters['search'] ) ? trim( sanitize_text_field( (string) $filters['search'] ) ) : '';
		$page          = max( 1, isset( $filters['page'] ) ? absint( $filters['page'] ) : 1 );
		$per_page      = isset( $filters['per_page'] ) ? absint( $filters['per_page'] ) : 50;
		$per_page      = in_array( $per_page, array( 20, 50, 100, 200 ), true ) ? $per_page : 50;
		$allowed       = array( 'available', 'disabled', 'assigned' );
		$status        = in_array( $status, $allowed, true ) ? $status : '';
		$date_from     = $this->normalize_filter_date( $filters['date_from'] ?? '' );
		$date_to       = $this->normalize_filter_date( $filters['date_to'] ?? '' );

		$where  = array( '1=1' );
		$params = array();
		if ( $pool_id > 0 ) {
			$where[]  = 'k.pool_id = %d';
			$params[] = $pool_id;
		}
		if ( '' !== $status ) {
			$where[]  = 'k.status = %s';
			$params[] = $status;
		}
		if ( '' !== $date_from ) {
			$where[]  = 'k.created_at >= %s';
			$params[] = $date_from . ' 00:00:00';
		}
		if ( '' !== $date_to ) {
			$where[]  = 'k.created_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			if ( ctype_digit( $search ) ) {
				$where[]  = '(k.id = %d OR k.masked_value LIKE %s)';
				$params[] = (int) $search;
				$params[] = $like;
			} else {
				$where[]  = 'k.masked_value LIKE %s';
				$params[] = $like;
			}
		}

		$from = "FROM {$keys} k
			INNER JOIN {$pools} p ON p.id = k.pool_id
			LEFT JOIN {$assignments} a ON a.key_id = k.id
			LEFT JOIN {$order_numbers} n ON n.order_id = a.order_id
			LEFT JOIN {$wpdb->users} u ON u.ID = k.created_by";
		$sql_where = ' WHERE ' . implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) {$from}{$sql_where}";
		$total     = (int) $wpdb->get_var( $this->prepare_dynamic_query( $count_sql, $params ) );
		$pages     = max( 1, (int) ceil( $total / $per_page ) );
		$page      = min( $page, $pages );
		$offset    = ( $page - 1 ) * $per_page;
		$list_sql  = "SELECT k.id, k.pool_id, k.masked_value, k.status, k.created_by,
			k.created_at, k.updated_at, p.name AS pool_name, u.display_name AS created_by_name,
			a.order_id, a.order_item_id, n.public_number
			{$from}{$sql_where}
			ORDER BY k.id DESC LIMIT %d OFFSET %d";
		$list_params   = array_merge( $params, array( $per_page, $offset ) );
		$items         = $wpdb->get_results( $this->prepare_dynamic_query( $list_sql, $list_params ) );

		return array(
			'items'       => is_array( $items ) ? $items : array(),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $pages,
		);
	}

	/**
	 * Safely moves, disables or restores up to 200 keys.
	 *
	 * @return array{processed:int,changed:int,skipped:int,errors:int}
	 */
	public function manage_keys( array $key_ids, string $operation, int $target_pool_id, int $actor_user_id ): array {
		$operation = sanitize_key( $operation );
		if ( ! in_array( $operation, array( 'move', 'disable', 'restore' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported key operation.' );
		}

		$key_ids = array_values( array_unique( array_filter( array_map( 'absint', $key_ids ) ) ) );
		if ( ! $key_ids || count( $key_ids ) > 200 ) {
			throw new InvalidArgumentException( 'Select between 1 and 200 keys.' );
		}

		if ( 'move' === $operation ) {
			$target_pool = $this->get_pool( $target_pool_id );
			if ( ! $target_pool || 'active' !== $target_pool->status ) {
				throw new InvalidArgumentException( 'An active target pool is required.' );
			}
		}

		$result         = array( 'processed' => count( $key_ids ), 'changed' => 0, 'skipped' => 0, 'errors' => 0 );
		$affected_pools = array();
		foreach ( $key_ids as $key_id ) {
			try {
				$outcome = $this->manage_single_key( $key_id, $operation, $target_pool_id, $actor_user_id );
				if ( 'changed' === $outcome['result'] ) {
					++$result['changed'];
				} else {
					++$result['skipped'];
				}
				foreach ( $outcome['pool_ids'] as $pool_id ) {
					$affected_pools[ (int) $pool_id ] = true;
				}
			} catch ( Throwable $exception ) {
				++$result['errors'];
				$this->record_event(
					'key.' . ( 'disable' === $operation ? 'disabled' : ( 'restore' === $operation ? 'restored' : 'moved' ) ),
					'error',
					$actor_user_id,
					array(
						'key_id'  => $key_id,
						'pool_id' => $target_pool_id > 0 ? $target_pool_id : null,
						'summary' => array( 'reason' => 'operation_failed' ),
					)
				);
			}
		}

		foreach ( array_keys( $affected_pools ) as $pool_id ) {
			$this->evaluate_pool_stock_level( (int) $pool_id );
		}

		return $result;
	}

	/**
	 * @return array{result:string,pool_ids:array}
	 */
	private function manage_single_key( int $key_id, string $operation, int $target_pool_id, int $actor_user_id ): array {
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			try {
				return $this->manage_single_key_transaction( $key_id, $operation, $target_pool_id, $actor_user_id );
			} catch ( RuntimeException $exception ) {
				if ( 2 === $attempt || ! $this->is_retryable_transaction_error( $exception ) ) {
					throw $exception;
				}
				usleep( 20000 * ( $attempt + 1 ) + random_int( 0, 20000 ) );
			}
		}
		return array( 'result' => 'skipped', 'pool_ids' => array() );
	}

	/**
	 * @return array{result:string,pool_ids:array}
	 */
	private function manage_single_key_transaction( int $key_id, string $operation, int $target_pool_id, int $actor_user_id ): array {
		global $wpdb;

		$keys        = $wpdb->prefix . 'itsoft_license_keys';
		$assignments = $wpdb->prefix . 'itsoft_license_assignments';
		$pools       = $wpdb->prefix . 'itsoft_license_pools';
		$event_name  = 'key.' . ( 'disable' === $operation ? 'disabled' : ( 'restore' === $operation ? 'restored' : 'moved' ) );
		$previous_suppress = $wpdb->suppress_errors( true );
		$wpdb->query( 'START TRANSACTION' );
		try {
			$key = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT k.id, k.pool_id, k.status, a.order_id, a.order_item_id
					FROM {$keys} k LEFT JOIN {$assignments} a ON a.key_id = k.id
					WHERE k.id = %d FOR UPDATE",
					$key_id
				)
			);
			$this->throw_on_database_error();
			if ( ! $key ) {
				$this->record_event(
					$event_name,
					'rejected',
					$actor_user_id,
					array( 'key_id' => $key_id, 'summary' => array( 'reason' => 'not_found' ) )
				);
				$wpdb->query( 'COMMIT' );
				$wpdb->suppress_errors( $previous_suppress );
				return array( 'result' => 'skipped', 'pool_ids' => array() );
			}

			$source_pool = (int) $key->pool_id;
			$summary     = array(
				'source_pool'    => $source_pool,
				'target_pool'    => 'move' === $operation ? $target_pool_id : $source_pool,
				'previous_status' => (string) $key->status,
			);
			$context = array(
				'key_id'        => $key_id,
				'pool_id'       => 'move' === $operation ? $target_pool_id : $source_pool,
				'order_id'      => $key->order_id ? (int) $key->order_id : null,
				'order_item_id' => $key->order_item_id ? (int) $key->order_item_id : null,
			);

			if ( 'move' === $operation ) {
				if ( ! in_array( $key->status, array( 'available', 'disabled' ), true ) ) {
					$context['summary'] = $summary + array( 'reason' => 'assigned_key_is_immutable' );
					$this->record_event( $event_name, 'conflict', $actor_user_id, $context );
					$wpdb->query( 'COMMIT' );
					$wpdb->suppress_errors( $previous_suppress );
					return array( 'result' => 'skipped', 'pool_ids' => array( $source_pool ) );
				}
				if ( $source_pool === $target_pool_id ) {
					$wpdb->query( 'COMMIT' );
					$wpdb->suppress_errors( $previous_suppress );
					return array( 'result' => 'skipped', 'pool_ids' => array( $source_pool ) );
				}
				$target_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$pools} WHERE id = %d", $target_pool_id ) );
				$this->throw_on_database_error();
				if ( 'active' !== $target_status ) {
					$context['summary'] = $summary + array( 'reason' => 'target_pool_is_not_active' );
					$this->record_event( $event_name, 'rejected', $actor_user_id, $context );
					$wpdb->query( 'COMMIT' );
					$wpdb->suppress_errors( $previous_suppress );
					return array( 'result' => 'skipped', 'pool_ids' => array( $source_pool ) );
				}
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$keys} SET pool_id = %d, updated_at = %s WHERE id = %d AND status IN ('available','disabled')",
						$target_pool_id,
						current_time( 'mysql', true ),
						$key_id
					)
				);
				$summary['new_status'] = (string) $key->status;
			} elseif ( 'disable' === $operation ) {
				if ( 'available' !== $key->status ) {
					$context['summary'] = $summary + array( 'reason' => 'status_must_be_available' );
					$this->record_event( $event_name, 'assigned' === $key->status ? 'conflict' : 'rejected', $actor_user_id, $context );
					$wpdb->query( 'COMMIT' );
					$wpdb->suppress_errors( $previous_suppress );
					return array( 'result' => 'skipped', 'pool_ids' => array( $source_pool ) );
				}
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$keys} SET status = 'disabled', updated_at = %s WHERE id = %d AND status = 'available'",
						current_time( 'mysql', true ),
						$key_id
					)
				);
				$summary['new_status'] = 'disabled';
			} else {
				if ( 'disabled' !== $key->status ) {
					$context['summary'] = $summary + array( 'reason' => 'status_must_be_disabled' );
					$this->record_event( $event_name, 'assigned' === $key->status ? 'conflict' : 'rejected', $actor_user_id, $context );
					$wpdb->query( 'COMMIT' );
					$wpdb->suppress_errors( $previous_suppress );
					return array( 'result' => 'skipped', 'pool_ids' => array( $source_pool ) );
				}
				$source_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$pools} WHERE id = %d", $source_pool ) );
				$this->throw_on_database_error();
				if ( 'active' !== $source_status ) {
					$context['summary'] = $summary + array( 'reason' => 'source_pool_is_not_active' );
					$this->record_event( $event_name, 'rejected', $actor_user_id, $context );
					$wpdb->query( 'COMMIT' );
					$wpdb->suppress_errors( $previous_suppress );
					return array( 'result' => 'skipped', 'pool_ids' => array( $source_pool ) );
				}
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$keys} SET status = 'available', updated_at = %s WHERE id = %d AND status = 'disabled'",
						current_time( 'mysql', true ),
						$key_id
					)
				);
				$summary['new_status'] = 'available';
			}

			if ( 1 !== $updated ) {
				$this->throw_on_database_error();
				throw new RuntimeException( 'The selected key changed during the operation.' );
			}
			$context['summary'] = $summary;
			$this->record_event( $event_name, 'success', $actor_user_id, $context );
			$wpdb->query( 'COMMIT' );
			$wpdb->suppress_errors( $previous_suppress );
			return array(
				'result'   => 'changed',
				'pool_ids' => array_values( array_unique( array( $source_pool, 'move' === $operation ? $target_pool_id : $source_pool ) ) ),
			);
		} catch ( Throwable $exception ) {
			$wpdb->query( 'ROLLBACK' );
			$wpdb->suppress_errors( $previous_suppress );
			throw $exception;
		}
	}

	private function normalize_filter_date( $value ): string {
		$value = sanitize_text_field( (string) $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	private function prepare_dynamic_query( string $query, array $params ): string {
		global $wpdb;
		return $params ? $wpdb->prepare( $query, ...$params ) : $query;
	}

	private function throw_on_database_error(): void {
		global $wpdb;
		if ( '' !== (string) $wpdb->last_error ) {
			throw new RuntimeException( 'Database transaction error: ' . $wpdb->last_error );
		}
	}

	private function is_retryable_transaction_error( Throwable $exception ): bool {
		$message = strtolower( $exception->getMessage() );
		return str_contains( $message, 'deadlock' ) || str_contains( $message, 'lock wait timeout' );
	}

	private function record_event( string $operation, string $result, int $actor_user_id, array $context ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'itsoft_license_events';

		$wpdb->insert(
			$table,
			array(
				'operation'     => $operation,
				'result'        => $result,
				'key_id'        => $context['key_id'] ?? null,
				'pool_id'       => $context['pool_id'] ?? null,
				'order_id'      => $context['order_id'] ?? null,
				'order_item_id' => $context['order_item_id'] ?? null,
				'actor_user_id' => $actor_user_id,
				'context_json'  => wp_json_encode( $context['summary'] ?? array() ),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
	}
}
