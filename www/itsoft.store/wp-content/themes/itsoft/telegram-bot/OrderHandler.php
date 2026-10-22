<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Itsoft_Tg_Order_Handler {
    private $api;
    private $logger;

    public function __construct( $api, $logger ) {
        $this->api = $api;
        $this->logger = $logger;

        // "статус не важен, просто вешаем на hook"
        // woocommerce_new_order triggers when an order is created (admin or checkout)
        // woocommerce_checkout_order_processed triggers specifically after checkout processing
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'process_order' ], 10, 1 );
        add_action( 'woocommerce_new_order', [ $this, 'process_order' ], 10, 1 );
        add_action( 'woocommerce_order_status_processing', [ $this, 'process_order' ], 10, 1 );
    }

    public function process_order( $order_id ) {
        if ( ! $order_id ) {
            return;
        }

        try {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }

            $already_notified = $order->get_meta( '_itsoft_tg_notified', true );
            if ( $already_notified ) {
                $this->logger->log( "Order $order_id already notified, skipping.", 'WARNING' );
                return;
            }

            $message = $this->format_order_message( $order );
            $sent = $this->send_notifications( $message );
            if ( $sent ) {
                $order->update_meta_data( '_itsoft_tg_notified', 1 );
                $order->save();
            } else {
                $this->logger->log( "Order $order_id notification not sent.", 'WARNING' );
            }

        } catch ( Exception $e ) {
            $this->logger->log( "Error processing order $order_id: " . $e->getMessage(), 'ERROR' );
        }
    }

        private function format_order_message( $order ) {
            $order_id = $order->get_id();
            $total = wp_strip_all_tags( $order->get_formatted_order_total() );
            $total = html_entity_decode( $total, ENT_QUOTES, 'UTF-8' );
            $total = str_replace( "\xC2\xA0", ' ', $total );
            $currency = $order->get_currency();
            $payment_method = wp_strip_all_tags( $order->get_payment_method_title() );
            
            // Customer Info
            $first_name = wp_strip_all_tags( $order->get_billing_first_name() );
            $last_name = wp_strip_all_tags( $order->get_billing_last_name() );
            $phone = wp_strip_all_tags( $order->get_billing_phone() );
            $email = wp_strip_all_tags( $order->get_billing_email() );
            $address_raw = $order->get_formatted_billing_address(); // Or shipping if physical goods
            $address = wp_kses( $address_raw, [ 'br' => [] ] );
            $address = preg_replace( '/<br\s*\/?>/i', "\n", $address );
            $address = wp_strip_all_tags( $address );

            $message = "<b>Новый заказ #{$order_id}</b>\n";
            $message .= "💰 Сумма: {$total}\n";
            $message .= "💳 Оплата: {$payment_method}\n\n";

            $message .= "<b>👤 Покупатель:</b>\n";
            $message .= "Имя: {$first_name} {$last_name}\n";
            if ( $phone ) $message .= "Телефон: {$phone}\n";
            if ( $email ) $message .= "Email: {$email}\n";
            if ( $address ) $message .= "Адрес:\n{$address}\n";

            $message .= "\n<b>📦 Товары:</b>\n";

            foreach ( $order->get_items() as $item_id => $item ) {
                $product = $item->get_product();
                $product_name = wp_strip_all_tags( $item->get_name() );
                $quantity = $item->get_quantity();
                $subtotal = wp_strip_all_tags( wc_price( $order->get_item_subtotal( $item, false, true ), [ 'currency' => $currency ] ) );
                $subtotal = html_entity_decode( $subtotal, ENT_QUOTES, 'UTF-8' );
                $subtotal = str_replace( "\xC2\xA0", ' ', $subtotal );
                
                $product_link = $product ? esc_url( $product->get_permalink() ) : '';

                if ( $product_link ) {
                    $message .= "• <a href=\"{$product_link}\">{$product_name}</a> x {$quantity} - {$subtotal}\n";
                } else {
                    $message .= "• {$product_name} x {$quantity} - {$subtotal}\n";
                }
            }
            
            $customer_note = wp_strip_all_tags( $order->get_customer_note() );
            if ( $customer_note ) {
                $message .= "\n📝 Примечание: {$customer_note}\n";
            }

            $allowed_tags = [
                'b' => [],
                'a' => [ 'href' => true ],
            ];

            return wp_kses( $message, $allowed_tags );
        }

    private function send_notifications( $message ) {
        $subscribers = get_option( 'itsoft_tg_subscribers', [] );

        if ( empty( $subscribers ) ) {
            $this->logger->log( "No subscribers found for notification.", 'WARNING' );
            return false;
        }

        $sent_any = false;
        foreach ( $subscribers as $chat_id ) {
            $result = $this->api->sendMessage( $chat_id, $message );
            if ( $result && isset( $result['ok'] ) && $result['ok'] ) {
                $sent_any = true;
            } else {
                $this->logger->log( "Failed to send message to chat_id $chat_id.", 'WARNING' );
            }
        }

        return $sent_any;
    }
}
