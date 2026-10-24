<?php
/**
 * Plugin Name: ITSOFT License Fulfillment
 * Description: Secure license pools, individual keys and WooCommerce fulfillment foundation.
 * Version: 0.15.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: ITSOFT
 * Text Domain: itsoft-license-fulfillment
 */

defined( 'ABSPATH' ) || exit;

define( 'ITSOFT_LF_VERSION', '0.15.0' );
define( 'ITSOFT_LF_FILE', __FILE__ );
define( 'ITSOFT_LF_DIR', plugin_dir_path( __FILE__ ) );

require_once ITSOFT_LF_DIR . 'includes/class-installer.php';
require_once ITSOFT_LF_DIR . 'includes/class-crypto.php';
require_once ITSOFT_LF_DIR . 'includes/class-inventory-repository.php';
require_once ITSOFT_LF_DIR . 'includes/class-admin.php';
require_once ITSOFT_LF_DIR . 'includes/class-key-admin.php';
require_once ITSOFT_LF_DIR . 'includes/class-product-admin.php';
require_once ITSOFT_LF_DIR . 'includes/class-order-number-service.php';
require_once ITSOFT_LF_DIR . 'includes/class-order-service.php';
require_once ITSOFT_LF_DIR . 'includes/class-order-communication.php';
require_once ITSOFT_LF_DIR . 'includes/class-order-admin.php';
require_once ITSOFT_LF_DIR . 'includes/class-mock-payment.php';
require_once ITSOFT_LF_DIR . 'includes/class-checkout.php';
require_once ITSOFT_LF_DIR . 'includes/class-cart.php';
require_once ITSOFT_LF_DIR . 'includes/class-search.php';
require_once ITSOFT_LF_DIR . 'includes/class-customer-account.php';
require_once ITSOFT_LF_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'ITSoft_LF_Installer', 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		ITSoft_LF_Plugin::instance()->boot();
	}
);
