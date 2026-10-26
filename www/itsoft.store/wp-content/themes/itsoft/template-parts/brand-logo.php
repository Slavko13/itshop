<?php
/**
 * Shared brand mark and wordmark.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$variant = isset( $args['variant'] ) && 'light' === $args['variant'] ? ' brand-logo--light' : '';
?>
<span class="brand-logo<?php echo esc_attr( $variant ); ?>">
	<img class="brand-logo__image" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/brand-logo.svg' ); ?>" alt="Айтисофт" width="2003" height="465">
</span>
