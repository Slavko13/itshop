<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Search {
	private const SYNONYMS_META = '_itsoft_search_synonyms';

	private const TOKEN_ALIASES = array(
		'виндовс'          => 'windows',
		'виндоус'          => 'windows',
		'винда'            => 'windows',
		'win'              => 'windows',
		'w'                => 'windows',
		'vindovs'          => 'windows',
		'vindous'          => 'windows',
		'windos'           => 'windows',
		'wndows'           => 'windows',
		'windwos'          => 'windows',
		'майкрософт'       => 'microsoft',
		'microsft'         => 'microsoft',
		'micro'            => 'microsoft',
		'ms'               => 'microsoft',
		'офис'             => 'office',
		'ofis'             => 'office',
		'offce'            => 'office',
		'msoffice'         => 'office',
		'про'              => 'professional',
		'pro'              => 'professional',
		'profess'          => 'professional',
		'профессиональная' => 'professional',
		'профессиональный' => 'professional',
		'домашняя'         => 'home',
		'домашний'         => 'home',
	);

	private const STOP_TOKENS = array(
		'ключ', 'ключи', 'ключа', 'лицензия', 'лицензии', 'лицензионный',
		'активация', 'активации', 'активировать', 'купить', 'для', 'электронный',
	);

	public function register_hooks(): void {
		add_action( 'pre_get_posts', array( $this, 'configure_search_query' ), 50 );
		add_filter( 'posts_search', array( $this, 'disable_core_search_clause' ), 20, 2 );
		add_filter( 'woocommerce_redirect_single_search_result', '__return_false' );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_synonyms_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_synonyms_field' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 40 );
	}

	public function configure_search_query( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		$search = trim( (string) $query->get( 's' ) );
		// Legacy theme forms submit to /search/?s=..., which WordPress also parses as a category slug.
		if ( 'search' === $query->get( 'category_name' ) ) {
			$query->set( 'category_name', '' );
		}
		if ( '' === $search ) {
			$query->set( 'post_type', array( 'product', 'post' ) );
			$query->set( 'post__in', array( 0 ) );
			$query->set( 'posts_per_page', 24 );
			$query->set( 'itsoft_ranked_search', 1 );
			return;
		}

		$requested_type = $query->get( 'post_type' );
		$types          = 'product' === $requested_type ? array( 'product' ) : array( 'product', 'post' );
		$ids            = $this->rank_site_content( $search, $types );
		$query->set( 'post_type', 1 === count( $types ) ? $types[0] : $types );
		$query->set( 'post__in', $ids ?: array( 0 ) );
		$query->set( 'orderby', 'post__in' );
		$query->set( 'order', 'ASC' );
		$query->set( 'posts_per_page', 24 );
		$query->set( 'itsoft_ranked_search', 1 );
	}

	public function disable_core_search_clause( string $search_sql, WP_Query $query ): string {
		return $query->get( 'itsoft_ranked_search' ) ? '' : $search_sql;
	}

	/**
	 * @param string[] $post_types
	 * @return int[]
	 */
	public function rank_site_content( string $search, array $post_types = array( 'product', 'post' ) ): array {
		global $wpdb;

		$post_types = array_values( array_intersect( array( 'product', 'post' ), array_map( 'sanitize_key', $post_types ) ) );
		if ( empty( $post_types ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		$sql          = "SELECT p.ID, p.post_type, p.post_title, p.post_excerpt, p.post_content, MAX(pm.meta_value) AS search_synonyms
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
			WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})
			GROUP BY p.ID, p.post_type, p.post_title, p.post_excerpt, p.post_content";
		$args         = array_merge( array( self::SYNONYMS_META ), $post_types );
		$candidates   = $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $this->rank_candidates( $search, is_array( $candidates ) ? $candidates : array() );
	}

	/**
	 * @param object[] $candidates
	 * @return int[]
	 */
	public function rank_candidates( string $search, array $candidates ): array {
		$query_variants = $this->query_variants( $search );
		if ( empty( $query_variants ) ) {
			return array();
		}

		$ranked = array();
		foreach ( $candidates as $candidate ) {
			$id   = (int) ( $candidate->ID ?? 0 );
			$type = (string) ( $candidate->post_type ?? '' );
			if ( ! $id || ! in_array( $type, array( 'product', 'post' ), true ) ) {
				continue;
			}
			if ( 'product' === $type ) {
				$product = wc_get_product( $id );
				if ( ! $product || in_array( $product->get_catalog_visibility(), array( 'catalog', 'hidden' ), true ) ) {
					continue;
				}
			}

			$title     = $this->canonicalize( (string) ( $candidate->post_title ?? '' ) );
			$synonyms  = $this->canonicalize( (string) ( $candidate->search_synonyms ?? '' ) );
			$secondary = 'post' === $type
				? $this->canonicalize( (string) ( $candidate->post_excerpt ?? '' ) . ' ' . (string) ( $candidate->post_content ?? '' ) )
				: '';
			$best = 0;
			foreach ( $query_variants as $query_tokens ) {
				$best = max( $best, $this->score_candidate( $query_tokens, $title, $synonyms, $secondary ) );
			}
			if ( $best <= 0 ) {
				continue;
			}
			$ranked[] = array(
				'id'    => $id,
				'score' => $best + ( 'product' === $type ? 100000 : 0 ),
				'title' => $title,
			);
		}

		usort(
			$ranked,
			static function ( array $left, array $right ): int {
				return $right['score'] <=> $left['score'] ?: strcmp( $left['title'], $right['title'] ) ?: $left['id'] <=> $right['id'];
			}
		);
		return array_map( 'intval', wp_list_pluck( $ranked, 'id' ) );
	}

	/**
	 * @param string[] $query_tokens
	 */
	private function score_candidate( array $query_tokens, string $title, string $synonyms, string $secondary ): int {
		$title_tokens     = $this->tokens( $title );
		$synonym_tokens   = $this->tokens( $synonyms );
		$secondary_tokens = $this->tokens( $secondary );
		$primary_tokens   = array_values( array_unique( array_merge( $title_tokens, $synonym_tokens ) ) );
		$full_query       = implode( ' ', $query_tokens );

		foreach ( $query_tokens as $token ) {
			if ( ctype_digit( $token ) && ! in_array( $token, $primary_tokens, true ) ) {
				return 0;
			}
		}

		$score = 0;
		if ( $title === $full_query ) {
			$score += 12000;
		} elseif ( str_starts_with( $title, $full_query . ' ' ) ) {
			$score += 7000;
		} elseif ( str_contains( ' ' . $title . ' ', ' ' . $full_query . ' ' ) ) {
			$score += 5000;
		} elseif ( '' !== $synonyms && str_contains( ' ' . $synonyms . ' ', ' ' . $full_query . ' ' ) ) {
			$score += 4200;
		}

		foreach ( $query_tokens as $token ) {
			$token_score = $this->score_token( $token, $title_tokens, 900, 340, 130 );
			if ( 0 === $token_score ) {
				$token_score = $this->score_token( $token, $synonym_tokens, 720, 280, 110 );
			}
			if ( 0 === $token_score && ! empty( $secondary_tokens ) ) {
				$token_score = $this->score_token( $token, $secondary_tokens, 100, 45, 20 );
			}
			if ( 0 === $token_score ) {
				return 0;
			}
			$score += $token_score;
		}

		return $score;
	}

	/**
	 * @param string[] $haystack
	 */
	private function score_token( string $needle, array $haystack, int $exact, int $prefix, int $fuzzy ): int {
		if ( in_array( $needle, $haystack, true ) ) {
			return $exact;
		}
		if ( mb_strlen( $needle ) >= 4 ) {
			foreach ( $haystack as $token ) {
				if ( str_starts_with( $token, $needle ) || str_starts_with( $needle, $token ) && mb_strlen( $token ) >= 4 ) {
					return $prefix;
				}
			}
		}
		$length = mb_strlen( $needle );
		if ( $length < 5 || ! preg_match( '/^[a-z]+$/', $needle ) ) {
			return 0;
		}
		$limit = $length >= 8 ? 2 : 1;
		foreach ( $haystack as $token ) {
			if ( preg_match( '/^[a-z]+$/', $token ) && abs( mb_strlen( $token ) - $length ) <= $limit && levenshtein( $needle, $token ) <= $limit ) {
				return $fuzzy;
			}
		}
		return 0;
	}

	/** @return array<int, string[]> */
	private function query_variants( string $search ): array {
		$raw_variants = array( $search, $this->keyboard_ru_to_en( $search ), $this->keyboard_en_to_ru( $search ) );
		$variants     = array();
		foreach ( $raw_variants as $variant ) {
			$tokens = $this->tokens( $this->canonicalize( $variant ) );
			$tokens = array_values( array_filter( $tokens, static fn( string $token ): bool => ! in_array( $token, self::STOP_TOKENS, true ) ) );
			if ( $tokens ) {
				$variants[ implode( '|', $tokens ) ] = $tokens;
			}
		}
		return array_values( $variants );
	}

	private function canonicalize( string $value ): string {
		$value = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = mb_strtolower( str_replace( 'ё', 'е', $value ), 'UTF-8' );
		$value = preg_replace( '/(?<=\p{L})(?=\p{N})|(?<=\p{N})(?=\p{L})/u', ' ', $value );
		$value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', (string) $value );
		$tokens = preg_split( '/\s+/u', trim( (string) $value ) ) ?: array();
		foreach ( $tokens as &$token ) {
			$token = self::TOKEN_ALIASES[ $token ] ?? $token;
		}
		unset( $token );
		return trim( implode( ' ', $tokens ) );
	}

	/** @return string[] */
	private function tokens( string $value ): array {
		return array_values( array_filter( preg_split( '/\s+/u', trim( $value ) ) ?: array(), 'strlen' ) );
	}

	private function keyboard_ru_to_en( string $value ): string {
		return strtr( mb_strtolower( $value, 'UTF-8' ), array_combine(
			preg_split( '//u', 'йцукенгшщзхъфывапролджэячсмитьбю', -1, PREG_SPLIT_NO_EMPTY ),
			str_split( 'qwertyuiop[]asdfghjkl;\'zxcvbnm,.' )
		) ?: array() );
	}

	private function keyboard_en_to_ru( string $value ): string {
		return strtr( strtolower( $value ), array_combine(
			str_split( 'qwertyuiop[]asdfghjkl;\'zxcvbnm,.' ),
			preg_split( '//u', 'йцукенгшщзхъфывапролджэячсмитьбю', -1, PREG_SPLIT_NO_EMPTY )
		) ?: array() );
	}

	public function render_synonyms_field(): void {
		woocommerce_wp_text_input(
			array(
				'id'          => self::SYNONYMS_META,
				'label'       => 'Синонимы поиска',
				'description' => 'Дополнительные названия через запятую, например: Виндовс 11, Win 11.',
				'desc_tip'    => true,
			)
		);
	}

	public function save_synonyms_field( WC_Product $product ): void {
		if ( ! isset( $_POST[ self::SYNONYMS_META ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$value = sanitize_text_field( wp_unslash( $_POST[ self::SYNONYMS_META ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( self::SYNONYMS_META, mb_substr( $value, 0, 1000 ) );
	}

	public function enqueue_assets(): void {
		if ( ! is_search() ) {
			return;
		}
		wp_enqueue_style(
			'itsoft-lf-search',
			plugins_url( 'assets/css/search.css', ITSOFT_LF_FILE ),
			array(),
			ITSOFT_LF_VERSION
		);
	}
}
