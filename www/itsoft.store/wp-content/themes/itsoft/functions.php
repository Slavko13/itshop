<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once get_template_directory() . '/inc/woocommerce/product-card-controller.php';
require_once get_template_directory() . '/inc/woocommerce/product-single-controller.php';
require_once get_template_directory() . '/inc/woocommerce/product-reviews.php';
require_once get_template_directory() . '/inc/woocommerce/catalog-template.php';
require_once get_template_directory() . '/inc/all-reviews.php';
require_once get_template_directory() . '/inc/distribution-post-type.php';
require_once get_template_directory() . '/inc/distribution-download-manager.php';
require_once get_template_directory() . '/customize.php';

/**
 * Keep the storefront source language predictable and use only the theme
 * switchers. GTranslate's browser detection and floating control otherwise
 * cover the mobile catalog button and may open the site in English on first
 * visit.
 */
add_action( 'after_setup_theme', 'itsoft_configure_gtranslate', 1 );
function itsoft_configure_gtranslate() {
	$configuration_version = '1';

	if ( get_option( 'itsoft_gtranslate_configuration_version' ) === $configuration_version ) {
		return;
	}

	$settings = get_option( 'GTranslate' );
	if ( ! is_array( $settings ) ) {
		return;
	}

	$settings['default_language']           = 'ru';
	$settings['detect_browser_language']    = 0;
	$settings['floating_language_selector'] = 'no';
	$settings['incl_langs']                 = array_values( array_unique( array_merge( (array) ( $settings['incl_langs'] ?? array() ), array( 'ru', 'en' ) ) ) );

	update_option( 'GTranslate', $settings );
	update_option( 'itsoft_gtranslate_configuration_version', $configuration_version, false );
}

/**
 * Keep Yoast organization metadata on the same brand logo as the storefront.
 * This migration also removes the legacy media-library logo URL restored by
 * older database backups.
 */
add_action( 'init', 'itsoft_sync_brand_logo_metadata', 20 );
function itsoft_sync_brand_logo_metadata() {
	$configuration_version = '2026-10-26-1';

	if ( get_option( 'itsoft_brand_logo_configuration_version' ) === $configuration_version ) {
		return;
	}

	$yoast_titles = get_option( 'wpseo_titles' );
	if ( is_array( $yoast_titles ) ) {
		$logo_path = get_template_directory() . '/assets/images/brand-logo.png';
		$logo_url  = get_template_directory_uri() . '/assets/images/brand-logo.png';

		$yoast_titles['company_logo']      = $logo_url;
		$yoast_titles['company_logo_id']   = 0;
		$yoast_titles['company_logo_meta'] = array(
			'width'    => 1200,
			'height'   => 279,
			'filesize' => file_exists( $logo_path ) ? filesize( $logo_path ) : 0,
			'url'      => $logo_url,
			'path'     => $logo_path,
			'size'     => 'full',
			'id'       => 0,
			'alt'      => 'Айтисофт',
			'pixels'   => 334800,
			'type'     => 'image/png',
		);

		update_option( 'wpseo_titles', $yoast_titles );
	}

	update_option( 'itsoft_brand_logo_configuration_version', $configuration_version, false );
}

/**
 * Keep unavailable products out of catalog archives and their result counts.
 * The direct product URL remains accessible.
 */
add_action( 'woocommerce_product_query', 'itsoft_catalog_available_products_only', 30 );
function itsoft_catalog_available_products_only( $query ) {
	if ( is_admin() || ! is_a( $query, 'WP_Query' ) ) {
		return;
	}

	$available_ids = itsoft_get_available_product_ids();
	$current_raw   = (array) $query->get( 'post__in' );
	$current_ids   = array_values( array_filter( array_map( 'absint', $current_raw ) ) );

	if ( $current_raw ) {
		$available_ids = $current_ids
			? array_values( array_intersect( $current_ids, $available_ids ) )
			: array();
	}

	$query->set( 'post__in', $available_ids ?: array( 0 ) );
}

/*--------------------------------------------------------------
# 1. Базовые настройки темы
--------------------------------------------------------------*/
add_action( 'after_setup_theme', 'itsoft_setup' );
function itsoft_setup() {

    // 1.1 Миниатюры постов и страниц
    add_theme_support( 'post-thumbnails' );

    // 1.2 Поддержка меню
    register_nav_menus( [
        'top'            => esc_html__( 'Верхнее меню', 'itsoft' ),
        'primary'        => esc_html__( 'Главное меню', 'itsoft' ),
        'footer'         => esc_html__( 'Меню в подвале', 'itsoft' ),
        'footer_catalog' => esc_html__( 'Подвал: Каталог', 'itsoft' ),
        'footer_services'=> esc_html__( 'Подвал: Сервисы', 'itsoft' ),
        'footer_about'   => esc_html__( 'Подвал: О магазине', 'itsoft' ),
    ] );

    // 1.3 HTML5-разметка для стандартных элементов
    add_theme_support( 'html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ] );

    // 1.4 Автоматический <title>
    add_theme_support( 'title-tag' );

    // 1.5 Responsive embed (видео, iframe)
    add_theme_support( 'responsive-embeds' );

    // 1.6 Ширина контента (нужна для гутенберга)
    global $content_width;
    if ( ! isset( $content_width ) ) {
        $content_width = 1280;
    }

	add_theme_support('woocommerce');

	// Remove default WooCommerce breadcrumbs
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0 );

}

add_filter( 'nav_menu_css_class', 'itsoft_footer_menu_item_class', 10, 4 );
function itsoft_footer_menu_item_class( $classes, $item, $args, $depth ) {
    if ( ! empty( $args->theme_location ) && strpos( $args->theme_location, 'footer_' ) === 0 ) {
        $classes[] = 'extra-nav__item';
    }

    return $classes;
}

add_filter( 'nav_menu_link_attributes', 'itsoft_footer_menu_link_attrs', 10, 4 );
function itsoft_footer_menu_link_attrs( $atts, $item, $args, $depth ) {
    if ( ! empty( $args->theme_location ) && strpos( $args->theme_location, 'footer_' ) === 0 ) {
        $existing = isset( $atts['class'] ) ? $atts['class'] : '';
        $atts['class'] = trim( $existing . ' extra-nav__link' );
    }

    return $atts;
}

add_filter( 'nav_menu_css_class', 'itsoft_top_menu_item_class', 10, 4 );
function itsoft_top_menu_item_class( $classes, $item, $args, $depth ) {
    if ( isset( $args->theme_location ) && 'top' === $args->theme_location ) {
        $classes[] = 'topnav__item';
        if ( 1 === $item->menu_order ) {
            $classes[] = 'topnav__item-first';
        }
    }
    return $classes;
}

add_filter( 'nav_menu_link_attributes', 'itsoft_top_menu_link_attrs', 10, 4 );
function itsoft_top_menu_link_attrs( $atts, $item, $args, $depth ) {
    if ( isset( $args->theme_location ) && 'top' === $args->theme_location ) {
        $existing = isset( $atts['class'] ) ? $atts['class'] : '';
        $atts['class'] = trim( $existing . ' topnav__link' );
    }
    return $atts;
}

// Remove the default WooCommerce product images and replace with our custom gallery
add_action( 'init', 'itsoft_remove_woocommerce_gallery' );
function itsoft_remove_woocommerce_gallery() {
    remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
    remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
    add_action( 'woocommerce_before_single_product_summary', 'itsoft_custom_product_gallery', 20 );
}

function itsoft_custom_product_gallery() {
    wc_get_template_part( 'single-product/product-gallery', 'custom' );
}

/*--------------------------------------------------------------
# 2. Отключаем лишнее
--------------------------------------------------------------*/
add_action( 'init', 'itsoft_cleanup' );
function itsoft_cleanup() {
    // 2.1 Emoji
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );

    // 2.2 Глобальные стили гутенберга на фронте
    add_filter( 'should_load_separate_core_block_assets', '__return_true' );

    // 2.3 Убираем версию WP
    remove_action( 'wp_head', 'wp_generator' );
}

