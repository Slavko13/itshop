<?php
/**
 * Controller for building single product page contexts.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Itsoft_Product_Single_Controller' ) ) {

	final class Itsoft_Product_Single_Controller {

		/**
		 * Returns a normalized context array for the single product template.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array<string, mixed>
		 */
		public static function get_context( WC_Product $product ) : array {
			$product_card_context = Itsoft_Product_Card_Controller::get_context( $product );

			return array(
				'product_id'   => $product->get_id(),
				'product_card' => $product_card_context,
				'purchase'     => self::prepare_purchase_context( $product ),
				'extra'        => self::prepare_extra_context( $product_card_context ),
				'content'      => self::prepare_content_context( $product ),
				'faq'          => self::prepare_faq_context( $product ),
			);
		}

		/**
		 * Prepares the customer-facing purchase and fulfilment summary.
		 *
		 * Every product in the store is delivered electronically. Older imports
		 * may still have the WooCommerce virtual flag disabled, so presentation
		 * follows the current business model rather than stale product data.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array<string, mixed>
		 */
		private static function prepare_purchase_context( WC_Product $product ) : array {
			$is_virtual = true;
			$terms      = get_the_terms( $product->get_id(), 'product_cat' );
			$category   = '';

			if ( is_array( $terms ) && ! empty( $terms ) ) {
				$category = (string) $terms[0]->name;
			}

			if ( $product->is_in_stock() ) {
				$stock_label = $product->is_on_backorder( 1 ) ? 'Доступно под заказ' : 'В наличии';
				$stock_state = $product->is_on_backorder( 1 ) ? 'backorder' : 'available';
			} else {
				$stock_label = 'Временно нет в наличии';
				$stock_state = 'unavailable';
			}

			$short_description = trim( (string) $product->get_short_description() );

			return array(
				'category'          => $category,
				'sku'               => (string) $product->get_sku(),
				'is_virtual'        => $is_virtual,
				'fulfilment_label'  => 'Электронная лицензия',
				'fulfilment_detail' => 'Ключ придёт на e-mail после подтверждения оплаты.',
				'stock_label'       => $stock_label,
				'stock_state'       => $stock_state,
				'short_description' => '' !== $short_description ? wp_kses_post( wpautop( $short_description ) ) : '',
				'steps'             => array(
					array( 'title' => 'Оформите заказ', 'text' => 'Укажите актуальный e-mail получателя.' ),
					array( 'title' => 'Подтвердите оплату', 'text' => 'Заказ перейдёт в обработку автоматически.' ),
					array( 'title' => 'Получите ключ', 'text' => 'Письмо с лицензией и инструкцией придёт на e-mail.' ),
				),
			);
		}

		/**
		 * Prepares auxiliary data used across extra sections.
		 *
		 * @param array<string, mixed> $product_card_context Product card view model.
		 *
		 * @return array<string, mixed>
		 */
		private static function prepare_extra_context( array $product_card_context ) : array {
			$extra_context = isset( $product_card_context['extra'] ) && is_array( $product_card_context['extra'] )
				? $product_card_context['extra']
				: array();

			$attributes = isset( $extra_context['attributes'] ) && is_array( $extra_context['attributes'] )
				? $extra_context['attributes']
				: array();

			$tags = isset( $extra_context['tags'] ) && is_array( $extra_context['tags'] )
				? array_values(
					array_filter(
						$extra_context['tags'],
						static function ( $tag ) {
							return $tag instanceof WP_Term;
						}
					)
				)
				: array();

			$download = isset( $extra_context['download'] ) && is_array( $extra_context['download'] )
				? $extra_context['download']
				: array();

			$has_tags     = (bool) ( $extra_context['has_tags'] ?? ! empty( $tags ) );
			$has_download = (bool) ( $extra_context['has_download'] ?? ! empty( $download ) );

			$should_render_download_wide = ( ! $has_tags && $has_download );
			$content_wrapper_class       = 'product-extra__content' . ( $should_render_download_wide ? ' wide' : '' );

			return array(
				'attributes'                  => $attributes,
				'tags'                        => $tags,
				'download'                    => $download,
				'has_tags'                    => $has_tags,
				'has_download'                => $has_download,
				'should_render_download_wide' => $should_render_download_wide,
				'show_secondary_download'     => ( $has_tags && $has_download ),
				'content_wrapper_class'       => trim( $content_wrapper_class ),
			);
		}

		/**
		 * Normalizes product description data for the layout partial.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array<string, mixed>
		 */
		private static function prepare_content_context( WC_Product $product ) : array {
			$raw_content = (string) get_post_field( 'post_content', $product->get_id() );
			$has_content = '' !== trim( wp_strip_all_tags( $raw_content ) );

			return array(
				'raw'         => $raw_content,
				'has_content' => $has_content,
				'rendered'    => $has_content ? apply_filters( 'the_content', $raw_content ) : '',
			);
		}

		/**
		 * Collects FAQ items once so front-end markup stays simple.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array<string, mixed>
		 */
		private static function prepare_faq_context( WC_Product $product ) : array {
			$items = array();

			if ( function_exists( 'get_field' ) ) {
				$faq_rows = get_field( 'faq', $product->get_id() );

				if ( is_array( $faq_rows ) ) {
					foreach ( $faq_rows as $faq_row ) {
						$question = isset( $faq_row['q'] ) ? trim( (string) $faq_row['q'] ) : '';
						$answer   = isset( $faq_row['a'] ) ? $faq_row['a'] : '';

						$has_question = '' !== $question;
						$has_answer   = '' !== trim( wp_strip_all_tags( (string) $answer ) );

						if ( ! $has_question && ! $has_answer ) {
							continue;
						}

						$items[] = array(
							'question' => $question,
							'answer'   => (string) $answer,
						);
					}
				}
			}

			return array(
				'items'     => $items,
				'has_items' => ! empty( $items ),
			);
		}
	}
}


