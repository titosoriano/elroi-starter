<?php
/**
 * Widget section (manufacturer color tool, map, booking calendar) from an `embed` field group.
 * Args: embed (url, heading, text, title, height as % of the width, min_height in pixels).
 *
 * Many widgets get taller as they get wider, so the frame is a size container and the height a
 * share of its width (cqi), never below min_height.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$e     = (array) ( $args['embed'] ?? array() );
$frame = ! empty( $e['url'] ) && function_exists( 'elroi_embed' ) ? elroi_embed(
	$e['url'],
	array(
		'title' => wp_strip_all_tags( elroi_text( ! empty( $e['title'] ) ? $e['title'] : __( 'Interactive tool', 'elroi-starter' ) ) ),
		'class' => 'es-embed__iframe',
		'style' => sprintf( 'height:max(%dpx,%scqi)', max( 200, (int) ( $e['min_height'] ?? 480 ) ), (float) max( 20, min( 300, (float) ( $e['height'] ?? 75 ) ) ) ),
	)
) : '';
if ( '' === $frame ) {
	return;
}
?>
<section class="es-section es-embed" aria-labelledby="es-embed-title">
	<div class="es-container">
		<h2 class="es-h2" id="es-embed-title"><?php echo elroi_heading( ! empty( $e['heading'] ) ? $e['heading'] : __( 'See it *for yourself.*', 'elroi-starter' ) ); // phpcs:ignore ?></h2>
		<?php if ( ! empty( $e['text'] ) ) : ?>
			<p class="es-embed__text"><?php echo es_rich( $e['text'] ); // phpcs:ignore ?></p>
		<?php endif; ?>
		<div class="es-embed__frame"><?php echo $frame; // phpcs:ignore WordPress.Security.EscapeOutput -- built by elroi_embed() ?></div>
	</div>
</section>
