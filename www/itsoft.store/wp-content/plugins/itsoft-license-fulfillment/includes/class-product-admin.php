<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Product_Admin {
	private ITSoft_LF_Inventory_Repository $repository;

	public function __construct( ITSoft_LF_Inventory_Repository $repository ) {
		$this->repository = $repository;
	}

	public function register_hooks(): void {
		add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'render_product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_fields' ) );
		add_action( 'woocommerce_variation_options_inventory', array( $this, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'delete_product_mapping' ), 10, 2 );
	}

	public function render_product_fields(): void {
		global $post;
		if ( ! $post ) {
			return;
		}
		$this->render_fields( (int) $post->ID, '' );
	}

	public function render_variation_fields( int $loop, array $variation_data, WP_Post $variation ): void {
		$this->render_fields( (int) $variation->ID, '[' . $loop . ']', $loop );
	}

	public function save_product_fields( WC_Product $product ): void {
		if ( ! $this->can_manage( $product->get_id() ) ) {
			return;
		}
		$this->save_mapping( $product->get_id(), $_POST, null ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies its product nonce before this hook.
	}

	public function save_variation_fields( int $variation_id, int $loop ): void {
		if ( ! $this->can_manage( $variation_id ) ) {
			return;
		}
		$this->save_mapping( $variation_id, $_POST, $loop ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies its product nonce before this hook.
	}

	public function delete_product_mapping( int $post_id, WP_Post $post ): void {
		if ( ! in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			return;
		}
		$this->repository->delete_product_mapping( $post_id, get_current_user_id() );
	}

	private function render_fields( int $product_id, string $name_suffix, ?int $loop = null ): void {
		$mapping = $this->repository->get_product_mapping( $product_id );
		$pools   = $this->repository->list_pools();
		$options = array( '' => ( null === $loop ? '— Не управлять ключами —' : '— Наследовать настройку родительского товара —' ) );
		foreach ( $pools as $pool ) {
			$options[ (string) $pool->id ] = sprintf( '%s (доступно: %d)', $pool->name, (int) $pool->available_keys );
		}

		$wrapper_class = null === $loop ? 'options_group' : 'form-row form-row-full';
		echo '<div class="' . esc_attr( $wrapper_class ) . '">';
		woocommerce_wp_select(
			array(
				'id'          => null === $loop ? 'itsoft_lf_pool_id' : 'itsoft_lf_pool_id_' . $loop,
				'name'        => 'itsoft_lf_pool_id' . $name_suffix,
				'label'       => 'Пул лицензионных ключей',
				'value'       => $mapping ? (string) $mapping->pool_id : '',
				'options'     => $options,
				'description' => 'Один пул можно связать с несколькими товарами и вариациями.',
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_select(
			array(
				'id'          => null === $loop ? 'itsoft_lf_mode' : 'itsoft_lf_mode_' . $loop,
				'name'        => 'itsoft_lf_mode' . $name_suffix,
				'label'       => 'Режим выдачи ключа',
				'value'       => $mapping ? $mapping->fulfillment_mode : 'manual',
				'options'     => array(
					'manual' => 'MANUAL — назначает менеджер',
					'auto'   => 'AUTO — после подтверждённой оплаты',
				),
				'description' => 'AUTO сохраняется как настройка, но начнёт работать только после подключения платёжной итерации.',
				'desc_tip'    => true,
			)
		);
		echo '</div>';
	}

	private function save_mapping( int $product_id, array $source, ?int $loop ): void {
		$raw_pool = $source['itsoft_lf_pool_id'] ?? '';
		$raw_mode = $source['itsoft_lf_mode'] ?? 'manual';
		if ( null !== $loop ) {
			$raw_pool = is_array( $raw_pool ) ? ( $raw_pool[ $loop ] ?? '' ) : '';
			$raw_mode = is_array( $raw_mode ) ? ( $raw_mode[ $loop ] ?? 'manual' ) : 'manual';
		}

		$pool_id = absint( wp_unslash( $raw_pool ) );
		$mode    = sanitize_key( wp_unslash( $raw_mode ) );

		try {
			if ( 0 === $pool_id ) {
				$this->repository->delete_product_mapping( $product_id, get_current_user_id() );
				return;
			}
			$this->repository->set_product_mapping( $product_id, $pool_id, $mode, get_current_user_id() );
		} catch ( Throwable $exception ) {
			if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
				WC_Admin_Meta_Boxes::add_error( 'Не удалось сохранить связь со складом ключей: ' . $exception->getMessage() );
			}
		}
	}

	private function can_manage( int $product_id ): bool {
		return current_user_can( ITSoft_LF_Installer::CAPABILITY ) && current_user_can( 'edit_post', $product_id );
	}
}