/**
 * Return a precise validation message when the login form is submitted empty.
 * WooCommerce otherwise collapses some failed cases into a misleading
 * "incorrect credentials" notice.
 */
add_filter( 'woocommerce_process_login_errors', 'itsoft_validate_required_login_fields', 10, 3 );
function itsoft_validate_required_login_fields( $validation_error, $username, $password ) {
	if ( '' !== trim( (string) $username ) && '' !== trim( (string) $password ) ) {
		return $validation_error;
	}

	$validation_error = new WP_Error();

	if ( '' === trim( (string) $username ) && '' === trim( (string) $password ) ) {
		$validation_error->add( 'itsoft_required_login_fields', __( 'Заполните обязательные поля: e-mail или имя пользователя и пароль.', 'itsoft' ) );
	} elseif ( '' === trim( (string) $username ) ) {
		$validation_error->add( 'itsoft_required_username', __( 'Заполните поле «E-mail или имя пользователя».', 'itsoft' ) );
	} else {
		$validation_error->add( 'itsoft_required_password', __( 'Заполните поле «Пароль».', 'itsoft' ) );
	}

	return $validation_error;
}

/**
 * Do not let browsers heuristically cache dynamic pages in the local build.
 * Production caching remains untouched.
 */
add_filter( 'wp_headers', 'itsoft_local_nocache_headers', PHP_INT_MAX );
function itsoft_local_nocache_headers( $headers ) {
    if ( 'local' !== wp_get_environment_type() || is_admin() ) {
        return $headers;
    }

    $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
    $headers['Expires']       = 'Wed, 11 Jan 1984 05:00:00 GMT';
    $headers['Pragma']        = 'no-cache';
    unset( $headers['Last-Modified'] );

    return $headers;
}

