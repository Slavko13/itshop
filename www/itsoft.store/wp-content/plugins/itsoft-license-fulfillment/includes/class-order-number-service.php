<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Order_Number_Service {
	private const META_KEY = '_itsoft_lf_public_order_number';

	public function register_hooks(): void {
		add_action( 'woocommerce_checkout_order_created', array( $this, 'initialize_order' ), 5 );
		add_filter( 'woocommerce_order_number', array( $this, 'filter_order_number' ), 10, 2 );
		add_filter( 'woocommerce_order_table_search_query_meta_keys', array( $this, 'add_hpos_search_meta' ) );
		add_filter( 'woocommerce_shop_order_search_fields', array( $this, 'add_cpt_search_meta' ) );
		add_filter( 'woocommerce_cot_shop_order_search_results', array( $this, 'extend_hpos_search' ), 10, 2 );
		add_filter( 'woocommerce_shop_order_search_results', array( $this, 'extend_cpt_search' ), 10, 2 );
	}

	public function initialize_order( WC_Order $order ): void {
		$this->get_or_create( $order );
	}

	public function get_or_create( WC_Order $order ): string {
		global $wpdb;

		if ( 'shop_order' !== $order->get_type() ) {
			return (string) $order->get_id();
		}

		$table  = $wpdb->prefix . 'itsoft_license_order_numbers';
		$number = $wpdb->get_var( $wpdb->prepare( "SELECT public_number FROM {$table} WHERE order_id = %d", $order->get_id() ) );
		if ( $number ) {
			$this->sync_meta( $order, (string) $number );
			return (string) $number;
		}

		for ( $attempt = 0; $attempt < 20; ++$attempt ) {
			$candidate = (string) random_int( 1000000000, 9999999999 );
			$inserted  = $wpdb->insert(
				$table,
				array(
					'order_id'      => $order->get_id(),
					'public_number' => $candidate,
					'created_at'    => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%s' )
			);
			if ( false !== $inserted ) {
				$this->sync_meta( $order, $candidate );
				return $candidate;
			}

			$number = $wpdb->get_var( $wpdb->prepare( "SELECT public_number FROM {$table} WHERE order_id = %d", $order->get_id() ) );
			if ( $number ) {
				$this->sync_meta( $order, (string) $number );
				return (string) $number;
			}
		}

		throw new RuntimeException( 'Unable to allocate a public order number.' );
	}

	public function filter_order_number( string $number, WC_Order $order ): string {
		if ( 'shop_order' !== $order->get_type() || $order->get_id() < 1 ) {
			return $number;
		}
		try {
			return $this->get_or_create( $order );
		} catch ( Throwable $exception ) {
			return $number;
		}
	}

	public function add_hpos_search_meta( array $keys ): array {
		$keys[] = self::META_KEY;
		return array_values( array_unique( $keys ) );
	}

	public function add_cpt_search_meta( array $keys ): array {
		$keys[] = self::META_KEY;
		return array_values( array_unique( $keys ) );
	}

	public function extend_hpos_search( array $order_ids, string $term ): array {
		return $this->extend_search_results( $order_ids, $term );
	}

	public function extend_cpt_search( array $order_ids, string $term ): array {
		return $this->extend_search_results( $order_ids, $term );
	}

	private function extend_search_results( array $order_ids, string $term ): array {
		global $wpdb;

		$term = wc_clean( $term );
		if ( '' === $term ) {
			return $order_ids;
		}

		$numbers = $wpdb->prefix . 'itsoft_license_order_numbers';
		$like    = '%' . $wpdb->esc_like( $term ) . '%';
		$extra   = $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM {$numbers} WHERE public_number LIKE %s", $like ) );

		$items     = $wpdb->prefix . 'woocommerce_order_items';
		$item_meta = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT oi.order_id
				FROM {$items} oi
				LEFT JOIN {$item_meta} oim ON oim.order_item_id = oi.order_item_id
				WHERE oi.order_item_type = 'line_item'
				AND (oi.order_item_name LIKE %s OR (oim.meta_key IN ('_product_id','_variation_id') AND oim.meta_value = %s))",
				$like,
				$term
			)
		);

		return array_values( array_unique( array_map( 'intval', array_merge( $order_ids, $extra, $product_ids ) ) ) );
	}

	private function sync_meta( WC_Order $order, string $number ): void {
		if ( $number === (string) $order->get_meta( self::META_KEY, true ) ) {
			return;
		}
		$order->update_meta_data( self::META_KEY, $number );
		$order->save_meta_data();
	}
}
