<?php
/**
 * Product extra about block (content + FAQ + optional download).
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$product_id = isset( $args['product_id'] ) ? (int) $args['product_id'] : 0;

$content_context = isset( $args['content'] ) && is_array( $args['content'] ) ? $args['content'] : array();
$faq_context     = isset( $args['faq'] ) && is_array( $args['faq'] ) ? $args['faq'] : array();
$extra_context   = isset( $args['extra'] ) && is_array( $args['extra'] ) ? $args['extra'] : array();

$content_wrapper_class = isset( $extra_context['content_wrapper_class'] )
	? trim( (string) $extra_context['content_wrapper_class'] )
	: 'product-extra__content';
$content_wrapper_class = $content_wrapper_class ?: 'product-extra__content';

$has_content = ! empty( $content_context['has_content'] ) && ! empty( $content_context['rendered'] );
$content_html = $content_context['rendered'] ?? '';

$faq_items = isset( $faq_context['items'] ) && is_array( $faq_context['items'] ) ? $faq_context['items'] : array();
$has_faq   = ! empty( $faq_context['has_items'] ) && ! empty( $faq_items );

$has_about_sections = $has_content || $has_faq;
?>

<div class="<?php echo esc_attr( $content_wrapper_class ); ?>">
	<?php if ( $has_content ) : ?>
		<div class="product-extra__article">
			<h2 class="product-extra__article-title">Описание товара</h2>
			<div class="product-extra__article-content">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- content is pre-sanitized via the_content filter.
				echo $content_html;
				?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $has_faq ) : ?>
		<div class="product-extra__faq" data-product-faq>
			<h2 class="product-extra__faq-title">Вопросы и ответы</h2>
			<div class="product-extra__faq-list" data-faq-list>
				<?php foreach ( $faq_items as $index => $faq_item ) : ?>
					<?php
					$question = isset( $faq_item['question'] ) ? trim( (string) $faq_item['question'] ) : '';
					$answer   = isset( $faq_item['answer'] ) ? $faq_item['answer'] : '';

					$has_question_text = '' !== $question;
					$has_answer_text   = '' !== trim( wp_strip_all_tags( (string) $answer ) );

					if ( ! $has_question_text && ! $has_answer_text ) {
						continue;
					}

					$item_index  = (int) $index + 1;
					$is_active   = ( 1 === $item_index );
					$item_class  = 'product-extra__faq-item' . ( $is_active ? ' active' : '' );
					$object_id   = $product_id ?: get_the_ID();
					$question_id = sprintf( 'product-faq-question-%1$d-%2$d', $object_id, $item_index );
					$answer_id   = sprintf( 'product-faq-answer-%1$d-%2$d', $object_id, $item_index );
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
						<?php if ( $has_answer_text ) : ?>
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
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</div>


