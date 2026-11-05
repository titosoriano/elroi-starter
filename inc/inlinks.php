<?php
/**
 * In-text links: a natural mention of a keyword in body text links to that keyword's page.
 *
 * Clients write plain text and rarely add links, so on Nuvista none of 77 pages linked to another
 * page from its body text until this module (Nuvista theme 3.2.0) did it at render time. Search
 * engines read those links to learn which page answers which search, and Rank Math counts them.
 *
 * - The map lives in Site Settings > In-text links: each row is the words that link (separated by
 *   commas, most specific first, e.g. "kitchen remodeling, kitchen remodel, kitchens") and the page
 *   they link to. Rows higher in the list win when a paragraph mentions several. Child themes can
 *   add or change rows with the `es_link_map` filter ( array( array( 'to' => '/page/', 'words' =>
 *   array( 'phrase' ) ) ) ). With no rows, nothing changes: the module is off.
 * - Limits (`es_inlink_limits`): at most 5 added links per page and 2 per paragraph, each page
 *   linked once, never the page itself or a page the text already links to, nothing in headings,
 *   lists, buttons or existing links, and no paragraph shorter than 80 characters (one-line
 *   blurbs read like lists, and linking them repeats the same words on every page).
 * - Where: body paragraphs of designed pages (es_paragraphs(): sections and the home page's about
 *   text) and paragraphs and list items of blog posts. `es_inlinks` false turns it off.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Limits: links per page, per paragraph, and the shortest paragraph that gets links.
 *
 * @return array{page:int, paragraph:int, min_length:int}
 */
function es_inlink_limits() {
	return wp_parse_args(
		(array) apply_filters( 'es_inlink_limits', array() ),
		array( 'page' => 5, 'paragraph' => 2, 'min_length' => 80 )
	);
}

/**
 * Key of a link target: the path for this site's pages (so /a/ and https://site/a/ match), the
 * whole URL for other sites.
 *
 * @param string $url URL or path.
 */
function es_inlink_key( $url ) {
	$url  = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( $host && strtolower( $host ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
		return strtolower( strtok( $url, '#' ) );
	}
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$home = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
	if ( $home && str_starts_with( $path, $home ) ) {
		$path = substr( $path, strlen( $home ) );
	}
	return '/' === $path || '' === $path ? '/' : trailingslashit( '/' . ltrim( $path, '/' ) );
}

/**
 * The link map in priority order: each entry has 'to' (a path or URL) and 'words' (phrases).
 *
 * @return array[]
 */
function es_link_map() {
	$map = array();
	foreach ( (array) es_setting( 'inlinks' ) as $row ) {
		$to    = trim( (string) ( $row['to'] ?? '' ) );
		$words = array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $row['words'] ?? '' ) ) ), 'strlen' ) );
		if ( '' !== $to && '#' !== $to[0] && $words ) {
			$map[] = array( 'to' => $to, 'words' => $words );
		}
	}
	return array_values( (array) apply_filters( 'es_link_map', $map ) );
}

/**
 * Whether links are added on this request.
 */
function es_inlinks_on() {
	return ! is_admin() && ! is_feed() && is_singular() && apply_filters( 'es_inlinks', true ) && es_link_map();
}

/**
 * Per-request state, by reference: this page's key, targets already linked, links added.
 */
function &es_inlink_state() {
	static $state = array();
	$id = (int) get_queried_object_id();
	if ( ! isset( $state[ $id ] ) ) {
		$state[ $id ] = array(
			'self'  => $id ? es_inlink_key( is_front_page() ? home_url( '/' ) : get_permalink( $id ) ) : '',
			'used'  => array(),
			'added' => 0,
		);
	}
	return $state[ $id ];
}

/**
 * Links to add to a run of text (already escaped HTML, between two tags): [ offset, length, url ]
 * sorted by offset. Marks the targets as used.
 *
 * @param string $text Text.
 * @param int    $room Most links to add.
 */
