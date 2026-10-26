<?php
/**
 * Shared product archive layout for the shop, product categories and tags.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

global $wp_query;

$current_term     = get_queried_object();
$is_category_term = $current_term instanceof WP_Term && 'product_cat' === $current_term->taxonomy;
$is_tag_term      = $current_term instanceof WP_Term && 'product_tag' === $current_term->taxonomy;
$is_term          = $is_category_term || $is_tag_term;
$term_id          = $is_term ? (int) $current_term->term_id : 0;
$shop_url         = get_permalink( wc_get_page_id( 'shop' ) );
$term_link        = $is_term ? get_term_link( $current_term ) : $shop_url;
$term_link        = ! is_wp_error( $term_link ) ? $term_link : home_url( '/' );
$term_desc        = $is_term ? term_description( $term_id, $current_term->taxonomy ) : '';
$page_title       = $is_term ? single_term_title( '', false ) : esc_html__( 'Каталог', 'itsoft' );

$category_tree      = itsoft_get_catalog_category_tree();
$category_tree_html = itsoft_render_catalog_category_tree(
	$category_tree,
	array(
		'current_id' => $is_category_term ? $term_id : 0,
		'open_first' => ! $is_category_term,
	)
);

$search_value = get_search_query( false );
$paged        = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$max_pages    = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 1;
$has_products = woocommerce_product_loop();

$preserve_keys   = array( 'orderby', 'order', 'min_price', 'max_price' );
$preserved_query = array();

foreach ( $preserve_keys as $key ) {
	if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$preserved_query[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}

if ( $has_products ) {
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
	do_action( 'woocommerce_before_shop_loop' );
	add_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
	add_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
}

remove_action( 'woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', 10 );
do_action( 'woocommerce_shop_loop_header' );
add_action( 'woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', 10 );
?>

<main class="catalog">
	<div class="grid-container catalog__shell">
		<nav class="breadcrumbs catalog__breadcrumbs" aria-label="<?php esc_attr_e( 'Хлебные крошки', 'itsoft' ); ?>">
			<?php
			kama_breadcrumbs(
				' ',
				array(
					'paged' => '<li class="breadcrumbs__item breadcrumbs__item_current"><span class="breadcrumbs__current" itemprop="name">' . esc_html__( 'Страница %d', 'itsoft' ) . '</span></li>',
				)
			);
			?>
		</nav>

		<header class="catalog__hero">
			<div>
				<?php if ( $is_category_term && $current_term->parent ) : ?>
					<?php $parent_term = get_term( (int) $current_term->parent, 'product_cat' ); ?>
					<?php if ( $parent_term && ! is_wp_error( $parent_term ) ) : ?>
						<a class="catalog__parent" href="<?php echo esc_url( get_term_link( $parent_term ) ); ?>">
							<span aria-hidden="true">←</span>
							<?php echo esc_html( sprintf( __( 'Назад в %s', 'itsoft' ), $parent_term->name ) ); ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>
				<h1 class="catalog__title"><?php echo esc_html( $page_title ); ?></h1>
				<p class="catalog__lead">
					<?php
					if ( $is_tag_term ) {
						echo esc_html( sprintf( __( 'Лицензии и программы с меткой «%s». Электронные товары приходят на e-mail после оплаты.', 'itsoft' ), $current_term->name ) );
					} elseif ( $is_category_term ) {
						esc_html_e( 'Выберите подходящую лицензию — электронные товары приходят на e-mail после оплаты.', 'itsoft' );
					} else {
						esc_html_e( 'Лицензии и программное обеспечение для дома и бизнеса с понятным оформлением заказа.', 'itsoft' );
					}
					?>
				</p>
			</div>
		</header>

		<div class="catalog__layout">
			<aside class="catalog__sidebar catalog-sidebar" data-catalog-sidebar>
				<div class="catalog-sidebar__header">
					<p class="catalog-sidebar__title"><?php esc_html_e( 'Категории', 'itsoft' ); ?></p>
					<button class="catalog-sidebar__toggle" type="button" aria-expanded="false" aria-controls="catalog-sidebar-tree">
						<span class="catalog-sidebar__toggle-label"><?php esc_html_e( 'Открыть категории', 'itsoft' ); ?></span>
						<span class="catalog-sidebar__toggle-icon" aria-hidden="true">
							<?php echo itsoft_catalog_get_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
					</button>
				</div>

				<div class="catalog-sidebar__body" id="catalog-sidebar-tree">
					<?php if ( $category_tree_html ) : ?>
						<?php echo $category_tree_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php else : ?>
						<p class="catalog-sidebar__empty"><?php esc_html_e( 'Категории пока не добавлены.', 'itsoft' ); ?></p>
					<?php endif; ?>
				</div>
			</aside>

			<section class="catalog__main" aria-labelledby="catalog-products-heading">
				<h2 class="screen-reader-text" id="catalog-products-heading"><?php esc_html_e( 'Товары', 'itsoft' ); ?></h2>

				<div class="catalog__filters catalog-filters">
					<form class="catalog-filters__search" role="search" method="get" action="<?php echo esc_url( $term_link ); ?>">
						<label class="catalog-filters__field">
							<span class="catalog-filters__icon" aria-hidden="true">
								<?php echo itsoft_catalog_get_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
							<span class="screen-reader-text"><?php esc_html_e( 'Поиск по каталогу', 'itsoft' ); ?></span>
							<input class="catalog-filters__input" type="search" name="s" value="<?php echo esc_attr( $search_value ); ?>" placeholder="<?php esc_attr_e( 'Найти программу или лицензию', 'itsoft' ); ?>" autocomplete="off" />
						</label>
						<input type="hidden" name="post_type" value="product" />
						<?php if ( $is_category_term ) : ?>
							<input type="hidden" name="product_cat" value="<?php echo esc_attr( $current_term->slug ); ?>" />
						<?php elseif ( $is_tag_term ) : ?>
							<input type="hidden" name="product_tag" value="<?php echo esc_attr( $current_term->slug ); ?>" />
						<?php endif; ?>
						<?php foreach ( $preserved_query as $key => $value ) : ?>
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
						<?php endforeach; ?>
						<button class="catalog-filters__submit" type="submit" aria-label="<?php esc_attr_e( 'Найти', 'itsoft' ); ?>"><span aria-hidden="true">→</span></button>
					</form>

					<div class="catalog-filters__base">
						<div class="catalog-filters__results" aria-live="polite"><?php if ( $has_products ) { woocommerce_result_count(); } ?></div>
						<div class="catalog-filters__ordering"><?php if ( $has_products ) { woocommerce_catalog_ordering(); } ?></div>
					</div>
				</div>

				<?php if ( $has_products ) : ?>
					<div class="catalog-grid" data-catalog-grid="true" data-max-pages="<?php echo esc_attr( $max_pages ); ?>" data-current-page="<?php echo esc_attr( $paged ); ?>">
						<div class="catalog-grid__list">
							<?php $GLOBALS['itsoft_prioritize_next_catalog_image'] = 1 === (int) $paged; ?>
							<?php while ( have_posts() ) : ?>
								<?php the_post(); ?>
								<?php do_action( 'woocommerce_shop_loop' ); ?>
								<?php wc_get_template_part( 'content', 'product-card' ); ?>
							<?php endwhile; ?>
						</div>
						<div class="catalog-grid__status" role="status" aria-live="polite"></div>
						<button class="catalog-grid__more btn" type="button" hidden><?php esc_html_e( 'Показать ещё', 'itsoft' ); ?></button>
						<div class="catalog-grid__sentinel" aria-hidden="true"></div>
						<noscript class="catalog-grid__pagination"><?php woocommerce_pagination(); ?></noscript>
					</div>
				<?php else : ?>
					<div class="catalog-empty">
						<span class="catalog-empty__icon" aria-hidden="true">⌕</span>
						<h2><?php esc_html_e( 'Ничего не нашли', 'itsoft' ); ?></h2>
						<p><?php esc_html_e( 'Попробуйте изменить запрос или вернуться ко всем категориям.', 'itsoft' ); ?></p>
						<a class="btn catalog-empty__button" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Показать весь каталог', 'itsoft' ); ?></a>
					</div>
				<?php endif; ?>

				<?php if ( $term_desc ) : ?>
					<div class="catalog__description catalog__description--below"><?php echo wp_kses_post( wpautop( $term_desc ) ); ?></div>
				<?php endif; ?>
			</section>
		</div>
	</div>
</main>

<?php
if ( $has_products ) {
	remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
	do_action( 'woocommerce_after_shop_loop' );
	add_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
}

