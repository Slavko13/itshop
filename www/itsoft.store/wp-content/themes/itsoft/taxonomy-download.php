<?php
/**
 * Download taxonomy archive.
 *
 * @package itsoft
 */

get_header();

$current_term = get_queried_object();
$term_desc_full = $current_term instanceof WP_Term ? term_description( $current_term->term_id, 'download' ) : '';
$term_desc      = $term_desc_full ? wp_trim_words( wp_strip_all_tags( $term_desc_full ), 38, '…' ) : '';
$child_terms  = $current_term instanceof WP_Term
	? get_terms( array( 'taxonomy' => 'download', 'parent' => $current_term->term_id, 'hide_empty' => true ) )
	: array();
?>
<main class="distribution-taxonomy distribution-surface">
	<div class="grid-container distribution-taxonomy__shell">
		<nav class="breadcrumbs distribution-breadcrumbs" aria-label="<?php esc_attr_e( 'Хлебные крошки', 'itsoft' ); ?>">
			<?php kama_breadcrumbs( ' ' ); ?>
		</nav>

		<header class="distribution-page-head">
			<h1><?php single_term_title(); ?></h1>
			<?php if ( $term_desc ) : ?>
				<p class="distribution-page-head__description"><?php echo esc_html( $term_desc ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Выберите подходящую версию, язык и архитектуру. На странице программы доступны системные требования и инструкция.', 'itsoft' ); ?></p>
			<?php endif; ?>
		</header>

		<div class="distribution-tools" data-distribution-tools>
			<label class="distribution-tools__search">
				<span class="screen-reader-text"><?php esc_html_e( 'Поиск в категории', 'itsoft' ); ?></span>
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.4-4.4m2.4-5.1a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
				<input type="search" data-distribution-search placeholder="<?php esc_attr_e( 'Найти программу или версию', 'itsoft' ); ?>">
			</label>
			<select class="distribution-tools__select" data-distribution-language aria-label="<?php esc_attr_e( 'Фильтр по языку', 'itsoft' ); ?>"><option value="all"><?php esc_html_e( 'Все языки', 'itsoft' ); ?></option></select>
			<select class="distribution-tools__select" data-distribution-architecture aria-label="<?php esc_attr_e( 'Фильтр по архитектуре', 'itsoft' ); ?>"><option value="all"><?php esc_html_e( 'Любая архитектура', 'itsoft' ); ?></option></select>
			<select class="distribution-tools__select" data-distribution-sort aria-label="<?php esc_attr_e( 'Сортировка', 'itsoft' ); ?>"><option value="modified"><?php esc_html_e( 'Сначала обновлённые', 'itsoft' ); ?></option><option value="title"><?php esc_html_e( 'По названию', 'itsoft' ); ?></option></select>
			<?php if ( ! is_wp_error( $child_terms ) && $child_terms ) : ?>
				<div class="distribution-filters" role="group" aria-label="<?php esc_attr_e( 'Фильтр версий', 'itsoft' ); ?>">
					<button type="button" class="is-active" data-distribution-filter="all" aria-pressed="true"><?php esc_html_e( 'Все версии', 'itsoft' ); ?></button>
					<?php foreach ( $child_terms as $child ) : ?>
						<button type="button" data-distribution-filter="<?php echo esc_attr( $child->slug ); ?>" aria-pressed="false"><?php echo esc_html( $child->name ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<span class="distribution-tools__count" data-distribution-count></span>
		</div>

		<?php if ( have_posts() ) : ?>
			<div class="distribution-grid" data-distribution-grid>
				<?php while ( have_posts() ) : the_post(); ?>
					<?php get_template_part( 'template-parts/distribution-card' ); ?>
				<?php endwhile; ?>
			</div>
			<div class="distribution-empty distribution-empty--filtered" data-distribution-empty hidden>
				<h2><?php esc_html_e( 'Подходящих файлов не найдено', 'itsoft' ); ?></h2>
				<p><?php esc_html_e( 'Очистите поиск или выберите «Все версии».', 'itsoft' ); ?></p>
			</div>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<div class="distribution-empty"><h2><?php esc_html_e( 'В этой категории пока нет доступных файлов', 'itsoft' ); ?></h2><p><?php esc_html_e( 'Мы добавим дистрибутивы после проверки источников.', 'itsoft' ); ?></p></div>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
