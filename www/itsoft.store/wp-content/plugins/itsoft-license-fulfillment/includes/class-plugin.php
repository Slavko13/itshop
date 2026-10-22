<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Plugin {
	private static ?self $instance = null;
	private bool $booted = false;
	private ?ITSoft_LF_Crypto $crypto = null;
	private ?ITSoft_LF_Inventory_Repository $repository = null;
	private ?ITSoft_LF_Order_Number_Service $order_numbers = null;
	private ?ITSoft_LF_Order_Service $orders = null;
	private ?ITSoft_LF_Order_Communication $communication = null;
	private ?ITSoft_LF_Customer_Account $customer_account = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		ITSoft_LF_Installer::maybe_migrate();
		$this->crypto     = new ITSoft_LF_Crypto();
		$this->repository = new ITSoft_LF_Inventory_Repository( $this->crypto );
		$this->order_numbers = new ITSoft_LF_Order_Number_Service();
		$this->orders        = new ITSoft_LF_Order_Service( $this->repository, $this->order_numbers );
		$this->communication = new ITSoft_LF_Order_Communication( $this->repository );
		$this->customer_account = new ITSoft_LF_Customer_Account( $this->repository );
		$this->order_numbers->register_hooks();
		$this->orders->register_hooks();
		$this->communication->register_hooks();
		$this->customer_account->register_hooks();
		ITSoft_LF_Mock_Payment::register_hooks();
		( new ITSoft_LF_Checkout() )->register_hooks();
		( new ITSoft_LF_Cart() )->register_hooks();
		( new ITSoft_LF_Search() )->register_hooks();

		if ( is_admin() ) {
			( new ITSoft_LF_Admin( $this->repository, $this->crypto ) )->register_hooks();
			( new ITSoft_LF_Key_Admin( $this->repository ) )->register_hooks();
			( new ITSoft_LF_Product_Admin( $this->repository ) )->register_hooks();
			( new ITSoft_LF_Order_Admin( $this->repository, $this->orders, $this->order_numbers, $this->communication ) )->register_hooks();
		}
	}

	public function crypto(): ITSoft_LF_Crypto {
		if ( null === $this->crypto ) {
			$this->boot();
		}
		return $this->crypto;
	}

	public function repository(): ITSoft_LF_Inventory_Repository {
		if ( null === $this->repository ) {
			$this->boot();
		}
		return $this->repository;
	}

	public function order_numbers(): ITSoft_LF_Order_Number_Service {
		if ( null === $this->order_numbers ) {
			$this->boot();
		}
		return $this->order_numbers;
	}

	public function orders(): ITSoft_LF_Order_Service {
		if ( null === $this->orders ) {
			$this->boot();
		}
		return $this->orders;
	}

	public function communication(): ITSoft_LF_Order_Communication {
		if ( null === $this->communication ) {
			$this->boot();
		}
		return $this->communication;
	}
}