/*--------------------------------------------------------------
# 3. Подключаем CSS и JS
--------------------------------------------------------------*/
add_action( 'wp_enqueue_scripts', 'itsoft_assets' );
function itsoft_assets() {
    $ver = wp_get_theme()->get( 'Version' ); // кешируем по версии темы
	$store_api_root = trailingslashit( get_rest_url( null, 'wc/store/v1' ) );
	$main_style_path = get_template_directory() . '/assets/css/main.css';

    // 3.1 Основной CSS
    wp_enqueue_style(
        'itsoft-style',
		get_template_directory_uri() . '/assets/css/main.css', [], file_exists( $main_style_path ) ? (string) filemtime( $main_style_path ) : $ver
    );

	if (
		function_exists( 'is_cart' )
		&& (
			is_cart()
			|| is_checkout()
			|| is_page_template( 'page-cart.php' )
			|| is_page_template( 'page-checkout.php' )
		)
	) {
		$cart_style_path = get_template_directory() . '/assets/css/cart.css';
		wp_enqueue_style(
			'itsoft-cart',
			get_template_directory_uri() . '/assets/css/cart.css',
			array( 'itsoft-style' ),
			file_exists( $cart_style_path ) ? (string) filemtime( $cart_style_path ) : $ver
		);
	}

	if ( function_exists( 'is_cart' ) && ( is_cart() || is_page_template( 'page-cart.php' ) ) ) {
		$cart_page_script_path = get_template_directory() . '/assets/js/cart-page.js';
		wp_enqueue_script(
			'itsoft-cart-page',
			get_template_directory_uri() . '/assets/js/cart-page.js',
			array( 'jquery', 'wc-cart' ),
			file_exists( $cart_page_script_path ) ? (string) filemtime( $cart_page_script_path ) : $ver,
			true
		);
	}

	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		$account_style_path = get_template_directory() . '/assets/css/account.css';
		$account_script_path = get_template_directory() . '/assets/js/account.js';

		wp_enqueue_style(
			'itsoft-account',
			get_template_directory_uri() . '/assets/css/account.css',
			array( 'itsoft-style' ),
			file_exists( $account_style_path ) ? (string) filemtime( $account_style_path ) : $ver
		);

		wp_enqueue_script(
			'itsoft-account',
			get_template_directory_uri() . '/assets/js/account.js',
			array(),
			file_exists( $account_script_path ) ? (string) filemtime( $account_script_path ) : $ver,
			true
		);
	}

    if ( is_front_page() ) {
		$popular_style_path = get_template_directory() . '/assets/css/popular-products.css';
        wp_enqueue_style(
            'itsoft-popular-products',
			get_template_directory_uri() . '/assets/css/popular-products.css',
			array( 'itsoft-style' ),
			file_exists( $popular_style_path ) ? (string) filemtime( $popular_style_path ) : $ver
        );
    }

	$is_storefront_view        = is_front_page()
		|| ( function_exists( 'is_shop' ) && is_shop() )
		|| is_tax( array( 'product_cat', 'product_tag' ) );
	$needs_storefront_styles   = $is_storefront_view || is_search();

	if ( $needs_storefront_styles ) {
		$storefront_style_path = get_template_directory() . '/assets/css/storefront-redesign.css';
		$storefront_style_dependencies = array( 'itsoft-style' );

		if ( is_front_page() ) {
			$storefront_style_dependencies[] = 'itsoft-popular-products';
		}

		wp_enqueue_style(
			'itsoft-storefront-redesign',
			get_template_directory_uri() . '/assets/css/storefront-redesign.css',
			$storefront_style_dependencies,
			file_exists( $storefront_style_path ) ? (string) filemtime( $storefront_style_path ) : $ver
		);
	}

	if ( $is_storefront_view ) {
		$storefront_script_path = get_template_directory() . '/assets/js/catalog-page.js';

		wp_enqueue_script(
			'itsoft-catalog-page',
			get_template_directory_uri() . '/assets/js/catalog-page.js',
			array( 'jquery', 'wc-add-to-cart' ),
			file_exists( $storefront_script_path ) ? (string) filemtime( $storefront_script_path ) : $ver,
			true
		);

		global $wp_query;
		$current_page = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$max_pages    = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 1;

		wp_localize_script(
			'itsoft-catalog-page',
			'ITSOFT_CATALOG_PAGE',
			array(
				'currentPage' => $current_page,
				'maxPages'    => $max_pages,
				'i18n'        => array(
					'loading'       => esc_html__( 'Загружаем товары…', 'itsoft' ),
					'error'         => esc_html__( 'Не удалось загрузить товары. Попробуйте ещё раз.', 'itsoft' ),
					'completed'     => '',
					'loadMore'      => esc_html__( 'Показать ещё', 'itsoft' ),
					'openCategories'=> esc_html__( 'Открыть категории', 'itsoft' ),
					'closeCategories'=> esc_html__( 'Скрыть категории', 'itsoft' ),
					'adding'        => esc_html__( 'Добавляем…', 'itsoft' ),
					'added'         => esc_html__( 'В корзине', 'itsoft' ),
				),
			)
		);
	}

	wp_enqueue_script(
		'matchHeigh-script',
		get_template_directory_uri() . '/assets/js/jquery.matchHeight-min.js', ['jquery'], $ver, true
	);

	if ( is_product() ) {
		$product_redesign_style_path = get_template_directory() . '/assets/css/product-redesign.css';
		$product_redesign_script_path = get_template_directory() . '/assets/js/product-redesign.js';

		wp_enqueue_style(
			'itsoft-product-redesign',
			get_template_directory_uri() . '/assets/css/product-redesign.css',
			array( 'itsoft-style' ),
			file_exists( $product_redesign_style_path ) ? (string) filemtime( $product_redesign_style_path ) : $ver
		);
		/* Swiper */
		wp_enqueue_style ( 'swiper-css' , get_template_directory_uri() . '/assets/css/swiper-bundle.min.css', [], '12.0.3');
		wp_enqueue_style( 'itsoft-product-upsells', get_template_directory_uri() . '/assets/css/product-upsells.css', [], $ver );
		wp_enqueue_script( 'swiper-js' ,  get_template_directory_uri() . '/assets/js/swiper-bundle.min.js',  [], '12.0.3', true);
	
		
		/* Product Slider */
		wp_enqueue_style ( 'product-slider' , get_template_directory_uri() . '/assets/css/product-slider.css', ['swiper-css'], '1.0');
		wp_enqueue_script( 'product-slider' , get_template_directory_uri() . '/assets/js/product-slider.js',   ['swiper-js'], '1.0', true);

		/* Fancybox */
		wp_enqueue_style ( 'fanccybox-css' , get_template_directory_uri() . '/assets/css/fancybox.css', [], '6.1');
		wp_enqueue_script( 'fanccybox-js' ,  get_template_directory_uri() . '/assets/js/fancybox.umd.js',  [], '6.1', true);

		wp_enqueue_script(
			'itsoft-product-page',
			get_template_directory_uri() . '/assets/js/product-page.js',
			[],
			$ver,
			true
		);
		wp_enqueue_script(
			'itsoft-product-redesign',
			get_template_directory_uri() . '/assets/js/product-redesign.js',
			array( 'itsoft-product-page' ),
			file_exists( $product_redesign_script_path ) ? (string) filemtime( $product_redesign_script_path ) : $ver,
			true
		);

		$product_id = get_queried_object_id();

		wp_localize_script(
			'itsoft-product-page',
			'ITSOFT_PRODUCT_PAGE',
			[
				'productId' => $product_id,
				'wcAjaxUrl'   => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'wcAjaxNonce' => wp_create_nonce( 'itsoft_cart' ),
				'storeApi'  => [
					'root'  => esc_url_raw( $store_api_root ),
					'nonce'   => wp_create_nonce( 'wc_store_api' ),
					'wpNonce' => wp_create_nonce( 'wp_rest' ),
				],
				'i18n'     => [
					'added'           => esc_html__( 'Товар добавлен в корзину', 'itsoft' ),
					'updateError'     => esc_html__( 'Не удалось обновить корзину. Попробуйте еще раз.', 'itsoft' ),
					'selectVariation' => esc_html__( 'Выберите вариацию товара', 'itsoft' ),
				],
			]
		);
	}

	$is_distribution_view = is_singular( 'distribution' )
		|| is_post_type_archive( 'distribution' )
		|| is_tax( 'download' )
		|| is_page_template( 'page-distributions.php' );

	if ( $is_distribution_view ) {
		$distribution_style_path = get_template_directory() . '/assets/css/distribution-redesign.css';
		$distribution_script_path = get_template_directory() . '/assets/js/distribution-redesign.js';

		wp_enqueue_style(
			'itsoft-distribution-redesign',
			get_template_directory_uri() . '/assets/css/distribution-redesign.css',
			array( 'itsoft-style' ),
			file_exists( $distribution_style_path ) ? (string) filemtime( $distribution_style_path ) : $ver
		);
		wp_enqueue_script(
			'itsoft-distribution-redesign',
			get_template_directory_uri() . '/assets/js/distribution-redesign.js',
			array(),
			file_exists( $distribution_script_path ) ? (string) filemtime( $distribution_script_path ) : $ver,
			true
		);

		if ( is_singular( 'distribution' ) ) {
			wp_enqueue_style( 'fanccybox-css', get_template_directory_uri() . '/assets/css/fancybox.css', array(), '6.1' );
			wp_enqueue_script( 'fanccybox-js', get_template_directory_uri() . '/assets/js/fancybox.umd.js', array(), '6.1', true );
		}
	}

	if ( is_product() || is_page_template( 'page-faq.php' ) ) {
		wp_enqueue_script(
			'itsoft-faq-accordion',
			get_template_directory_uri() . '/assets/js/faq-accordion.js',
			[],
			$ver,
			true
		);

		if ( is_page_template( 'page-faq.php' ) ) {
			$faq_page_style_path = get_template_directory() . '/assets/css/page-faq.css';

			wp_enqueue_style(
				'itsoft-page-faq',
				get_template_directory_uri() . '/assets/css/page-faq.css',
				array( 'itsoft-style' ),
				file_exists( $faq_page_style_path ) ? (string) filemtime( $faq_page_style_path ) : $ver
			);
		}
	}

	if ( is_category() || is_page_template( 'page-all-posts.php' ) ) {
		global $wp_query;

		$current_page = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$max_pages    = 1;

		if ( is_category() && isset( $wp_query->max_num_pages ) ) {
			$max_pages = (int) $wp_query->max_num_pages;
		}

		wp_enqueue_script(
			'itsoft-blog-page',
			get_template_directory_uri() . '/assets/js/blog-page.js',
			array(),
			$ver,
			true
		);

		wp_localize_script(
			'itsoft-blog-page',
			'ITSOFT_BLOG_PAGE',
			array(
				'currentPage' => $current_page,
				'maxPages'    => $max_pages,
				'i18n'        => array(
					'loading'   => esc_html__( 'Загружаем статьи…', 'itsoft' ),
					'error'     => esc_html__( 'Не удалось загрузить статьи. Попробуйте ещё раз.', 'itsoft' ),
					'completed' => esc_html__( 'Больше нет статей.', 'itsoft' ),
				),
			)
		);
	}

	// The shared token layer is intentionally enqueued after all page-specific CSS.
	$design_system_path = get_template_directory() . '/assets/css/design-system.css';
	wp_enqueue_style(
		'itsoft-design-system',
		get_template_directory_uri() . '/assets/css/design-system.css',
		array( 'itsoft-style' ),
		file_exists( $design_system_path ) ? (string) filemtime( $design_system_path ) : $ver
	);

	$brand_refresh_path = get_template_directory() . '/assets/css/brand-refresh.css';
	wp_enqueue_style(
		'itsoft-brand-refresh',
		get_template_directory_uri() . '/assets/css/brand-refresh.css',
		array( 'itsoft-design-system' ),
		file_exists( $brand_refresh_path ) ? (string) filemtime( $brand_refresh_path ) : $ver
	);

	$main_script_path = get_template_directory() . '/assets/js/main.js';
    wp_enqueue_script(
        'itsoft-script',
        get_template_directory_uri() . '/assets/js/main.js',
        [],
		file_exists( $main_script_path ) ? (string) filemtime( $main_script_path ) : $ver,
        true
    );

	$search_suggestions_path = get_template_directory() . '/assets/js/search-suggestions.js';
	wp_enqueue_script(
		'itsoft-search-suggestions',
		get_template_directory_uri() . '/assets/js/search-suggestions.js',
		array( 'itsoft-script' ),
		file_exists( $search_suggestions_path ) ? (string) filemtime( $search_suggestions_path ) : $ver,
		true
	);

	$search_products = array();
	if ( function_exists( 'wc_get_products' ) ) {
		$product_ids = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => -1,
				'orderby' => 'title',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! itsoft_is_product_available_for_sale( $product ) || ! in_array( $product->get_catalog_visibility(), array( 'visible', 'search' ), true ) ) {
				continue;
			}

			$terms      = get_the_terms( $product_id, 'product_cat' );
			$term_names = array();
			$term_slugs = array();
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_names[] = $term->name;
					$term_slugs[] = $term->slug;
				}
			}

			$image_url = $product->get_image_id()
				? wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' )
				: '';
			if ( ! $image_url ) {
				$image_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
			}

			$search_products[] = array(
				'id'        => $product->get_id(),
				'name'      => wp_strip_all_tags( $product->get_name() ),
				'url'       => esc_url_raw( get_permalink( $product->get_id() ) ),
				'image'     => esc_url_raw( $image_url ),
				'priceHtml' => wp_kses_post( $product->get_price_html() ),
				'category'  => (string) ( $term_names[0] ?? '' ),
				'search'    => implode(
					' ',
					array_filter(
						array_merge(
							array(
								$product->get_name(),
								$product->get_slug(),
								$product->get_sku(),
								get_post_meta( $product->get_id(), '_itsoft_search_synonyms', true ),
							),
							$term_names,
							$term_slugs
						)
					)
				),
			);
		}
	}

	$search_aliases = class_exists( 'ITSoft_LF_Search' )
		? ITSoft_LF_Search::client_aliases()
		: array(
			'виндовс' => 'windows',
			'виндоус' => 'windows',
			'офис'    => 'office',
		);

	wp_localize_script(
		'itsoft-search-suggestions',
		'ITSOFT_SEARCH_SUGGESTIONS',
		array(
			'products' => $search_products,
			'aliases'  => $search_aliases,
			'i18n'     => array(
				'productsLabel' => esc_html__( 'Товары', 'itsoft' ),
				'noResults'     => esc_html__( 'Подходящие товары не найдены', 'itsoft' ),
				'allResults'    => esc_html__( 'Показать все результаты', 'itsoft' ),
				'openProduct'   => esc_html__( 'Открыть товар', 'itsoft' ),
			),
		)
	);

	$english_copy_path = get_template_directory() . '/assets/js/english-copy.js';
	wp_enqueue_script(
		'itsoft-english-copy',
		get_template_directory_uri() . '/assets/js/english-copy.js',
		array( 'itsoft-script' ),
		file_exists( $english_copy_path ) ? (string) filemtime( $english_copy_path ) : $ver,
		true
	);

	wp_enqueue_script(
		'itsoft-cart-badge',
		get_template_directory_uri() . '/assets/js/cart-badge.js',
		array( 'jquery' ),
		$ver,
		true
	);

	$cart_count = 0;

	if ( function_exists( 'WC' ) ) {
		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( WC()->cart ) {
			$cart_count = (int) WC()->cart->get_cart_contents_count();
		}
	}

	wp_localize_script(
		'itsoft-cart-badge',
		'ITSOFT_CART_BADGE',
		array(
			'initialCount' => $cart_count,
			'storeApi'     => array(
				'root'   => esc_url_raw( $store_api_root ),
				'nonce'  => wp_create_nonce( 'wc_store_api' ),
				'wpNonce'=> wp_create_nonce( 'wp_rest' ),
			),
		)
	);
}

