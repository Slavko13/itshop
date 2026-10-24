<?php
/**
 * Template Name: Все отзывы
 *
 * @package itsoft
 */

get_header();

$reviews_context = Itsoft_All_Reviews::get_page_context();
$container_attrs = sprintf(
	'data-all-reviews data-total="%1$d" data-loaded="%2$d" data-per-page="%3$d"',
	absint( $reviews_context['total'] ),
	absint( $reviews_context['loaded'] ),
	absint( $reviews_context['per_page'] )
);

$reviews_require_login = (bool) get_option( 'comment_registration' );
$verified_only         = ( 'yes' === get_option( 'woocommerce_review_rating_verification_required' ) );
$current_user          = wp_get_current_user();
$review_products       = function_exists( 'wc_get_products' )
	? wc_get_products(
		[
			'status'  => 'publish',
			'limit'   => -1,
			'orderby' => 'title',
			'order'   => 'ASC',
		]
	)
	: [];

$review_products = array_values(
	array_filter(
		$review_products,
		static function ( $product ) use ( $verified_only, $current_user ) {
			if ( ! $product instanceof WC_Product || ! comments_open( $product->get_id() ) ) {
				return false;
			}

			if ( ! $verified_only ) {
				return true;
			}

			return $current_user->exists()
				&& wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product->get_id() );
		}
	)
);
?>

<div class="grid-container">
	<nav class="breadcrumbs" aria-label="Хлебные крошки">
		<?php if ( function_exists( 'kama_breadcrumbs' ) ) : ?>
			<?php kama_breadcrumbs( " " ); ?>
		<?php endif; ?>
	</nav>
</div>

<div class="article-head">
	<div class="grid-container">
		<h1 class="article-head__title"><?php the_field('review_h1', get_the_ID()); ?></h1>
	</div>
</div>

