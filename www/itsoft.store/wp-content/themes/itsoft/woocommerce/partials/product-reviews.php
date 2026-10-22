<?php
/**
 * Product reviews section.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! ( $product instanceof WC_Product ) ) {
	return;
}

$reviews_context = Itsoft_Product_Reviews::get_section_context( $product->get_id() );
$container_attrs = sprintf(
	'data-product-reviews data-product-id="%1$d" data-total="%2$d" data-loaded="%3$d" data-per-page="%4$d"',
	absint( $product->get_id() ),
	absint( $reviews_context['total'] ),
	absint( $reviews_context['loaded'] ),
	absint( $reviews_context['per_page'] )
);

?>
<section class="product-reviews" id="product-reviews" <?php echo $container_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="product-reviews__inner">
		<header class="product-reviews__heading">
			<div class="product-reviews__title"><?php esc_html_e( 'Отзывы о товаре', 'itsoft' ); ?></div>
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

		<?php if ( comments_open( $product->get_id() ) ) : ?>
			<?php
			$verification_required = ( 'yes' === get_option( 'woocommerce_review_rating_verification_required' ) );
			$can_submit_review     = ( ! $verification_required || wc_customer_bought_product( '', get_current_user_id(), $product->get_id() ) );
			?>
			<div class="product-reviews__form" id="review_form_wrapper" data-review-form hidden>
				<div class="product-reviews__form-header">
					<div class="product-reviews__form-title"><?php esc_html_e( 'Оставьте отзыв о товаре', 'itsoft' ); ?></div>
					<p class="product-reviews__form-subtitle">
						<?php esc_html_e( 'Ваш e-mail не будет опубликован. Поля со * обязательны для заполнения.', 'itsoft' ); ?>
					</p>
				</div>

				<?php if ( $can_submit_review ) : ?>
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
							'label_submit'         => esc_html__( 'Отправить отзыв', 'itsoft' ),
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

						if ( wc_review_ratings_enabled() ) {
							$comment_form['comment_field'] .= '<p class="comment-form-rating"><label for="rating">'
								. esc_html__( 'Ваша оценка', 'itsoft' );

							if ( wc_review_ratings_required() ) {
								$comment_form['comment_field'] .= '&nbsp;<span class="required">*</span>';
							}

							$comment_form['comment_field'] .= '</label><select name="rating" id="rating" '
								. ( wc_review_ratings_required() ? 'required' : '' )
								. '>
									<option value="">' . esc_html__( 'Выберите оценку…', 'itsoft' ) . '</option>
									<option value="5">' . esc_html__( 'Отлично', 'itsoft' ) . '</option>
									<option value="4">' . esc_html__( 'Хорошо', 'itsoft' ) . '</option>
									<option value="3">' . esc_html__( 'Средне', 'itsoft' ) . '</option>
									<option value="2">' . esc_html__( 'Плохо', 'itsoft' ) . '</option>
									<option value="1">' . esc_html__( 'Очень плохо', 'itsoft' ) . '</option>
								</select></p>';
						}

						$comment_form['comment_field'] .= '<p class="comment-form-comment"><label for="comment">'
							. esc_html__( 'Ваш отзыв', 'itsoft' )
							. '&nbsp;<span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="8" required></textarea></p>';

						$account_page_url = wc_get_page_permalink( 'myaccount' );
						if ( $account_page_url ) {
							$comment_form['must_log_in'] = '<p class="must-log-in">'
								. sprintf(
									esc_html__( 'Вы должны %1$sвойти%2$s, чтобы оставить отзыв.', 'itsoft' ),
									'<a href="' . esc_url( $account_page_url ) . '">',
									'</a>'
								)
								. '</p>';
						}

						comment_form( apply_filters( 'woocommerce_product_review_comment_form_args', $comment_form ), $product->get_id() );
						?>
					</div>
				<?php else : ?>
					<p class="product-reviews__verify-notice">
						<?php esc_html_e( 'Только авторизованные покупатели, совершившие заказ этого товара, могут оставлять отзывы.', 'itsoft' ); ?>
					</p>
				<?php endif; ?>
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
				<?php esc_html_e( 'Показать больше отзывов', 'itsoft' ); ?>
			</button>
		</footer>
	</div>
</section>

