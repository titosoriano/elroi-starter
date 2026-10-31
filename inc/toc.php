<?php
/**
 * Table of contents on blog posts: an "On this page" list of the post's H2 and H3 headings,
 * printed above the text when the post has at least two H2s. Headings get stable ids so the
 * links (and links shared from outside) jump to them.
 *
 * Skipped when the post already has a table of contents block (Rank Math's or another). Turn it
 * off with add_filter( 'es_toc', '__return_false' ), or change the minimum with `es_toc_min`.
 * Rank Math's "Table of Contents" check is told that the site has one.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post HTML with ids on its H2/H3 headings, and the list of those headings.
 *
 * @param string $html Post content.
 * @return array{0:string,1:array} HTML, [ level, id, text ].
 */
function es_toc_headings( $html ) {
	$items = array();
	$used  = array();
	$html  = preg_replace_callback(
		'#<h([23])([^>]*)>(.*?)</h\1>#is',
		function ( $m ) use ( &$items, &$used ) {
			$text = trim( html_entity_decode( wp_strip_all_tags( $m[3] ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $text ) {
				return $m[0];
			}
			$attrs = $m[2];
			if ( preg_match( '/\sid=(["\'])(.*?)\1/i', $attrs, $idm ) ) {
				$id = $idm[2];
			} else {
				$base = sanitize_title( $text );
				$base = '' === $base ? 'section' : $base;
				$id   = $base;
				for ( $i = 2; isset( $used[ $id ] ); $i++ ) {
					$id = $base . '-' . $i;
				}
				$attrs .= ' id="' . esc_attr( $id ) . '"';
			}
			$used[ $id ] = true;
			$items[]     = array( (int) $m[1], $id, $text );
			return '<h' . $m[1] . $attrs . '>' . $m[3] . '</h' . $m[1] . '>';
		},
		$html
	);
	return array( $html, $items );
}

/**
 * The "On this page" list.
 *
 * @param array $items [ level, id, text ].
 */
function es_toc_html( $items ) {
	$link = fn( $it ) => '<a href="#' . esc_attr( $it[1] ) . '">' . esc_html( $it[2] ) . '</a>';
	$out  = '<nav class="es-toc" aria-labelledby="es-toc-title"><p class="es-toc__title" id="es-toc-title">' . esc_html__( 'On this page', 'elroi-starter' ) . '</p><ol>';
	$in2  = false;
	$in3  = false;
	foreach ( $items as $it ) {
		if ( 3 === $it[0] ) {
			if ( $in2 ) {
				$out .= ( $in3 ? '' : '<ol>' ) . '<li>' . $link( $it ) . '</li>';
				$in3  = true;
			}
			continue;
		}
		$out .= ( $in3 ? '</ol>' : '' ) . ( $in2 ? '</li>' : '' ) . '<li>' . $link( $it );
		$in2  = true;
		$in3  = false;
	}
	return $out . ( $in3 ? '</ol>' : '' ) . ( $in2 ? '</li>' : '' ) . '</ol></nav>';
}

add_filter(
	'the_content',
	function ( $html ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || ! apply_filters( 'es_toc', true ) ) {
			return $html;
		}
		if ( preg_match( '/wp-block-rank-math-toc-block|rank-math-toc|ez-toc|lwptoc|wp-block-table-of-contents/', $html ) ) {
			return $html;
		}
		list( $html, $items ) = es_toc_headings( $html );
		$h2s = count( array_filter( $items, fn( $i ) => 2 === $i[0] ) );
		if ( $h2s < (int) apply_filters( 'es_toc_min', 2 ) ) {
			return $html;
		}
		return es_toc_html( $items ) . $html;
	},
	20
);

// Rank Math checks for a table of contents by looking for known plugins; the theme prints one.
add_filter(
	'rank_math/researches/toc_plugins',
	function ( $plugins ) {
		if ( apply_filters( 'es_toc', true ) ) {
			$plugins['elroi-site-editor/elroi-site-editor.php'] = 'ElRoi Starter table of contents';
		}
		return $plugins;
	}
);
