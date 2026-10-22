<?php
/**
 * Helper functions for the WooCommerce catalog templates.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'itsoft_get_catalog_category_tree' ) ) {
	/**
	 * Returns a nested array of product categories for the sidebar tree.
	 *
	 * @return array<int, array{term: WP_Term, children: array}>
	 */
	function itsoft_get_catalog_category_tree() : array {
		static $tree_cache = null;

		if ( null !== $tree_cache ) {
			return $tree_cache;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'menu_order',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$tree_cache = array();
			return $tree_cache;
		}

		$grouped = array();

		foreach ( $terms as $term ) {
			$parent_id = (int) $term->parent;

			if ( ! isset( $grouped[ $parent_id ] ) ) {
				$grouped[ $parent_id ] = array();
			}

			$grouped[ $parent_id ][] = $term;
		}

		$build_branch = static function( int $parent_id ) use ( &$build_branch, &$grouped ) : array {
			if ( empty( $grouped[ $parent_id ] ) ) {
				return array();
			}

			$branch = array();

			foreach ( $grouped[ $parent_id ] as $term ) {
				$branch[] = array(
					'term'     => $term,
					'children' => $build_branch( (int) $term->term_id ),
				);
			}

			return $branch;
		};

		$tree_cache = $build_branch( 0 );
		$tree_cache = itsoft_prune_catalog_category_tree( $tree_cache );

		return $tree_cache;
	}
}

if ( ! function_exists( 'itsoft_prune_catalog_category_tree' ) ) {
	/**
	 * Removes categories that contain no products and have no non-empty descendants.
	 *
	 * @param array $nodes Category tree.
	 *
	 * @return array
	 */
	function itsoft_prune_catalog_category_tree( array $nodes ) : array {
		$filtered = array();

		foreach ( $nodes as $node ) {
			if ( empty( $node['term'] ) || ! $node['term'] instanceof WP_Term ) {
				continue;
			}

			$children = array();

			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$children = itsoft_prune_catalog_category_tree( $node['children'] );
			}

			$has_products = (int) $node['term']->count > 0;

			if ( ! $has_products && empty( $children ) ) {
				continue;
			}

			$filtered[] = array(
				'term'     => $node['term'],
				'children' => $children,
			);
		}

		return $filtered;
	}
}

if ( ! function_exists( 'itsoft_render_catalog_category_tree' ) ) {
	/**
	 * Renders the product category tree as nested lists.
	 *
	 * @param array $tree Category tree.
	 * @param array $args Additional arguments.
	 *
	 * @return string
	 */
	function itsoft_render_catalog_category_tree( array $tree, array $args = array() ) : string {
		if ( empty( $tree ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'current_id'   => 0,
				'ancestor_ids' => array(),
				'depth'        => 0,
				'list_class'   => 'catalog-tree',
				'item_class'   => 'catalog-tree__item',
				'link_class'   => 'catalog-tree__link',
			)
		);

		if ( empty( $args['ancestor_ids'] ) && $args['current_id'] ) {
			$args['ancestor_ids'] = get_ancestors( (int) $args['current_id'], 'product_cat' );
		}

		$list_class = 0 === (int) $args['depth'] ? $args['list_class'] : 'catalog-tree__children';
		$role       = 0 === (int) $args['depth'] ? 'tree' : 'group';

		$output = '<ul class="' . esc_attr( $list_class ) . '" role="' . esc_attr( $role ) . '">';

		foreach ( $tree as $node ) {
			if ( empty( $node['term'] ) || ! $node['term'] instanceof WP_Term ) {
				continue;
			}

			$term        = $node['term'];
			$children    = $node['children'] ?? array();
			$is_current  = (int) $term->term_id === (int) $args['current_id'];
			$is_ancestor = in_array( (int) $term->term_id, $args['ancestor_ids'], true );

			$item_classes = array( $args['item_class'] );
			$link_classes = array( $args['link_class'] );

			if ( $is_current ) {
				$item_classes[] = $args['item_class'] . '--current';
				$link_classes[] = $args['link_class'] . '--current';
			}

			if ( $is_ancestor ) {
				$item_classes[] = $args['item_class'] . '--ancestor';
				$link_classes[] = $args['link_class'] . '--ancestor';
			}

			if ( ! empty( $children ) ) {
				$item_classes[] = $args['item_class'] . '--has-children';
			}

			$term_link = get_term_link( $term );

			if ( is_wp_error( $term_link ) ) {
				continue;
			}

			$output .= sprintf(
				'<li class="%1$s" role="treeitem"%2$s>',
				esc_attr( implode( ' ', array_filter( $item_classes ) ) ),
				! empty( $children ) ? ' aria-expanded="true"' : ''
			);

			$output .= sprintf(
				'<a class="%1$s" href="%2$s">%3$s%4$s</a>',
				esc_attr( implode( ' ', array_filter( $link_classes ) ) ),
				esc_url( $term_link ),
				esc_html( $term->name ),
				! empty( $children ) ? '<span class="catalog-tree__icon">' . itsoft_catalog_get_icon( 'arrow-right' ) . '</span>' : ''
			);

			if ( ! empty( $children ) ) {
				$child_args            = $args;
				$child_args['depth']   = (int) $args['depth'] + 1;
				$output               .= itsoft_render_catalog_category_tree( $children, $child_args );
			}

			$output .= '</li>';
		}

		$output .= '</ul>';

		return $output;
	}
}

