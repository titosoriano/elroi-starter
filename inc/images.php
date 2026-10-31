<?php
/**
 * Lighter photos.
 *
 * - New uploads get their resized copies in WebP (much smaller than JPEG at the same quality)
 *   when the server can write WebP. The original file is kept. Photos uploaded before keep their
 *   JPEG copies until they are regenerated (any "Regenerate thumbnails" tool). Turn it off with
 *   add_filter( 'es_webp', '__return_false' ).
 * - Every `elroi_image()` and featured image already gets srcset from WordPress; templates pass a
 *   `sizes` that matches the layout (es_sizes()) so phones download a small copy, and the first
 *   photo of the page (the hero) loads first with fetchpriority="high". WordPress lazy-loads the rest.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'image_editor_output_format',
	function ( $formats ) {
		if ( ! apply_filters( 'es_webp', true ) || ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			return $formats;
		}
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	}
);

/**
 * `sizes` attribute for a layout slot, so the browser picks the right copy from srcset.
 *
 * @param string $slot full | wide | half | third | card.
 */
function es_sizes( $slot ) {
	$map = array(
		'full'  => '100vw',
		'wide'  => '(min-width: 1200px) 1140px, 100vw',
		'half'  => '(min-width: 900px) 45vw, 100vw',
		'third' => '(min-width: 900px) 30vw, (min-width: 600px) 50vw, 100vw',
		'card'  => '(min-width: 900px) 30vw, (min-width: 600px) 50vw, 100vw',
	);
	return (string) apply_filters( 'es_sizes', $map[ $slot ] ?? '100vw', $slot );
}
