<?php
/**
 * Search results.
 *
 * @package itsoft
 */

get_header();

global $wp_query;
$query_text     = trim( get_search_query( false ) );
$has_query_text = '' !== $query_text;
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
				<h1 class="itsoft-search__title"><?php echo $has_query_text ? 'Результаты поиска' : 'Поиск по каталогу'; ?></h1>
				<p class="itsoft-search__count">
					<?php
					if ( $has_query_text ) {
						echo esc_html( sprintf( 'По запросу «%s» найдено: %d', $query_text, (int) $wp_query->found_posts ) );
					} else {
						echo esc_html__( 'Введите название программы, версии или лицензии.', 'itsoft' );
					}
					?>
				</p>
			</div>
			<form class="itsoft-search__form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search">
				<label class="screen-reader-text" for="itsoft-search-results-input">Поиск</label>
				<span class="itsoft-search__icon" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.6" />
						<path d="M13.5 13.5L18 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
					</svg>
				</span>
				<input id="itsoft-search-results-input" class="itsoft-search__input" type="search" name="s" value="<?php echo esc_attr( $query_text ); ?>" placeholder="Найти программу или лицензию" required />
				<button class="itsoft-search__submit btn" type="submit">
					<span>Найти</span>
					<span class="itsoft-search__submit-arrow" aria-hidden="true">→</span>
				</button>
			</form>
		</header>

		<?php if ( ! $has_query_text ) : ?>
			<section class="itsoft-search__empty" aria-labelledby="itsoft-empty-search-title">
				<span class="itsoft-search__empty-icon" aria-hidden="true">
					<svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.7" />
						<path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
					</svg>
				</span>
				<h2 id="itsoft-empty-search-title">Что будем искать?</h2>
				<p>Например: Windows 11, Office 2021 или Microsoft Visio.</p>
			</section>
		<?php elseif ( have_posts() ) : ?>
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

			<?php if ( (int) $wp_query->max_num_pages > 1 ) : ?>
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
			<?php endif; ?>
		<?php else : ?>
			<section class="itsoft-search__empty" aria-labelledby="itsoft-no-results-title">
				<span class="itsoft-search__empty-icon" aria-hidden="true">×</span>
				<h2 id="itsoft-no-results-title">Ничего не нашли</h2>
				<p>Проверьте написание или попробуйте более короткий запрос.</p>
				<a class="itsoft-search__catalog-link btn" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Перейти в каталог →</a>
			</section>
		<?php endif; ?>
	</div>
</main>
<?php
wp_reset_postdata();
get_footer();
