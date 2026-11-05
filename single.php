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
				<p class="es-eyebrow es-post__meta">
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					<?php if ( es_was_updated() ) : ?>
						<span><?php esc_html_e( 'Updated', 'elroi-starter' ); ?> <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( get_the_modified_date() ); ?></time></span>
					<?php endif; ?>
					<?php /* translators: %d: minutes. */ ?>
					<span><?php echo esc_html( sprintf( _n( '%d min read', '%d min read', es_reading_time(), 'elroi-starter' ), es_reading_time() ) ); ?></span>
				</p>
				<h1 class="es-hero__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?><p class="es-hero__lede"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
			</div>
		</header>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="es-container es-post__media"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => es_sizes( 'wide' ) ) ); ?></figure>
		<?php endif; ?>
		<div class="es-container es-prose"><?php the_content(); ?></div>
	</article>
	<?php $es_related = es_related_posts(); ?>
	<?php if ( $es_related ) : ?>
		<section class="es-section es-related" aria-labelledby="es-related-title">
			<div class="es-container">
				<h2 id="es-related-title"><?php esc_html_e( 'Keep reading', 'elroi-starter' ); ?></h2>
				<ul class="es-cards es-cards--posts">
					<?php foreach ( $es_related as $es_post ) : ?>
						<?php es_post_card( $es_post, 'h3' ); ?>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>
	<?php get_template_part( 'parts/cta', null, array( 'cta' => array( 'heading' => 'Ready to start *your project?*', 'text' => sprintf( 'We reply within %s hours.', es_setting( 'response_hours' ) ), 'button' => 'Get a free estimate' ) ) ); ?>
</main>
<?php
get_footer();
