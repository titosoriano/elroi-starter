<?php
/**
 * Call to action band. Args: cta (heading, text, button).
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$cta = (array) ( $args['cta'] ?? array() );
if ( empty( $cta['heading'] ) ) {
	return;
}
?>
<section class="es-cta" aria-labelledby="es-cta-title">
	<div class="es-container es-cta__inner">
		<div>
			<h2 class="es-h2" id="es-cta-title"><?php echo elroi_heading( $cta['heading'] ); // phpcs:ignore ?></h2>
			<?php if ( ! empty( $cta['text'] ) ) : ?>
				<p><?php echo elroi_text( $cta['text'] ); // phpcs:ignore ?></p>
			<?php endif; ?>
		</div>
		<div class="es-cta__actions">
			<a class="es-btn es-btn--light" href="<?php echo es_form_href(); // phpcs:ignore ?>"><?php echo elroi_text( $cta['button'] ?? '' ); // phpcs:ignore ?></a>
			<?php if ( es_setting( 'phone' ) ) : ?>
				<a class="es-cta__phone" href="tel:<?php echo esc_attr( es_tel( es_setting( 'phone' ) ) ); ?>"><?php echo es_icon( 'phone', 18 ); // phpcs:ignore ?> <?php echo esc_html( es_setting( 'phone' ) ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>
