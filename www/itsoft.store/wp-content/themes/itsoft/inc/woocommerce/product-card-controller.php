<?php
/**
 * View-model builder (controller) for the single product card.
 *
 * Centralizes all expensive WooCommerce calls so the partials stay lean.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Itsoft_Product_Card_Controller' ) ) {

	final class Itsoft_Product_Card_Controller {

		/**
		 * Builds a ready-to-use context array for every product card block.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array
		 */
		public static function get_context( WC_Product $product ) : array {
			$charset = get_bloginfo( 'charset' );

			return array(
				'product_id'  => $product->get_id(),
				'is_variable' => $product->is_type( 'variable' ),
				'charset'     => $charset,
				'variations'  => self::prepare_variations( $product, $charset ),
				'prices'      => self::prepare_prices( $product ),
				'rating'      => self::prepare_rating( $product ),
				'cart'        => self::prepare_cart_context( $product ),
				'extra'       => self::prepare_extra_context( $product ),
			);
		}

		/**
		 * Prepares variation data once so it can be reused in every block.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 * @param string     $charset Blog charset for html_entity_decode.
		 *
		 * @return array{items: array<int, array<string, mixed>>, active_id: int|null}
		 */
		private static function prepare_variations( WC_Product $product, string $charset ) : array {
			$linked_product = self::get_linked_product( $product );

			if ( $linked_product ) {
				$primary_item = self::prepare_single_option_item( $product, $charset );
				$linked_item  = self::prepare_single_option_item( $linked_product, $charset );

				if ( $primary_item && $linked_item ) {
					$primary_item['is_linked'] = false;
					$primary_item['label']     = $product->get_name();
					$linked_item['is_linked']  = true;
					$linked_item['label']      = $linked_product->get_name();
					$linked_item['is_active']  = true;
					$linked_item['linked_url'] = get_permalink( $linked_product->get_id() );

					return array(
						'items'      => array( $primary_item, $linked_item ),
						'active_id'  => $primary_item['id'] ?? null,
						'has_linked' => true,
					);
				}
			}

			if ( ! $product instanceof WC_Product_Variable ) {
				return array(
					'items'     => array(),
					'active_id' => null,
				);
			}

			$items = self::build_variation_items( $product, $charset );

			if ( ! $items ) {
				return array(
					'items'     => array(),
					'active_id' => null,
				);
			}

			$default_attributes  = $product->get_default_attributes();
			$active_variation_id = null;

			if ( ! empty( $default_attributes ) ) {
				foreach ( $items as $item ) {
					$matches_defaults = true;

					foreach ( $default_attributes as $attribute_slug => $default_value ) {
						if ( ! isset( $item['attributes'][ $attribute_slug ] ) || $item['attributes'][ $attribute_slug ] !== $default_value ) {
							$matches_defaults = false;
							break;
						}
					}

					if ( $matches_defaults ) {
						$active_variation_id = $item['id'];
						break;
					}
				}
			}

			if ( ! $active_variation_id ) {
				$active_variation_id = $items[0]['id'];
			}

			return array(
				'items'     => $items,
				'active_id' => $active_variation_id,
			);
		}

		private static function get_linked_product( WC_Product $product ) : ?WC_Product {
			if ( ! function_exists( 'get_field' ) ) {
				return null;
			}

			$linked_value = get_field( 'product_obj', $product->get_id() );

			if ( ! $linked_value ) {
				return null;
			}

			if ( $linked_value instanceof WC_Product ) {
				return $linked_value;
			}

			$linked_id = 0;

			if ( $linked_value instanceof WP_Post ) {
				$linked_id = (int) $linked_value->ID;
			} else {
				$linked_id = (int) $linked_value;
			}

			if ( ! $linked_id || $linked_id === $product->get_id() ) {
				return null;
			}

			$linked_product = wc_get_product( $linked_id );

			return $linked_product instanceof WC_Product ? $linked_product : null;
		}

		private static function prepare_single_option_item( WC_Product $product, string $charset ) : ?array {
			if ( $product instanceof WC_Product_Variable ) {
				$items = self::build_variation_items( $product, $charset );
				return $items ? $items[0] : null;
			}

			return self::build_simple_option_item( $product, $charset );
		}

		private static function build_variation_items( WC_Product_Variable $product, string $charset ) : array {
			$items = array();

			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );

				if ( ! $variation instanceof WC_Product_Variation ) {
					continue;
				}

				if ( ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
					continue;
				}

				$attributes       = array();
				$attribute_values = array();

				foreach ( $variation->get_attributes() as $attribute_key => $attribute_value ) {
					$attribute_slug = str_replace( 'attribute_', '', $attribute_key );
					$taxonomy       = $attribute_slug;
					$display_value  = $attribute_value;

					if ( taxonomy_exists( $taxonomy ) ) {
						$term          = get_term_by( 'slug', $attribute_value, $taxonomy );
						$display_value = ( $term && ! is_wp_error( $term ) ) ? $term->name : $attribute_value;
					}

					$attributes[ $attribute_slug ]  = $attribute_value;
					$attribute_values[ $attribute_slug ] = $display_value;
				}

				$option_label_values = array();
				foreach ( $attribute_values as $k => $v ) {
					if ( strpos( $k, 'attr_' ) !== 0 ) {
						$option_label_values[] = $v;
					}
				}
				$option_label = $option_label_values ? implode( ', ', array_filter( $option_label_values ) ) : $variation->get_name();

				// Re-loop to set display keys for JS
				foreach ( $attribute_values as $k => $v ) {
					if ( strpos( $k, 'attr_' ) === 0 ) {
						continue;
					}
					$label = wc_attribute_label( $k, $product );
					$match_key = 'attr_' . md5( $label );
					$attribute_values[ $match_key ] = $v;
				}

				$current_price_raw = (float) wc_get_price_to_display( $variation );

				$regular_price_value = $variation->get_regular_price();
				$regular_price_raw   = '' === $regular_price_value
					? 0.0
					: (float) wc_get_price_to_display(
						$variation,
						array(
							'price' => $regular_price_value,
						)
					);

				$sale_price_value = $variation->get_sale_price();
				$sale_price_raw   = '' === $sale_price_value
					? 0.0
					: (float) wc_get_price_to_display(
						$variation,
						array(
							'price' => $sale_price_value,
						)
					);

				if ( $current_price_raw <= 0 && $regular_price_raw > 0 ) {
					$current_price_raw = $regular_price_raw;
				}

				if ( $regular_price_raw <= 0 ) {
					$regular_price_raw = $current_price_raw;
				}

				$has_discount     = $regular_price_raw > 0 && $current_price_raw > 0 && $current_price_raw < $regular_price_raw;
				$discount_percent = $has_discount ? absint(
					round(
						( ( $regular_price_raw - $current_price_raw ) / $regular_price_raw ) * 100
					)
				) : 0;

				$items[] = array(
					'id'                 => $variation->get_id(),
					'label'              => $option_label,
					'description'        => $variation->get_description(),
					'attributes'         => $attributes,
					'attributes_display' => $attribute_values,
					'prices'             => array(
						'current'          => self::format_plain_price( $current_price_raw, $charset ),
						'regular'          => self::format_plain_price( $regular_price_raw, $charset ),
						'sale'             => self::format_plain_price( $sale_price_raw, $charset ),
						'raw'              => array(
							'current' => $current_price_raw,
							'regular' => $regular_price_raw,
							'sale'    => $sale_price_raw,
						),
						'has_discount'     => $has_discount,
						'discount_percent' => $discount_percent,
					),
				);
			}

			return $items;
		}

		private static function build_simple_option_item( WC_Product $product, string $charset ) : ?array {
			if ( ! $product->exists() ) {
				return null;
			}

			$current_price_raw = (float) wc_get_price_to_display( $product );

			$regular_price_value = $product->get_regular_price();
			$regular_price_raw   = '' === $regular_price_value
				? 0.0
				: (float) wc_get_price_to_display(
					$product,
					array(
						'price' => $regular_price_value,
					)
				);

			$sale_price_value = $product->get_sale_price();
			$sale_price_raw   = '' === $sale_price_value
				? 0.0
				: (float) wc_get_price_to_display(
					$product,
					array(
						'price' => $sale_price_value,
					)
				);

			if ( $current_price_raw <= 0 && $regular_price_raw > 0 ) {
				$current_price_raw = $regular_price_raw;
			}

			if ( $regular_price_raw <= 0 ) {
				$regular_price_raw = $current_price_raw;
			}

			$has_discount     = $regular_price_raw > 0 && $current_price_raw > 0 && $current_price_raw < $regular_price_raw;
			$discount_percent = $has_discount ? absint(
				round(
					( ( $regular_price_raw - $current_price_raw ) / $regular_price_raw ) * 100
				)
			) : 0;

			return array(
				'id'                 => $product->get_id(),
				'label'              => $product->get_name(),
				'description'        => $product->get_short_description(),
				'attributes'         => array(),
				'attributes_display' => array(),
				'prices'             => array(
					'current'          => self::format_plain_price( $current_price_raw, $charset ),
					'regular'          => self::format_plain_price( $regular_price_raw, $charset ),
					'sale'             => self::format_plain_price( $sale_price_raw, $charset ),
					'raw'              => array(
						'current' => $current_price_raw,
						'regular' => $regular_price_raw,
						'sale'    => $sale_price_raw,
					),
					'has_discount'     => $has_discount,
					'discount_percent' => $discount_percent,
				),
			);
		}

		/**
		 * Prepares price line data (both raw numbers and formatted values).
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array<string, mixed>
		 */
		private static function prepare_prices( WC_Product $product ) : array {
			$regular_price_raw = 0.0;
			$sale_price_raw    = 0.0;
			$current_price_raw = 0.0;

			if ( $product->is_type( 'variable' ) ) {
				$regular_price_raw = (float) $product->get_variation_regular_price( 'min', false );
				$sale_price_raw    = (float) $product->get_variation_sale_price( 'min', false );
				$current_price_raw = (float) $product->get_variation_price( 'min', false );
			} else {
				$regular_price_raw = (float) $product->get_regular_price();
				$sale_price_raw    = (float) $product->get_sale_price();
				$current_price_raw = (float) $product->get_price();
			}

			if ( $current_price_raw <= 0 && $regular_price_raw > 0 ) {
				$current_price_raw = $regular_price_raw;
			}

			$regular_price = $regular_price_raw ? wc_get_price_to_display(
				$product,
				array( 'price' => $regular_price_raw )
			) : 0.0;

			$sale_price = $sale_price_raw ? wc_get_price_to_display(
				$product,
				array( 'price' => $sale_price_raw )
			) : 0.0;

			$current_price = wc_get_price_to_display(
				$product,
				array( 'price' => $current_price_raw )
			);

			$has_discount     = $sale_price > 0 && $regular_price > 0 && $sale_price < $regular_price;
			$discount_percent = $has_discount ? absint(
				round( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 )
			) : 0;

			if ( $has_discount ) {
				$current_price = $sale_price;
			}

			$currency_symbol = get_woocommerce_currency_symbol();

			$format_price_value = static function( float $price ) use ( $currency_symbol ) : string {
				if ( $price <= 0 ) {
					return '';
				}

				$localized = wc_format_localized_price( $price );
				return trim( $localized . ' ' . $currency_symbol );
			};

			return array(
				'current_raw'       => $current_price,
				'regular_raw'       => $regular_price,
				'sale_raw'          => $sale_price,
				'current_display'   => $format_price_value( $current_price ),
				'regular_display'   => $has_discount ? $format_price_value( $regular_price ) : '',
				'has_discount'      => $has_discount,
				'discount_percent'  => $discount_percent,
			);
		}

		/**
		 * Prepares star rating data for product card blocks.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array<string, mixed>
		 */
		private static function prepare_rating( WC_Product $product ) : array {
			$average_rating = (float) $product->get_average_rating();
			$average_rating = max( 0.0, min( 5.0, $average_rating ) );

			$average_display = $average_rating > 0
				? number_format( $average_rating, 1, '.', '' )
				: '0';

			return array(
				'average'         => $average_rating,
				'average_display' => $average_display,
				'filled_stars'    => min( (int) floor( $average_rating ), 5 ),
				'max_stars'       => 5,
				'review_count'    => (int) $product->get_review_count(),
				'rating_count'    => (int) $product->get_rating_count(),
			);
		}

		/**
		 * Formats a WooCommerce price into plain text without inner spans.
		 *
		 * @param string|float $price Raw price value.
		 * @param string       $charset Site charset.
		 *
		 * @return string
		 */
		private static function format_plain_price( $price, string $charset ) : string {
			if ( '' === $price || null === $price ) {
				return '';
			}

			$value = wc_price( $price );

			return html_entity_decode(
				wp_strip_all_tags( $value ),
				ENT_QUOTES,
				$charset
			);
		}

		/**
		 * Prepares ancillary data used in the product extra blocks.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array<string, mixed>
		 */
		private static function prepare_extra_context( WC_Product $product ) : array {
			$attributes = self::prepare_extra_attributes( $product );
			$tags       = self::prepare_product_tags( $product );
			$download   = self::prepare_download_block( $product );

			return array(
				'attributes'   => $attributes,
				'tags'         => $tags,
				'has_tags'     => ! empty( $tags ),
				'download'     => $download,
				'has_download' => ! empty( $download ),
			);
		}

		/**
		 * Collects visible product attributes for the characteristics block.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array<int, array{label: string, value: string}>
		 */
		private static function prepare_extra_attributes( WC_Product $product ) : array {
			$attributes          = array();
			$display_dimensions  = apply_filters(
				'wc_product_enable_dimensions_display',
				$product->has_weight() || $product->has_dimensions()
			);

			if ( $display_dimensions && $product->has_weight() ) {
				$attributes[] = array(
					'label' => __( 'Weight', 'woocommerce' ),
					'value' => wc_format_weight( $product->get_weight() ),
				);
			}

			if ( $display_dimensions && $product->has_dimensions() ) {
				$attributes[] = array(
					'label' => __( 'Dimensions', 'woocommerce' ),
					'value' => wc_format_dimensions( $product->get_dimensions( false ) ),
				);
			}

			$visible_attributes = array_filter( $product->get_attributes(), 'wc_attributes_array_filter_visible' );

			foreach ( $visible_attributes as $visible_attribute ) {
				$attribute_values = $visible_attribute->is_taxonomy()
					? wc_get_product_terms(
						$product->get_id(),
						$visible_attribute->get_name(),
						array( 'fields' => 'names' )
					)
					: $visible_attribute->get_options();

				if ( is_wp_error( $attribute_values ) ) {
					continue;
				}

				$attribute_values = array_filter(
					array_map(
						static function ( $value ) {
							return trim( (string) $value );
						},
						(array) $attribute_values
					),
					'strlen'
				);

				if ( empty( $attribute_values ) ) {
					continue;
				}

				$label = wc_attribute_label( $visible_attribute->get_name() );
				$slug  = 'attr_' . md5( $label );

				$attributes[] = array(
					'slug'  => $slug,
					'label' => $label,
					'value' => implode( ', ', $attribute_values ),
				);
			}

			return $attributes;
		}

		/**
		 * Collects product tags once so templates can stay dumb.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array<int, WP_Term>
		 */
		private static function prepare_product_tags( WC_Product $product ) : array {
			$product_tags = wc_get_product_terms(
				$product->get_id(),
				'product_tag',
				array(
					'orderby'    => 'name',
					'order'      => 'ASC',
					'hide_empty' => false,
				)
			);

			if ( is_wp_error( $product_tags ) ) {
				return array();
			}

			return array_values(
				array_filter(
					(array) $product_tags,
					static function ( $tag ) {
						return $tag instanceof WP_Term;
					}
				)
			);
		}

		/**
		 * Collects download block payload from ACF.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array<string, mixed>
		 */
		private static function prepare_download_block( WC_Product $product ) : array {
			if ( ! function_exists( 'get_field' ) ) {
				return array();
			}

			$add_info = get_field( 'add-info', $product->get_id() );
			$add_info = is_array( $add_info ) ? $add_info : array();

			$title   = isset( $add_info['title'] ) ? trim( (string) $add_info['title'] ) : '';
			$content = isset( $add_info['text'] ) ? (string) $add_info['text'] : '';
			$content = self::normalize_download_content( $content );

			$distribution_link = get_field( 'distribution_link', $product->get_id() );
			$button_url        = $distribution_link ? (string) get_permalink( $distribution_link ) : '';

			$button_text = isset( $add_info['button_text'] ) ? trim( (string) $add_info['button_text'] ) : '';

			if ( '' === $button_text && $button_url ) {
				$button_text = apply_filters(
					'itsoft_product_download_button_text',
					esc_html__( 'Скачать дистрибутив', 'itsoft' ),
					$product
				);
			}

			$should_render = $title || ! empty( $content ) || ( $button_text && $button_url );

			if ( ! $should_render ) {
				return array();
			}

			$download_block = array(
				'title'       => $title,
				'content'     => $content,
				'button_text' => $button_text,
				'button_url'  => $button_url,
			);

			return $download_block;
		}

		/**
		 * Normalizes multi-line text into trimmed paragraphs.
		 *
		 * @param string $text Raw textarea value.
		 *
		 * @return array<int, string>
		 */
		private static function normalize_download_content( string $text ) : array {
			$text = trim( (string) wp_strip_all_tags( $text ) );

			if ( '' === $text ) {
				return array();
			}

			$paragraphs = preg_split( '/\r\n|\r|\n/', $text );

			if ( ! $paragraphs ) {
				return array( $text );
			}

			return array_filter(
				array_map(
					static function ( $paragraph ) {
						return trim( (string) $paragraph );
					},
					$paragraphs
				),
				'strlen'
			);
		}

		/**
		 * Prepares add-to-cart block data.
		 *
		 * @param WC_Product $product WooCommerce product instance.
		 *
		 * @return array<string, mixed>
		 */
		private static function prepare_cart_context( WC_Product $product ) : array {
			$is_purchasable       = $product->is_purchasable();
			$is_in_stock          = $product->is_in_stock();
			$is_sold_individually = $product->is_sold_individually();

			$min_quantity = (int) apply_filters(
				'woocommerce_quantity_input_min',
				$product->get_min_purchase_quantity(),
				$product
			);

			if ( $min_quantity < 1 ) {
				$min_quantity = 1;
			}

			$max_quantity_raw = apply_filters(
				'woocommerce_quantity_input_max',
				$product->get_max_purchase_quantity(),
				$product
			);

			if ( $is_sold_individually ) {
				$min_quantity   = 1;
				$max_quantity   = 1;
			} else {
				$max_quantity = is_numeric( $max_quantity_raw ) ? (float) $max_quantity_raw : '';

				if ( is_numeric( $max_quantity ) && ( (float) $max_quantity ) <= 0 ) {
					$max_quantity = '';
				}
			}

			$step = apply_filters( 'woocommerce_quantity_input_step', 1, $product );

			if ( ! is_numeric( $step ) || (float) $step <= 0 ) {
				$step = 1;
			}

			$default_quantity = $is_sold_individually ? 1 : $min_quantity;

			return array(
				'product_id'           => $product->get_id(),
				'product_type'         => $product->get_type(),
				'sku'                  => $product->get_sku(),
				'is_purchasable'       => $is_purchasable,
				'is_in_stock'          => $is_in_stock,
				'is_sold_individually' => $is_sold_individually,
				'min_quantity'         => $min_quantity,
				'max_quantity'         => $max_quantity,
				'step'                 => (float) $step,
				'default_quantity'     => $default_quantity,
				'button_text'          => apply_filters( 'woocommerce_product_single_add_to_cart_text', $product->add_to_cart_text(), $product ),
				'button_in_cart_text'  => apply_filters( 'itsoft_product_in_cart_text', esc_html__( 'В корзине', 'itsoft' ), $product ),
				'button_url'           => apply_filters( 'woocommerce_add_to_cart_form_action', $product->add_to_cart_url(), $product ),
				'cart_url'             => wc_get_cart_url(),
				'ajax_url'             => admin_url( 'admin-ajax.php' ),
			);
		}
	}
}

