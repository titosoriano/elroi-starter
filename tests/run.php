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

foreach ( get_pages() as $p ) {
	if ( ! elroi_template_for( $p ) ) {
		continue;
	}
	$url    = get_permalink( $p );
	$before = $render( $url );
	$check( "{$p->post_name}: one h1, no PHP errors", 1 === substr_count( $before, '<h1' ) && ! preg_match( '/(Fatal error|Warning:|Notice:|Deprecated:)/', $before ) );

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

$leads = get_posts( array( 'post_type' => 'es_lead', 'post_status' => 'private', 'numberposts' => 1 ) );
$check( 'a sent form message is stored', $leads && 'test@example.com' === get_post_meta( $leads[0]->ID, 'es_email', true ) && 'Service two' === get_post_meta( $leads[0]->ID, 'es_service', true ) );
$home = $render( home_url( '/' ) );
$check( 'home outputs business schema', str_contains( $home, '"@type":"LocalBusiness"' ) );
$svc = $render( home_url( '/services/service-one/' ) );
$check( 'service page outputs FAQPage and breadcrumbs', str_contains( $svc, '"FAQPage"' ) && str_contains( $svc, '"BreadcrumbList"' ) );

$failed = array_values( array_filter( $results, fn( $r ) => ! $r['ok'] ) );
echo wp_json_encode( array( 'passed' => count( $results ) - count( $failed ), 'failed' => count( $failed ), 'fails' => $failed ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
