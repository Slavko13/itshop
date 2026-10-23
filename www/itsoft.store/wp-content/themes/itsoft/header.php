<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <script>document.documentElement.classList.add('js');</script>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="header">
    <div class="topbar">
        <div class="topbar__content grid-container">
            <?php get_template_part( 'template-parts/header/top-nav' ); ?>
            <?php get_template_part( 'template-parts/header/lang-switcher' ); ?>
        </div>
    </div>
    <div class="header-main">
        <div class="header-main__row grid-container">
            <?php get_template_part( 'template-parts/header/mobile-nav' ); ?>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo header-main__logo">
                <?php get_template_part( 'template-parts/brand-logo' ); ?>
            </a>
            <?php get_template_part( 'template-parts/header/catalog-btn' ); ?>
            <?php get_template_part( 'template-parts/header/search-form' ); ?>
            <?php get_template_part( 'template-parts/header/user-nav' ); ?>
            <?php get_template_part( 'template-parts/header/catalog-nav' ); ?>
        </div>
    </div>
    <?php
    $itsoft_is_catalog_archive = ( function_exists( 'is_shop' ) && ( is_shop() || is_tax( array( 'product_cat', 'product_tag' ) ) ) )
        || is_singular( 'distribution' )
        || is_post_type_archive( 'distribution' )
        || is_tax( 'download' )
        || is_page_template( 'page-distributions.php' )
		|| ( function_exists( 'is_product' ) && is_product() )
        || get_query_var( 'itsoft_dist_file' )
        || get_query_var( 'itsoft_dist_token' );
    ?>
    <?php if( is_front_page() ): ?>
        <?php get_template_part( 'template-parts/home/hero' ); ?>
    <?php elseif ( ! $itsoft_is_catalog_archive ) : ?>
        <?php
        $hero_small_shortcode = trim( (string) get_theme_mod( 'itsoft_hero_small_shortcode', '' ) );
        $hero_small_has_slider = '' !== $hero_small_shortcode;
        ?>
        <div class="hero-small grid-container">
            <div class="hero-small__container<?php echo $hero_small_has_slider ? ' hero-small__container--smartslider' : ''; ?>">
                <?php if ( $hero_small_has_slider ) : ?>
                    <?php echo do_shortcode( $hero_small_shortcode ); ?>
                <?php else : ?>
                    <div class="hero-small__content">Быстрая и удобная покупка ключей в любое время</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</header>