if ( ! function_exists( 'itsoft_catalog_get_icon' ) ) {
	/**
	 * Returns inline SVG icons used across catalog templates.
	 *
	 * @param string $name Icon name.
	 *
	 * @return string
	 */
	function itsoft_catalog_get_icon( string $name ) : string {
		$icons = array(
			'search' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
				<path d="M9.16667 15C12.3883 15 15 12.3883 15 9.16667C15 5.94501 12.3883 3.33334 9.16667 3.33334C5.94501 3.33334 3.33334 5.94501 3.33334 9.16667C3.33334 12.3883 5.94501 15 9.16667 15Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
				<path d="M16.6667 16.6667L13.875 13.875" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>',
			'star'   => '<svg width="17" height="17" viewBox="0 0 17 17" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
				<path d="M7.94701 1.04069C7.98226 0.954713 8.04228 0.881166 8.11944 0.829403C8.19661 0.77764 8.28743 0.75 8.38034 0.75C8.47326 0.75 8.56408 0.77764 8.64125 0.829403C8.71841 0.881166 8.77843 0.954713 8.81368 1.04069L10.5845 5.29985C10.6177 5.37955 10.6722 5.44856 10.742 5.49928C10.8118 5.55 10.8943 5.58048 10.9803 5.58735L15.5787 5.95569C15.9945 5.98902 16.1628 6.50819 15.8462 6.77902L12.3428 9.78069C12.2774 9.83668 12.2286 9.90961 12.2019 9.99148C12.1751 10.0734 12.1714 10.161 12.1912 10.2449L13.262 14.7324C13.2835 14.8224 13.2779 14.9168 13.2458 15.0036C13.2137 15.0904 13.1566 15.1658 13.0817 15.2202C13.0068 15.2746 12.9175 15.3056 12.825 15.3092C12.7325 15.3129 12.641 15.289 12.562 15.2407L8.62451 12.8365C8.55099 12.7916 8.4665 12.7678 8.38034 12.7678C8.29419 12.7678 8.2097 12.7916 8.13618 12.8365L4.19868 15.2415C4.11973 15.2898 4.02822 15.3137 3.93573 15.31C3.84323 15.3064 3.75389 15.2754 3.67898 15.221C3.60408 15.1666 3.54698 15.0913 3.51489 15.0044C3.4828 14.9176 3.47716 14.8232 3.49868 14.7332L4.56951 10.2449C4.58937 10.161 4.58572 10.0733 4.55896 9.99144C4.5322 9.90954 4.48337 9.83662 4.41784 9.78069L0.914512 6.77902C0.843964 6.71888 0.792851 6.63916 0.767645 6.54995C0.742439 6.46074 0.744275 6.36606 0.772919 6.27789C0.801564 6.18973 0.855729 6.11205 0.928554 6.05469C1.00138 5.99733 1.08959 5.96288 1.18201 5.95569L5.78034 5.58735C5.86639 5.58048 5.94886 5.55 6.0187 5.49928C6.08854 5.44856 6.14303 5.37955 6.17618 5.29985L7.94701 1.04069Z" fill="#FDEB00" stroke="#FCE700" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>',
			'chat'   => '<svg width="17" height="16" viewBox="0 0 17 16" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
				<path d="M8.25 14.5C12.3917 14.5 15.75 11.4217 15.75 7.625C15.75 3.82833 12.3917 0.75 8.25 0.75C4.10833 0.75 0.75 3.82833 0.75 7.625C0.75 9.37833 1.46583 10.9775 2.64417 12.1917C3.00417 12.5642 3.26083 13.0583 3.1325 13.5592C2.99198 14.1068 2.72898 14.6154 2.36333 15.0467C2.65595 15.0992 2.9527 15.1254 3.25 15.125C4.31833 15.125 5.30833 14.79 6.12083 14.2192C6.79583 14.4025 7.51083 14.5 8.25 14.5Z" stroke="#6C4CFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>',
			'menu'   => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
				<path d="M2 4H14M2 8H14M2 12H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
			</svg>',
			'arrow-right' => '<svg width="7" height="12" viewBox="0 0 7 12" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M0.78125 0.624695L4.78125 5.62469L0.78125 10.6247" stroke="#6C4CFF" stroke-width="2" />
			</svg>',
		);

		return $icons[ $name ] ?? '';
	}
}

if ( ! function_exists( 'itsoft_catalog_trim_excerpt' ) ) {
	/**
	 * Trims text safely for card subtitles.
	 *
	 * @param string $text Text to trim.
	 * @param int    $length Desired length.
	 *
	 * @return string
	 */
	function itsoft_catalog_trim_excerpt( string $text, int $length = 120 ) : string {
		$text = wp_strip_all_tags( $text );

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		return trim( mb_substr( $text, 0, $length ) ) . '…';
	}
}

