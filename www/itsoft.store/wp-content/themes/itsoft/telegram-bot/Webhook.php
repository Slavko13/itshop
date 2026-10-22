<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Itsoft_Tg_Webhook {
    private $api;
    private $logger;

    public function __construct( $api, $logger ) {
        $this->api = $api;
        $this->logger = $logger;

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'itsoft-tg/v1', '/webhook', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_webhook' ],
            'permission_callback' => [ $this, 'verify_telegram_secret' ],
        ] );
    }

    public function verify_telegram_secret( $request ) {
        if (
            ! defined( 'ITSOFT_TG_WEBHOOK_SECRET' ) ||
            ! is_string( ITSOFT_TG_WEBHOOK_SECRET ) ||
            '' === ITSOFT_TG_WEBHOOK_SECRET
        ) {
            return new WP_Error(
                'itsoft_tg_webhook_not_configured',
                'Webhook is not configured.',
                [ 'status' => 503 ]
            );
        }

        $provided_secret = (string) $request->get_header( 'x-telegram-bot-api-secret-token' );

        return '' !== $provided_secret && hash_equals( ITSOFT_TG_WEBHOOK_SECRET, $provided_secret );
    }

    public function handle_webhook( $request ) {
        $params = $request->get_json_params();

        if ( ! is_array( $params ) || ! isset( $params['message'] ) || ! is_array( $params['message'] ) ) {
            return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
        }

        $message = $params['message'];
        $chat_id = isset( $message['chat']['id'] ) ? (int) $message['chat']['id'] : 0;
        $text = isset( $message['text'] ) ? trim( $message['text'] ) : '';

        if ( ! $chat_id ) {
            return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
        }

        if ( $text === '/start' ) {
            $this->api->sendMessage( $chat_id, "Здравствуйте! Введите секретный пароль для доступа к уведомлениям о заказах." );
            return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
        }

        $subscription_password = defined( 'ITSOFT_TG_SUBSCRIPTION_PASSWORD' )
            ? (string) ITSOFT_TG_SUBSCRIPTION_PASSWORD
            : '';

        if ( '' !== $subscription_password && hash_equals( $subscription_password, $text ) ) {
            $this->add_subscriber( $chat_id );
            $this->api->sendMessage( $chat_id, "Пароль принят! Вы подписаны на уведомления о заказах." );
            $this->logger->log( "New subscriber added: $chat_id", 'WARNING' ); // Important event
        } else {
             // Optional: Check if already subscribed
             if ( $this->is_subscribed( $chat_id ) ) {
                 $this->api->sendMessage( $chat_id, "Бот активен. Ожидайте уведомлений." );
             } else {
                 $this->api->sendMessage( $chat_id, "Неверный пароль." );
                 $this->logger->log( "Failed auth attempt from $chat_id", 'WARNING' );
             }
        }

        return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
    }

    private function get_subscribers() {
        return get_option( 'itsoft_tg_subscribers', [] );
    }

    private function add_subscriber( $chat_id ) {
        $subscribers = $this->get_subscribers();
        if ( ! in_array( $chat_id, $subscribers ) ) {
            $subscribers[] = $chat_id;
            update_option( 'itsoft_tg_subscribers', $subscribers );
        }
    }

    private function is_subscribed( $chat_id ) {
        $subscribers = $this->get_subscribers();
        return in_array( $chat_id, $subscribers );
    }
}
