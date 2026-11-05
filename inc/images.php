<?php
/**
 * Lighter photos.
 *
 * - New uploads get their resized copies in WebP (much smaller than JPEG at the same quality)
 *   when the server can write WebP. The original file is kept. Photos uploaded before keep their
 *   JPEG copies until they are regenerated (any "Regenerate thumbnails" tool). Turn it off with
 *   add_filter( 'es_webp', '__return_false' ).
 *   The site icon stays PNG/JPEG (iOS ignores WebP touch icons), and share images (Open Graph,
 *   X/Twitter) point to the original upload, since some apps show no link preview for WebP.
 * - Every `elroi_image()` and featured image already gets srcset from WordPress; templates pass a
 *   `sizes` that matches the layout (es_sizes()) so phones download a small copy, and the first
 *   photo of the page (the hero) loads first with fetchpriority="high". WordPress lazy-loads the rest.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'image_editor_output_format',
	function ( $formats, $filename = '' ) {
		if ( ! apply_filters( 'es_webp', true ) || ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) || es_is_site_icon_file( $filename ) ) {
			return $formats;
		}
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	},
	10,
	2
);

/**
 * Whether an image being written is the site icon, which stays PNG/JPEG: iOS ignores a WebP
 * apple-touch-icon. True while the icon is cropped (Customizer or Settings > General) and for
 * files of the current icon (regenerating thumbnails).
 *
 * @param string $filename File being resized.
 */
function es_is_site_icon_file( $filename = '' ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read only; core checks the nonce.
	if ( wp_doing_ajax() && 'site-icon' === ( $_POST['context'] ?? '' ) ) {
		return true;
	}
	$icon = (int) get_option( 'site_icon' );
	if ( ! $icon || ! $filename ) {
		return false;
	}
	$name = pathinfo( (string) get_post_meta( $icon, '_wp_attached_file', true ), PATHINFO_FILENAME );
	$name = preg_replace( '/-scaled$/', '', (string) $name );
	return '' !== $name && str_starts_with( pathinfo( (string) $filename, PATHINFO_FILENAME ), $name );
}

/**
 * Share images (Open Graph, X/Twitter) use the original JPEG or PNG upload instead of a WebP
 * copy: some apps still show no link preview for WebP. Only when the original is under 5 MB;
 * the type always matches the file sent. Rank Math's filters.
 *
 * @param array $image Rank Math image: url, id, width, height, type...
 */
function es_share_image( $image ) {
	$id  = (int) ( $image['id'] ?? 0 );
	$url = (string) ( $image['url'] ?? '' );
	if ( ! $id || ! str_ends_with( strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) ), '.webp' ) ) {
		return $image;
	}
	$path = wp_get_original_image_path( $id );
	if ( $path && is_readable( $path ) && ! str_ends_with( strtolower( $path ), '.webp' ) && filesize( $path ) < 5 * MB_IN_BYTES ) {
		$size = wp_getimagesize( $path );
		if ( $size ) {
			return array_merge(
				$image,
				array(
					'url'    => wp_get_original_image_url( $id ),
					'width'  => $size[0],
					'height' => $size[1],
					'type'   => $size['mime'],
				)
			);
		}
	}
	$image['type'] = 'image/webp';
	return $image;
}
add_filter( 'rank_math/opengraph/facebook/image_array', 'es_share_image' );
add_filter( 'rank_math/opengraph/twitter/image_array', 'es_share_image' );

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
