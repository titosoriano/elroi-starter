<?php
/**
 * Checks for a client child theme, on a local WordPress Playground (and on GitHub through
 * .github/workflows/checks.yml): every designed page renders one <h1> without PHP errors and keeps
 * its h1 inside <main>, every image has alt text, saving each page's form unchanged leaves the
 * HTML identical, the blog, a post and the 404 page render, and the site runs as a child of
 * ElRoi Starter. Add the client's own checks before the summary at the end.
 * Open /es-tests/run.php.
 *
 * @package ElRoiStarter
 */

require dirname( __DIR__ ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
wp_set_current_user( 1 );
header( 'Content-Type: application/json' );
$results = array();
$check   = function ( $name, $ok, $info = null ) use ( &$results ) {
	$results[] = array( 'ok' => (bool) $ok, 'check' => $name ) + ( $ok || null === $info ? array() : array( 'info' => $info ) );
};
$render  = function ( $url ) {
	$html = wp_remote_retrieve_body( wp_remote_get( $url, array( 'timeout' => 20 ) ) );
	return preg_replace( array( '/name="es_nonce" value="[^"]+"/', '/ver=[\w.]+/', '/("dateModified":|<p class="es-hero__updated">.*?datetime=)"[^"]+"/' ), array( 'N', 'ver=X', '$1"T"' ), $html ); // Saving a page legitimately changes its updated time.
};
$php_err = '/(Fatal error|Warning:|Notice:|Deprecated:|There has been a critical error)/';

$check( 'runs as a child theme of ElRoi Starter', is_child_theme() && 'elroi-starter' === get_template() );

foreach ( get_pages() as $p ) {
	if ( ! elroi_template_for( $p ) ) {
		continue;
	}
	$url    = get_permalink( $p );
	$before = $render( $url );
	$main   = preg_match( '#<main\b.*?</main>#s', $before, $m ) ? $m[0] : '';
	$check( "{$p->post_name}: one h1 inside <main>, no PHP errors", 1 === substr_count( $before, '<h1' ) && str_contains( $main, '<h1' ) && ! preg_match( $php_err, $before ) );
	$check( "{$p->post_name}: every image has an alt attribute", ! preg_match( '/<img(?![^>]*\salt=)[^>]*>/i', $before ) );

	// Save the page's form exactly as wp-admin shows it: nothing may change.
	ob_start();
	elroi_box( $p );
	$html = ob_get_clean();
	$dom  = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	foreach ( iterator_to_array( $dom->getElementsByTagName( 'template' ) ) as $t ) {
		$t->parentNode->removeChild( $t );
	}
	$pairs = array();
	foreach ( $dom->getElementsByTagName( 'input' ) as $i ) {
		if ( $i->getAttribute( 'name' ) && ( 'checkbox' !== $i->getAttribute( 'type' ) || $i->hasAttribute( 'checked' ) ) ) {
			$pairs[] = rawurlencode( $i->getAttribute( 'name' ) ) . '=' . rawurlencode( $i->getAttribute( 'value' ) );
		}
	}
	foreach ( $dom->getElementsByTagName( 'textarea' ) as $i ) {
		$pairs[] = rawurlencode( $i->getAttribute( 'name' ) ) . '=' . rawurlencode( $i->textContent );
	}
	foreach ( $dom->getElementsByTagName( 'select' ) as $s ) {
		foreach ( $s->getElementsByTagName( 'option' ) as $o ) {
			if ( $o->hasAttribute( 'selected' ) ) {
				$pairs[] = rawurlencode( $s->getAttribute( 'name' ) ) . '=' . rawurlencode( $o->getAttribute( 'value' ) );
			}
		}
	}
	parse_str( implode( '&', $pairs ), $form );
	$cfg                  = elroi_template_for( $p );
	$raw                  = get_post_meta( $p->ID, $cfg['meta_key'], true );
	$_POST                = wp_slash( $form );
	$_POST['elroi_nonce'] = wp_create_nonce( 'elroi_save' );
	wp_update_post( array( 'ID' => $p->ID ) );
	$_POST = array();
	$check( "{$p->post_name}: unchanged save renders identical HTML", $before === $render( $url ) );
	'' === $raw ? delete_post_meta( $p->ID, $cfg['meta_key'] ) : update_post_meta( $p->ID, $cfg['meta_key'], wp_slash( $raw ) );
}

$blog = (int) get_option( 'page_for_posts' );
$post = get_posts( array( 'numberposts' => 1 ) );
foreach ( array_filter( array( 'blog' => $blog ? get_permalink( $blog ) : '', 'post' => $post ? get_permalink( $post[0] ) : '', '404' => home_url( '/no-such-page/' ) ) ) as $name => $url ) {
	$html = $render( $url );
	$check( "$name page renders one h1 without PHP errors", 1 === substr_count( $html, '<h1' ) && ! preg_match( $php_err, $html ) );
}

// Client checks go here.

$failed = array_values( array_filter( $results, fn( $r ) => ! $r['ok'] ) );
echo wp_json_encode( array( 'passed' => count( $results ) - count( $failed ), 'failed' => count( $failed ), 'fails' => $failed ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
