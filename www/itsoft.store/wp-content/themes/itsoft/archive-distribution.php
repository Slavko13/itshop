<?php
/**
 * Archive template for all distributions.
 *
 * @package itsoft
 */

get_header();
?>
<main class="distribution-archive distribution-surface">
	<div class="grid-container distribution-archive__shell">
		<nav class="breadcrumbs distribution-breadcrumbs" aria-label="<?php esc_attr_e( 'Хлебные крошки', 'itsoft' ); ?>">
			<?php kama_breadcrumbs( ' ' ); ?>
		</nav>
		<header class="distribution-page-head">
			<h1><?php post_type_archive_title(); ?></h1>
			<p><?php esc_html_e( 'Установочные файлы, системные требования и инструкции по установке в одном разделе.', 'itsoft' ); ?></p>
		</header>
		<div class="distribution-tools" data-distribution-tools>
			<label class="distribution-tools__search">
				<span class="screen-reader-text"><?php esc_html_e( 'Фильтр по названию', 'itsoft' ); ?></span>
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.4-4.4m2.4-5.1a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
				<input type="search" data-distribution-search placeholder="<?php esc_attr_e( 'Найти программу или версию', 'itsoft' ); ?>">
			</label>
			<select class="distribution-tools__select" data-distribution-language aria-label="<?php esc_attr_e( 'Фильтр по языку', 'itsoft' ); ?>"><option value="all"><?php esc_html_e( 'Все языки', 'itsoft' ); ?></option></select>
			<select class="distribution-tools__select" data-distribution-architecture aria-label="<?php esc_attr_e( 'Фильтр по архитектуре', 'itsoft' ); ?>"><option value="all"><?php esc_html_e( 'Любая архитектура', 'itsoft' ); ?></option></select>
			<select class="distribution-tools__select" data-distribution-sort aria-label="<?php esc_attr_e( 'Сортировка', 'itsoft' ); ?>"><option value="modified"><?php esc_html_e( 'Сначала обновлённые', 'itsoft' ); ?></option><option value="title"><?php esc_html_e( 'По названию', 'itsoft' ); ?></option></select>
			<span class="distribution-tools__count" data-distribution-count></span>
		</div>
		<?php if ( have_posts() ) : ?>
			<div class="distribution-grid" data-distribution-grid>
				<?php while ( have_posts() ) : the_post(); ?>
					<?php get_template_part( 'template-parts/distribution-card' ); ?>
				<?php endwhile; ?>
			</div>
			<div class="distribution-empty distribution-empty--filtered" data-distribution-empty hidden>
				<h2><?php esc_html_e( 'Ничего не найдено', 'itsoft' ); ?></h2>
				<p><?php esc_html_e( 'Измените запрос или очистите строку поиска.', 'itsoft' ); ?></p>
			</div>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<div class="distribution-empty"><h2><?php esc_html_e( 'Дистрибутивы пока не опубликованы', 'itsoft' ); ?></h2></div>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
