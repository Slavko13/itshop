<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Order_Service {
	public const ITEM_STATUS_META  = '_itsoft_lf_issue_status';
	public const ORDER_STATUS_META = '_itsoft_lf_issue_status';

	private ITSoft_LF_Inventory_Repository $repository;
	private ITSoft_LF_Order_Number_Service $order_numbers;

	public function __construct( ITSoft_LF_Inventory_Repository $repository, ITSoft_LF_Order_Number_Service $order_numbers ) {
		$this->repository    = $repository;
		$this->order_numbers = $order_numbers;
	}

	public function register_hooks(): void {
		add_action( 'woocommerce_checkout_order_created', array( $this, 'initialize_order' ), 20 );
		add_action( 'woocommerce_payment_complete', array( $this, 'handle_payment_complete' ), 20 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'mark_order_paid' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'mark_order_paid' ), 20 );
		add_filter( 'woocommerce_product_is_in_stock', array( $this, 'mapped_product_is_in_stock' ), 20, 2 );
		add_filter( 'woocommerce_product_backorders_allowed', array( $this, 'mapped_product_allows_backorders' ), 20, 3 );
		add_filter( 'woocommerce_product_backorders_require_notification', array( $this, 'mapped_product_backorder_notification' ), 20, 2 );
	}

	public function initialize_order( WC_Order $order ): void {
		$this->order_numbers->get_or_create( $order );
		$this->sync_issue_status( $order );
	}

	public function mark_order_paid( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->sync_issue_status( $order );
		}
	}

	public function handle_payment_complete( int $order_id ): void {
		$this->mark_order_paid( $order_id );
		if ( 'yes' === get_option( 'itsoft_lf_auto_fulfillment_enabled', 'no' ) ) {
			$this->automatically_fulfill_order( $order_id );
		}
	}

	/**
	 * @return array{assigned:int,existing:int,missing:int,status:string}
	 */
	public function automatically_fulfill_order( int $order_id ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() ) {
			throw new RuntimeException( 'Order payment must be confirmed before automatic fulfillment.' );
		}

		$total = array( 'assigned' => 0, 'existing' => 0, 'missing' => 0, 'status' => 'not_applicable' );
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$mapping = $this->mapping_for_item( $item );
			if ( ! $mapping || 'auto' !== $mapping->fulfillment_mode ) {
				continue;
			}
			$result = $this->manually_assign_item( $order_id, (int) $item_id, 0, false );
			$total['assigned'] += $result['assigned'];
			$total['existing'] += $result['existing'];
			$total['missing']  += $result['missing'];
		}

		$total['status'] = $this->sync_issue_status( $order );
		if ( $total['assigned'] > 0 ) {
			$order->add_order_note( sprintf( 'ITSOFT: автоматическая выдача назначила ключей: %d.', $total['assigned'] ) );
			do_action( 'itsoft_lf_keys_assigned', $order_id, 0, $total['assigned'] );
		}
		return $total;
	}

	/**
	 * One individual key is assigned for each purchased unit.
	 *
	 * @return array{assigned:int,existing:int,missing:int,status:string}
	 */
	public function manually_assign_item( int $order_id, int $order_item_id, int $actor_user_id, bool $notify = true ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() ) {
			throw new RuntimeException( 'Order payment must be confirmed before assigning a key.' );
		}

		$item = $order->get_item( $order_item_id );
		if ( ! $item instanceof WC_Order_Item_Product ) {
			throw new InvalidArgumentException( 'Order item does not exist.' );
		}

		$mapping = $this->mapping_for_item( $item );
		if ( ! $mapping ) {
			throw new RuntimeException( 'The order item has no license pool mapping.' );
		}

		$quantity = max( 1, (int) $item->get_quantity() );
		$result   = array( 'assigned' => 0, 'existing' => 0, 'missing' => 0, 'status' => 'waiting_key' );
		for ( $unit = 1; $unit <= $quantity; ++$unit ) {
			$assignment = $this->repository->claim_key_for_unit(
				(int) $mapping->pool_id,
				$order_id,
				$order_item_id,
				$unit,
				$actor_user_id
			);
			if ( ! $assignment ) {
				$result['missing'] = $quantity - $unit + 1;
				break;
			}
			if ( ! empty( $assignment->was_existing ) ) {
				++$result['existing'];
			} else {
				++$result['assigned'];
			}
		}

		$result['status'] = $this->sync_issue_status( $order );
		if ( $result['assigned'] > 0 ) {
			$order->add_order_note( sprintf( 'ITSOFT: назначено ключей: %d; позиция #%d.', $result['assigned'], $order_item_id ) );
			if ( $notify ) {
				do_action( 'itsoft_lf_keys_assigned', $order_id, $order_item_id, $result['assigned'] );
			}
		}
		return $result;
	}

	public function sync_issue_status( WC_Order $order ): string {
		$tracked_statuses = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$mapping = $this->mapping_for_item( $item );
			if ( ! $mapping ) {
				continue;
			}

			$quantity         = max( 1, (int) $item->get_quantity() );
			$assignment_count = count( $this->repository->get_assignments_for_item( $item->get_id() ) );
			if ( $assignment_count >= $quantity ) {
				$status = 'issued';
			} elseif ( $assignment_count > 0 ) {
				$status = 'partially_issued';
			} elseif ( $order->is_paid() ) {
				$status = 'waiting_key';
			} else {
				$status = 'pending_payment';
			}

			if ( $status !== $item->get_meta( self::ITEM_STATUS_META, true ) ) {
				$item->update_meta_data( self::ITEM_STATUS_META, $status );
				$item->save();
			}
			$tracked_statuses[] = $status;
		}

		$aggregate = $this->aggregate_status( $tracked_statuses );
		if ( $aggregate !== $order->get_meta( self::ORDER_STATUS_META, true ) ) {
			$order->update_meta_data( self::ORDER_STATUS_META, $aggregate );
			$order->save_meta_data();
		}
		return $aggregate;
	}

	public static function status_label( string $status ): string {
		$labels = array(
			'not_applicable'   => 'Не требуется',
			'pending_payment'  => 'Ожидает оплаты',
			'waiting_key'      => 'Ожидает ключ',
			'partially_issued' => 'Выдан частично',
			'issued'           => 'Ключ выдан',
		);
		return $labels[ $status ] ?? $status;
	}

	public function mapping_for_item( WC_Order_Item_Product $item ): ?object {
		$product_id = $item->get_variation_id() ?: $item->get_product_id();
		return $this->repository->resolve_product_mapping( (int) $product_id );
	}

	public function mapped_product_is_in_stock( bool $in_stock, WC_Product $product ): bool {
		return $this->repository->resolve_product_mapping( $product->get_id() ) ? true : $in_stock;
	}

	public function mapped_product_allows_backorders( bool $allowed, int $product_id, WC_Product $product ): bool {
		return $this->repository->resolve_product_mapping( $product_id ) ? true : $allowed;
	}

	public function mapped_product_backorder_notification( bool $notify, WC_Product $product ): bool {
		return $this->repository->resolve_product_mapping( $product->get_id() ) ? false : $notify;
	}

	private function aggregate_status( array $statuses ): string {
		if ( ! $statuses ) {
			return 'not_applicable';
		}
		if ( count( array_unique( $statuses ) ) === 1 ) {
			return $statuses[0];
		}
		if ( in_array( 'partially_issued', $statuses, true ) || in_array( 'issued', $statuses, true ) ) {
			return 'partially_issued';
		}
		if ( in_array( 'waiting_key', $statuses, true ) ) {
			return 'waiting_key';
		}
		return 'pending_payment';
	}
}
