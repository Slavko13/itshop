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
	<img class="brand-logo__mark" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo-mark.svg' ); ?>" alt="" aria-hidden="true">
	<span class="brand-logo__wordmark" aria-label="IT Софт">
		<strong>IT</strong><span>СОФТ</span>
	</span>
</span>
