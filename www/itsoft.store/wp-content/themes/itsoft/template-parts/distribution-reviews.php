<?php
/**
 * Distribution reviews section.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$post_id = get_the_ID();
$reviews_context = Itsoft_Product_Reviews::get_section_context( $post_id );
$container_attrs = sprintf(
	'data-product-reviews data-product-id="%1$d" data-total="%2$d" data-loaded="%3$d" data-per-page="%4$d"',
	absint( $post_id ),
	absint( $reviews_context['total'] ),
	absint( $reviews_context['loaded'] ),
	absint( $reviews_context['per_page'] )
);

?>
<section class="product-reviews" id="product-reviews" <?php echo $container_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="product-reviews__inner">
		<header class="product-reviews__heading">
			<div class="product-reviews__title"><?php esc_html_e( 'Отзывы и комментарии', 'itsoft' ); ?></div>
			<a
				class="product-reviews__write"
				href="#review_form"
				data-review-form-toggle
				aria-controls="review_form_wrapper"
				aria-expanded="false"
			>
				<?php esc_html_e( 'Написать отзыв', 'itsoft' ); ?>
			</a>
		</header>

		<div class="product-reviews__list" data-reviews-list>
			<?php echo $reviews_context['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<?php if ( comments_open( $post_id ) ) : ?>
			<div class="product-reviews__form" id="review_form_wrapper" data-review-form hidden>
				<div class="product-reviews__form-header">
					<div class="product-reviews__form-title"><?php esc_html_e( 'Оставьте ваш отзыв или комментарий', 'itsoft' ); ?></div>
					<p class="product-reviews__form-subtitle">
						<?php esc_html_e( 'Ваш e-mail не будет опубликован. Поля со * обязательны для заполнения.', 'itsoft' ); ?>
					</p>
				</div>

				<div class="product-reviews__form-body" id="review_form">
					<?php
					$commenter    = wp_get_current_commenter();
					$comment_form = [
						'title_reply'          => '',
						'title_reply_to'       => esc_html__( 'Ответить %s', 'itsoft' ),
						'title_reply_before'   => '',
						'title_reply_after'    => '',
						'comment_notes_before' => '',
						'comment_notes_after'  => '',
						'label_submit'         => esc_html__( 'Отправить', 'itsoft' ),
						'class_submit'         => 'product-reviews__submit',
						'class_form'           => 'comment-form product-reviews__form-fields',
						'logged_in_as'         => '',
						'comment_field'        => '',
					];

					$name_email_required = (bool) get_option( 'require_name_email', 1 );
					$fields              = [
						'author' => [
							'label'        => esc_html__( 'Имя', 'itsoft' ),
							'type'         => 'text',
							'value'        => $commenter['comment_author'],
							'required'     => $name_email_required,
							'autocomplete' => 'name',
						],
						'email'  => [
							'label'        => esc_html__( 'E-mail', 'itsoft' ),
							'type'         => 'email',
							'value'        => $commenter['comment_author_email'],
							'required'     => $name_email_required,
							'autocomplete' => 'email',
						],
					];

					$comment_form['fields'] = [];

					foreach ( $fields as $key => $field ) {
						$field_html  = '<p class="comment-form-' . esc_attr( $key ) . '">';
						$field_html .= '<label for="' . esc_attr( $key ) . '">' . esc_html( $field['label'] );

						if ( $field['required'] ) {
							$field_html .= '&nbsp;<span class="required">*</span>';
						}

						$field_html .= '</label>';
						$field_html .= sprintf(
							'<input id="%1$s" name="%1$s" type="%2$s" autocomplete="%3$s" value="%4$s" size="30" %5$s/>',
							esc_attr( $key ),
							esc_attr( $field['type'] ),
							esc_attr( $field['autocomplete'] ),
							esc_attr( $field['value'] ),
							$field['required'] ? 'required' : ''
						);
						$field_html .= '</p>';

						$comment_form['fields'][ $key ] = $field_html;
					}

					$comment_form['comment_field'] .= '<p class="comment-form-comment"><label for="comment">'
						. esc_html__( 'Ваш комментарий', 'itsoft' )
						. '&nbsp;<span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="8" required></textarea></p>';

					comment_form( $comment_form, $post_id );
					?>
				</div>
			</div>
		<?php endif; ?>

		<footer class="product-reviews__footer">
			<div class="product-reviews__feedback" data-reviews-feedback aria-live="polite"></div>
			<button
				class="product-reviews__more"
				type="button"
				data-reviews-more
				<?php echo $reviews_context['has_more'] ? '' : 'hidden'; ?>
			>
				<?php esc_html_e( 'Показать больше', 'itsoft' ); ?>
			</button>
		</footer>
	</div>
</section>