<div class="grid-container">
	<div class="main">
		<div class="article page-reviews__description">
			<?php the_content(); ?>
		</div>

		<section class="page-reviews__form-card" aria-labelledby="review-form-title">
			<div class="page-reviews__form-heading">
				<span class="page-reviews__form-kicker"><?php esc_html_e( 'Ваше мнение важно', 'itsoft' ); ?></span>
				<h2 id="review-form-title"><?php esc_html_e( 'Оставить отзыв', 'itsoft' ); ?></h2>
				<p><?php esc_html_e( 'Выберите товар, поставьте оценку и расскажите о своём опыте. После проверки отзыв появится на сайте.', 'itsoft' ); ?></p>
			</div>

			<?php if ( ( $reviews_require_login || $verified_only ) && ! is_user_logged_in() ) : ?>
				<div class="page-reviews__form-notice">
					<?php
					printf(
						wp_kses_post( __( 'Отзывы могут оставлять авторизованные покупатели. <a href="%s">Войдите в аккаунт</a>, чтобы продолжить.', 'itsoft' ) ),
						esc_url( wc_get_page_permalink( 'myaccount' ) )
					);
					?>
				</div>
			<?php elseif ( empty( $review_products ) ) : ?>
				<div class="page-reviews__form-notice">
					<?php esc_html_e( 'Сейчас нет доступных товаров для отправки отзыва.', 'itsoft' ); ?>
				</div>
			<?php else : ?>
				<form class="page-reviews__form" action="<?php echo esc_url( site_url( '/wp-comments-post.php' ) ); ?>" method="post">
					<div class="page-reviews__form-grid">
						<label class="page-reviews__field page-reviews__field--wide" for="review-product">
							<span><?php esc_html_e( 'Товар', 'itsoft' ); ?> <b aria-hidden="true">*</b></span>
							<select id="review-product" name="comment_post_ID" required>
								<option value=""><?php esc_html_e( 'Выберите товар', 'itsoft' ); ?></option>
								<?php foreach ( $review_products as $review_product ) : ?>
									<option value="<?php echo esc_attr( $review_product->get_id() ); ?>"><?php echo esc_html( $review_product->get_name() ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>

						<?php if ( ! is_user_logged_in() ) : ?>
							<label class="page-reviews__field" for="review-author">
								<span><?php esc_html_e( 'Имя', 'itsoft' ); ?> <b aria-hidden="true">*</b></span>
								<input id="review-author" name="author" type="text" autocomplete="name" required>
							</label>
							<label class="page-reviews__field" for="review-email">
								<span><?php esc_html_e( 'E-mail', 'itsoft' ); ?> <b aria-hidden="true">*</b></span>
								<input id="review-email" name="email" type="email" autocomplete="email" required>
							</label>
						<?php endif; ?>

						<?php if ( function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled() ) : ?>
							<label class="page-reviews__field page-reviews__field--wide" for="review-rating">
								<span><?php esc_html_e( 'Оценка', 'itsoft' ); ?> <b aria-hidden="true">*</b></span>
								<select id="review-rating" name="rating" required>
									<option value=""><?php esc_html_e( 'Выберите оценку', 'itsoft' ); ?></option>
									<option value="5"><?php esc_html_e( '5 — Отлично', 'itsoft' ); ?></option>
									<option value="4"><?php esc_html_e( '4 — Хорошо', 'itsoft' ); ?></option>
									<option value="3"><?php esc_html_e( '3 — Нормально', 'itsoft' ); ?></option>
									<option value="2"><?php esc_html_e( '2 — Плохо', 'itsoft' ); ?></option>
									<option value="1"><?php esc_html_e( '1 — Очень плохо', 'itsoft' ); ?></option>
								</select>
							</label>
						<?php endif; ?>

						<label class="page-reviews__field page-reviews__field--wide" for="review-comment">
							<span><?php esc_html_e( 'Ваш отзыв', 'itsoft' ); ?> <b aria-hidden="true">*</b></span>
							<textarea id="review-comment" name="comment" rows="6" placeholder="<?php esc_attr_e( 'Что вам понравилось? Насколько удобно было оформить и получить заказ?', 'itsoft' ); ?>" required></textarea>
						</label>
					</div>

					<input type="hidden" name="comment_parent" value="0">
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() . '#all-reviews' ); ?>">
					<button class="page-reviews__submit" type="submit"><?php esc_html_e( 'Отправить отзыв', 'itsoft' ); ?></button>
				</form>
			<?php endif; ?>
		</section>

		<div class="page-reviews__subtitle" id="all-reviews">
			<?php esc_html_e( 'Все отзывы', 'itsoft' ); ?>
		</div>

		<section class="product-reviews" <?php echo $container_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="product-reviews__list" data-reviews-list>
				<?php echo $reviews_context['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<footer class="product-reviews__footer">
				<div class="product-reviews__feedback" data-reviews-feedback aria-live="polite"></div>
				<button
					class="product-reviews__more"
					type="button"
					data-reviews-more
					<?php echo $reviews_context['has_more'] ? '' : 'hidden'; ?>
				>
					<?php esc_html_e( 'Показать все отзывы', 'itsoft' ); ?>
				</button>
			</footer>
		</section>
	</div>

	<aside class="aside">
		<?php
		$y_title    = get_field( 'yandex_review_title' );
		$y_desc     = get_field( 'yandex_review_desc' );
		$y_btn_text = get_field( 'yandex_review_btn_text' );
		$y_btn_link = get_field( 'yandex_review_btn_link' );

		if ( $y_title || $y_desc ) :
			?>
			<div class="product-extra__download product-extra__download--sidebar">
				<?php if ( $y_title ) : ?>
					<div class="product-extra__download-title"><?php echo esc_html( $y_title ); ?></div>
				<?php endif; ?>

				<?php if ( $y_desc ) : ?>
					<div class="product-extra__download-content">
						<?php echo wp_kses_post( $y_desc ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $y_btn_text && $y_btn_link ) : ?>
					<div class="product-extra__download-link">
						<a href="<?php echo esc_url( $y_btn_link ); ?>" class="product-extra__download-btn" target="_blank" rel="nofollow noopener">
							<?php echo esc_html( $y_btn_text ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php
		$i_title = get_field( 'info_block_title' );
		$i_text  = get_field( 'info_block_text' );

		if ( $i_title || $i_text ) :
			?>
			<div class="product-extra__download product-extra__download--sidebar">
				<?php if ( $i_title ) : ?>
					<div class="product-extra__download-title"><?php echo esc_html( $i_title ); ?></div>
				<?php endif; ?>

				<?php if ( $i_text ) : ?>
					<div class="product-extra__download-content">
						<?php echo wp_kses_post( $i_text ); ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</aside>
</div>

<?php get_footer(); ?>