/**
 * Keep theme assets cache-safe even when an optimisation plugin strips ?ver=.
 * Curio SEO removes the standard WordPress version argument at priority 15.
 */
function itsoft_theme_asset_cache_buster( $src, $handle ) {
	$theme_uri_path = wp_parse_url( trailingslashit( get_template_directory_uri() ), PHP_URL_PATH );
	$asset_path     = wp_parse_url( $src, PHP_URL_PATH );

	if ( ! $theme_uri_path || ! $asset_path || 0 !== strpos( $asset_path, $theme_uri_path ) ) {
		return $src;
	}

	$relative_path = ltrim( substr( $asset_path, strlen( $theme_uri_path ) ), '/' );
	$file_path     = get_template_directory() . '/' . $relative_path;

	if ( ! is_file( $file_path ) ) {
		return $src;
	}

	return add_query_arg( 'itsoft_v', (string) filemtime( $file_path ), remove_query_arg( 'itsoft_v', $src ) );
}
add_filter( 'style_loader_src', 'itsoft_theme_asset_cache_buster', 999, 2 );
add_filter( 'script_loader_src', 'itsoft_theme_asset_cache_buster', 999, 2 );

/**
 * Adds a product and returns the small cart shape used by the product page.
 *
 * WooCommerce's stock AJAX response renders all cart fragments, while the
 * Store API follow-up boots WordPress a second time. Returning the required
 * state here keeps the classic WooCommerce session and needs one request.
 */
function itsoft_ajax_add_to_cart() {
	check_ajax_referer( 'itsoft_cart', 'nonce' );

	if ( ! function_exists( 'WC' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Корзина временно недоступна.', 'itsoft' ) ), 503 );
	}

	if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	$quantity   = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1;
	$product    = $product_id ? wc_get_product( $product_id ) : false;

	if ( ! $product || $quantity <= 0 ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Не удалось определить товар.', 'itsoft' ) ), 400 );
	}

	$cart_product_id = $product_id;
	$variation_id    = 0;
	$variation       = array();

	if ( $product instanceof WC_Product_Variation ) {
		$variation_id    = $product_id;
		$cart_product_id = $product->get_parent_id();
		$variation       = $product->get_variation_attributes();
	}

	$passed_validation = apply_filters(
		'woocommerce_add_to_cart_validation',
		true,
		$cart_product_id,
		$quantity,
		$variation_id,
		$variation
	);

	$cart_key = $passed_validation
		? WC()->cart->add_to_cart( $cart_product_id, $quantity, $variation_id, $variation )
		: false;

	if ( ! $cart_key ) {
		$notices = wc_get_notices( 'error' );
		$message = ! empty( $notices[0]['notice'] )
			? wp_strip_all_tags( $notices[0]['notice'] )
			: esc_html__( 'Не удалось добавить товар в корзину.', 'itsoft' );

		wc_clear_notices();
		wp_send_json_error( array( 'message' => $message ), 400 );
	}

	WC()->cart->calculate_totals();
	WC()->cart->set_session();

	if ( WC()->session ) {
		WC()->session->set_customer_session_cookie( true );
	}

	$items = array();

	foreach ( WC()->cart->get_cart() as $key => $item ) {
		$items[] = array(
			'id'       => ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : (int) $item['product_id'],
			'key'      => (string) $key,
			'quantity' => (float) $item['quantity'],
		);
	}

	do_action( 'woocommerce_ajax_added_to_cart', $cart_product_id );

	wp_send_json_success(
		array(
			'cart' => array(
				'items'       => $items,
				'items_count' => (int) WC()->cart->get_cart_contents_count(),
			),
		)
	);
}
add_action( 'wp_ajax_itsoft_add_to_cart', 'itsoft_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_itsoft_add_to_cart', 'itsoft_ajax_add_to_cart' );

/**
 * Builds a checkout URL that adds the product without opening its detail page.
 * For variable products the first available/default variation is used.
 *
 * @param WC_Product $product Product shown in a catalog card.
 * @return string
 */
function itsoft_get_quick_buy_url( $product ) {
	if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return '';
	}

	$args = array(
		'add-to-cart'    => $product->get_id(),
		'quantity'       => 1,
		'itsoft-buy-now' => 1,
	);

	if ( $product instanceof WC_Product_Variable ) {
		$selected_variation = null;

		foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
			if ( $variation instanceof WC_Product_Variation && $variation->is_purchasable() && $variation->is_in_stock() ) {
				$selected_variation = $variation;
				break;
			}
		}

		if ( ! $selected_variation ) {
			return '';
		}

		$args['variation_id'] = $selected_variation->get_id();
		$defaults             = $product->get_default_attributes();
		$available_attributes = $product->get_variation_attributes();

		foreach ( $selected_variation->get_variation_attributes() as $attribute_name => $attribute_value ) {
			$taxonomy_name = preg_replace( '/^attribute_/', '', $attribute_name );
			$value         = $attribute_value;

			if ( '' === $value && isset( $defaults[ $taxonomy_name ] ) ) {
				$value = $defaults[ $taxonomy_name ];
			}

			if ( '' === $value && ! empty( $available_attributes[ $taxonomy_name ] ) ) {
				$value = reset( $available_attributes[ $taxonomy_name ] );
			}

			if ( '' === (string) $value ) {
				return '';
			}

			$args[ $attribute_name ] = $value;
		}
	} elseif ( ! $product instanceof WC_Product_Simple ) {
		return '';
	}

	return add_query_arg( $args, wc_get_checkout_url() );
}

/**
 * Advertise the native WordPress sitemap to search crawlers.
 *
 * The URL is environment-aware, so local/staging copies never publish the
 * production hostname and production does not need a manual robots.txt edit.
 */
add_filter( 'robots_txt', 'itsoft_add_sitemap_to_robots', 20, 2 );
function itsoft_add_sitemap_to_robots( $output, $is_public ) {
	if ( ! $is_public || false !== stripos( $output, 'Sitemap:' ) ) {
		return $output;
	}

	return rtrim( $output ) . "\nSitemap: " . esc_url_raw( home_url( '/wp-sitemap.xml' ) ) . "\n";
}

