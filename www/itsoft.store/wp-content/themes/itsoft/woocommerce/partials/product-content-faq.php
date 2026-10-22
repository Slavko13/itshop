<?php
/**
 * Product content + FAQ + additional info block.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$product = $args['product'] ?? null;

if ( ! $product instanceof WC_Product ) {
	return;
}

$has_acf       = function_exists( 'get_field' );
$faq_items_raw = $has_acf ? get_field( 'faq', $product->get_id() ) : array();
$faq_items     = array();

if ( is_array( $faq_items_raw ) ) {
	foreach ( $faq_items_raw as $faq_row ) {
		$question = isset( $faq_row['q'] ) ? (string) $faq_row['q'] : '';
		$answer   = isset( $faq_row['a'] ) ? (string) $faq_row['a'] : '';

		if ( '' === trim( $question ) && '' === trim( wp_strip_all_tags( $answer ) ) ) {
			continue;
		}

		$faq_items[] = array(
			'q' => $question,
			'a' => $answer,
		);
	}
}

$add_info = $has_acf ? get_field( 'add-info', $product->get_id() ) : array();
$add_info = is_array( $add_info ) ? $add_info : array();

$add_info_title = isset( $add_info['title'] ) ? trim( (string) $add_info['title'] ) : '';
$add_info_text  = isset( $add_info['text'] ) ? (string) $add_info['text'] : '';
$add_info_dist  = isset( $add_info['dist'] ) && is_array( $add_info['dist'] ) ? $add_info['dist'] : array();

$download_external = isset( $add_info_dist['ext-url'] ) ? trim( (string) $add_info_dist['ext-url'] ) : '';
$download_local    = isset( $add_info_dist['local'] ) ? trim( (string) $add_info_dist['local'] ) : '';
$download_url      = $download_external ? $download_external : $download_local;

$has_content = '' !== trim( get_post_field( 'post_content', $product->get_id() ) );
$has_faq     = ! empty( $faq_items );
$has_addinfo = $add_info_title || $add_info_text || $download_url;

if ( ! $has_content && ! $has_faq && ! $has_addinfo ) {
	return;
}
?>

<div class="product-row product-row--details">
	<?php if ( $has_content || $has_faq ) : ?>
		<div class="product-row__main">
			<?php if ( $has_content ) : ?>
				<section class="product-description" aria-label="<?php esc_attr_e( 'Описание товара', 'itsoft' ); ?>">
					<div class="product-description__heading">
						<p class="product-description__title"><?php esc_html_e( 'Описание', 'itsoft' ); ?></p>
					</div>
					<div class="product-description__content">
						<?php the_content(); ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $has_faq ) : ?>
				<section class="product-faq" aria-label="<?php esc_attr_e( 'Частые вопросы', 'itsoft' ); ?>">
					<div class="product-faq__heading">
						<p class="product-faq__title"><?php esc_html_e( 'Вопрос/ответ', 'itsoft' ); ?></p>
					</div>
					<div class="product-faq__list" data-accordion-group>
						<?php foreach ( $faq_items as $index => $faq_item ) : ?>
							<?php
							$faq_item_id = sprintf( 'product-faq-%d-%d', $product->get_id(), $index );
							?>
							<article class="product-faq__item" data-accordion-item>
								<div
									class="product-faq__question"
									data-accordion-control
									aria-controls="<?php echo esc_attr( $faq_item_id ); ?>"
									aria-expanded="false"
								>
									<span class="product-faq__question-text"><?php echo esc_html( $faq_item['q'] ); ?></span>
									<span class="product-faq__icon" aria-hidden="true"></span>
								</div>
								<div
									class="product-faq__answer"
									id="<?php echo esc_attr( $faq_item_id ); ?>"
									hidden
									data-accordion-content
								>
									<?php echo wp_kses_post( $faq_item['a'] ); ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $has_addinfo ) : ?>
		<aside class="product-row__aside product-add-info" aria-label="<?php esc_attr_e( 'Дополнительная информация к скачиванию', 'itsoft' ); ?>">
			<?php if ( $add_info_title ) : ?>
				<p class="product-add-info__title"><?php echo esc_html( $add_info_title ); ?></p>
			<?php endif; ?>

			<?php if ( $add_info_text ) : ?>
				<div class="product-add-info__text">
					<?php echo wpautop( wp_kses_post( $add_info_text ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $download_url ) : ?>
				<a
					class="product-add-info__button"
					href="<?php echo esc_url( $download_url ); ?>"
					target="_blank"
					rel="noreferrer noopener"
				>
					<?php esc_html_e( 'Скачать дистрибутив', 'itsoft' ); ?>
				</a>
			<?php endif; ?>
		</aside>
	<?php endif; ?>
</div>

