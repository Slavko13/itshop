<?php
/**
 * Distribution card shared by download landing, taxonomy and archive.
 *
 * @package itsoft
 */

defined( 'ABSPATH' ) || exit;

$distribution_id = get_the_ID();
$summary         = function_exists( 'itsoft_dist_get_summary' ) ? itsoft_dist_get_summary( $distribution_id ) : array();
$terms           = get_the_terms( $distribution_id, 'download' );
$term_slugs      = array();

if ( is_array( $terms ) ) {
	foreach ( $terms as $term ) {
		$term_slugs[] = $term->slug;
	}
}

$search_text = strtolower( wp_strip_all_tags( get_the_title() . ' ' . implode( ' ', $summary['languages'] ?? array() ) . ' ' . implode( ' ', $summary['architectures'] ?? array() ) ) );
?>
<article
	class="distribution-card"
	data-distribution-card
	data-category="<?php echo esc_attr( implode( ' ', $term_slugs ) ); ?>"
	data-search="<?php echo esc_attr( $search_text ); ?>"
	data-language="<?php echo esc_attr( implode( '|', $summary['languages'] ?? array() ) ); ?>"
	data-architecture="<?php echo esc_attr( implode( '|', $summary['architectures'] ?? array() ) ); ?>"
	data-title="<?php echo esc_attr( strtolower( wp_strip_all_tags( get_the_title() ) ) ); ?>"
	data-modified="<?php echo esc_attr( get_the_modified_date( 'U' ) ); ?>"
>
	<a class="distribution-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'medium', array( 'class' => 'distribution-card__image', 'loading' => 'lazy' ) ); ?>
		<?php else : ?>
			<span class="distribution-card__placeholder" aria-hidden="true">↓</span>
		<?php endif; ?>
	</a>
	<div class="distribution-card__body">
		<div class="distribution-card__eyebrow">
			<?php if ( ! empty( $summary['sources'] ) ) : ?>
				<span><?php echo esc_html( implode( ', ', array_slice( $summary['sources'], 0, 2 ) ) ); ?></span>
			<?php else : ?>
				<span><?php esc_html_e( 'Дистрибутив', 'itsoft' ); ?></span>
			<?php endif; ?>
			<time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( get_the_modified_date( 'd.m.Y' ) ); ?></time>
		</div>
		<h2 class="distribution-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<div class="distribution-card__tags" aria-label="<?php esc_attr_e( 'Доступные варианты', 'itsoft' ); ?>">
			<?php foreach ( array_slice( $summary['languages'] ?? array(), 0, 2 ) as $language ) : ?>
				<span><?php echo esc_html( $language ); ?></span>
			<?php endforeach; ?>
			<?php foreach ( array_slice( $summary['architectures'] ?? array(), 0, 2 ) as $architecture ) : ?>
				<span><?php echo esc_html( strtoupper( $architecture ) ); ?></span>
			<?php endforeach; ?>
			<?php foreach ( array_slice( $summary['formats'] ?? array(), 0, 1 ) as $format ) : ?>
				<span><?php echo esc_html( $format ); ?></span>
			<?php endforeach; ?>
		</div>
		<div class="distribution-card__footer">
			<span class="distribution-card__availability <?php echo ! empty( $summary['file_count'] ) ? 'is-available' : 'is-unavailable'; ?>">
				<?php
				if ( ! empty( $summary['file_count'] ) ) {
					echo esc_html( sprintf( _n( '%d вариант', '%d вариантов', (int) $summary['file_count'], 'itsoft' ), (int) $summary['file_count'] ) );
				} else {
					esc_html_e( 'Временно недоступно', 'itsoft' );
				}
				?>
			</span>
			<a class="distribution-card__button" href="<?php the_permalink(); ?>">
				<?php esc_html_e( 'Открыть', 'itsoft' ); ?><span aria-hidden="true">→</span>
			</a>
		</div>
	</div>
</article>
