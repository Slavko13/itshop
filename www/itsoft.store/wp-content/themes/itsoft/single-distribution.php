<?php
/**
 * Single distribution page.
 *
 * @package itsoft
 */

get_header();

the_post();
$distribution_id = get_the_ID();
$files           = itsoft_dist_get_files( $distribution_id );
$summary         = itsoft_dist_get_summary( $distribution_id );
$gallery         = function_exists( 'get_field' ) ? get_field( 'gallery' ) : array();
$requirements    = function_exists( 'get_field' ) ? get_field( 'system_requirements' ) : array();
$steps           = function_exists( 'get_field' ) ? get_field( 'installation_steps' ) : array();
$before_install  = function_exists( 'get_field' ) ? trim( (string) get_field( 'before_installation' ) ) : '';
$product_obj     = function_exists( 'get_field' ) ? get_field( 'product_link' ) : null;
$linked_product  = $product_obj && function_exists( 'wc_get_product' ) ? wc_get_product( $product_obj->ID ) : null;
$primary_image   = has_post_thumbnail() ? get_the_post_thumbnail_url( $distribution_id, 'large' ) : '';
if ( ! $primary_image && is_array( $gallery ) && ! empty( $gallery[0]['sizes']['large'] ) ) {
	$primary_image = $gallery[0]['sizes']['large'];
}
?>
<main class="distribution-single distribution-surface">
	<div class="grid-container distribution-single__shell">
		<nav class="breadcrumbs distribution-breadcrumbs" aria-label="<?php esc_attr_e( 'Хлебные крошки', 'itsoft' ); ?>">
			<?php kama_breadcrumbs( ' ' ); ?>
		</nav>

		<header class="distribution-single-hero">
			<div class="distribution-single-hero__content">
				<h1><?php the_title(); ?></h1>
				<p><?php esc_html_e( 'Проверьте язык и архитектуру, скачайте нужный файл и выполните шаги установки ниже.', 'itsoft' ); ?></p>
				<div class="distribution-single-hero__meta">
					<span><?php esc_html_e( 'Обновлено', 'itsoft' ); ?>: <strong><?php echo esc_html( get_the_modified_date( 'd.m.Y' ) ); ?></strong></span>
					<span><?php esc_html_e( 'Вариантов', 'itsoft' ); ?>: <strong><?php echo esc_html( (string) count( array_filter( $files, static function ( $file ) { return 'active' === $file['status']; } ) ) ); ?></strong></span>
					<?php if ( ! empty( $summary['sources'] ) ) : ?><span><?php esc_html_e( 'Источник', 'itsoft' ); ?>: <strong><?php echo esc_html( implode( ', ', $summary['sources'] ) ); ?></strong></span><?php endif; ?>
				</div>
			</div>
			<?php if ( $primary_image ) : ?>
				<div class="distribution-single-hero__visual"><img src="<?php echo esc_url( $primary_image ); ?>" alt="" loading="eager"></div>
			<?php else : ?>
				<div class="distribution-single-hero__visual distribution-single-hero__visual--placeholder" aria-hidden="true">↓</div>
			<?php endif; ?>
		</header>

		<div class="distribution-single__layout">
			<div class="distribution-single__main">
				<section class="distribution-download-panel" aria-labelledby="distribution-files-title">
					<div class="distribution-download-panel__head">
						<div><span><?php esc_html_e( 'Шаг 1', 'itsoft' ); ?></span><h2 id="distribution-files-title"><?php esc_html_e( 'Выберите файл', 'itsoft' ); ?></h2></div>
						<p><?php esc_html_e( 'Постоянные ссылки проходят через безопасный внутренний маршрут; защищённые выдаются администратором на 24 часа.', 'itsoft' ); ?></p>
					</div>
					<?php if ( $files ) : ?>
						<div class="distribution-file-list">
							<?php foreach ( $files as $file ) : ?>
								<?php $is_available = 'active' === $file['status']; ?>
								<article class="distribution-file <?php echo $is_available ? '' : 'is-disabled'; ?>">
									<div class="distribution-file__main">
										<strong><?php echo esc_html( $file['label'] ?: __( 'Установочный файл', 'itsoft' ) ); ?></strong>
										<div class="distribution-file__tags">
											<?php if ( $file['language'] ) : ?><span><?php echo esc_html( $file['language'] ); ?></span><?php endif; ?>
											<?php if ( $file['architecture'] ) : ?><span><?php echo esc_html( strtoupper( $file['architecture'] ) ); ?></span><?php endif; ?>
											<?php if ( $file['file_format'] ) : ?><span><?php echo esc_html( strtoupper( $file['file_format'] ) ); ?></span><?php endif; ?>
											<?php if ( $file['file_size'] ) : ?><span><?php echo esc_html( itsoft_dist_format_size( $file['file_size'] ) ); ?></span><?php endif; ?>
										</div>
										<?php if ( $file['checksum_sha256'] ) : ?><code title="SHA-256">SHA-256: <?php echo esc_html( substr( $file['checksum_sha256'], 0, 16 ) ); ?>…</code><?php endif; ?>
									</div>
									<div class="distribution-file__action">
										<?php if ( ! $is_available ) : ?>
											<span class="distribution-file__unavailable"><?php esc_html_e( 'Временно недоступно', 'itsoft' ); ?></span>
										<?php elseif ( 'temporary' === $file['link_mode'] ) : ?>
											<span class="distribution-file__protected">🔒 <?php esc_html_e( 'Ссылка выдаётся на 24 часа', 'itsoft' ); ?></span>
										<?php else : ?>
											<a class="distribution-file__download" href="<?php echo esc_url( itsoft_dist_get_public_url( $file ) ); ?>" rel="nofollow"><?php esc_html_e( 'Скачать', 'itsoft' ); ?><span aria-hidden="true">↓</span></a>
										<?php endif; ?>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="distribution-empty"><h3><?php esc_html_e( 'Файлы проходят проверку', 'itsoft' ); ?></h3><p><?php esc_html_e( 'Мы не показываем кнопку, пока администратор не подтвердит корректный источник.', 'itsoft' ); ?></p></div>
					<?php endif; ?>
				</section>

				<section class="distribution-instructions" aria-labelledby="distribution-instructions-title">
					<div class="distribution-section-heading">
						<div><span><?php esc_html_e( 'Шаг 2', 'itsoft' ); ?></span><h2 id="distribution-instructions-title"><?php esc_html_e( 'Установка программы', 'itsoft' ); ?></h2></div>
					</div>
					<?php if ( $before_install ) : ?><div class="distribution-instructions__before"><?php echo wp_kses_post( wpautop( $before_install ) ); ?></div><?php endif; ?>
					<?php if ( is_array( $steps ) && $steps ) : ?>
						<ol class="distribution-steps">
							<?php foreach ( $steps as $index => $step ) : ?>
								<li class="distribution-step">
									<span class="distribution-step__number"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
									<div class="distribution-step__content">
										<h3><?php echo esc_html( $step['title'] ?? sprintf( __( 'Шаг %d', 'itsoft' ), $index + 1 ) ); ?></h3>
										<?php if ( ! empty( $step['text'] ) ) : ?><div><?php echo wp_kses_post( $step['text'] ); ?></div><?php endif; ?>
										<?php if ( ! empty( $step['image']['url'] ) ) : ?><img src="<?php echo esc_url( $step['image']['url'] ); ?>" alt="<?php echo esc_attr( $step['image']['alt'] ?? '' ); ?>" loading="lazy"><?php endif; ?>
										<?php if ( ! empty( $step['note'] ) ) : ?><aside class="distribution-step__note distribution-step__note--<?php echo esc_attr( $step['note_type'] ?? 'info' ); ?>"><?php echo esc_html( $step['note'] ); ?></aside><?php endif; ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php elseif ( trim( get_the_content() ) ) : ?>
						<div class="distribution-instructions__legacy-content"><?php the_content(); ?></div>
					<?php else : ?>
						<div class="distribution-empty"><h3><?php esc_html_e( 'Инструкция готовится', 'itsoft' ); ?></h3><p><?php esc_html_e( 'Перед установкой сверяйтесь с официальной документацией производителя.', 'itsoft' ); ?></p></div>
					<?php endif; ?>
				</section>
			</div>

			<aside class="distribution-single__aside">
				<?php if ( is_array( $requirements ) && $requirements ) : ?>
					<section class="distribution-side-card"><h2><?php esc_html_e( 'Системные требования', 'itsoft' ); ?></h2><dl>
						<?php foreach ( $requirements as $requirement ) : ?><div><dt><?php echo esc_html( $requirement['pc_part'] ?? '' ); ?></dt><dd><?php echo esc_html( $requirement['property'] ?? '' ); ?></dd></div><?php endforeach; ?>
					</dl></section>
				<?php endif; ?>
				<?php if ( $linked_product ) : ?>
					<section class="distribution-side-card distribution-side-card--product">
						<span><?php esc_html_e( 'Нужен ключ активации?', 'itsoft' ); ?></span>
						<h2><?php echo esc_html( $linked_product->get_name() ); ?></h2>
						<strong><?php echo wp_kses_post( $linked_product->get_price_html() ); ?></strong>
						<a href="<?php echo esc_url( get_permalink( $linked_product->get_id() ) ); ?>"><?php esc_html_e( 'Перейти к лицензии', 'itsoft' ); ?> →</a>
					</section>
				<?php endif; ?>
				<section class="distribution-side-card distribution-side-card--help"><h2><?php esc_html_e( 'Перед скачиванием', 'itsoft' ); ?></h2><ul><li><?php esc_html_e( 'Проверьте язык и разрядность системы.', 'itsoft' ); ?></li><li><?php esc_html_e( 'Сохраните важные данные.', 'itsoft' ); ?></li><li><?php esc_html_e( 'Используйте только подходящий ключ активации.', 'itsoft' ); ?></li></ul></section>
			</aside>
		</div>

		<?php if ( is_array( $gallery ) && count( $gallery ) > 1 ) : ?>
			<section class="distribution-gallery" aria-labelledby="distribution-gallery-title"><h2 id="distribution-gallery-title"><?php esc_html_e( 'Скриншоты', 'itsoft' ); ?></h2><div>
				<?php foreach ( array_slice( $gallery, 0, 6 ) as $index => $image ) : ?>
					<?php $image_label = sprintf( __( 'Открыть скриншот %1$d: %2$s', 'itsoft' ), $index + 1, get_the_title() ); ?>
					<a href="<?php echo esc_url( $image['url'] ); ?>" data-fancybox="distribution" aria-label="<?php echo esc_attr( $image_label ); ?>"><img src="<?php echo esc_url( $image['sizes']['medium_large'] ?? $image['url'] ); ?>" alt="<?php echo esc_attr( ! empty( $image['alt'] ) ? $image['alt'] : $image_label ); ?>" loading="lazy"></a>
				<?php endforeach; ?>
			</div></section>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/distribution-reviews' ); ?>
	</div>
</main>
<?php get_footer(); ?>
