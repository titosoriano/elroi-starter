<?php
/**
 * A fast first paint: nothing in <head> waits for a file.
 *
 * - Inline stylesheets: the theme's stylesheets (tokens.css, site.css, the child's client.css and
 *   the motion layer's CSS) print inside the page as <style> instead of <link>, so the browser
 *   paints without waiting for a CSS file. The copy is minified (comments and indentation
 *   removed), its relative url()s are made absolute (without ?ver, so fonts match their preload
 *   links and never download twice) and it is cached until the file changes. On Nuvista this
 *   took the mobile Lighthouse score of the home page from 91 to 94. Each page's HTML grows by
 *   the size of the CSS (compressed), which is why files over 100 KB stay links.
 *   Filters: `es_inline_styles` (handles to inline; a child adds its own, or small plugin
 *   stylesheets such as a cookie banner's; return array() to turn it off), `es_inline_max_bytes`.
 * - Preloads: `add_theme_support( 'es-preload', array( 'fonts' => array( 'assets/fonts/body.woff2' ),
 *   'hosts' => array( 'https://cdn.example.com' ) ) )` preloads the fonts the first screen uses
 *   (paths in the child theme first, then the starter) and opens connections to third-party hosts
 *   early. `es_preload` adds per-page links, e.g. a hero video's poster:
 *   add_filter( 'es_preload', fn( $l ) => is_front_page() ? array_merge( $l, array( es_preload_image( $poster_id, 'full' ) ) ) : $l );
 *   A photo printed with fetchpriority="high" (the hero) needs no preload: the browser already
 *   finds it early.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Style handles printed inline.
 *
 * @return string[]
 */
function es_inline_styles() {
	return array_values( (array) apply_filters( 'es_inline_styles', array( 'es-tokens', 'es-site', 'es-client', 'es-motion' ) ) );
}

/**
 * Local file behind a stylesheet URL (theme, plugin or wp-includes), or '' for a remote one.
 *
 * @param string $src Stylesheet URL.
 */
function es_style_file( $src ) {
	$src = strtok( (string) $src, '?' );
	$map = array(
		get_theme_root_uri() => get_theme_root(),
		content_url()        => WP_CONTENT_DIR,
		includes_url()       => ABSPATH . WPINC . '/',
	);
	foreach ( $map as $url => $dir ) {
		$url = set_url_scheme( $url );
		foreach ( array( $url, set_url_scheme( $url, 'relative' ) ) as $prefix ) {
			if ( $prefix && str_starts_with( set_url_scheme( $src ), $prefix ) ) {
				$file = wp_normalize_path( untrailingslashit( $dir ) . '/' . ltrim( substr( set_url_scheme( $src ), strlen( $prefix ) ), '/' ) );
				return ( str_ends_with( $file, '.css' ) && is_readable( $file ) ) ? $file : '';
			}
		}
	}
	return '';
}

/**
 * `$rel` resolved against the folder of the stylesheet at `$base` (both URLs), with ./ and ../
 * removed.
 *
 * @param string $rel  Relative URL from the CSS.
 * @param string $base Stylesheet URL.
 */
function es_css_resolve( $rel, $base ) {
	$parts = wp_parse_url( strtok( $base, '?' ) );
	$root  = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '//' ) . ( $parts['host'] ?? '' ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	$path  = explode( '/', dirname( $parts['path'] ?? '/' ) . '/' . $rel );
	$out   = array();
	foreach ( $path as $seg ) {
		if ( '..' === $seg ) {
			array_pop( $out );
		} elseif ( '.' !== $seg && '' !== $seg ) {
			$out[] = $seg;
		}
	}
	return $root . '/' . implode( '/', $out ) . ( str_ends_with( $rel, '/' ) ? '/' : '' );
}

/**
 * Minified CSS of a local stylesheet with absolute url()s, or null when it should stay a link.
 * Cached until the file changes.
 *
 * @param string $src Stylesheet URL.
 */