/*--------------------------------------------------------------
# 4. (Опционально) Добавлять свои image-размеры
--------------------------------------------------------------*/
add_action( 'after_setup_theme', 'itsoft_image_sizes' );
function itsoft_image_sizes() {
    add_image_size( 'aside-news', 596, 384, true );
    add_image_size( 'category-post', 784, 460, ['center', 'top'] );
    add_image_size( 'product-image', 862, 788 );
    add_image_size( 'product-slide', 120, 135, ['center', 'top'] );
    add_image_size( 'recommend-thumb', 288, 168, ['left', 'top'] );
    add_image_size( 'distribution-thumb', 80, 90, true );
}

/**
 * Return a native branded cover for editorial cards.
 *
 * A small topic-based set keeps every article card visually consistent while
 * avoiding embedded copy that could be cropped at responsive breakpoints.
 */
function itsoft_get_article_cover_url( $post_id = 0 ) {
	$post_id = $post_id ? absint( $post_id ) : get_the_ID();
	$title   = wp_strip_all_tags( get_the_title( $post_id ) );
	$cover   = 'license-security-v1.jpg';

	if ( preg_match( '/office|microsoft\s*365|офис/iu', $title ) ) {
		$cover = 'office-activation-v1.jpg';
	} elseif ( preg_match( '/установ|активир|без\s+интернет|перенест|windows\s*10\s*\/\s*11/iu', $title ) ) {
		$cover = 'windows-setup-v1.jpg';
	}

	return get_template_directory_uri() . '/assets/images/article-covers/' . $cover;
}

/**
 * Render a consistently sized article cover.
 */
function itsoft_article_thumbnail( $post_id = 0, $class_name = 'item-posts__thumb', $loading = 'lazy' ) {
	$post_id = $post_id ? absint( $post_id ) : get_the_ID();
	$title   = wp_strip_all_tags( get_the_title( $post_id ) );
	$loading = 'eager' === $loading ? 'eager' : 'lazy';

	printf(
		'<img class="%1$s" src="%2$s" width="1672" height="941" alt="%3$s" loading="%4$s" decoding="async">',
		esc_attr( $class_name ),
		esc_url( itsoft_get_article_cover_url( $post_id ) ),
		esc_attr( sprintf( __( 'Обложка статьи «%s»', 'itsoft' ), $title ) ),
		esc_attr( $loading )
	);
}

add_filter('acf/settings/save_json', function ($path) {
    return get_stylesheet_directory() . '/acf-json';
});

// Разрешаем ACF читать JSON из этой папки
add_filter('acf/settings/load_json', function ($paths) {
    unset($paths[0]);

    $paths[] = get_stylesheet_directory() . '/acf-json';

    return $paths;
});

add_filter( 'loop_shop_per_page', 'itsoft_catalog_products_per_page', 20 );
function itsoft_catalog_products_per_page( $per_page ) {
	return 9;
}

add_filter( 'loop_shop_columns', 'itsoft_catalog_loop_columns' );
function itsoft_catalog_loop_columns( $columns ) {
	return 3;
}

/**
 * The shop sells electronic licenses only. Some imported products still have
 * the WooCommerce virtual flag disabled, so shipping is disabled centrally.
 */
add_filter( 'woocommerce_product_needs_shipping', 'itsoft_disable_product_shipping', 1000, 2 );
function itsoft_disable_product_shipping( $needs_shipping, $product ) {
	return false;
}

add_filter( 'woocommerce_cart_needs_shipping', '__return_false', 1000 );
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false', 1000 );

add_filter( 'woocommerce_checkout_fields', 'itsoft_require_checkout_contact_fields', 999 );
function itsoft_require_checkout_contact_fields( $fields ) {
	$physical_fields = array( 'billing_company', 'billing_country', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode' );

	foreach ( $physical_fields as $field_key ) {
		if ( isset( $fields['billing'][ $field_key ] ) ) {
			unset( $fields['billing'][ $field_key ] );
		}
	}

	$fields['shipping'] = array();

	foreach ( array( 'billing_email', 'billing_phone' ) as $field_key ) {
		if ( isset( $fields['billing'][ $field_key ] ) ) {
			$fields['billing'][ $field_key ]['required'] = true;
		}
	}

	return $fields;
}

add_filter( 'option_woocommerce_checkout_phone_field', 'itsoft_force_required_checkout_phone_field', 999 );
function itsoft_force_required_checkout_phone_field( $value ) {
	return 'required';
}

add_action( 'woocommerce_checkout_process', 'itsoft_validate_required_checkout_contacts' );
function itsoft_validate_required_checkout_contacts() {
	$billing_email = isset( $_POST['billing_email'] ) ? wc_clean( wp_unslash( $_POST['billing_email'] ) ) : '';
	$billing_phone = isset( $_POST['billing_phone'] ) ? wc_clean( wp_unslash( $_POST['billing_phone'] ) ) : '';

	if ( '' === $billing_email ) {
		wc_add_notice( __( 'Пожалуйста, укажите email.', 'itsoft' ), 'error' );
	}

	if ( '' === $billing_phone ) {
		wc_add_notice( __( 'Пожалуйста, укажите телефон.', 'itsoft' ), 'error' );
	}
}

add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'itsoft_validate_store_api_required_phones', 10, 2 );
function itsoft_validate_store_api_required_phones( $order, $request ) {
	// Checkout Blocks sends PUT requests while the customer is still filling the form.
	// Required-field errors belong only to the final POST ("Place order").
	if ( 'POST' !== strtoupper( (string) $request->get_method() ) ) {
		return;
	}

	$billing_address = (array) $request->get_param( 'billing_address' );
	$billing_phone   = wc_clean( wp_unslash( $billing_address['phone'] ?? '' ) );

	if ( '' === $billing_phone && class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
		throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
			'itsoft_required_billing_phone',
			__( 'Пожалуйста, укажите телефон.', 'itsoft' ),
			400
		);
	}

}

add_shortcode( 'posts', 'dummy_posts_shortcode' );

