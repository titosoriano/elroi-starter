<?php
/**
 * Local Playground checks for the starter: every designed page renders, saving each page's form
 * unchanged leaves the HTML identical, and form messages are stored. Open /es-tests/run.php.
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

$render = function ( $url ) {
	$html = wp_remote_retrieve_body( wp_remote_get( $url, array( 'timeout' => 20 ) ) );
	return preg_replace( array( '/name="es_nonce" value="[^"]+"/', '/ver=[\w.]+/' ), array( 'N', 'ver=X' ), $html );
};

/**
 * What search engines read on a rendered page: the h1 and words inside <main>, heading levels,
 * images without an alt attribute, and links whose words say nothing about where they go.
 *
 * @param string $html Page HTML.
 */
function es_test_seo( $html ) {
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	$xp   = new DOMXPath( $dom );
	$main = $xp->query( '//main' )->item( 0 );
	$out  = array( 'main_h1' => false, 'main_words' => 0, 'skips' => array(), 'no_alt' => array(), 'vague' => array() );
	if ( ! $main ) {
		return $out;
	}
	$out['main_h1']    = $xp->query( './/h1', $main )->length > 0;
	$out['main_words'] = str_word_count( wp_strip_all_tags( $dom->saveHTML( $main ) ) );
	$last              = 0;
	foreach ( $xp->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $h ) {
		$level = (int) substr( $h->nodeName, 1 );
		if ( $last && $level > $last + 1 ) {
			$out['skips'][] = $h->nodeName . ': ' . trim( $h->textContent );
		}
		$last = $level;
	}
	foreach ( $xp->query( '//img[not(@alt)]' ) as $img ) {
		$out['no_alt'][] = $img->getAttribute( 'src' );
	}
	if ( function_exists( 'elroi_is_vague_link_text' ) ) {
		foreach ( $xp->query( '//a' ) as $a ) {
			$words = trim( $a->getAttribute( 'aria-label' ) ? $a->getAttribute( 'aria-label' ) : preg_replace( '/\s+/', ' ', $a->textContent ) );
			if ( elroi_is_vague_link_text( $words ) ) {
				$out['vague'][] = $words . ' -> ' . $a->getAttribute( 'href' );
			}
		}
	}
	return $out;
}

foreach ( get_pages() as $p ) {
	if ( ! elroi_template_for( $p ) ) {
		continue;
	}
	$url    = get_permalink( $p );
	$before = $render( $url );
	$check( "{$p->post_name}: one h1, no PHP errors", 1 === substr_count( $before, '<h1' ) && ! preg_match( '/(Fatal error|Warning:|Notice:|Deprecated:)/', $before ) );
	$seo = es_test_seo( $before );
	$check( "{$p->post_name}: the h1 and the page's words are inside <main>", $seo['main_h1'] && $seo['main_words'] > 0, $seo );
	$check( "{$p->post_name}: headings never skip a level", ! $seo['skips'], $seo['skips'] );
	$check( "{$p->post_name}: every image has alt text or is marked decorative", ! $seo['no_alt'], $seo['no_alt'] );
	$check( "{$p->post_name}: no link says only \"Learn more\" or similar", ! $seo['vague'], $seo['vague'] );

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
	$raw                  = get_post_meta( $p->ID, '_elroi_content', true );
	$_POST                = wp_slash( $form );
	$_POST['elroi_nonce'] = wp_create_nonce( 'elroi_save' );
	wp_update_post( array( 'ID' => $p->ID ) );
	$_POST = array();
	$after = $render( $url );
	$check( "{$p->post_name}: unchanged save renders identical HTML", $before === $after, array( 'saved' => get_post_meta( $p->ID, '_elroi_content', true ) ) );
	'' === $raw ? delete_post_meta( $p->ID, '_elroi_content' ) : update_post_meta( $p->ID, '_elroi_content', wp_slash( $raw ) );
}

// Send a message through the form as a visitor (signed out), then read it back.
wp_set_current_user( 0 );
$lead_nonce = wp_create_nonce( 'es_lead' );
wp_set_current_user( 1 );
wp_remote_post(
	admin_url( 'admin-post.php' ),
	array(
		'timeout'     => 20,
		'redirection' => 0,
		'body'        => array( 'action' => 'es_lead', 'es_nonce' => $lead_nonce, 'es_name' => 'Test Person', 'es_phone' => '(555) 010-0000', 'es_email' => 'test@example.com', 'es_service' => 'Service two', 'es_message' => 'Hello from the test.' ),
	)
);
$leads = get_posts( array( 'post_type' => 'es_lead', 'post_status' => 'private', 'numberposts' => 1 ) );
$check( 'a sent form message is stored', $leads && 'test@example.com' === get_post_meta( $leads[0]->ID, 'es_email', true ) && 'Service two' === get_post_meta( $leads[0]->ID, 'es_service', true ) );
$home = $render( home_url( '/' ) );
$check( 'home outputs business schema', str_contains( $home, '"@type":"LocalBusiness"' ) );
$svc = $render( home_url( '/services/service-one/' ) );
$check( 'service page outputs FAQPage and breadcrumbs', str_contains( $svc, '"FAQPage"' ) && str_contains( $svc, '"BreadcrumbList"' ) );

$check( 'FAQ answers show inline links on the page', str_contains( $svc, '>our process</a>' ) );
$check( 'FAQ answers are plain words in the structured data', str_contains( $svc, '"text":"Most projects take two to four weeks. See our process."' ), substr( $svc, strpos( $svc, 'FAQPage' ) ?: 0, 400 ) );
$check( 'service areas go into the business schema', str_contains( $home, '"areaServed":[{"@type":"Place","name":"Morristown, NJ"}' ) );

$failed = array_values( array_filter( $results, fn( $r ) => ! $r['ok'] ) );
echo wp_json_encode( array( 'passed' => count( $results ) - count( $failed ), 'failed' => count( $failed ), 'fails' => $failed ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
