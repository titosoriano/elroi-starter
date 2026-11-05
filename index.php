<?php
/**
 * Blog and archives: a grid of posts.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main" class="es-main">
	<header class="es-hero es-hero--inner">
		<div class="es-container es-hero__inner">
			<h1 class="es-hero__title"><?php echo esc_html( is_home() && get_option( 'page_for_posts' ) ? es_page_title( get_option( 'page_for_posts' ) ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		</div>
	</header>
	<div class="es-container es-section">
		<?php if ( have_posts() ) : ?>
			<ul class="es-cards es-cards--posts">
				<?php
				$es_first = es_blog_has_feature();
				while ( have_posts() ) :
					the_post();
					es_post_card( get_post(), 'h2', $es_first );
					$es_first = false;
				endwhile;
				?>
			</ul>
			<?php the_posts_pagination( array( 'class' => 'es-pagination' ) ); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing here yet.', 'elroi-starter' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
