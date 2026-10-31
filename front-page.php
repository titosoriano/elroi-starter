<?php
/**
 * Home page. Every word and picture comes from Pages > Home > Home page content.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$c = es_content();
$h = $c['hero'];
get_header();
?>
<main id="main" class="es-main">

	<section class="es-hero es-hero--home<?php echo $h['photo'] ? ' es-hero--photo' : ''; ?>" aria-labelledby="es-hero-title">
		<?php if ( $h['photo'] ) : ?>
			<div class="es-hero__media"><?php echo elroi_image( $h['photo'], 'full', array( 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => es_sizes( 'full' ) ) ); // phpcs:ignore ?></div>
		<?php endif; ?>
		<div class="es-container es-hero__grid">
			<div class="es-hero__inner">
				<?php if ( $h['eyebrow'] ) : ?>
					<p class="es-eyebrow"><?php echo elroi_text( $h['eyebrow'] ); // phpcs:ignore ?></p>
				<?php endif; ?>
				<h1 class="es-hero__title" id="es-hero-title"><?php echo elroi_heading( $h['h1'] ); // phpcs:ignore ?></h1>
				<p class="es-hero__lede"><?php echo elroi_text( $h['lede'] ); // phpcs:ignore ?></p>
				<div class="es-hero__actions">
					<?php if ( es_setting( 'phone' ) ) : ?>
						<a class="es-btn es-btn--primary" href="tel:<?php echo esc_attr( es_tel( es_setting( 'phone' ) ) ); ?>"><?php echo es_icon( 'phone', 18 ); // phpcs:ignore ?> <?php echo esc_html( es_setting( 'phone' ) ); ?></a>
					<?php endif; ?>
					<?php echo es_button( $h['button'], $h['button_url'], 'ghost' ); // phpcs:ignore ?>
				</div>
			</div>
			<div class="es-hero__card"><?php es_lead_form( $h['form_title'], $h['form_text'] ); ?></div>
		</div>
	</section>

	<?php if ( $c['badges'] ) : ?>
		<ul class="es-badges es-container" aria-label="<?php esc_attr_e( 'Why choose us', 'elroi-starter' ); ?>">
			<?php foreach ( $c['badges'] as $b ) : ?>
				<li><?php echo es_icon( 'check', 18 ); // phpcs:ignore ?><span><?php echo elroi_text( $b ); // phpcs:ignore ?></span></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<section class="es-services" id="services" aria-labelledby="es-services-title">
		<div class="es-container">
			<header class="es-head" data-es-reveal>
				<h2 class="es-h2" id="es-services-title"><?php echo elroi_heading( $c['services']['heading'] ); // phpcs:ignore ?></h2>
				<p><?php echo elroi_text( $c['services']['intro'] ); // phpcs:ignore ?></p>
			</header>
			<ul class="es-cards es-cards--services">
				<?php foreach ( $c['services']['items'] as $s ) : ?>
					<li class="es-card" data-es-reveal>
						<?php if ( $s['image'] ) : ?>
							<?php echo elroi_image( $s['image'], 'medium_large', array( 'class' => 'es-card__img', 'loading' => 'lazy', 'sizes' => es_sizes( 'card' ) ) ); // phpcs:ignore ?>
						<?php endif; ?>
						<h3 class="es-card__title"><?php if ( $s['link'] ) : ?><a href="<?php echo elroi_link( $s['link'] ); // phpcs:ignore ?>"><?php echo elroi_text( $s['name'] ); // phpcs:ignore ?></a><?php else : ?><?php echo elroi_text( $s['name'] ); // phpcs:ignore ?><?php endif; ?></h3>
						<p><?php echo elroi_text( $s['text'] ); // phpcs:ignore ?></p>
						<?php if ( $s['link'] ) : ?><span class="es-card__more" aria-hidden="true"><?php echo es_icon( 'arrow', 18 ); // phpcs:ignore ?></span><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<section class="es-about" aria-labelledby="es-about-title">
		<div class="es-container es-about__grid">
			<div data-es-reveal>
				<h2 class="es-h2" id="es-about-title"><?php echo elroi_heading( $c['about']['heading'] ); // phpcs:ignore ?></h2>
				<?php echo elroi_paragraphs( $c['about']['paragraphs'], '', true ); // phpcs:ignore ?>
				<?php if ( $c['about']['facts'] ) : ?>
					<dl class="es-facts">
						<?php foreach ( $c['about']['facts'] as $f ) : ?>
							<div><dt><?php echo elroi_text( $f['value'] ); // phpcs:ignore ?></dt><dd><?php echo elroi_text( $f['label'] ); // phpcs:ignore ?></dd></div>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
			</div>
			<?php if ( $c['about']['photo'] ) : ?>
				<figure class="es-about__media" data-es-reveal><?php echo elroi_image( $c['about']['photo'], 'large', array( 'loading' => 'lazy', 'sizes' => '(min-width: 900px) 40vw, 100vw' ) ); // phpcs:ignore ?></figure>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $c['reviews']['items'] ) : ?>
		<section class="es-reviews" aria-labelledby="es-reviews-title">
			<div class="es-container">
				<h2 class="es-h2" id="es-reviews-title"><?php echo elroi_heading( $c['reviews']['heading'] ); // phpcs:ignore ?></h2>
				<ul class="es-reviews__list">
					<?php foreach ( $c['reviews']['items'] as $r ) : ?>
						<li data-es-reveal><figure>
							<p class="es-stars" aria-label="<?php esc_attr_e( '5 out of 5 stars', 'elroi-starter' ); ?>"><?php echo str_repeat( es_icon( 'star', 16 ), 5 ); // phpcs:ignore ?></p>
							<blockquote><p><?php echo elroi_text( $r['quote'] ); // phpcs:ignore ?></p></blockquote>
							<figcaption><strong><?php echo elroi_text( $r['name'] ); // phpcs:ignore ?></strong><?php if ( $r['detail'] ) : ?> <span><?php echo elroi_text( $r['detail'] ); // phpcs:ignore ?></span><?php endif; ?></figcaption>
						</figure></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>

	<?php get_template_part( 'parts/faq', null, array( 'faqs' => $c['faqs'] ) ); ?>
	<?php get_template_part( 'parts/cta', null, array( 'cta' => $c['cta'] ) ); ?>
</main>
<?php
get_footer();