function es_inlink_find( $text, $room ) {
	$state = &es_inlink_state();
	$room  = min( $room, es_inlink_limits()['page'] - $state['added'] );
	$found = array();
	if ( $room < 1 || '' === trim( $text ) ) {
		return $found;
	}
	foreach ( es_link_map() as $entry ) {
		$key = es_inlink_key( $entry['to'] );
		if ( $key === $state['self'] || isset( $state['used'][ $key ] ) ) {
			continue;
		}
		foreach ( (array) $entry['words'] as $phrase ) {
			$re = '/(?<![\p{L}\p{N}_-])' . preg_quote( esc_html( (string) $phrase ), '/' ) . '(?![\p{L}\p{N}_-])/iu';
			if ( '' === trim( (string) $phrase ) || ! preg_match( $re, $text, $m, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			$start = $m[0][1];
			$end   = $start + strlen( $m[0][0] );
			foreach ( $found as $f ) {
				if ( $start < $f[0] + $f[1] && $end > $f[0] ) {
					continue 2; // Overlaps a link from a row higher in the list: try the next phrase.
				}
			}
			$found[]               = array( $start, $end - $start, $entry['to'] );
			$state['used'][ $key ] = true;
			++$state['added'];
			break;
		}
		if ( count( $found ) >= $room ) {
			break;
		}
	}
	usort( $found, fn( $a, $b ) => $a[0] - $b[0] );
	return $found;
}

/**
 * HTML with in-text links added inside the given elements (paragraphs by default).
 *
 * @param string   $html HTML (escaped, as printed).
 * @param string[] $tags Elements whose text gets links.
 */
function es_autolink( $html, $tags = array( 'p' ) ) {
	if ( '' === trim( (string) $html ) || ! es_inlinks_on() ) {
		return $html;
	}
	$state = &es_inlink_state();
	if ( preg_match_all( '/<a\s[^>]*?href=(["\'])(.*?)\1/i', $html, $hrefs ) ) {
		foreach ( $hrefs[2] as $href ) {
			$state['used'][ es_inlink_key( $href ) ] = true;
		}
	}
	$limits = es_inlink_limits();
	$names  = implode( '|', array_map( fn( $t ) => preg_quote( $t, '#' ), $tags ) );
	return preg_replace_callback(
		'#(<(' . $names . ')\b[^>]*>)(.*?)(</\2>)#is',
		function ( $m ) use ( &$state, $limits ) {
			if ( $state['added'] >= $limits['page'] || mb_strlen( trim( wp_strip_all_tags( $m[3] ) ) ) < $limits['min_length'] ) {
				return $m[0];
			}
			$parts = preg_split( '/(<[^>]+>)/', $m[3], -1, PREG_SPLIT_DELIM_CAPTURE );
			$skip  = 0; // Inside a link, heading, list, button, code or caption.
			$in    = 0;
			foreach ( $parts as $i => $part ) {
				if ( '' === $part ) {
					continue;
				}
				if ( '<' === $part[0] ) {
					if ( preg_match( '#^<(/?)(a|h[1-6]|ul|ol|button|code|pre|figcaption|script|style|kbd)\b#i', $part, $t ) && ! str_ends_with( $part, '/>' ) ) {
						$skip = max( 0, $skip + ( '/' === $t[1] ? -1 : 1 ) );
					}
					continue;
				}
				if ( $skip || $in >= $limits['paragraph'] ) {
					continue;
				}
				$found = es_inlink_find( $part, $limits['paragraph'] - $in );
				if ( ! $found ) {
					continue;
				}
				$out = '';
				$at  = 0;
				foreach ( $found as $f ) {
					$href = function_exists( 'elroi_link' ) ? elroi_link( $f[2] ) : esc_url( preg_match( '#^https?:#i', $f[2] ) ? $f[2] : home_url( $f[2] ) );
					$out .= substr( $part, $at, $f[0] - $at ) . '<a class="es-inlink" href="' . $href . '">' . substr( $part, $f[0], $f[1] ) . '</a>';
					$at   = $f[0] + $f[1];
				}
				$parts[ $i ] = $out . substr( $part, $at );
				$in         += count( $found );
			}
			return $m[1] . implode( '', $parts ) . $m[4];
		},
		$html
	);
}

/**
 * Body paragraphs of a designed page: `[words](/path)` links the client typed, then in-text links.
 *
 * @param array $list Paragraph texts.
 */
function es_paragraphs( $list ) {
	$html = function_exists( 'elroi_paragraphs' ) ? elroi_paragraphs( $list, '', true ) : implode( '', array_map( fn( $p ) => '<p>' . esc_html( (string) $p ) . '</p>', (array) $list ) );
	return es_autolink( $html );
}

// Blog posts: paragraphs and list items of the post body (after the table of contents ids).
add_filter(
	'the_content',
	function ( $html ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $html;
		}
		return es_autolink( $html, array( 'p', 'li' ) );
	},
	30
);
