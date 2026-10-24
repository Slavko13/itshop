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
		if ( ! $this->can_view_order_keys( $order ) ) {
			return;
		}

		$assignments = $this->repository->get_assignments_for_order( $order->get_id() );
		if ( ! $assignments ) {
			return;
		}

		$entries = array();
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

			$entries[] = array(
				'product_name' => $item->get_name(),
				'masked'       => (string) $assignment->masked_value,
				'value'        => $key,
			);
		}

		if ( ! $entries ) {
			return;
		}
		?>
		<section class="woocommerce-order-details itsoft-license-keys" aria-labelledby="itsoft-license-keys-title-<?php echo esc_attr( (string) $order->get_id() ); ?>">
			<header class="itsoft-license-keys__header">
				<div>
					<span class="itsoft-license-keys__eyebrow"><?php esc_html_e( 'Заказ выполнен', 'itsoft-license-fulfillment' ); ?></span>
					<h2 id="itsoft-license-keys-title-<?php echo esc_attr( (string) $order->get_id() ); ?>"><?php esc_html_e( 'Лицензионные ключи', 'itsoft-license-fulfillment' ); ?></h2>
				</div>
				<p><?php esc_html_e( 'Ключи скрыты для безопасности. Нажмите «Показать», чтобы открыть значение.', 'itsoft-license-fulfillment' ); ?></p>
			</header>

			<div class="itsoft-license-keys__list">
				<?php foreach ( $entries as $entry ) : ?>
					<article class="itsoft-license-keys__item">
						<div class="itsoft-license-keys__product">
							<span><?php esc_html_e( 'Товар', 'itsoft-license-fulfillment' ); ?></span>
							<strong><?php echo esc_html( $entry['product_name'] ); ?></strong>
						</div>

						<div class="itsoft-license-control" data-license-control>
							<span class="itsoft-license-control__label"><?php esc_html_e( 'Лицензионный ключ', 'itsoft-license-fulfillment' ); ?></span>
							<code class="itsoft-license-control__value" data-license-value data-masked="<?php echo esc_attr( $entry['masked'] ); ?>" data-value="<?php echo esc_attr( $entry['value'] ); ?>"><?php echo esc_html( $entry['masked'] ); ?></code>
							<div class="itsoft-license-control__actions">
								<button type="button" data-license-reveal aria-pressed="false"><?php esc_html_e( 'Показать', 'itsoft-license-fulfillment' ); ?></button>
								<button type="button" data-license-copy><?php esc_html_e( 'Скопировать', 'itsoft-license-fulfillment' ); ?></button>
							</div>
							<span class="itsoft-license-control__feedback" data-license-feedback role="status" aria-live="polite"></span>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	private function can_view_order_keys( WC_Order $order ): bool {
		if ( is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id() ) {
			return true;
		}

		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return false;
		}

		$order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return '' !== $order_key && hash_equals( $order->get_order_key(), $order_key );
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
