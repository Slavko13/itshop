<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Itsoft_Tg_Bot_Api {
    private $token;
    private $logger;
    private $api_url = 'https://api.telegram.org/bot';

    public function __construct( $token, $logger ) {
        $this->token = $token;
        $this->logger = $logger;
    }

    public function request( $method, $params = [] ) {
        $url = $this->api_url . $this->token . '/' . $method;

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query( $params ),
                'timeout' => 10,
                'ignore_errors' => true // To capture error response
            ]
        ];

        $context = stream_context_create( $options );
        $attempts = 0;
        $max_attempts = 5;
        $result = false;

        while ( $attempts < $max_attempts ) {
            $attempts++;
            // Suppress warnings for file_get_contents
            $result = @file_get_contents( $url, false, $context );

            if ( $result !== false ) {
                // Check if it's a valid JSON and 'ok' is true
                $json = json_decode( $result, true );
                if ( isset( $json['ok'] ) && $json['ok'] ) {
                    return $json;
                } else {
                     // API returned an error (e.g., 400 Bad Request)
                     $error_msg = isset($json['description']) ? $json['description'] : 'Unknown API error';
                     $this->logger->log( "API Error (Attempt $attempts): $error_msg", 'ERROR' );
                     // Break if it's a client error (4xx) usually no point retrying unless it's rate limit
                     if ( isset($json['error_code']) && $json['error_code'] >= 400 && $json['error_code'] < 500 && $json['error_code'] != 429 ) {
                         break;
                     }
                }
            } else {
                // Network error
                $error = error_get_last();
                $this->logger->log( "Network Error (Attempt $attempts): " . ($error['message'] ?? 'Unknown'), 'WARNING' );
            }

            // Exponential backoff or simple delay: 1s, 2s, 3s...
            if ( $attempts < $max_attempts ) {
                sleep( $attempts ); 
            }
        }

        $this->logger->log( "Failed to execute method $method after $max_attempts attempts", 'ERROR' );
        return false;
    }

    public function sendMessage( $chat_id, $text, $parse_mode = 'HTML' ) {
        return $this->request( 'sendMessage', [
            'chat_id'    => $chat_id,
            'text'       => $text,
            'parse_mode' => $parse_mode
        ] );
    }

    public function setWebhook( $url ) {
        return $this->request( 'setWebhook', [
            'url' => $url
        ] );
    }
}
