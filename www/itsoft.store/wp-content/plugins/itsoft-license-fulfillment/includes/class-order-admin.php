<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Order_Admin {
	private ITSoft_LF_Inventory_Repository $repository;
	private ITSoft_LF_Order_Service $orders;
	private ITSoft_LF_Order_Number_Service $order_numbers;
	private ITSoft_LF_Order_Communication $communication;

	public function __construct( ITSoft_LF_Inventory_Repository $repository, ITSoft_LF_Order_Service $orders, ITSoft_LF_Order_Number_Service $order_numbers, ITSoft_LF_Order_Communication $communication ) {
		$this->repository    = $repository;
		$this->orders        = $orders;
		$this->order_numbers = $order_numbers;
		$this->communication = $communication;
	}

	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'admin_post_itsoft_lf_assign_order_item', array( $this, 'handle_assign' ) );
		add_action( 'admin_post_itsoft_lf_resend_order_keys', array( $this, 'handle_resend' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_hpos_order_column' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_cpt_order_column' ), 25, 2 );
	}

	public function register_meta_box(): void {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box(
				'itsoft-license-fulfillment-order',
				'Лицензионные ключи',
				array( $this, 'render_meta_box' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	public function render_meta_box( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID ?? 0 );
		if ( ! $order ) {
			return;
		}

		$public_number = $this->order_numbers->get_or_create( $order );
		$status        = $this->orders->sync_issue_status( $order );
		echo '<p><strong>Публичный номер:</strong> <code>' . esc_html( $public_number ) . '</code></p>';
		echo '<p><strong>Статус выдачи:</strong> ' . esc_html( ITSoft_LF_Order_Service::status_label( $status ) ) . '</p>';

		$has_mapped_items = false;
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$mapping = $this->orders->mapping_for_item( $item );
			if ( ! $mapping ) {
				continue;
			}
			$has_mapped_items = true;
			$assignments      = $this->repository->get_assignments_for_item( (int) $item_id );
			$item_status      = (string) $item->get_meta( ITSoft_LF_Order_Service::ITEM_STATUS_META, true );

			echo '<hr><p><strong>' . esc_html( $item->get_name() ) . '</strong><br>';
			echo esc_html( ITSoft_LF_Order_Service::status_label( $item_status ) );
			echo ' · ' . esc_html( sprintf( '%d/%d ключей', count( $assignments ), max( 1, (int) $item->get_quantity() ) ) ) . '</p>';
			if ( $assignments ) {
				echo '<ul>';
				foreach ( $assignments as $assignment ) {
					echo '<li><code>' . esc_html( $assignment->masked_value ) . '</code></li>';
				}
				echo '</ul>';
			}

			if ( current_user_can( ITSoft_LF_Installer::CAPABILITY ) && current_user_can( 'manage_woocommerce' ) ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="itsoft_lf_assign_order_item">';
				echo '<input type="hidden" name="order_id" value="' . esc_attr( (string) $order->get_id() ) . '">';
				echo '<input type="hidden" name="order_item_id" value="' . esc_attr( (string) $item_id ) . '">';
				wp_nonce_field( 'itsoft_lf_assign_' . $order->get_id() . '_' . $item_id );
				submit_button( 'Назначить недостающие ключи', 'secondary', 'submit', false, $order->is_paid() ? array() : array( 'disabled' => 'disabled' ) );
				echo '</form>';
				if ( ! $order->is_paid() ) {
					echo '<p class="description">Сначала должна быть подтверждена оплата.</p>';
				}
			}
		}

		if ( ! $has_mapped_items ) {
			echo '<p>В заказе нет товаров, связанных с пулом ключей.</p>';
		} elseif ( $this->repository->get_assignments_for_order( $order->get_id() ) && current_user_can( ITSoft_LF_Installer::CAPABILITY ) && current_user_can( 'manage_woocommerce' ) ) {
			echo '<hr><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="itsoft_lf_resend_order_keys">';
			echo '<input type="hidden" name="order_id" value="' . esc_attr( (string) $order->get_id() ) . '">';
			wp_nonce_field( 'itsoft_lf_resend_' . $order->get_id() );
			echo '<p><label for="itsoft_lf_recipient_email"><strong>Email получателя</strong></label><br>';
			echo '<input type="email" class="widefat" id="itsoft_lf_recipient_email" name="recipient_email" value="' . esc_attr( $order->get_billing_email() ) . '" required></p>';
			submit_button( 'Сохранить email и отправить те же ключи', 'secondary', 'submit', false );
			echo '<p class="description">Новый ключ при повторной отправке не списывается.</p></form>';
		}
	}

	public function handle_assign(): void {
		if ( ! current_user_can( ITSoft_LF_Installer::CAPABILITY ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to assign license keys.', 'itsoft-license-fulfillment' ), 403 );
		}

		$order_id      = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order_item_id = isset( $_POST['order_item_id'] ) ? absint( $_POST['order_item_id'] ) : 0;
		check_admin_referer( 'itsoft_lf_assign_' . $order_id . '_' . $order_item_id );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'itsoft-license-fulfillment' ), 404 );
		}

		try {
			$result = $this->orders->manually_assign_item( $order_id, $order_item_id, get_current_user_id() );
			$args   = array(
				'itsoft_lf_order_result' => 'assigned',
				'assigned'               => $result['assigned'],
				'existing'               => $result['existing'],
				'missing'                => $result['missing'],
			);
		} catch ( Throwable $exception ) {
			$args = array( 'itsoft_lf_order_result' => 'error' );
		}

		wp_safe_redirect( add_query_arg( $args, $order->get_edit_order_url() ) );
		exit;
	}

	public function handle_resend(): void {
		if ( ! current_user_can( ITSoft_LF_Installer::CAPABILITY ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to resend license keys.', 'itsoft-license-fulfillment' ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		check_admin_referer( 'itsoft_lf_resend_' . $order_id );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'itsoft-license-fulfillment' ), 404 );
		}

		$email = isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			$result = 'invalid_email';
		} else {
			$order->set_billing_email( $email );
			$order->save();
			$result = $this->communication->send_order_keys( $order_id, true ) ? 'resent' : 'email_failed';
		}

		wp_safe_redirect( add_query_arg( 'itsoft_lf_order_result', $result, $order->get_edit_order_url() ) );
		exit;
	}

	public function render_notice(): void {
		$result = isset( $_GET['itsoft_lf_order_result'] ) ? sanitize_key( wp_unslash( $_GET['itsoft_lf_order_result'] ) ) : '';
		if ( 'assigned' === $result ) {
			$assigned = isset( $_GET['assigned'] ) ? absint( $_GET['assigned'] ) : 0;
			$existing = isset( $_GET['existing'] ) ? absint( $_GET['existing'] ) : 0;
			$missing  = isset( $_GET['missing'] ) ? absint( $_GET['missing'] ) : 0;
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( 'Назначено: %d; уже было назначено: %d; не хватило: %d.', $assigned, $existing, $missing ) ) . '</p></div>';
		} elseif ( 'error' === $result ) {
			echo '<div class="notice notice-error"><p>Ключи не назначены. Проверьте оплату, связь товара с пулом и остаток.</p></div>';
		} elseif ( 'resent' === $result ) {
			echo '<div class="notice notice-success"><p>Email сохранён; ранее назначенные ключи отправлены повторно без списания новых.</p></div>';
		} elseif ( in_array( $result, array( 'invalid_email', 'email_failed' ), true ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( 'invalid_email' === $result ? 'Укажите корректный email.' : 'Письмо не отправлено. Назначенные ключи сохранены.' ) . '</p></div>';
		}
	}

	public function add_order_column( array $columns ): array {
		$columns['itsoft_lf_issue_status'] = 'Выдача ключа';
		return $columns;
	}

	public function render_hpos_order_column( string $column, WC_Order $order ): void {
		if ( 'itsoft_lf_issue_status' === $column ) {
			echo esc_html( ITSoft_LF_Order_Service::status_label( (string) $order->get_meta( ITSoft_LF_Order_Service::ORDER_STATUS_META, true ) ) );
		}
	}

	public function render_cpt_order_column( string $column, int $post_id ): void {
		if ( 'itsoft_lf_issue_status' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order ) {
			echo esc_html( ITSoft_LF_Order_Service::status_label( (string) $order->get_meta( ITSoft_LF_Order_Service::ORDER_STATUS_META, true ) ) );
		}
	}
}
