<?php
/**
 * The template for displaying all pages
 *
 * @package itsoft
 */

get_header();
?>

<div class="grid-container">
	<?php if ( function_exists( 'kama_breadcrumbs' ) ) : ?>
		<nav class="breadcrumbs" aria-label="Хлебные крошки">
			<?php kama_breadcrumbs( " " ); ?>
		</nav>
	<?php endif; ?>
</div>

<?php while ( have_posts() ) : the_post(); ?>

	<div class="article-head">
		<div class="grid-container">
			<h1 class="article-head__title"><?php the_title(); ?></h1>
		</div>
	</div>

	<div class="grid-container">
		<div class="page-main" style="grid-column: span 12;">
			<div class="article">
				<?php the_content(); ?>
			</div>
		</div>
	</div>

<?php endwhile; ?>

<?php
get_footer();
