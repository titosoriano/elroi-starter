<?php
/**
 * Template Name: Contact page
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$c       = es_content();
$address = trim( implode( ', ', array_filter( array( es_setting( 'street' ), es_setting( 'city' ), trim( es_setting( 'region' ) . ' ' . es_setting( 'zip' ) ) ) ) ) );
get_header();
?>
<main id="main" class="es-main">
	<?php get_template_part( 'parts/hero', null, array( 'hero' => $c['hero'] ) ); ?>
	<section class="es-contact">
		<div class="es-container es-contact__grid">
			<div class="es-contact__form"><?php es_lead_form( $c['form']['title'], $c['form']['text'] ); ?></div>
			<aside class="es-contact__info" aria-label="<?php esc_attr_e( 'Contact details', 'elroi-starter' ); ?>">
				<?php if ( es_setting( 'phone' ) ) : ?>
					<p><span class="es-eyebrow"><?php esc_html_e( 'Call', 'elroi-starter' ); ?></span><a href="tel:<?php echo esc_attr( es_tel( es_setting( 'phone' ) ) ); ?>"><?php echo esc_html( es_setting( 'phone' ) ); ?></a></p>
				<?php endif; ?>
				<?php if ( es_setting( 'email' ) ) : ?>
					<p><span class="es-eyebrow"><?php esc_html_e( 'Email', 'elroi-starter' ); ?></span><a href="mailto:<?php echo esc_attr( es_setting( 'email' ) ); ?>"><?php echo esc_html( es_setting( 'email' ) ); ?></a></p>
				<?php endif; ?>
				<?php if ( $address ) : ?>
					<p><span class="es-eyebrow"><?php esc_html_e( 'Visit', 'elroi-starter' ); ?></span><a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address ) ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $address ); ?><span class="es-sr"> <?php esc_html_e( '(opens Google Maps in a new tab)', 'elroi-starter' ); ?></span></a></p>
				<?php endif; ?>
				<?php if ( es_setting( 'hours' ) ) : ?>
					<div><span class="es-eyebrow"><?php esc_html_e( 'Hours', 'elroi-starter' ); ?></span><ul><?php foreach ( (array) es_setting( 'hours' ) as $h ) : ?><li><?php echo esc_html( $h ); ?></li><?php endforeach; ?></ul></div>
				<?php endif; ?>
			</aside>
		</div>
	</section>
	<?php get_template_part( 'parts/faq', null, array( 'faqs' => $c['faqs'] ) ); ?>
</main>
<?php
get_footer();
