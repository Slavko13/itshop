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

		<div class="page-reviews__subtitle">
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
