<?php
/**
 * Product shop archive.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

get_header();
do_action( 'woocommerce_before_main_content' );
wc_get_template( 'partials/catalog-archive.php' );
do_action( 'woocommerce_after_main_content' );
get_footer( 'shop' );