function dummy_posts_shortcode( $atts ) {

    shortcode_atts( [ 'ids' => '' ], $atts );

    $out = '
    <div class="plate-products">
  
    <div class="plate-products__item">

        <div class="plate-products__title">
        <a href="/product/123" class="plate-products__link">Windows 11 Pro (Профессиональная)</a>
        </div>


        <div class="plate-products__price">
        <span class="plate-products__price-current">1900 ₽</span>
        <span class="plate-products__price-old">3500₽</span>
        </div>


        <div class="plate-products__rating">
        <span class="plate-products__rating-icon" aria-hidden="true">
        <svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M7.94701 1.04069C7.98226 0.954713 8.04228 0.881166 8.11944 0.829403C8.19661 0.77764 8.28743 0.75 8.38034 0.75C8.47326 0.75 8.56408 0.77764 8.64125 0.829403C8.71841 0.881166 8.77843 0.954713 8.81368 1.04069L10.5845 5.29985C10.6177 5.37955 10.6722 5.44856 10.742 5.49928C10.8118 5.55 10.8943 5.58048 10.9803 5.58735L15.5787 5.95569C15.9945 5.98902 16.1628 6.50819 15.8462 6.77902L12.3428 9.78069C12.2774 9.83668 12.2286 9.90961 12.2019 9.99148C12.1751 10.0734 12.1714 10.161 12.1912 10.2449L13.262 14.7324C13.2835 14.8224 13.2779 14.9168 13.2458 15.0036C13.2137 15.0904 13.1566 15.1658 13.0817 15.2202C13.0068 15.2746 12.9175 15.3056 12.825 15.3092C12.7325 15.3129 12.641 15.289 12.562 15.2407L8.62451 12.8365C8.55099 12.7916 8.4665 12.7678 8.38034 12.7678C8.29419 12.7678 8.2097 12.7916 8.13618 12.8365L4.19868 15.2415C4.11973 15.2898 4.02822 15.3137 3.93573 15.31C3.84323 15.3064 3.75389 15.2754 3.67898 15.221C3.60408 15.1666 3.54698 15.0913 3.51489 15.0044C3.4828 14.9176 3.47716 14.8232 3.49868 14.7332L4.56951 10.2449C4.58937 10.161 4.58572 10.0733 4.55896 9.99144C4.5322 9.90954 4.48337 9.83662 4.41784 9.78069L0.914512 6.77902C0.843964 6.71888 0.792851 6.63916 0.767645 6.54995C0.742439 6.46074 0.744275 6.36606 0.772919 6.27789C0.801564 6.18973 0.855729 6.11205 0.928554 6.05469C1.00138 5.99733 1.08959 5.96288 1.18201 5.95569L5.78034 5.58735C5.86639 5.58048 5.94886 5.55 6.0187 5.49928C6.08854 5.44856 6.14303 5.37955 6.17618 5.29985L7.94701 1.04069Z" fill="#FDEB00" stroke="#FCE700" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        </span>
        <span class="plate-products__rating-value">5.5</span>
        </div>


        <div class="plate-products__reviews">
        <span class="plate-products__reviews-icon" aria-hidden="true">
        <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M8.25 14.5C12.3917 14.5 15.75 11.4217 15.75 7.625C15.75 3.82833 12.3917 0.75 8.25 0.75C4.10833 0.75 0.75 3.82833 0.75 7.625C0.75 9.37833 1.46583 10.9775 2.64417 12.1917C3.00417 12.5642 3.26083 13.0583 3.1325 13.5592C2.99198 14.1068 2.72898 14.6154 2.36333 15.0467C2.65595 15.0992 2.9527 15.1254 3.25 15.125C4.31833 15.125 5.30833 14.79 6.12083 14.2192C6.79583 14.4025 7.51083 14.5 8.25 14.5Z" stroke="#6C4CFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        </span>
        <span class="plate-products__reviews-count">1059</span>
        </div>


        <button class="plate-products__buy" type="button">Купить ключ</button>
    </div>

    <div class="plate-products__item">

        <div class="plate-products__title">
        <a href="/product/123" class="plate-products__link">Windows 10 Pro (Профессиональная)</a>
        </div>


        <div class="plate-products__price">
        <span class="plate-products__price-current">1650 ₽</span>
        <span class="plate-products__price-old">2500₽</span>
        </div>


        <div class="plate-products__rating">
        <span class="plate-products__rating-icon" aria-hidden="true">
        <svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M7.94701 1.04069C7.98226 0.954713 8.04228 0.881166 8.11944 0.829403C8.19661 0.77764 8.28743 0.75 8.38034 0.75C8.47326 0.75 8.56408 0.77764 8.64125 0.829403C8.71841 0.881166 8.77843 0.954713 8.81368 1.04069L10.5845 5.29985C10.6177 5.37955 10.6722 5.44856 10.742 5.49928C10.8118 5.55 10.8943 5.58048 10.9803 5.58735L15.5787 5.95569C15.9945 5.98902 16.1628 6.50819 15.8462 6.77902L12.3428 9.78069C12.2774 9.83668 12.2286 9.90961 12.2019 9.99148C12.1751 10.0734 12.1714 10.161 12.1912 10.2449L13.262 14.7324C13.2835 14.8224 13.2779 14.9168 13.2458 15.0036C13.2137 15.0904 13.1566 15.1658 13.0817 15.2202C13.0068 15.2746 12.9175 15.3056 12.825 15.3092C12.7325 15.3129 12.641 15.289 12.562 15.2407L8.62451 12.8365C8.55099 12.7916 8.4665 12.7678 8.38034 12.7678C8.29419 12.7678 8.2097 12.7916 8.13618 12.8365L4.19868 15.2415C4.11973 15.2898 4.02822 15.3137 3.93573 15.31C3.84323 15.3064 3.75389 15.2754 3.67898 15.221C3.60408 15.1666 3.54698 15.0913 3.51489 15.0044C3.4828 14.9176 3.47716 14.8232 3.49868 14.7332L4.56951 10.2449C4.58937 10.161 4.58572 10.0733 4.55896 9.99144C4.5322 9.90954 4.48337 9.83662 4.41784 9.78069L0.914512 6.77902C0.843964 6.71888 0.792851 6.63916 0.767645 6.54995C0.742439 6.46074 0.744275 6.36606 0.772919 6.27789C0.801564 6.18973 0.855729 6.11205 0.928554 6.05469C1.00138 5.99733 1.08959 5.96288 1.18201 5.95569L5.78034 5.58735C5.86639 5.58048 5.94886 5.55 6.0187 5.49928C6.08854 5.44856 6.14303 5.37955 6.17618 5.29985L7.94701 1.04069Z" fill="#FDEB00" stroke="#FCE700" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        </span>
        <span class="plate-products__rating-value">5.0</span>
        </div>


        <div class="plate-products__reviews">
        <span class="plate-products__reviews-icon" aria-hidden="true">
        <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M8.25 14.5C12.3917 14.5 15.75 11.4217 15.75 7.625C15.75 3.82833 12.3917 0.75 8.25 0.75C4.10833 0.75 0.75 3.82833 0.75 7.625C0.75 9.37833 1.46583 10.9775 2.64417 12.1917C3.00417 12.5642 3.26083 13.0583 3.1325 13.5592C2.99198 14.1068 2.72898 14.6154 2.36333 15.0467C2.65595 15.0992 2.9527 15.1254 3.25 15.125C4.31833 15.125 5.30833 14.79 6.12083 14.2192C6.79583 14.4025 7.51083 14.5 8.25 14.5Z" stroke="#6C4CFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        </span>
        <span class="plate-products__reviews-count">753</span>
        </div>


        <button class="plate-products__buy" type="button">Купить ключ</button>
    </div>


    </div>
    ';

    return $out;
}


function kama_breadcrumbs( $sep = '', $l10n = [], $args = [] ) {
    $kb = new Kama_Breadcrumbs;
    echo $kb->get_crumbs( $sep, $l10n, $args );
}

class Kama_Breadcrumbs {

	public $arg;

	// Localisation
	static $l10n = [
		'home'       => 'Главная',
		'paged'      => 'Страница %d',
		'_404'       => 'Ошибка 404',
		'search'     => 'Результаты поиска по запросу - <b>%s</b>',
		'author'     => 'Архив автора: <b>%s</b>',
		'year'       => 'Архив за <b>%d</b> год',
		'month'      => 'Архив за: <b>%s</b>',
		'day'        => '',
		'attachment' => 'Медиа: %s',
		'tag'        => 'Записи по метке: <b>%s</b>',
		'tax_tag'    => '%1$s из "%2$s" по тегу: <b>%3$s</b>',
		// tax_tag will output: 'post_type from "taxonomy_name" by tag: term_name'.
		// If separate placeholders are needed, for example, only the term name, write like this: 'posts by tag: %3$s'
	];

	// Default parameters
	static $args = [
		// Display breadcrumbs on the front page
		'on_front_page'   => true,
		// Show the post title at the end (last element). For posts, pages, attachments
		'show_post_title' => true,
		// Show the taxonomy item title at the end (last element). For tags, categories, and other taxonomies
		'show_term_title' => true,
		// Template for the last title. If enabled: show_post_title or show_term_title
		'title_patt'      => '<li class="breadcrumbs__item breadcrumbs__item_current"><span class="breadcrumbs__current" itemprop="name">%s</span></li>',
		// Show the last separator when the title at the end is not displayed
		'last_sep'        => true,
		// 'markup' - microdata. Can be: 'rdf.data-vocabulary.org', 'schema.org', '' - without microdata
		// Or you can specify your own markup array:
		// array( 'wrappatt'=>'<div class="kama_breadcrumbs">%s</div>', 'linkpatt'=>'<a href="%s">%s</a>', 'sep_after'=>'', )
		'markup'          => 'schema.org',
		// Priority taxonomies, needed when a post is in multiple taxonomies
		'priority_tax'    => [ 'category' ],
		// 'priority_terms' - priority items of taxonomies when a post is in multiple items of the same taxonomy at the same time.
		// For example: array( 'category'=>array(45,'term_name'), 'tax_name'=>array(1,2,'name') )
		// 'category' - taxonomy for which priority items are specified: 45 - term ID and 'term_name' - label.
		// The order of 45 and 'term_name' matters: the earlier, the more important. All specified terms are more important than unspecified ones...
		'priority_terms'  => [],
		// Add rel=nofollow to links?
		'nofollow'        => false,

		// Service
		'sep'             => '',
		'linkpatt'        => '',
		'pg_end'          => '',
	];