function es_inline_css( $src ) {
	$file = es_style_file( $src );
	if ( ! $file ) {
		return null;
	}
	$size = (int) filesize( $file );
	if ( $size > (int) apply_filters( 'es_inline_max_bytes', 100 * KB_IN_BYTES ) ) {
		return null;
	}
	$key = 'es_css_' . md5( $file . '|' . filemtime( $file ) . '|' . $size . '|' . $src . '|' . ES_VERSION );
	$css = get_transient( $key );
	if ( is_string( $css ) ) {
		return $css;
	}
	$css = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	$css = preg_replace( '/^[ \t]+|[ \t]+$/m', '', $css );
	$css = trim( preg_replace( '/\n+/', "\n", $css ) );
	$css = preg_replace_callback(
		'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
		function ( $m ) use ( $src ) {
			$u = trim( $m[2] );
			if ( preg_match( '#^([a-z][a-z0-9+.-]*:|//|/|\#)#i', $u ) ) {
				return $m[0]; // data:, absolute, root-relative or a fragment.
			}
			return 'url("' . esc_url_raw( es_css_resolve( $u, $src ) ) . '")';
		},
		$css
	);
	// A stylesheet that could close the <style> element is never printed inline.
	if ( false !== stripos( $css, '</style' ) ) {
		return null;
	}
	set_transient( $key, $css, MONTH_IN_SECONDS );
	return $css;
}

add_filter(
	'style_loader_tag',
	function ( $tag, $handle, $href, $media ) {
		if ( is_admin() || ! in_array( $handle, es_inline_styles(), true ) ) {
			return $tag;
		}
		$css = es_inline_css( $href );
		if ( null === $css ) {
			return $tag;
		}
		$media = ( $media && 'all' !== $media ) ? ' media="' . esc_attr( $media ) . '"' : '';
		return sprintf( "<style id=\"%s-inline-file\"%s>\n%s\n</style>\n", esc_attr( $handle ), $media, $css );
	},
	10,
	4
);

/**
 * A preload link for a Media Library image, with its srcset so the browser fetches the same copy
 * the page will show.
 *
 * @param int    $id    Attachment ID.
 * @param string $size  Image size.
 * @param string $sizes `sizes` attribute (match the <img>, e.g. es_sizes( 'full' )).
 * @return array Link attributes for the `es_preload` filter.
 */
function es_preload_image( $id, $size = 'full', $sizes = '100vw' ) {
	$src = wp_get_attachment_image_url( $id, $size );
	if ( ! $src ) {
		return array();
	}
	$link   = array( 'href' => $src, 'as' => 'image', 'fetchpriority' => 'high' );
	$srcset = wp_get_attachment_image_srcset( $id, $size );
	if ( $srcset ) {
		$link['imagesrcset'] = $srcset;
		$link['imagesizes']  = $sizes;
	}
	return $link;
}

/**
 * Preload links for the current page: fonts from `es-preload`, then the `es_preload` filter.
 *
 * @return array[] Each link's attributes (href, as, type, crossorigin...).
 */
function es_preload_links() {
	$support = get_theme_support( 'es-preload' );
	$opts    = is_array( $support ) && is_array( $support[0] ?? null ) ? $support[0] : array();
	$links   = array();
	foreach ( (array) ( $opts['fonts'] ?? array() ) as $font ) {
		$links[] = array(
			'href'        => preg_match( '#^(https?:)?//#', $font ) ? $font : get_theme_file_uri( ltrim( $font, '/' ) ),
			'as'          => 'font',
			'type'        => 'font/' . strtolower( pathinfo( strtok( $font, '?' ), PATHINFO_EXTENSION ) ),
			'crossorigin' => '',
		);
	}
	return array_values( array_filter( (array) apply_filters( 'es_preload', $links ) ) );
}

/**
 * Print the preload links (wp_head, priority 2: before the styles and scripts).
 */
function es_print_preloads() {
	foreach ( es_preload_links() as $link ) {
		if ( empty( $link['href'] ) || empty( $link['as'] ) ) {
			continue;
		}
		$attrs = '';
		foreach ( $link as $name => $value ) {
			if ( 'crossorigin' === $name && '' === $value ) {
				$attrs .= ' crossorigin';
				continue;
			}
			$attrs .= sprintf( ' %s="%s"', esc_attr( $name ), 'href' === $name ? esc_url( $value ) : esc_attr( $value ) );
		}
		printf( "<link rel=\"preload\"%s>\n", $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'wp_head', 'es_print_preloads', 2 );

// Early connections to the third-party hosts listed in `es-preload` (fonts or widgets).
add_filter(
	'wp_resource_hints',
	function ( $urls, $type ) {
		if ( 'preconnect' !== $type ) {
			return $urls;
		}
		$support = get_theme_support( 'es-preload' );
		foreach ( (array) ( is_array( $support ) ? ( $support[0]['hosts'] ?? array() ) : array() ) as $host ) {
			$urls[] = array( 'href' => $host, 'crossorigin' );
		}
		return $urls;
	},
	10,
	2
);
