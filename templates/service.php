<?php
/**
 * Template Name: Service page
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$c = es_content();
get_header();
?>
<main id="main" class="es-main">
	<?php get_template_part( 'parts/hero', null, array( 'hero' => $c['hero'] ) ); ?>
	<?php get_template_part( 'parts/sections', null, array( 'sections' => $c['sections'] ) ); ?>
	<?php if ( ! empty( $c['video']['video_url'] ) ) : ?>
		<section class="es-section" aria-label="<?php echo esc_attr( wp_strip_all_tags( elroi_text( $c['video']['title'] ? $c['video']['title'] : __( 'Video', 'elroi-starter' ) ) ) ); ?>">
			<div class="es-container"><?php get_template_part( 'parts/video', null, array( 'video' => $c['video'] ) ); ?></div>
		</section>
	<?php endif; ?>
	<section class="es-formband" aria-label="<?php esc_attr_e( 'Request an estimate', 'elroi-starter' ); ?>">
		<div class="es-container es-formband__inner"><?php es_lead_form( __( 'Get a free estimate', 'elroi-starter' ), sprintf( 'We reply within %s hours.', es_setting( 'response_hours' ) ) ); ?></div>
	</section>
	<?php get_template_part( 'parts/faq', null, array( 'faqs' => $c['faqs'] ) ); ?>
	<?php get_template_part( 'parts/cta', null, array( 'cta' => $c['cta'] ) ); ?>
</main>
<?php
get_footer();
