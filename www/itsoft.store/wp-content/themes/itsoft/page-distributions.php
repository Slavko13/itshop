<?php
/**
 * Template Name: Дистрибутивы
 *
 * @package itsoft
 */

get_header();

$download_terms = get_terms(
	array(
		'taxonomy'   => 'download',
		'parent'     => 0,
		'hide_empty' => false,
	)
);

$distribution_query = new WP_Query(
	array(
		'post_type'      => 'distribution',
		'post_status'    => 'publish',
		'posts_per_page' => 6,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	)
);

$published_count = (int) wp_count_posts( 'distribution' )->publish;
?>

<main class="downloads-hub distribution-surface">
	<div class="grid-container downloads-hub__shell">
		<nav class="breadcrumbs distribution-breadcrumbs" aria-label="<?php esc_attr_e( 'Хлебные крошки', 'itsoft' ); ?>">
			<?php kama_breadcrumbs( ' ' ); ?>
		</nav>

		<section class="downloads-hero">
			<div class="downloads-hero__content">
				<h1 class="downloads-hero__title">
					<?php
					$custom_h1 = function_exists( 'get_field' ) ? trim( (string) get_field( 'h1' ) ) : '';
					echo esc_html( $custom_h1 ?: get_the_title() );
					?>
				</h1>
				<p class="downloads-hero__lead"><?php esc_html_e( 'Выберите программу, скачайте подходящую версию и следуйте пошаговой инструкции по установке.', 'itsoft' ); ?></p>
				<form class="distribution-search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search">
					<label>
						<span class="screen-reader-text"><?php esc_html_e( 'Поиск дистрибутива', 'itsoft' ); ?></span>
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.4-4.4m2.4-5.1a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
						<input type="search" name="s" placeholder="<?php esc_attr_e( 'Например, Office 2021 или Visio', 'itsoft' ); ?>">
					</label>
					<input type="hidden" name="post_type" value="distribution">
					<button type="submit"><?php esc_html_e( 'Найти', 'itsoft' ); ?><span aria-hidden="true">→</span></button>
				</form>
			</div>
			<div class="downloads-hero__aside" aria-label="<?php esc_attr_e( 'Преимущества раздела', 'itsoft' ); ?>">
				<div><strong><?php echo esc_html( $published_count ); ?></strong><span><?php esc_html_e( 'дистрибутивов', 'itsoft' ); ?></span></div>
				<ul>
					<li><?php esc_html_e( 'Понятный выбор языка и архитектуры', 'itsoft' ); ?></li>
					<li><?php esc_html_e( 'Системные требования до скачивания', 'itsoft' ); ?></li>
					<li><?php esc_html_e( 'Инструкции по установке и активации', 'itsoft' ); ?></li>
				</ul>
			</div>
		</section>

		<?php if ( ! is_wp_error( $download_terms ) && $download_terms ) : ?>
			<section class="download-categories" aria-labelledby="download-categories-title">
				<div class="distribution-section-heading">
					<div><span><?php esc_html_e( 'Шаг 1', 'itsoft' ); ?></span><h2 id="download-categories-title"><?php esc_html_e( 'Выберите категорию', 'itsoft' ); ?></h2></div>
					<p><?php esc_html_e( 'Показываем только категории, в которых уже есть опубликованные материалы.', 'itsoft' ); ?></p>
				</div>
				<div class="download-categories__grid">
					<?php foreach ( $download_terms as $term ) : ?>
						<?php
						$count = itsoft_dist_get_term_count( $term->term_id );
						if ( $count <= 0 ) {
							continue;
						}
						$term_link = get_term_link( $term );
						$icon_url  = itsoft_dist_get_term_icon_url( $term->term_id );
						$description = function_exists( 'get_field' ) ? trim( (string) get_field( 'short_description', 'download_' . $term->term_id ) ) : '';
						if ( ! $description ) {
							$description = wp_trim_words( wp_strip_all_tags( term_description( $term->term_id, 'download' ) ), 15, '…' );
						}
						?>
						<a class="download-category-card" href="<?php echo esc_url( $term_link ); ?>">
							<span class="download-category-card__count"><?php echo esc_html( (string) $count ); ?></span>
							<span class="download-category-card__icon">
								<?php if ( $icon_url ) : ?><img src="<?php echo esc_url( $icon_url ); ?>" alt="" loading="lazy"><?php else : ?><span aria-hidden="true">↓</span><?php endif; ?>
							</span>
							<strong><?php echo esc_html( $term->name ); ?></strong>
							<?php if ( $description ) : ?><span class="download-category-card__description"><?php echo esc_html( $description ); ?></span><?php endif; ?>
							<span class="download-category-card__action"><?php esc_html_e( 'Смотреть программы', 'itsoft' ); ?> →</span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="distribution-latest" aria-labelledby="distribution-latest-title">
			<div class="distribution-section-heading">
				<div><span><?php esc_html_e( 'Шаг 2', 'itsoft' ); ?></span><h2 id="distribution-latest-title"><?php esc_html_e( 'Недавно обновлённые', 'itsoft' ); ?></h2></div>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'distribution' ) ); ?>"><?php esc_html_e( 'Все дистрибутивы', 'itsoft' ); ?> →</a>
			</div>
			<?php if ( $distribution_query->have_posts() ) : ?>
				<div class="distribution-grid">
					<?php while ( $distribution_query->have_posts() ) : $distribution_query->the_post(); ?>
						<?php get_template_part( 'template-parts/distribution-card' ); ?>
					<?php endwhile; ?>
				</div>
			<?php else : ?>
				<div class="distribution-empty"><h2><?php esc_html_e( 'Материалы готовятся', 'itsoft' ); ?></h2><p><?php esc_html_e( 'Опубликованные дистрибутивы появятся здесь.', 'itsoft' ); ?></p></div>
			<?php endif; wp_reset_postdata(); ?>
		</section>
	</div>
</main>

<?php get_footer(); ?>
