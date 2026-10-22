<?php
/**
 * Product extra download block.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$download    = isset( $args['download'] ) && is_array( $args['download'] ) ? $args['download'] : array();
$extra_class = isset( $args['extra_class'] ) ? trim( (string) $args['extra_class'] ) : '';

$download    = array_filter( $download );

if ( empty( $download ) ) {
	return;
}

$title       = isset( $download['title'] ) ? trim( (string) $download['title'] ) : '';
$content     = $download['content'] ?? array();
$button_text = isset( $download['button_text'] ) ? trim( (string) $download['button_text'] ) : '';
$button_url        = '';
if ( function_exists( 'get_field' ) ) {
	$distribution_link = get_field( 'distribution_link', get_the_ID() );
	$button_url        = $distribution_link ? (string) get_permalink( $distribution_link ) : '';
}
$button_rel  = isset( $download['button_rel'] ) ? trim( (string) $download['button_rel'] ) : '';
$button_attr = isset( $download['button_attr'] ) && is_array( $download['button_attr'] ) ? $download['button_attr'] : array();

if ( isset( $download['button_target'] ) ) {
	$button_attr['target'] = trim( (string) $download['button_target'] );
}

if ( is_string( $content ) ) {
	$content = array( $content );
} else {
	$content = array_filter(
		array_map(
			function ( $paragraph ) {
				return trim( (string) $paragraph );
			},
			(array) $content
		),
		'strlen'
	);
}

if ( $button_rel ) {
	$button_attr['rel'] = $button_rel;
}

$has_button = $button_text && $button_url;

if ( '' === $title && empty( $content ) && ! $has_button ) {
	return;
}

$wrapper_classes = trim( 'product-extra__download ' . $extra_class );
$wrapper_classes = $wrapper_classes ?: 'product-extra__download';
?>

<div class="<?php echo esc_attr( $wrapper_classes ); ?>">
	<?php if ( $title ) : ?>
		<div class="product-extra__download-title"><?php echo esc_html( $title ); ?></div>
	<?php endif; ?>
	<?php if ( ! empty( $content ) ) : ?>
		<div class="product-extra__download-content">
			<?php foreach ( $content as $paragraph ) : ?>
				<p><?php echo esc_html( $paragraph ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php if ( $has_button ) : ?>
		<div class="product-extra__download-link">
			<a
				href="<?php echo esc_url( $button_url ); ?>"
				class="product-extra__download-btn"
				<?php foreach ( $button_attr as $attr_name => $attr_value ) : ?>
					<?php if ( $attr_value ) : ?>
						<?php printf( ' %1$s="%2$s"', esc_attr( $attr_name ), esc_attr( $attr_value ) ); ?>
					<?php endif; ?>
				<?php endforeach; ?>
			>
				<?php echo esc_html( $button_text ); ?>
			</a>
		</div>
	<?php endif; ?>
</div>

