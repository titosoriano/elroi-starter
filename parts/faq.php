<?php
/**
 * Frequently asked questions (FAQPage schema comes from inc/seo.php). Args: faqs.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$faqs = array_filter( (array) ( $args['faqs'] ?? array() ), fn( $f ) => ! empty( $f['q'] ) && ! empty( $f['a'] ) );
if ( ! $faqs ) {
	return;
}
?>
<section class="es-faq" aria-labelledby="es-faq-title">
	<div class="es-container es-faq__grid">
		<h2 class="es-h2" id="es-faq-title"><?php echo elroi_heading( 'Questions, *answered.*' ); // phpcs:ignore ?></h2>
		<div class="es-faq__list">
			<?php foreach ( $faqs as $f ) : ?>
				<details class="es-faq__item">
					<summary><?php echo elroi_text( $f['q'] ); // phpcs:ignore ?><?php echo es_icon( 'chevron', 20 ); // phpcs:ignore ?></summary>
					<p><?php echo elroi_text( $f['a'] ); // phpcs:ignore ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>
