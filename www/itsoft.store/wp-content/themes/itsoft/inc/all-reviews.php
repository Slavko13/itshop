<?php
/**
 * All reviews page functionality.
 *
 * @package itsoft
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Itsoft_All_Reviews {
	private const PER_PAGE     = 8;
	private const AJAX_ACTION  = 'itsoft_load_all_reviews';
	private const NONCE_ACTION = 'itsoft_all_reviews_nonce';

	/**
	 * Bootstrap hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ __CLASS__, 'handle_ajax' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ __CLASS__, 'handle_ajax' ] );
	}

	/**
	 * Enqueue assets for all reviews page.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( ! is_page_template( 'page-reviews.php' ) ) {
			return;
		}

		$theme_version = wp_get_theme()->get( 'Version' );

		wp_enqueue_style(
			'itsoft-page-reviews',
			get_template_directory_uri() . '/assets/css/page-reviews.css',
			[],
			$theme_version
		);

		wp_enqueue_script(
			'itsoft-all-reviews',
			get_template_directory_uri() . '/assets/js/all-reviews.js',
			[],
			$theme_version,
			true
		);

		wp_localize_script(
			'itsoft-all-reviews',
			'ITSOFT_ALL_REVIEWS',
			[
				'ajaxUrl' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'perPage' => self::PER_PAGE,
				'i18n'    => [
					'loadMore' => esc_html__( 'Показать все отзывы', 'itsoft' ),
					'loading'  => esc_html__( 'Загружаем отзывы…', 'itsoft' ),
					'error'    => esc_html__( 'Не удалось загрузить отзывы. Попробуйте ещё раз.', 'itsoft' ),
					'empty'    => esc_html__( 'Это все отзывы', 'itsoft' ),
				],
			]
		);
	}

	/**
	 * Build context for initial render.
	 *
	 * @return array
	 */
	public static function get_page_context(): array {
		$comments = self::get_reviews( 0 );
		$total    = self::get_reviews_total();
		$loaded   = count( $comments );

		return [
			'total'    => $total,
			'per_page' => self::PER_PAGE,
			'loaded'   => $loaded,
			'has_more' => ( $total > $loaded ),
			'html'     => $loaded ? self::render_reviews_markup( $comments ) : self::get_empty_state_markup(),
		];
	}

	/**
	 * AJAX handler for load more reviews.
	 *
	 * @return void
	 */
	public static function handle_ajax(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$offset = isset( $_POST['offset'] ) ? max( 0, absint( wp_unslash( $_POST['offset'] ) ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$comments = self::get_reviews( $offset );
		$total    = self::get_reviews_total();
		$loaded   = $offset + count( $comments );

		$response = [
			'html'    => $comments ? self::render_reviews_markup( $comments ) : '',
			'hasMore' => ( $loaded < $total ),
			'loaded'  => $loaded,
			'total'   => $total,
		];

		if ( ! $comments ) {
			$response['message'] = esc_html__( 'Это все отзывы', 'itsoft' );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Render reviews markup.
	 *
	 * @param array $comments Comment objects.
	 *
	 * @return string
	 */
	public static function render_reviews_markup( array $comments ): string {
		if ( empty( $comments ) ) {
			return '';
		}

		ob_start();

		foreach ( $comments as $comment ) {
			if ( ! $comment instanceof WP_Comment ) {
				continue;
			}

			// For all reviews, we need the product ID from the comment
			self::render_single_review( $comment, $comment->comment_post_ID );
		}

		return ob_get_clean();
	}

	/**
	 * Empty state markup.
	 *
	 * @return string
	 */
	public static function get_empty_state_markup(): string {
		ob_start();
		?>
		<div class="product-reviews__empty">
			<div class="product-reviews__empty-title"><?php esc_html_e( 'Отзывов пока нет', 'itsoft' ); ?></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Output single review card markup.
	 *
	 * @param WP_Comment $comment    Comment object.
	 * @param int        $product_id Product ID.
	 *
	 * @return void
	 */
	private static function render_single_review( WP_Comment $comment, int $product_id ): void {
		$rating        = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
		$rating        = max( 0, min( 5, $rating ) );
		$product_link  = get_permalink( $product_id );
		$product_title = get_the_title( $product_id );
		$initial       = self::get_initial( $comment );
		$masked_email  = self::mask_email( $comment->comment_author_email );
		$content       = apply_filters( 'comment_text', $comment->comment_content, $comment );
		$time_diff     = human_time_diff( get_comment_date( 'U', $comment ), current_time( 'timestamp' ) );
		$time_label    = sprintf( esc_html__( '%s назад', 'itsoft' ), $time_diff );
		?>
		<article class="product-reviews__item review-card" data-review-id="<?php echo esc_attr( $comment->comment_ID ); ?>">
			<header class="review-card__header">
				<div class="review-card__avatar" aria-hidden="true">
					<span><?php echo esc_html( $initial ); ?></span>
				</div>
				<div class="review-card__meta">
					<div class="review-card__author">
						<span class="review-card__email"><?php echo esc_html( $masked_email ); ?></span>
						<span class="review-card__verified" aria-label="<?php esc_attr_e( 'Покупка подтверждена', 'itsoft' ); ?>">
							<?php echo self::get_check_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
					</div>
					<div class="review-card__product">
						<span><?php esc_html_e( 'Товар:', 'itsoft' ); ?></span>
						<?php if ( $product_link ) : ?>
							<a href="<?php echo esc_url( $product_link ); ?>">
								<?php echo esc_html( $product_title ); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $product_title ); ?>
						<?php endif; ?>
					</div>
					<div class="review-card__rating" role="img" aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Оценка %d из 5', 'itsoft' ), $rating ) ); ?>">
						<?php for ( $i = 1; $i <= 5; $i++ ) :
							$is_filled = $i <= $rating;
							?>
							<span class="review-card__star <?php echo esc_attr( $is_filled ? 'review-card__star--filled' : 'review-card__star--empty' ); ?>">
								<?php echo $is_filled ? self::get_star_icon_filled() : self::get_star_icon_empty(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
						<?php endfor; ?>
					</div>
				</div>
			</header>
			<div class="review-card__content">
				<?php echo wp_kses_post( wpautop( $content ) ); ?>
				<time class="review-card__time" datetime="<?php echo esc_attr( get_comment_date( DATE_W3C, $comment ) ); ?>">
					<?php echo esc_html( $time_label ); ?>
				</time>
			</div>
		</article>
		<?php
	}

	/**
	 * Get reviews chunk.
	 *
	 * @param int $offset Offset.
	 *
	 * @return array
	 */
	private static function get_reviews( int $offset ): array {
		return get_comments(
			[
				'status'  => 'approve',
				'number'  => self::PER_PAGE,
				'offset'  => $offset,
				'type'    => 'review',
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			]
		);
	}

	/**
	 * Count total product reviews.
	 *
	 * @return int
	 */
	private static function get_reviews_total(): int {
		return (int) get_comments(
			[
				'status'  => 'approve',
				'type'    => 'review',
				'count'   => true,
			]
		);
	}

	/**
	 * Get masked email.
	 *
	 * @param string $email Email.
	 *
	 * @return string
	 */
	private static function mask_email( string $email ): string {
		if ( empty( $email ) || false === strpos( $email, '@' ) ) {
			return $email ? $email : esc_html__( 'Покупатель', 'itsoft' );
		}

		$parts = explode( '@', $email, 2 );

		if ( count( $parts ) < 2 ) {
			return $email;
		}

		list( $name, $domain ) = $parts;
		$length               = max( 6, strlen( $name ) );
		$masked               = str_repeat( '*', $length );

		return sprintf( '%s@%s', $masked, $domain );
	}

	/**
	 * Avatar initial.
	 *
	 * @param WP_Comment $comment Comment object.
	 *
	 * @return string
	 */
	private static function get_initial( WP_Comment $comment ): string {
		$source = $comment->comment_author ?: $comment->comment_author_email;

		if ( function_exists( 'mb_substr' ) ) {
			$initial = mb_substr( trim( (string) $source ), 0, 1, 'UTF-8' );
		} else {
			$initial = substr( trim( (string) $source ), 0, 1 );
		}

		if ( empty( $initial ) ) {
			return 'U';
		}

		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initial, 'UTF-8' ) : strtoupper( $initial );
	}

	/**
	 * Check icon SVG.
	 *
	 * @return string
	 */
	private static function get_check_icon(): string {
		return '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.5 10.625L9.375 12.5L12.5 8.125M17.5 10C17.5 11.0567 16.975 11.9917 16.1725 12.5567C16.259 13.0506 16.2251 13.5582 16.0738 14.0363C15.9225 14.5144 15.6582 14.949 15.3033 15.3033C14.949 15.6582 14.5144 15.9225 14.0363 16.0738C13.5582 16.2251 13.0506 16.259 12.5567 16.1725C12.2685 16.5829 11.8857 16.9178 11.4407 17.1489C10.9956 17.38 10.5015 17.5004 10 17.5C8.94333 17.5 8.00833 16.975 7.44333 16.1725C6.94936 16.2589 6.44184 16.225 5.96374 16.0737C5.48564 15.9224 5.05102 15.6582 4.69667 15.3033C4.34177 14.949 4.07748 14.5144 3.92619 14.0363C3.77489 13.5582 3.74104 13.0506 3.8275 12.5567C3.4171 12.2685 3.08218 11.8857 2.85111 11.4407C2.62003 10.9956 2.49959 10.5015 2.5 10C2.5 8.94333 3.025 8.00833 3.8275 7.44333C3.74104 6.94936 3.77489 6.44182 3.92619 5.96371C4.07748 5.48559 4.34177 5.05098 4.69667 4.69667C5.05102 4.34183 5.48564 4.07758 5.96374 3.92629C6.44184 3.77499 6.94936 3.74111 7.44333 3.8275C7.73154 3.41715 8.11435 3.08227 8.55938 2.8512C9.00441 2.62013 9.49856 2.49966 10 2.5C11.0567 2.5 11.9917 3.025 12.5567 3.8275C13.0506 3.74111 13.5582 3.77499 14.0363 3.92629C14.5144 4.07758 14.949 4.34183 15.3033 4.69667C15.6582 5.05102 15.9224 5.48564 16.0737 5.96374C16.225 6.44184 16.2589 6.94936 16.1725 7.44333C16.5829 7.73149 16.9178 8.11429 17.1489 8.55933C17.38 9.00437 17.5004 9.49854 17.5 10Z" stroke="#6C4CFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>';
	}

	/**
	 * Star icon SVG (Filled).
	 *
	 * @return string
	 */
	private static function get_star_icon_filled(): string {
		return '<svg width="20" height="19" viewBox="0 0 20 19" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.23888 1.08939C9.28045 0.989012 9.35125 0.903143 9.44226 0.842707C9.53328 0.782271 9.6404 0.75 9.75 0.75C9.85959 0.75 9.96671 0.782271 10.0577 0.842707C10.1487 0.903143 10.2195 0.989012 10.2611 1.08939L12.3498 6.06218C12.3889 6.15523 12.4532 6.2358 12.5356 6.29502C12.6179 6.35424 12.7152 6.38983 12.8167 6.39785L18.2404 6.8279C18.7309 6.86682 18.9295 7.47297 18.556 7.78918L14.4238 11.2938C14.3466 11.3591 14.289 11.4443 14.2575 11.5399C14.2259 11.6355 14.2216 11.7378 14.2449 11.8357L15.5079 17.0751C15.5333 17.1802 15.5267 17.2904 15.4888 17.3918C15.451 17.4932 15.3836 17.5812 15.2953 17.6447C15.2069 17.7082 15.1015 17.7443 14.9924 17.7486C14.8833 17.7529 14.7754 17.725 14.6823 17.6686L10.038 14.8616C9.95127 14.8092 9.85162 14.7814 9.75 14.7814C9.64837 14.7814 9.54872 14.8092 9.462 14.8616L4.81772 17.6696C4.72459 17.726 4.61667 17.7538 4.50757 17.7496C4.39847 17.7453 4.29309 17.7092 4.20474 17.6456C4.11639 17.5821 4.04904 17.4941 4.01119 17.3928C3.97334 17.2914 3.96668 17.1812 3.99207 17.0761L5.25512 11.8357C5.27854 11.7378 5.27423 11.6355 5.24267 11.5398C5.21111 11.4442 5.15352 11.3591 5.07623 11.2938L0.944042 7.78918C0.860831 7.71897 0.800542 7.62589 0.770812 7.52173C0.741082 7.41757 0.743247 7.30703 0.777033 7.20409C0.81082 7.10115 0.874707 7.01046 0.960605 6.94349C1.0465 6.87652 1.15055 6.83629 1.25956 6.8279L6.68329 6.39785C6.78478 6.38983 6.88206 6.35424 6.96444 6.29502C7.04681 6.2358 7.11108 6.15523 7.15018 6.06218L9.23888 1.08939Z" fill="#FDEB00" stroke="#FCE700" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>';
	}

	/**
	 * Star icon SVG (Empty).
	 *
	 * @return string
	 */
	private static function get_star_icon_empty(): string {
		return '<svg width="20" height="19" viewBox="0 0 20 19" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.23888 1.08939C9.28045 0.989012 9.35125 0.903143 9.44226 0.842707C9.53328 0.782271 9.6404 0.75 9.75 0.75C9.85959 0.75 9.96671 0.782271 10.0577 0.842707C10.1487 0.903143 10.2195 0.989012 10.2611 1.08939L12.3498 6.06218C12.3889 6.15523 12.4532 6.2358 12.5356 6.29502C12.6179 6.35424 12.7152 6.38983 12.8167 6.39785L18.2404 6.8279C18.7309 6.86682 18.9295 7.47297 18.556 7.78918L14.4238 11.2938C14.3466 11.3591 14.289 11.4443 14.2575 11.5399C14.2259 11.6355 14.2216 11.7378 14.2449 11.8357L15.5079 17.0751C15.5333 17.1802 15.5267 17.2904 15.4888 17.3918C15.451 17.4932 15.3836 17.5812 15.2953 17.6447C15.2069 17.7082 15.1015 17.7443 14.9924 17.7486C14.8833 17.7529 14.7754 17.725 14.6823 17.6686L10.038 14.8616C9.95127 14.8092 9.85162 14.7814 9.75 14.7814C9.64837 14.7814 9.54872 14.8092 9.462 14.8616L4.81772 17.6696C4.72459 17.726 4.61667 17.7538 4.50757 17.7496C4.39847 17.7453 4.29309 17.7092 4.20474 17.6456C4.11639 17.5821 4.04904 17.4941 4.01119 17.3928C3.97334 17.2914 3.96668 17.1812 3.99207 17.0761L5.25512 11.8357C5.27854 11.7378 5.27423 11.6355 5.24267 11.5398C5.21111 11.4442 5.15352 11.3591 5.07623 11.2938L0.944042 7.78918C0.860831 7.71897 0.800542 7.62589 0.770812 7.52173C0.741082 7.41757 0.743247 7.30703 0.777033 7.20409C0.81082 7.10115 0.874707 7.01046 0.960605 6.94349C1.0465 6.87652 1.15055 6.83629 1.25956 6.8279L6.68329 6.39785C6.78478 6.38983 6.88206 6.35424 6.96444 6.29502C7.04681 6.2358 7.11108 6.15523 7.15018 6.06218L9.23888 1.08939Z" stroke="#FCE700" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>';
	}
}

Itsoft_All_Reviews::init();
