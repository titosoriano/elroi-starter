<?php
/**
 * A blog post (block editor content).
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
get_header();
the_post();
?>
<main id="main" class="es-main">
	<article>
		<header class="es-hero es-hero--inner">
			<div class="es-container es-hero__inner">
				<p class="es-eyebrow"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></p>
				<h1 class="es-hero__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?><p class="es-hero__lede"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
			</div>
		</header>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="es-container es-post__media"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => es_sizes( 'wide' ) ) ); ?></figure>
		<?php endif; ?>
		<div class="es-container es-prose"><?php the_content(); ?></div>
	</article>
	<?php get_template_part( 'parts/cta', null, array( 'cta' => array( 'heading' => 'Ready to start *your project?*', 'text' => sprintf( 'We reply within %s hours.', es_setting( 'response_hours' ) ), 'button' => 'Get a free estimate' ) ) ); ?>
</main>
<?php
get_footer();
