<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Order_Communication {
	private ITSoft_LF_Inventory_Repository $repository;

	public function __construct( ITSoft_LF_Inventory_Repository $repository ) {
		$this->repository = $repository;
	}

	public function register_hooks(): void {
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_class' ) );
		add_action( 'itsoft_lf_keys_assigned', array( $this, 'send_after_assignment' ), 10, 3 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_account_keys' ) );
		add_action( 'itsoft_lf_inventory_threshold', array( $this, 'send_inventory_alert' ), 10, 3 );
	}

	public function register_email_class( array $emails ): array {
		if ( ! class_exists( 'ITSoft_LF_Email_License_Key' ) ) {
			require_once ITSOFT_LF_DIR . 'includes/class-email-license-key.php';
		}
		$emails['ITSoft_LF_Email_License_Key'] = new ITSoft_LF_Email_License_Key( $this->repository );
		return $emails;
	}

	public function send_after_assignment( int $order_id, int $order_item_id, int $count ): void {
		$this->send_order_keys( $order_id, false );
	}

	public function send_order_keys( int $order_id, bool $resend ): bool {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! is_email( $order->get_billing_email() ) || ! $this->repository->get_assignments_for_order( $order_id ) ) {
			return false;
		}

		$mailer = WC()->mailer();
		$emails = $mailer->get_emails();
		$email  = $emails['ITSoft_LF_Email_License_Key'] ?? null;
		$sent   = $email instanceof ITSoft_LF_Email_License_Key ? $email->trigger( $order_id ) : false;

		$order->update_meta_data( '_itsoft_lf_key_email_status', $sent ? 'sent' : 'failed' );
		$order->update_meta_data( '_itsoft_lf_key_email_last_attempt', current_time( 'mysql', true ) );
		$order->save_meta_data();
		$order->add_order_note(
			$sent
				? sprintf( 'ITSOFT: %s письмо с назначенными ключами на %s.', $resend ? 'повторно отправлено' : 'отправлено', $order->get_billing_email() )
				: 'ITSOFT: не удалось отправить письмо с ключами.'
		);

		return $sent;
	}

	public function render_account_keys( WC_Order $order ): void {
		if ( ! is_user_logged_in() || (int) $order->get_customer_id() !== get_current_user_id() ) {
			return;
		}

		$assignments = $this->repository->get_assignments_for_order( $order->get_id() );
		if ( ! $assignments ) {
			return;
		}

		echo '<section class="woocommerce-order-details itsoft-license-keys">';
		echo '<h2>Лицензионные ключи</h2>';
		echo '<table class="woocommerce-table shop_table"><thead><tr><th>Товар</th><th>Ключ</th></tr></thead><tbody>';
		foreach ( $assignments as $assignment ) {
			$item = $order->get_item( (int) $assignment->order_item_id );
			if ( ! $item ) {
				continue;
			}
			try {
				$key = $this->repository->decrypt_assignment( $assignment );
			} catch ( Throwable $exception ) {
				continue;
			}
			echo '<tr><td>' . esc_html( $item->get_name() ) . '</td><td><code>' . esc_html( $key ) . '</code></td></tr>';
		}
		echo '</tbody></table></section>';
	}

	public function send_inventory_alert( int $pool_id, int $available, string $level ): void {
		$pool = $this->repository->get_pool( $pool_id );
		if ( ! $pool ) {
			return;
		}
		$recipient = sanitize_email( (string) get_option( 'admin_email' ) );
		if ( ! is_email( $recipient ) ) {
			return;
		}
		$subject = 0 === $available ? 'ITSOFT: в пуле закончились ключи' : 'ITSOFT: в пуле остался 1 ключ';
		$message = sprintf( "Пул: %s\nID: %d\nДоступно ключей: %d\nУровень: %s", $pool->name, $pool_id, $available, $level );
		wp_mail( $recipient, $subject, $message );
	}
}
