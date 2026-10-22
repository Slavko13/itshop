<?php
/**
 * Telegram Bot Integration for WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
if ( ! defined( 'ITSOFT_TG_LOG_DIR' ) ) {
    define( 'ITSOFT_TG_LOG_DIR', WP_CONTENT_DIR . '/uploads/telegram' );
}

// Autoloader or require classes
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/BotApi.php';
require_once __DIR__ . '/Webhook.php';
require_once __DIR__ . '/OrderHandler.php';
require_once __DIR__ . '/Admin.php';

// Initialize classes
add_action( 'init', function() {
    if ( defined( 'ITSOFT_EXTERNAL_INTEGRATIONS_DISABLED' ) && ITSOFT_EXTERNAL_INTEGRATIONS_DISABLED ) {
        return;
    }

    // Secrets must be supplied by wp-config.php or environment-backed configuration.
    if (
        ! defined( 'ITSOFT_TG_BOT_TOKEN' ) ||
        ! is_string( ITSOFT_TG_BOT_TOKEN ) ||
        '' === trim( ITSOFT_TG_BOT_TOKEN )
    ) {
        return;
    }

    $logger = new Itsoft_Tg_Logger();
    $api = new Itsoft_Tg_Bot_Api( ITSOFT_TG_BOT_TOKEN, $logger );
    
    new Itsoft_Tg_Webhook( $api, $logger );
    new Itsoft_Tg_Order_Handler( $api, $logger );
    new Itsoft_Tg_Admin( $logger );
});
