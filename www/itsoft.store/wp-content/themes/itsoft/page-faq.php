<?php
/**
 * Template Name: FAQ
 *
 * @package itsoft
 */

get_header();
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
		<h1 class="article-head__title"><?php the_title(); ?></h1>
	</div>
</div>

<div class="grid-container">
	<div class="main" style="grid-column: span 12;">
		<div class="article page-content">
			<?php the_content(); ?>
		</div>

		<?php
		$faq_rows = get_field( 'faq' );
		if ( $faq_rows && is_array( $faq_rows ) ) :
			?>
			<div class="product-extra__faq" data-product-faq>
				<div class="product-extra__faq-list" data-faq-list>
					<?php
					foreach ( $faq_rows as $index => $faq_row ) :
						$question = isset( $faq_row['q'] ) ? trim( (string) $faq_row['q'] ) : '';
						$answer   = isset( $faq_row['a'] ) ? $faq_row['a'] : '';

						if ( ! $question && ! trim( wp_strip_all_tags( (string) $answer ) ) ) {
							continue;
						}

						$item_index  = $index + 1;
						// First item active by default
						$is_active   = ( 1 === $item_index );
						$item_class  = 'product-extra__faq-item' . ( $is_active ? ' active' : '' );
						$object_id   = get_the_ID();
						$question_id = sprintf( 'faq-question-%1$d-%2$d', $object_id, $item_index );
						$answer_id   = sprintf( 'faq-answer-%1$d-%2$d', $object_id, $item_index );
						?>
						<div class="<?php echo esc_attr( $item_class ); ?>" data-faq-item>
							<button
								type="button"
								class="product-extra__faq-question"
								id="<?php echo esc_attr( $question_id ); ?>"
								data-faq-trigger
								aria-controls="<?php echo esc_attr( $answer_id ); ?>"
								aria-expanded="<?php echo esc_attr( $is_active ? 'true' : 'false' ); ?>"
							>
								<span class="product-extra__faq-question-text">
									<?php echo esc_html( $question ); ?>
								</span>
								<span class="product-extra__faq-icon" aria-hidden="true">
									<svg class="product-extra__faq-icon-open" width="14" height="8" viewBox="0 0 14 8" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12.6406 0.768188L6.64062 5.76819L0.640625 0.768188" stroke="#6C4CFF" stroke-width="2" />
									</svg>
									<svg class="product-extra__faq-icon-close" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M0.707031 0.707153L6.95703 6.95715M13.207 13.2072L6.95703 6.95715M6.95703 6.95715L13.207 0.707153M6.95703 6.95715L0.707031 13.2072" stroke="white" stroke-width="2" />
									</svg>
								</span>
							</button>
							<div
								class="product-extra__faq-answer"
								id="<?php echo esc_attr( $answer_id ); ?>"
								role="region"
								aria-labelledby="<?php echo esc_attr( $question_id ); ?>"
								aria-hidden="<?php echo esc_attr( $is_active ? 'false' : 'true' ); ?>"
								data-faq-content
							>
								<div class="product-extra__faq-answer-inner">
									<?php echo wp_kses_post( $answer ); ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