	function get_crumbs( $sep, $l10n, $args ) {
		global $post, $wp_post_types;

		self::$args['sep'] = $sep;

		// Filters the defaults and merges
		$loc = (object) array_merge( apply_filters( 'kama_breadcrumbs_default_loc', self::$l10n ), $l10n );
		$arg = (object) array_merge( apply_filters( 'kama_breadcrumbs_default_args', self::$args ), $args );


		// simplify
		$sep = &$arg->sep;
		$this->arg = &$arg;

		// micro-markup ---
		if( 1 ){
			$mark = &$arg->markup;

			// Default markup
			if( ! $mark ){
				$mark = [
					'wrappatt'  => '<div class="kama_breadcrumbs">%s</div>',
					'linkpatt'  => '<a href="%s">%s</a>',
					'sep_after' => '',
				];
			}
			// rdf
			elseif( $mark === 'rdf.data-vocabulary.org' ){
				$mark = [
					'wrappatt'  => '<div class="kama_breadcrumbs" prefix="v: http://rdf.data-vocabulary.org/#">%s</div>',
					'linkpatt'  => '<span typeof="v:Breadcrumb"><a href="%s" rel="v:url" property="v:title">%s</a>',
					'sep_after' => '</span>', // close span after the separator!
				];
			}
			// schema.org
			elseif( $mark === 'schema.org' ){
				$mark = [
					'wrappatt'  => '<ol class="breadcrumbs__list" itemscope itemtype="http://schema.org/BreadcrumbList">%s</ol>',
					'linkpatt'  => '<li class="breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"><a class="breadcrumbs__link" href="%s" itemprop="item"><span itemprop="name">%s</span></a></li>',
					'sep_after' => '',
				];
			}

			elseif( ! is_array( $mark ) ){
				die( __CLASS__ . ': "markup" parameter must be array...' );
			}

			$wrappatt = $mark['wrappatt'];
			$arg->linkpatt = $arg->nofollow ? str_replace( '<a ', '<a rel="nofollow"', $mark['linkpatt'] ) : $mark['linkpatt'];
			$arg->sep .= $mark['sep_after'] . "\n";
		}

		$linkpatt = $arg->linkpatt; // simplify

		$q_obj = get_queried_object();

		// maybe it's an empty archive of the tax
		$ptype = null;
		if( ! $post ){
			if( isset( $q_obj->taxonomy ) ){
				$ptype = $wp_post_types[ get_taxonomy( $q_obj->taxonomy )->object_type[0] ];
			}
		}
		else{
			$ptype = $wp_post_types[ $post->post_type ];
		}

		// paged
		$arg->pg_end = '';
		$paged_num = get_query_var( 'paged' ) ?: get_query_var( 'page' );
		if( $paged_num ){
			$arg->pg_end = $sep . sprintf( $loc->paged, (int) $paged_num );
		}

		$pg_end = $arg->pg_end; // simplify

		$out = '';

		if( is_front_page() ){
			return $arg->on_front_page ? sprintf( $wrappatt, ( $paged_num ? sprintf( $linkpatt, get_home_url(), $loc->home ) . $pg_end : $loc->home ) ) : '';
		}
		// post page when a separate page is set for the main page.
		elseif( is_home() ){
			$out = $paged_num ? ( sprintf( $linkpatt, get_permalink($q_obj), esc_html($q_obj->post_title) ) . $pg_end ) : esc_html($q_obj->post_title);
		}
		elseif( is_404() ){
			$out = $loc->_404;
		}
		elseif( is_search() ){
			$out = sprintf( $loc->search, esc_html( $GLOBALS['s'] ) );
		}
		elseif( is_author() ){
			$tit = sprintf( $loc->author, esc_html( $q_obj->display_name ) );
			$out = ( $paged_num ? sprintf( $linkpatt, get_author_posts_url( $q_obj->ID, $q_obj->user_nicename ) . $pg_end, $tit ) : $tit );
		}
		elseif( is_year() || is_month() || is_day() ){
			$y_url = get_year_link( $year = get_the_time( 'Y' ) );

			if( is_year() ){
				$tit = sprintf( $loc->year, $year );
				$out = ( $paged_num ? sprintf( $linkpatt, $y_url, $tit ) . $pg_end : $tit );
			}
			// month day
			else{
				$y_link = sprintf( $linkpatt, $y_url, $year );
				$m_url = get_month_link( $year, get_the_time( 'm' ) );

				if( is_month() ){
					$tit = sprintf( $loc->month, get_the_time( 'F' ) );
					$out = $y_link . $sep . ( $paged_num ? sprintf( $linkpatt, $m_url, $tit ) . $pg_end : $tit );
				}
				elseif( is_day() ){
					$m_link = sprintf( $linkpatt, $m_url, get_the_time( 'F' ) );
					$out = $y_link . $sep . $m_link . $sep . get_the_time( 'l' );
				}
			}
		}
		// Hierarchical posts
		elseif( is_singular() && $ptype->hierarchical ){
			$out = $this->_add_title( $this->_page_crumbs( $post ), $post );
		}
		// Taxonomies, flat posts, and attachments
		else{
			$term = $q_obj; // taxonomies

			// define a term for posts (including attachments)
			if( is_singular() ){
				// modify $post to define the term for the parent of the attachment
				if( is_attachment() && $post->post_parent ){
					$save_post = $post; // сохраним
					$post = get_post( $post->post_parent );
				}

				// takes into account if attachments are attached to hierarchical taxonomies - it happens :)
				$taxonomies = get_object_taxonomies( $post->post_type );
				// let's leave only hierarchical and public, just in case....
				$taxonomies = array_intersect( $taxonomies, get_taxonomies( [
					'hierarchical' => true,
					'public'       => true,
				] ) );

				if( $taxonomies ){
					// sort by priority
					if( ! empty( $arg->priority_tax ) ){

						usort( $taxonomies, static function( $a, $b ) use ( $arg ) {
							$a_index = array_search( $a, $arg->priority_tax );
							if( $a_index === false ){
								$a_index = 9999999;
							}

							$b_index = array_search( $b, $arg->priority_tax );
							if( $b_index === false ){
								$b_index = 9999999;
							}

							return ( $b_index === $a_index ) ? 0 : ( $b_index < $a_index ? 1 : -1 ); // меньше индекс - выше
						} );
					}

					// try to get the terms, in order of tax priority
					foreach( $taxonomies as $taxname ){

						if( $terms = get_the_terms( $post->ID, $taxname ) ){
							// проверим приоритетные термины для таксы
							$prior_terms = &$arg->priority_terms[ $taxname ];

							if( $prior_terms && count( $terms ) > 2 ){

								foreach( (array) $prior_terms as $term_id ){
									$filter_field = is_numeric( $term_id ) ? 'term_id' : 'slug';
									$_terms = wp_list_filter( $terms, [ $filter_field => $term_id ] );

									if( $_terms ){
										$term = array_shift( $_terms );
										break;
									}
								}
							}
							else{
								$term = array_shift( $terms );
							}

							break;
						}
					}
				}

				// revert back (for attachments)
				if( isset( $save_post ) ){
					$post = $save_post;
				}
			}

			// output

			// terms or post of any type with terms
			if( $term && isset( $term->term_id ) ){
				$term = apply_filters( 'kama_breadcrumbs_term', $term );

				// attachment
				if( is_attachment() ){
					if( ! $post->post_parent ){
						$out = sprintf( $loc->attachment, esc_html( $post->post_title ) );
					}
					else{
						if( ! $out = apply_filters( 'attachment_tax_crumbs', '', $term, $this ) ){
							$_crumbs = $this->_tax_crumbs( $term, 'self' );
							$parent_tit = sprintf( $linkpatt, get_permalink( $post->post_parent ), get_the_title( $post->post_parent ) );
							$_out = implode( $sep, [ $_crumbs, $parent_tit ] );
							$out = $this->_add_title( $_out, $post );
						}
					}
				}
				// single
				elseif( is_single() ){
					if( ! $out = apply_filters( 'post_tax_crumbs', '', $term, $this ) ){
						$_crumbs = $this->_tax_crumbs( $term, 'self' );
						$out = $this->_add_title( $_crumbs, $post );
					}
				}
				// non-hierarchical taxonomy (tags)
				elseif( ! is_taxonomy_hierarchical( $term->taxonomy ) ){
					// метка
					if( is_tag() ){
						$out = $this->_add_title( '', $term, sprintf( $loc->tag, esc_html( $term->name ) ) );
					}
					// taxonomy
					elseif( is_tax() ){
						$post_label = $ptype->labels->name;
						$tax_label = $GLOBALS['wp_taxonomies'][ $term->taxonomy ]->labels->name;
						$out = $this->_add_title( '', $term, sprintf( $loc->tax_tag, $post_label, $tax_label, esc_html( $term->name ) ) );
					}
				}
				// hierarchical taxonomy (category)
				elseif( ! $out = apply_filters( 'term_tax_crumbs', '', $term, $this ) ){
					$_crumbs = $this->_tax_crumbs( $term, 'parent' );
					$out = $this->_add_title( $_crumbs, $term, esc_html( $term->name ) );
				}
			}
			// attachments (of post) without terms
			elseif( is_attachment() ){
				$parent = get_post( $post->post_parent );
				$parent_link = sprintf( $linkpatt, get_permalink( $parent ), esc_html( $parent->post_title ) );
				$_out = $parent_link;

				// attachment of post with hierarchical type
				if( is_post_type_hierarchical( $parent->post_type ) ){
					$parent_crumbs = $this->_page_crumbs( $parent );
					$_out = implode( $sep, [ $parent_crumbs, $parent_link ] );
				}

				$out = $this->_add_title( $_out, $post );
			}
			// posts without terms
			elseif( is_singular() ){
				$out = $this->_add_title( '', $post );
			}
		}

		// replacement of link to post type archive page
		$home_after = apply_filters( 'kama_breadcrumbs_home_after', '', $linkpatt, $sep, $ptype );

		if( '' === $home_after ){
			// A link to the archive page of a post type for:
			// individual pages of that type; archives of that type; taxonomies associated with that type.
			if( $ptype && $ptype->has_archive && ! in_array( $ptype->name, [ 'post', 'page', 'attachment' ] )
			    && ( is_post_type_archive() || is_singular() || ( is_tax() && in_array( $term->taxonomy, $ptype->taxonomies ) ) )
			){
				$pt_title = $ptype->labels->name;

				// first page of the post type archive
				if( is_post_type_archive() && ! $paged_num ){
					$home_after = sprintf( $this->arg->title_patt, $pt_title );
				}
				// singular, paged post_type_archive, tax
				else{
					$home_after = sprintf( $linkpatt, get_post_type_archive_link( $ptype->name ), $pt_title );

					$home_after .= ( ( $paged_num && ! is_tax() ) ? $pg_end : $sep ); // pagination
				}
			}
		}

		$before_out = sprintf( $linkpatt, home_url(), $loc->home ) . ( $home_after ? $sep . $home_after : ( $out ? $sep : '' ) );

		$out = apply_filters( 'kama_breadcrumbs_pre_out', $out, $sep, $loc, $arg );

		$out = sprintf( $wrappatt, $before_out . $out );

		return apply_filters( 'kama_breadcrumbs', $out, $sep, $loc, $arg );
	}

