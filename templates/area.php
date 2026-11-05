<?php
/**
 * Template Name: Service area page
 *
 * One template for the service areas hub, its regions (counties) and their towns; the kind comes
 * from the page tree (inc/areas.php). Put town pages under their region page and region pages
 * under the hub, so addresses read /service-areas/bergen-county/fair-lawn/ or, without a hub page,
 * /bergen-county/fair-lawn/.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$c        = es_content();
$es_kind  = es_area_kind();
$es_place = wp_strip_all_tags( get_the_title() );
$es_kids  = es_area_children( get_the_ID() );
get_header();
?>
<main id="main" class="es-main es-area es-area--<?php echo esc_attr( $es_kind ); ?>">
	<?php get_template_part( 'parts/hero', null, array( 'hero' => $c['hero'], 'rating' => (bool) apply_filters( 'es_show_rating', true ) ) ); ?>

	<?php if ( 'hub' === $es_kind ) : ?>
		<section class="es-section" aria-labelledby="es-regions-title">
			<div class="es-container">
				<h2 class="es-h2" id="es-regions-title"><?php esc_html_e( 'Where we work', 'elroi-starter' ); ?></h2>
				<ul class="es-regions">
					<?php foreach ( $es_kids as $es_region ) : ?>
						<?php
						$es_towns = es_area_children( $es_region->ID );
						$es_photo = (int) ( elroi_content( $es_region )['hero']['photo'] ?? 0 );
						?>
						<li class="es-region" data-es-reveal>
							<?php if ( $es_photo ) : ?>
								<?php echo elroi_image( $es_photo, 'large', array( 'alt' => '', 'class' => 'es-region__img', 'loading' => 'lazy', 'sizes' => es_sizes( 'half' ) ) ); // phpcs:ignore ?>
							<?php endif; ?>
							<div class="es-region__body">
								<p class="es-eyebrow">
									<?php
									/* translators: %d: number of towns. */
									echo esc_html( sprintf( _n( '%d town', '%d towns', count( $es_towns ), 'elroi-starter' ), count( $es_towns ) ) );
									?>
								</p>
								<h3 class="es-region__title"><a href="<?php echo esc_url( get_permalink( $es_region ) ); ?>"><?php echo esc_html( get_the_title( $es_region ) ); ?></a></h3>
								<?php if ( $es_towns ) : ?>
									<ul class="es-chips">
										<?php foreach ( $es_towns as $es_town ) : ?>
											<li><a href="<?php echo esc_url( get_permalink( $es_town ) ); ?>"><?php echo esc_html( get_the_title( $es_town ) ); ?></a></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>

	<?php get_template_part( 'parts/sections', null, array( 'sections' => $c['sections'] ) ); ?>

	<?php if ( 'region' === $es_kind && $es_kids ) : ?>
		<?php
		$es_letters = array();
		foreach ( $es_kids as $es_town ) {
			$es_letters[ mb_strtoupper( mb_substr( wp_strip_all_tags( get_the_title( $es_town ) ), 0, 1 ) ) ][] = $es_town;
		}
		?>
		<section class="es-section es-section--alt" aria-labelledby="es-towns-title">
			<div class="es-container">
				<h2 class="es-h2" id="es-towns-title">
					<?php
					/* translators: %s: region name. */
					echo esc_html( sprintf( __( 'Towns we serve in %s', 'elroi-starter' ), $es_place ) );
					?>
				</h2>
				<?php if ( count( $es_letters ) > 3 ) : ?>
					<nav class="es-letters" aria-label="<?php esc_attr_e( 'Towns by first letter', 'elroi-starter' ); ?>">
						<?php foreach ( array_keys( $es_letters ) as $es_l ) : ?>
							<a href="#towns-<?php echo esc_attr( strtolower( $es_l ) ); ?>"><?php echo esc_html( $es_l ); ?></a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
				<div class="es-towns">
					<?php foreach ( $es_letters as $es_l => $es_group ) : ?>
						<div class="es-towns__group" id="towns-<?php echo esc_attr( strtolower( $es_l ) ); ?>">
							<p class="es-towns__letter" aria-hidden="true"><?php echo esc_html( $es_l ); ?></p>
							<ul>
								<?php foreach ( $es_group as $es_town ) : ?>
									<li><a href="<?php echo esc_url( get_permalink( $es_town ) ); ?>"><?php echo esc_html( get_the_title( $es_town ) ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php $es_services = 'hub' === $es_kind ? array() : es_service_pages(); ?>
	<?php if ( $es_services ) : ?>
		<section class="es-section" aria-labelledby="es-local-services-title">
			<div class="es-container">
				<h2 class="es-h2" id="es-local-services-title">
					<?php
					/* translators: %s: place name. */
					echo esc_html( sprintf( __( 'Our services in %s', 'elroi-starter' ), $es_place ) );
					?>
				</h2>
				<ul class="es-chips es-chips--large">
					<?php foreach ( $es_services as $es_s ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $es_s ) ); ?>"><?php echo esc_html( get_the_title( $es_s ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>

	<?php
	$es_parent   = wp_get_post_parent_id( get_the_ID() );
	$es_siblings = 'town' === $es_kind && $es_parent ? array_filter( es_area_children( $es_parent ), fn( $p ) => $p->ID !== get_the_ID() ) : array();
	?>
	<?php if ( $es_siblings ) : ?>
		<section class="es-section es-section--alt" aria-labelledby="es-nearby-title">
			<div class="es-container">
				<h2 class="es-h2" id="es-nearby-title">
					<?php
					/* translators: %s: region name. */
					echo esc_html( sprintf( __( 'More towns in %s', 'elroi-starter' ), wp_strip_all_tags( get_the_title( $es_parent ) ) ) );
					?>
				</h2>
				<ul class="es-chips">
					<?php foreach ( $es_siblings as $es_town ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $es_town ) ); ?>"><?php echo esc_html( get_the_title( $es_town ) ); ?></a></li>
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
