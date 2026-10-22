<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Email_License_Key extends WC_Email {
	private ITSoft_LF_Inventory_Repository $repository;
	private array $license_rows = array();

	public function __construct( ITSoft_LF_Inventory_Repository $repository ) {
		$this->repository  = $repository;
		$this->id          = 'itsoft_license_key';
		$this->title       = 'ITSOFT — лицензионные ключи';
		$this->description = 'Отправляется покупателю после назначения ключей заказу.';
		$this->customer_email = true;
		$this->template_html  = 'emails/customer-license-key.php';
		$this->template_plain = 'emails/plain/customer-license-key.php';
		$this->template_base  = ITSOFT_LF_DIR . 'templates/';
		$this->placeholders   = array( '{order_number}' => '' );
		parent::__construct();
	}

	public function get_default_subject(): string {
		return 'Лицензионные ключи для заказа #{order_number}';
	}

	public function get_default_heading(): string {
		return 'Ваши лицензионные ключи';
	}

	public function trigger( int $order_id ): bool {
		$this->setup_locale();
		$this->object = wc_get_order( $order_id );
		if ( ! $this->object ) {
			$this->restore_locale();
			return false;
		}

		$this->recipient = $this->object->get_billing_email();
		$this->placeholders['{order_number}'] = $this->object->get_order_number();
		$this->license_rows = $this->build_rows( $this->object );
		$sent = false;
		if ( $this->is_enabled() && $this->get_recipient() && $this->license_rows ) {
			$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
		return (bool) $sent;
	}

	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'license_rows'  => $this->license_rows,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'license_rows'  => $this->license_rows,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	private function build_rows( WC_Order $order ): array {
		$rows = array();
		foreach ( $this->repository->get_assignments_for_order( $order->get_id() ) as $assignment ) {
			$item = $order->get_item( (int) $assignment->order_item_id );
			if ( ! $item ) {
				continue;
			}
			try {
				$rows[] = array(
					'product_name' => $item->get_name(),
					'key'          => $this->repository->decrypt_assignment( $assignment ),
				);
			} catch ( Throwable $exception ) {
				continue;
			}
		}
		return $rows;
	}
}