	function _page_crumbs( $post ) {
		$parent = $post->post_parent;

		$crumbs = [];
		while( $parent ){
			$page = get_post( $parent );
			$crumbs[] = sprintf( $this->arg->linkpatt, get_permalink( $page ), esc_html( $page->post_title ) );
			$parent = $page->post_parent;
		}

		return implode( $this->arg->sep, array_reverse( $crumbs ) );
	}

	function _tax_crumbs( $term, $start_from = 'self' ) {
		$termlinks = [];
		$term_id = ( $start_from === 'parent' ) ? $term->parent : $term->term_id;
		while( $term_id ){
			$term = get_term( $term_id, $term->taxonomy );
			$termlinks[] = sprintf( $this->arg->linkpatt, get_term_link( $term ), esc_html( $term->name ) );
			$term_id = $term->parent;
		}

		if( $termlinks ){
			return implode( $this->arg->sep, array_reverse( $termlinks ) );
		}

		return '';
	}

	/**
	 * Adds a title to the passed text, taking into account all options.
	 * Adds a delimiter at the beginning, if necessary.
	 *
	 * @param string $add_to
	 * @param object $obj
	 * @param string $term_title
	 *
	 * @return string
	 */
	function _add_title( $add_to, $obj, $term_title = '' ) {

		// simplify.
		$arg = & $this->arg;

		// $term_title is cleaned separately, HTML tags can be inside.
		$title = $term_title ?: esc_html( $obj->post_title );
		$show_title = $term_title ? $arg->show_term_title : $arg->show_post_title;

		// pagination
		if( $arg->pg_end ){
			$link = $term_title ? get_term_link( $obj ) : get_permalink( $obj );
			$add_to .= ( $add_to ? $arg->sep : '' ) . sprintf( $arg->linkpatt, $link, $title ) . $arg->pg_end;
		}
		// add sep
		elseif( $add_to ){
			if( $show_title ){
				$add_to .= $arg->sep . sprintf( $arg->title_patt, $title );
			}
			elseif( $arg->last_sep ){
				$add_to .= $arg->sep;
			}
		}
		// sep will be later...
		elseif( $show_title ){
			$add_to = sprintf( $arg->title_patt, $title );
		}

		return $add_to;
	}

}

function itsoft_plural_form( $number, $titles ) {
	$cases = array( 2, 0, 1, 1, 1, 2 );
	return $titles[ ( $number % 100 > 4 && $number % 100 < 20 ) ? 2 : $cases[ min( $number % 10, 5 ) ] ];
}

function itsoft_get_search_placeholder_text() {
    $count_posts = wp_count_posts( 'product' );
    $published_posts = $count_posts->publish;
    $declension = itsoft_plural_form( $published_posts, array( 'товара', 'товаров', 'товаров' ) );
    return sprintf( 'Поиск среди %d %s...', $published_posts, $declension );
}

add_action( 'wp_footer', 'itsoft_jivochat_code' );
function itsoft_jivochat_code() {
    if ( defined( 'ITSOFT_EXTERNAL_INTEGRATIONS_DISABLED' ) && ITSOFT_EXTERNAL_INTEGRATIONS_DISABLED ) {
        return;
    }

    ?>
    <script src="//code.jivo.ru/widget/ELZfuC7yXZ" async></script>
    <?php
}

/**
 * Persists the optional phone field displayed in the customer profile.
 */
add_action( 'woocommerce_save_account_details', 'itsoft_save_account_phone' );
function itsoft_save_account_phone( $user_id ) {
	if ( ! isset( $_POST['billing_phone'] ) ) {
		return;
	}

	update_user_meta( (int) $user_id, 'billing_phone', sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) );
}

/*--------------------------------------------------------------
# 5. Telegram Bot
--------------------------------------------------------------*/
require_once get_template_directory() . '/telegram-bot/init.php';
