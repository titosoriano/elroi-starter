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
			<h1 class="es-hero__title"><?php echo esc_html( is_home() && get_option( 'page_for_posts' ) ? get_the_title( get_option( 'page_for_posts' ) ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		</div>
	</header>
	<div class="es-container es-section">
		<?php if ( have_posts() ) : ?>
			<ul class="es-cards">
				<?php while ( have_posts() ) : the_post(); ?>
					<li class="es-card">
						<?php if ( has_post_thumbnail() ) : ?><?php the_post_thumbnail( 'medium_large', array( 'class' => 'es-card__img', 'loading' => 'lazy' ) ); ?><?php endif; ?>
						<p class="es-eyebrow"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></p>
						<h2 class="es-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p><?php echo esc_html( get_the_excerpt() ); ?></p>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php the_posts_pagination( array( 'class' => 'es-pagination' ) ); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing here yet.', 'elroi-starter' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
