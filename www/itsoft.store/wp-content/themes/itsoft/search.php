<?php
/**
 * Search results.
 *
 * @package itsoft
 */

get_header();

global $wp_query;
$query_text = get_search_query( false );
?>
<main class="itsoft-search">
	<div class="grid-container">
		<?php if ( function_exists( 'kama_breadcrumbs' ) ) : ?>
			<nav class="breadcrumbs" aria-label="Хлебные крошки">
				<?php kama_breadcrumbs( ' ' ); ?>
			</nav>
		<?php endif; ?>

		<header class="itsoft-search__head">
			<div>
				<h1 class="itsoft-search__title">Результаты поиска</h1>
				<p class="itsoft-search__count">
					<?php echo esc_html( sprintf( 'По запросу «%s» найдено: %d', $query_text, (int) $wp_query->found_posts ) ); ?>
				</p>
			</div>
			<form class="itsoft-search__form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search">
				<label class="screen-reader-text" for="itsoft-search-results-input">Поиск</label>
				<input id="itsoft-search-results-input" class="itsoft-search__input" type="search" name="s" value="<?php echo esc_attr( $query_text ); ?>" />
				<button class="itsoft-search__submit btn" type="submit">Найти</button>
			</form>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="itsoft-search__grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<?php if ( 'product' === get_post_type() ) : ?>
						<?php
						$GLOBALS['product'] = wc_get_product( get_the_ID() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
						wc_get_template_part( 'content', 'product-card' );
						?>
					<?php else : ?>
						<article class="itsoft-search-article">
							<span class="itsoft-search-article__type">Статья</span>
							<h2 class="itsoft-search-article__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p class="itsoft-search-article__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24 ) ); ?></p>
						</article>
					<?php endif; ?>
				<?php endwhile; ?>
			</div>

			<nav class="itsoft-search__pagination" aria-label="Страницы результатов">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'total'   => (int) $wp_query->max_num_pages,
							'current' => max( 1, (int) get_query_var( 'paged' ) ),
						)
					)
				);
				?>
			</nav>
		<?php else : ?>
			<p>Ничего не найдено. Проверьте написание или попробуйте более короткий запрос.</p>
		<?php endif; ?>
	</div>
</main>
<?php
wp_reset_postdata();
get_footer();
