<?php
/**
 * Rank Math scores without a browser: the data Rank Math's analyzer needs for every page and post,
 * for `tests/rank-math/score.js`, which runs Rank Math's own analyzer.js in Node.
 *
 * The browser check (tests/seo_scores.py) signs in to wp-admin and opens every page; this needs
 * no password and scores a whole site in seconds, so it fits after every content or code change.
 * On Nuvista it reproduced the scores Rank Math stored in wp-admin exactly.
 *
 *     wp es seo-papers --out=papers.json        # every published page and post
 *     wp es seo-papers --ids=12,48 > papers.json
 *
 * Each page is loaded once: the SEO title and description are the ones Rank Math prints (its
 * Titles & Meta template when a page has none of its own). Pages built from fields are scored on
 * what visitors see inside <main>, cleaned the same way as ElRoi Site Editor's live analysis in the
 * editor, so the numbers match the edit screen. Other pages and posts use their stored content, as
 * Rank Math does.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * A page or post as visitors get it: the <title> and meta description Rank Math prints, and what
 * is inside <main>, cleaned like the editor's live analysis (no scripts, icons, hidden or
 * decorative copies). Empty strings when the page cannot be loaded.
 *
 * @param int|WP_Post $post Page or post.
 * @return array{title:string, description:string, main:string}
 */
function es_seo_page( $post ) {
	$out  = array( 'title' => '', 'description' => '', 'main' => '' );
	$post = get_post( $post );
	if ( ! $post ) {
		return $out;
	}
	$url = (int) get_option( 'page_on_front' ) === (int) $post->ID ? home_url( '/' ) : get_permalink( $post );
	$res = wp_remote_get( add_query_arg( 'es-seo', wp_generate_password( 8, false ), $url ), array( 'timeout' => 30, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return $out;
	}
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?>' . wp_remote_retrieve_body( $res ) );
	libxml_clear_errors();
	$xp   = new DOMXPath( $dom );
	$node = $xp->query( '//head/title' )->item( 0 );
	$out['title'] = $node ? trim( $node->textContent ) : '';
	$node = $xp->query( '//head/meta[@name="description"]/@content' )->item( 0 );
	$out['description'] = $node ? trim( $node->nodeValue ) : '';
	$selector = (string) apply_filters( 'elroi_seo_selector', 'main', $post );
	$main     = preg_match( '/^[a-z][a-z0-9-]*$/i', $selector ) ? $xp->query( '//' . $selector )->item( 0 ) : null;
	$main     = $main ? $main : $xp->query( '//main' )->item( 0 );
	if ( ! $main ) {
		return $out;
	}
	$drop = './/comment()|.//script|.//style|.//noscript|.//template|.//*[local-name()="svg"]|.//*[@hidden]|.//*[@aria-hidden="true"]|.//*[@data-elroi-seo="skip"]|.//*[@id="wpadminbar"]';
	foreach ( iterator_to_array( $xp->query( $drop, $main ) ) as $node ) {
		if ( $node->parentNode ) {
			$node->parentNode->removeChild( $node );
		}
	}
	foreach ( $main->childNodes as $child ) {
		$out['main'] .= $dom->saveHTML( $child );
	}
	$out['main'] = trim( $out['main'] );
	return $out;
}

/**
 * Rank Math's analysis tests for a post type (Content AI left out as ElRoi Site Editor sets it),
 * or array() without Rank Math.
 *
 * @param string $type Post type.
 */
function es_seo_tests( $type ) {
	if ( ! class_exists( '\RankMath\Admin\Metabox\Post_Screen' ) ) {
		return array();
	}
	$screen = new \RankMath\Admin\Metabox\Post_Screen();
	return array_keys( (array) apply_filters( 'rank_math/researches/tests', $screen->get_analysis(), $type ) );
}

/**
 * Rank Math's power words (English), or array().
 */
function es_seo_power_words() {
	$file = function_exists( 'rank_math' ) ? rank_math()->plugin_dir() . 'assets/vendor/powerwords/en.php' : '';
	return $file && is_readable( $file ) ? array_values( array_map( 'strtolower', (array) include $file ) ) : array();
}

/**
 * One item for the analyzer per page or post.
 *
 * @param int[] $ids Post IDs; empty for every published page and post.
 * @return array[]
 */
function es_seo_papers( $ids = array() ) {
	$posts = $ids
		? array_filter( array_map( 'get_post', array_map( 'absint', (array) $ids ) ) )
		: get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'has_password' => false ) );
	$words = es_seo_power_words();
	$tests = array();
	$out   = array();
	foreach ( $posts as $post ) {
		$type             = $post->post_type;
		$tests[ $type ] ??= es_seo_tests( $type );
		$designed         = 'page' === $type && function_exists( 'elroi_seo_page_from_theme' ) && elroi_seo_page_from_theme( $post );
		$keywords         = array_values( array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $post->ID, 'rank_math_focus_keyword', true ) ) ), 'strlen' ) );
		$thumb            = (int) get_post_thumbnail_id( $post );
		$page             = es_seo_page( $post );
		$out[]            = array(
			'id'          => (int) $post->ID,
			'type'        => $type,
			'slug'        => $post->post_name,
			'url'         => (int) get_option( 'page_on_front' ) === (int) $post->ID ? home_url( '/' ) : get_permalink( $post ),
			'site'        => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'title'       => $page['title'] ? $page['title'] : (string) get_post_meta( $post->ID, 'rank_math_title', true ),
			'post_title'  => $post->post_title,
			'description' => $page['description'] ? $page['description'] : (string) get_post_meta( $post->ID, 'rank_math_description', true ),
			'keyword'     => $keywords[0] ?? '',
			'keywords'    => $keywords,
			'content'     => $designed ? $page['main'] : (string) $post->post_content,
			'designed'    => $designed,
			'thumb'       => $thumb ? (string) wp_get_attachment_url( $thumb ) : '',
			'thumb_alt'   => $thumb ? (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) : '',
			'stored'      => get_post_meta( $post->ID, 'rank_math_seo_score', true ),
			'tests'       => $tests[ $type ],
			'power_words' => $words,
		);
	}
	return $out;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Export the data Rank Math's analyzer needs, for tests/rank-math/score.js.
	 *
	 * ## OPTIONS
	 *
	 * [--ids=<ids>]
	 * : Comma-separated post IDs. Default: every published page and post.
	 *
	 * [--out=<file>]
	 * : Write to this file instead of printing.
	 */
	WP_CLI::add_command(
		'es seo-papers',
		function ( $args, $assoc ) {
			$ids  = isset( $assoc['ids'] ) ? array_filter( array_map( 'absint', explode( ',', $assoc['ids'] ) ) ) : array();
			$json = wp_json_encode( es_seo_papers( $ids ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( empty( $assoc['out'] ) ) {
				WP_CLI::line( $json );
				return;
			}
			file_put_contents( $assoc['out'], $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			WP_CLI::success( 'Wrote ' . $assoc['out'] );
		}
	);
}
