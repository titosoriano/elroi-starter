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
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_set_current_user( 1 );
header( 'Content-Type: application/json' );
$results = array();
$check   = function ( $name, $ok, $info = null ) use ( &$results ) {
	$results[] = array( 'ok' => (bool) $ok, 'check' => $name ) + ( $ok || null === $info ? array() : array( 'info' => $info ) );
};

$render = function ( $url ) {
	$html = wp_remote_retrieve_body( wp_remote_get( $url, array( 'timeout' => 20 ) ) );
	return preg_replace( array( '/name="es_nonce" value="[^"]+"/', '/ver=[\w.]+/', '/("dateModified":|<p class="es-hero__updated">.*?datetime=)"[^"]+"/' ), array( 'N', 'ver=X', '$1"T"' ), $html ); // Saving a page legitimately changes its updated time.
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

// A CRM form replaces the built-in fields inside the same designed box, and switching back restores them.
$contact_url = get_permalink( get_page_by_path( 'contact' ) );
update_option( 'es_form', array( 'mode' => 'embed', 'embed' => '<div class="crm-test">CRM FORM [es_test_code]</div>' ) );
add_shortcode( 'es_test_code', fn() => 'shortcode-ran' );
$crm = es_form_embed_html();
$check( 'a pasted CRM form (and its shortcodes) is what the form shows', str_contains( $crm, 'crm-test' ) && str_contains( $crm, 'shortcode-ran' ), $crm );
ob_start();
es_lead_form( 'Title here', 'Text here' );
$box = ob_get_clean();
$check( 'the CRM form keeps the box, title, text and #lead-form anchor, without the built-in fields', str_contains( $box, 'id="lead-form"' ) && str_contains( $box, 'Title here' ) && str_contains( $box, 'crm-test' ) && ! str_contains( $box, 'name="es_name"' ), $box );
$check( 'Site Health says the form is connected when it uses a CRM form', 'good' === es_form_health_test()['status'] );
update_option( 'es_form', array( 'mode' => 'builtin', 'embed' => '<div class="crm-test">kept</div>' ) );
ob_start();
es_lead_form();
$box = ob_get_clean();
$check( 'switching back shows the built-in form and keeps the pasted code', str_contains( $box, 'name="es_name"' ) && str_contains( es_form_connection()['embed'], 'kept' ) );
$check( 'Site Health recommends connecting the form when messages only stay in wp-admin', 'recommended' === es_form_health_test()['status'] || es_mail_plugin_active() );
ob_start();
es_form_connect_page();
$screen = ob_get_clean();
$check( 'the Connect a form screen offers both choices and the code box', str_contains( $screen, 'value="builtin"' ) && str_contains( $screen, 'value="embed"' ) && str_contains( $screen, 'name="es_form[embed]"' ) && str_contains( $screen, 'kept' ) );
if ( ! defined( 'FLUENTMAIL' ) ) {
	define( 'FLUENTMAIL', 'test' );
}
$fluent = get_option( 'fluentmail-settings' );
update_option( 'fluentmail-settings', array( 'connections' => array() ) );
$fluent_empty = es_mail_plugin_active();
update_option( 'fluentmail-settings', array( 'connections' => array( 'abc' => array( 'title' => 'Hostinger' ) ) ) );
$fluent_set = es_mail_plugin_active();
false === $fluent ? delete_option( 'fluentmail-settings' ) : update_option( 'fluentmail-settings', $fluent );
$check( 'FluentSMTP counts as connected only once it has a mail connection set up', ! $fluent_empty && $fluent_set );
delete_option( 'es_form' );
$san = es_form_sanitize( array( 'mode' => 'embed', 'embed' => '<script>x()</script><iframe src="https://crm.example/f"></iframe>' ) );
wp_set_current_user( 0 );
$san_anon = es_form_sanitize( array( 'mode' => 'embed', 'embed' => '<script>x()</script><iframe src="https://crm.example/f"></iframe>' ) );
wp_set_current_user( 1 );
$check( 'embed code keeps scripts only for people allowed to post raw HTML', ( ! current_user_can( 'unfiltered_html' ) || str_contains( $san['embed'], '<script>' ) ) && ! str_contains( $san_anon['embed'], '<script>' ) && str_contains( $san_anon['embed'], '<iframe' ), array( $san, $san_anon ) );

// Video links: YouTube, Vimeo and files are recognized; anything else is not a video.
$yt = es_video_source( 'https://youtu.be/dQw4w9WgXcQ?t=3' );
$check( 'YouTube links become a privacy-friendly player and a cover', $yt && 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ' === $yt['embed'] && str_contains( $yt['thumb'], 'dQw4w9WgXcQ' ) );
$check( 'Vimeo and MP4 links are recognized, other links are not', 'vimeo' === ( es_video_source( 'https://vimeo.com/76979871' )['provider'] ?? '' ) && 'video/mp4' === ( es_video_source( 'https://x.test/a/clip.mp4' )['type'] ?? '' ) && null === es_video_source( 'https://example.com/page' ) );

// A video on a service page shows a player and becomes a VideoObject.
$one_id  = get_page_by_path( 'services/service-one' )->ID;
$one_raw = get_post_meta( $one_id, '_elroi_content', true );
$one_c   = json_decode( $one_raw, true );
$one_c['video'] = array( 'title' => 'How a project runs', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'description' => 'A two-minute walk through a project.', 'date' => '2026-01-10' );
update_post_meta( $one_id, '_elroi_content', wp_slash( wp_json_encode( $one_c ) ) );
$vid = $render( home_url( '/services/service-one/' ) );
$check( 'a video field shows a cover that plays the video', str_contains( $vid, 'data-es-video="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"' ) && str_contains( $vid, 'Play video: How a project runs' ) );
$check( 'a video field becomes VideoObject structured data with name, cover, date and player', str_contains( $vid, '"@type":"VideoObject"' ) && str_contains( $vid, '"name":"How a project runs"' ) && str_contains( $vid, '"thumbnailUrl":["https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg"]' ) && str_contains( $vid, '"uploadDate":"2026-01-10T12:00:00' ) && str_contains( $vid, '"embedUrl":"https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"' ), substr( $vid, strpos( $vid, 'VideoObject' ) ?: 0, 500 ) );
$seo_vid = es_test_seo( $vid );
$check( 'the video page still passes the heading, alt and link checks', ! $seo_vid['skips'] && ! $seo_vid['no_alt'] && ! $seo_vid['vague'], $seo_vid );
$one_c['video'] = array( 'title' => 'No cover', 'video_url' => 'https://vimeo.com/76979871' );
$check( 'a Vimeo video without a cover photo gets no structured data (Google needs one)', null === es_video_schema( $one_c['video'], get_post( $one_id ) ) );
update_post_meta( $one_id, '_elroi_content', wp_slash( $one_raw ) );

// Posts get an "On this page" list once they have two H2s; one that already has a TOC block does not.
$toc_post = wp_insert_post( array( 'post_status' => 'publish', 'post_title' => 'TOC test', 'post_name' => 'toc-test', 'post_content' => '<h2>First part</h2><p>a</p><h3>A detail</h3><p>b</p><h2>First part</h2><p>c</p>' ) );
$toc_html = $render( get_permalink( $toc_post ) );
$check( 'posts with two or more H2s get an "On this page" list that links to each heading', str_contains( $toc_html, 'class="es-toc"' ) && str_contains( $toc_html, '<h2 id="first-part">' ) && str_contains( $toc_html, '<h2 id="first-part-2">' ) && str_contains( $toc_html, 'href="#a-detail"' ) && str_contains( $toc_html, 'href="#first-part-2"' ), substr( $toc_html, strpos( $toc_html, 'es-toc' ) ?: 0, 600 ) );
$check( 'the list is valid nesting (every list closes)', substr_count( $toc_html, '<ol>' ) === substr_count( $toc_html, '</ol>' ) );
wp_update_post( array( 'ID' => $toc_post, 'post_content' => '<div class="wp-block-rank-math-toc-block"><nav>x</nav></div><h2>One</h2><h2>Two</h2>' ) );
$check( 'a post that already has a table of contents block gets no second one', ! str_contains( $render( get_permalink( $toc_post ) ), 'class="es-toc"' ) );
wp_update_post( array( 'ID' => $toc_post, 'post_content' => '<h2>Only one</h2><p>Short post.</p>' ) );
$check( 'a short post with one H2 gets no list', ! str_contains( $render( get_permalink( $toc_post ) ), 'class="es-toc"' ) );
wp_delete_post( $toc_post, true );
$check( 'Rank Math is told the site has a table of contents', isset( apply_filters( 'rank_math/researches/toc_plugins', array() )['elroi-site-editor/elroi-site-editor.php'] ) );

// Photos: new uploads get WebP copies when the server can write WebP; layouts ask for the right size.
$fmt = apply_filters( 'image_editor_output_format', array(), 'x.jpg', 'image/jpeg' );
$check( 'new JPEG uploads get WebP copies when the server supports it', wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ? 'image/webp' === ( $fmt['image/jpeg'] ?? '' ) : empty( $fmt['image/jpeg'] ) );
$check( 'half-width photos ask phones for a full-width copy and desktops for under half', '(min-width: 900px) 45vw, 100vw' === es_sizes( 'half' ) );

// Child themes can extend the starter without copying its files.
add_filter( 'es_business_schema', $add_yt = fn( $n ) => $n + array( 'slogan' => 'Test slogan' ) );
$check( 'a child theme can add business details through es_business_schema', 'Test slogan' === ( es_business_schema()['slogan'] ?? '' ) );
remove_filter( 'es_business_schema', $add_yt );
add_filter( 'es_settings', $set_co = function ( $s ) {
	$s['sections']['business']['fields']['company']['default'] = 'Child Co';
	return $s;
} );
do_action( 'after_setup_theme' );
$check( 'a child theme sets Site Settings defaults through es_settings', 'Child Co' === ( elroi_settings_config()['sections']['business']['fields']['company']['default'] ?? '' ) );
remove_filter( 'es_settings', $set_co );
do_action( 'after_setup_theme' );
$check( 'on the starter itself, the child folder is the starter folder', ES_CHILD_DIR === ES_DIR && ES_VERSION === es_child_version() );

// Updates: a newer GitHub release with elroi-starter.zip is offered as a theme update; the same version is not.
$fake_release = function ( $version ) {
	return function ( $pre, $args, $url ) use ( $version ) {
		if ( ! str_contains( $url, 'api.github.com/repos/titosoriano/elroi-starter/releases/latest' ) ) {
			return $pre;
		}
		$body = array( 'tag_name' => 'v' . $version, 'body' => 'notes', 'assets' => array( array( 'name' => 'elroi-starter.zip', 'browser_download_url' => 'https://github.com/titosoriano/elroi-starter/releases/download/v' . $version . '/elroi-starter.zip' ) ) );
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => wp_json_encode( $body ), 'headers' => array(), 'cookies' => array() );
	};
};
foreach ( array( '99.0.0' => true, ES_VERSION => false ) as $v => $offered ) {
	delete_site_transient( 'es_starter_release' );
	add_filter( 'pre_http_request', $f = $fake_release( (string) $v ), 10, 3 );
	$t = apply_filters( 'pre_set_site_transient_update_themes', (object) array( 'response' => array() ) );
	remove_filter( 'pre_http_request', $f, 10 );
	$got = $t->response['elroi-starter'] ?? null;
	$check( $offered ? 'a newer release is offered as a theme update with its zip' : 'the installed version is not offered again', $offered ? ( '99.0.0' === ( $got['new_version'] ?? '' ) && str_ends_with( $got['package'] ?? '', '/v99.0.0/elroi-starter.zip' ) ) : null === $got, $got );
}
delete_site_transient( 'es_starter_release' );

// Backups: Site Health and es_backup_status() flag a site without recent off-site backups.
$b = es_backup_status();
$check( 'without a backup plugin, Site Health recommends one', ! $b['ok'] && 'recommended' === es_backup_health_test()['status'] && isset( apply_filters( 'site_status_tests', array( 'direct' => array() ) )['direct']['es_backups'] ) );
if ( ! class_exists( 'UpdraftPlus' ) ) {
	eval( 'class UpdraftPlus {}' ); // phpcs:ignore Squiz.PHP.Eval -- stand-in for the plugin in this test only.
}
update_option( 'updraft_last_backup', array( 'success' => 1, 'backup_time' => time() - HOUR_IN_SECONDS ) );
update_option( 'updraft_service', array( 'googledrive' ) );
update_option( 'updraft_retain', 1 );
$b = es_backup_status();
$check( 'a fresh Google Drive backup that keeps only one copy is flagged for keeping more', ! $b['ok'] && 1 === count( $b['problems'] ) && str_contains( $b['problems'][0], 'at least 7' ), $b );
update_option( 'updraft_retain', 7 );
$check( 'a fresh off-site backup keeping 7 copies passes', es_backup_status()['ok'] && 'good' === es_backup_health_test()['status'] );
update_option( 'updraft_last_backup', array( 'success' => 1, 'backup_time' => time() - 20 * DAY_IN_SECONDS ) );
update_option( 'updraft_service', array( 'none' ) );
$check( 'an old backup kept only on the server is flagged twice', 2 === count( es_backup_status()['problems'] ) );
foreach ( array( 'updraft_last_backup', 'updraft_service', 'updraft_retain' ) as $o ) {
	delete_option( $o );
}

// Motion layer, auto mode: the libraries load only on pages that use a motion hook.
$check( 'motion: plain add_theme_support keeps loading on every page (older child themes)', ( function () {
	add_theme_support( 'es-motion' );
	$o = es_motion_options();
	remove_theme_support( 'es-motion' );
	return 'always' === $o['load'];
} )() );
$check( 'motion: starter hooks and child markers are found, look-alike attributes are not', es_motion_html_uses( '<h2 data-es-split="0.2">x</h2>', es_motion_markers( array( 'markers' => array() ) ) ) && es_motion_html_uses( '<div class="a" data-reveal>x</div>', array( 'data-reveal' ) ) && ! es_motion_html_uses( '<div data-revealed="1" data-es-notice>x</div>', es_motion_markers( array( 'markers' => array( 'data-reveal' ) ) ) ) && ! es_motion_html_uses( '<p>data-es-split in text</p>', es_motion_markers( array( 'markers' => array() ) ) ) );
add_theme_support( 'es-motion', array( 'load' => 'auto', 'markers' => array( 'data-reveal' ) ) );
$motion_reset = function () {
	foreach ( array( 'es-gsap', 'es-scrolltrigger', 'es-lenis', 'es-motion', 'es-test-scene' ) as $h ) {
		wp_dequeue_script( $h );
		wp_deregister_script( $h );
	}
	wp_dequeue_style( 'es-motion' );
	wp_deregister_style( 'es-motion' );
	es_motion_register( es_motion_options() );
	wp_register_script( 'es-test-scene', 'https://example.com/scene.js', array( 'es-motion' ), '1', array( 'in_footer' => true ) );
};
$motion_reset();
$used = es_motion_decide( '<main><h1>Plain page</h1></main>' );
$stub = wp_scripts()->registered['es-motion'] ?? null;
$check( 'motion: a page without hooks loads none of the libraries, and ESMotion is a stand-in', ! $used && $stub && false === $stub->src && array() === $stub->deps && str_contains( implode( '', (array) $stub->extra['after'] ), 'scene:function(){}' ) && ! wp_script_is( 'es-gsap', 'enqueued' ) && ! wp_style_is( 'es-motion', 'enqueued' ) );
$motion_reset();
$used = es_motion_decide( '<main><h1 data-reveal>Animated page</h1></main>' );
$check( 'motion: a page with a child marker loads the libraries', $used && wp_script_is( 'es-motion', 'enqueued' ) && str_ends_with( wp_scripts()->registered['es-motion']->src, '/assets/js/motion.js' ) && wp_style_is( 'es-motion', 'enqueued' ) );

// LiteSpeed must leave the motion scripts (and scripts built on them) alone.
$tag  = fn( $h ) => apply_filters( 'script_loader_tag', '<script src="https://example.com/' . $h . '.js" id="' . $h . '-js" defer data-wp-strategy="defer"></script>', $h, '' );
$check( 'speed: motion scripts are never delayed by LiteSpeed; libraries are not minified again', str_contains( $tag( 'es-gsap' ), '<script data-no-defer="1" data-no-optimize="1" src=' ) && str_contains( $tag( 'es-test-scene' ), 'data-no-defer="1"' ) && ! str_contains( $tag( 'es-test-scene' ), 'data-no-optimize' ) && ! str_contains( $tag( 'es-site' ), 'data-no-' ), array( $tag( 'es-gsap' ), $tag( 'es-test-scene' ) ) );
remove_theme_support( 'es-motion' );

// Speed check: .htaccess that keeps pages in browsers, LiteSpeed settings, Google Fonts.
require_once ABSPATH . 'wp-admin/includes/file.php';
$ht = wp_tempnam( 'es-htaccess' );
file_put_contents( $ht, "# BEGIN WordPress\n<IfModule mod_expires.c>\nExpiresActive On\nExpiresByType image/png \"access plus 1 year\"\nExpiresDefault \"access plus 1 weeks\"\n</IfModule>\n# END WordPress\n" );
$before = es_speed_html_cached( $ht );
es_speed_fix_html_cache( $ht );
$check( 'speed: a one-week ExpiresDefault is found and the fix keeps pages fresh', $before && ! es_speed_html_cached( $ht ) && str_contains( file_get_contents( $ht ), 'ExpiresByType text/html "access plus 0 seconds"' ) && str_contains( file_get_contents( $ht ), 'ExpiresDefault' ) );
file_put_contents( $ht, "# ExpiresDefault \"access plus 1 weeks\"\nRewriteEngine On\n" );
$check( 'speed: a commented-out ExpiresDefault is not a problem', ! es_speed_html_cached( $ht ) );
unlink( $ht );
add_filter( 'es_speed_htaccess_file', $no_ht = fn() => '/nonexistent/.htaccess' );
add_filter( 'es_speed_litespeed_active', '__return_true' );
update_option( 'litespeed.conf.cache', 1 );
update_option( 'litespeed.conf.optm-js_defer', 2 );
update_option( 'litespeed.conf.media-lazy', '' );
update_option( 'litespeed.conf.optm-ucss', '1' );
$diff = es_speed_litespeed_diff();
$check( 'speed: LiteSpeed "Delayed" JS and UCSS are flagged; page cache on and lazy load off pass; unknown options are skipped', array( 'optm-ucss', 'optm-js_defer' ) === array_values( array_intersect( array( 'optm-ucss', 'optm-js_defer' ), array_keys( $diff ) ) ) && 2 === count( $diff ) && 0 === $diff['optm-js_defer'][1], $diff );
$check( 'speed: Site Health recommends the fix with a button', 'recommended' === es_speed_health_test()['status'] && str_contains( es_speed_health_test()['actions'], 'es_speed_apply' ) && isset( apply_filters( 'site_status_tests', array( 'direct' => array() ) )['direct']['es_speed'] ) );
foreach ( array( 'cache', 'optm-js_defer', 'media-lazy', 'optm-ucss' ) as $o ) {
	delete_option( 'litespeed.conf.' . $o );
}
remove_filter( 'es_speed_litespeed_active', '__return_true' );
update_option( 'es_speed_remote_fonts', array( 'https://fonts.googleapis.com/css2' ) );
$s = es_speed_status();
$check( 'speed: Google Fonts added by a plugin are reported', ! $s['ok'] && 1 === count( $s['problems'] ) && str_contains( $s['problems'][0], 'fonts.googleapis.com' ), $s );
delete_option( 'es_speed_remote_fonts' );
$check( 'speed: without LiteSpeed, page caching rules or Google Fonts there is nothing to fix', es_speed_status()['ok'] && 'good' === es_speed_health_test()['status'] );
remove_filter( 'es_speed_htaccess_file', $no_ht );
$check( 'speed: the starter pages load no motion libraries and no Google Fonts', ! str_contains( $render( home_url( '/' ) ), 'gsap.min.js' ) && ! str_contains( $render( home_url( '/' ) ), 'fonts.googleapis.com' ) );

// Short name (ElRoi Site Editor 1.4+): breadcrumbs show it, the h1 keeps the full title.
if ( function_exists( 'elroi_full_title' ) ) {
	$sn_id = get_page_by_path( 'about' )->ID;
	update_post_meta( $sn_id, '_elroi_short_name', 'About' );
	wp_update_post( array( 'ID' => $sn_id, 'post_title' => 'About Our Remodeling Company' ) );
	$sn_html = $render( get_permalink( $sn_id ) );
	$check( 'short name: the h1 keeps the full title, the breadcrumb shows the short name', (bool) preg_match( '#<h1[^>]*>About Our Remodeling Company</h1>#', $sn_html ) && str_contains( $sn_html, '<span aria-current="page">About</span>' ), substr( $sn_html, (int) strpos( $sn_html, 'es-crumbs' ), 600 ) );
	delete_post_meta( $sn_id, '_elroi_short_name' );
	wp_update_post( array( 'ID' => $sn_id, 'post_title' => 'About' ) );
}

// Blog: whole grid rows (feature + 9 on page 1, 12 after), reading time, related posts, credits.
$bl_cat   = wp_insert_term( 'Kitchens test', 'category' );
$bl_cat   = is_wp_error( $bl_cat ) ? (int) $bl_cat->get_error_data( 'term_exists' ) : (int) $bl_cat['term_id'];
$bl_ids   = array();
$bl_total = (int) wp_count_posts()->publish;
for ( $i = $bl_total; $i < 22; $i++ ) {
	$bl_ids[] = wp_insert_post( array( 'post_status' => 'publish', 'post_title' => "Blog test $i", 'post_content' => '<p>' . str_repeat( 'word ', 700 ) . '</p>', 'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( "-$i days" ) ), 'post_category' => $i < 6 ? array( $bl_cat ) : array() ) );
}
$bl_blog = get_permalink( get_option( 'page_for_posts' ) );
$bl_p1   = $render( $bl_blog );
$bl_p2   = $render( trailingslashit( $bl_blog ) . 'page/2/' );
$bl_p3   = wp_remote_retrieve_response_code( wp_remote_get( trailingslashit( $bl_blog ) . 'page/3/', array( 'timeout' => 20 ) ) );
$check( 'blog: page 1 is the newest post as a feature plus 9 cards', 1 === substr_count( $bl_p1, '<li class="es-card es-card--feature">' ) && 10 === substr_count( $bl_p1, '<li class="es-card' ), substr_count( $bl_p1, '<li class="es-card' ) );
$check( 'blog: page 2 holds the remaining 12 posts and nothing is left for a page 3', 12 === substr_count( $bl_p2, '<li class="es-card' ) && ! str_contains( $bl_p2, '<li class="es-card es-card--feature">' ) && 404 === $bl_p3 && ! str_contains( $bl_p2, '/page/3/' ), array( substr_count( $bl_p2, '<li class="es-card' ), $bl_p3 ) );
$check( 'blog: page 1 links to page 2 and no further', str_contains( $bl_p1, '/page/2/' ) && ! str_contains( $bl_p1, '/page/3/' ) );
$check( 'blog: cards show the reading time', str_contains( $bl_p1, '3 min read' ) );
$check( 'blog: the page count follows the page sizes', 1 === es_blog_page_count( 10 ) && 2 === es_blog_page_count( 11 ) && 2 === es_blog_page_count( 22 ) && 3 === es_blog_page_count( 23 ) );
add_filter( 'es_blog_page_sizes', '__return_false' );
$check( 'blog: es_blog_page_sizes false gives Settings > Reading back', null === es_blog_page_sizes() && 0 === es_blog_page_count( 30 ) );
remove_filter( 'es_blog_page_sizes', '__return_false' );
$bl_post = $bl_ids[0];
wp_update_post( array( 'ID' => $bl_post, 'post_date' => '2026-01-05 10:00:00', 'post_date_gmt' => '2026-01-05 10:00:00' ) );
$bl_single = $render( get_permalink( $bl_post ) );
$check( 'post: shows reading time and the date it was updated', str_contains( $bl_single, '3 min read' ) && str_contains( $bl_single, 'Updated <time datetime=' ), substr( $bl_single, (int) strpos( $bl_single, 'es-post__meta' ), 500 ) );
$bl_rel = es_related_posts( $bl_post );
$check( 'post: "Keep reading" lists 3 posts, the same category first', 3 === count( $bl_rel ) && has_category( $bl_cat, $bl_rel[0] ) && has_category( $bl_cat, $bl_rel[1] ) && ! in_array( $bl_post, wp_list_pluck( $bl_rel, 'ID' ), true ) && str_contains( $bl_single, 'id="es-related-title"' ) && 3 === substr_count( $bl_single, '<h3 class="es-card__title">' ) );
$GLOBALS['wp_query']     = new WP_Query( array( 'p' => $bl_post ) );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$bl_ld = apply_filters( 'rank_math/json_ld', array( 'ProfilePage' => array( '@type' => 'Person', 'name' => 'admin' ), 'richSnippet' => array( '@type' => 'BlogPosting', 'author' => array( '@id' => 'x#author' ) ) ) );
$check( 'post: Rank Math\'s BlogPosting credits the business, not the WordPress user', ! isset( $bl_ld['ProfilePage'] ) && home_url( '/#organization' ) === ( $bl_ld['richSnippet']['author']['@id'] ?? '' ), $bl_ld );
wp_reset_query();
foreach ( $bl_ids as $bl_id ) {
	wp_delete_post( $bl_id, true );
}
wp_delete_term( $bl_cat, 'category' );

// Service pages: Service schema; every page: WebPage with its dates and a visible "Updated" date.
$sv_html = $render( get_permalink( get_page_by_path( 'services/service-one' ) ) );
$check( 'service page: Service schema names the service, the business as provider and the service areas', str_contains( $sv_html, '"@type":"Service"' ) && str_contains( $sv_html, '"provider":{"@id":"' . home_url( '/#organization' ) . '"}' ) && str_contains( $sv_html, '"name":"Service one, done right."' ) && str_contains( $sv_html, '"areaServed":[{"@type":"Place","name":"Morristown, NJ"}' ), substr( $sv_html, (int) strpos( $sv_html, '"Service"' ), 600 ) );
$check( 'pages: WebPage schema carries the published and updated dates', str_contains( $sv_html, '"@type":"WebPage"' ) && str_contains( $sv_html, '"dateModified":"' ) && str_contains( $sv_html, '"datePublished":"' ) );
$check( 'pages: the hero shows the updated date', str_contains( $sv_html, '<p class="es-hero__updated">Updated <time datetime="' ) );
$check( 'other pages get no Service schema', ! str_contains( $render( get_permalink( get_page_by_path( 'about' ) ) ), '"@type":"Service"' ) );

// First paint: the theme's stylesheets print inline, minified, with absolute url()s; preloads.
$home_html = $render( home_url( '/' ) );
$check( 'first paint: tokens.css and site.css print inline, not as files', str_contains( $home_html, '<style id="es-tokens-inline-file">' ) && str_contains( $home_html, '<style id="es-site-inline-file">' ) && ! preg_match( '#<link[^>]+/assets/css/(tokens|site)\.css#', $home_html ), substr( $home_html, 0, 3000 ) );
preg_match( '#<style id="es-site-inline-file">(.*?)</style>#s', $home_html, $es_m );
$check( 'first paint: the inline CSS has no comments or indentation', isset( $es_m[1] ) && '' !== trim( $es_m[1] ) && ! str_contains( $es_m[1], '/*' ) && ! preg_match( '/^[ \t]+/m', $es_m[1] ) );
$check( 'first paint: relative url()s become absolute, without ?ver', 'http://x.test/wp-content/themes/t/assets/fonts/a.woff2' === es_css_resolve( '../fonts/a.woff2', 'http://x.test/wp-content/themes/t/assets/css/site.css?ver=1' ) && 'http://x.test/wp-content/themes/t/assets/css/img/b.svg' === es_css_resolve( './img/b.svg', 'http://x.test/wp-content/themes/t/assets/css/site.css' ) );
$es_tmp = ES_DIR . '/assets/css/es-test-inline.css';
file_put_contents( $es_tmp, "/* note */\n@font-face {\n\tsrc: url('../fonts/x.woff2') format('woff2');\n}\n.a { background: url(data:image/png;base64,AAA) , url(\"/root.png\"), url(https://cdn.test/y.png); }\n" );
$es_css = es_inline_css( ES_URI . '/assets/css/es-test-inline.css?ver=9' );
$check( 'first paint: font URLs resolve to the theme folder; data:, root and remote URLs are kept', is_string( $es_css ) && str_contains( $es_css, 'url("' . ES_URI . '/assets/fonts/x.woff2")' ) && str_contains( $es_css, 'url(data:image/png;base64,AAA)' ) && str_contains( $es_css, 'url("/root.png")' ) && str_contains( $es_css, 'url(https://cdn.test/y.png)' ) && ! str_contains( $es_css, 'note' ), $es_css );
file_put_contents( $es_tmp, '.a{}</style><script>x</script>' );
$check( 'first paint: CSS that could close the <style> element stays a file', null === es_inline_css( ES_URI . '/assets/css/es-test-inline.css?ver=10' ) );
unlink( $es_tmp );
$es_link = "<link rel='stylesheet' id='x-css' href='" . ES_URI . "/assets/css/site.css?ver=1' media='all' />\n";
$check( 'first paint: other handles and remote stylesheets stay links', $es_link === apply_filters( 'style_loader_tag', $es_link, 'some-plugin', ES_URI . '/assets/css/site.css?ver=1', 'all' ) && $es_link === apply_filters( 'style_loader_tag', $es_link, 'es-site', 'https://cdn.test/site.css', 'all' ) );
add_filter( 'es_inline_styles', '__return_empty_array' );
$check( 'first paint: es_inline_styles can turn inlining off', $es_link === apply_filters( 'style_loader_tag', $es_link, 'es-site', ES_URI . '/assets/css/site.css?ver=1', 'all' ) );
remove_filter( 'es_inline_styles', '__return_empty_array' );
add_filter( 'es_inline_max_bytes', $es_small = fn() => 10 );
$check( 'first paint: stylesheets over the size limit stay links', $es_link === apply_filters( 'style_loader_tag', $es_link, 'es-site', ES_URI . '/assets/css/site.css?ver=1', 'all' ) );
remove_filter( 'es_inline_max_bytes', $es_small );
$check( 'first paint: a print stylesheet keeps its media', str_contains( (string) apply_filters( 'style_loader_tag', $es_link, 'es-site', ES_URI . '/assets/css/site.css?ver=1', 'print' ), '<style id="es-site-inline-file" media="print">' ) );

add_theme_support( 'es-preload', array( 'fonts' => array( 'assets/fonts/test.woff2' ), 'hosts' => array( 'https://cdn.example.com' ) ) );
ob_start();
es_print_preloads();
$es_pre = ob_get_clean();
$check( 'preload: es-preload fonts print as font preloads with crossorigin, from the theme folder', str_contains( $es_pre, '<link rel="preload" href="' . get_theme_file_uri( 'assets/fonts/test.woff2' ) . '" as="font" type="font/woff2" crossorigin>' ), $es_pre );
$es_hints = apply_filters( 'wp_resource_hints', array(), 'preconnect' );
$check( 'preload: es-preload hosts get a preconnect', in_array( array( 'href' => 'https://cdn.example.com', 'crossorigin' ), $es_hints, true ), $es_hints );
remove_theme_support( 'es-preload' );
ob_start();
es_print_preloads();
$check( 'preload: nothing prints without es-preload or es_preload links', '' === ob_get_clean() );

// Site icon stays PNG; share images use the original upload instead of a WebP copy.
$es_up  = wp_upload_dir();
$es_png = $es_up['basedir'] . '/es-test-share.png';
file_put_contents( $es_png, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAAEUlEQVR42mP8z8DwnwEIGGEAAB0TAgFJ6bVsAAAAAElFTkSuQmCC' ) );
$es_att = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'es-test-share', 'post_status' => 'inherit' ), $es_png );
update_option( 'site_icon', $es_att );
$es_fmt_icon  = apply_filters( 'image_editor_output_format', array(), $es_up['basedir'] . '/es-test-share-180x180.png', 'image/png' );
$es_fmt_photo = apply_filters( 'image_editor_output_format', array(), $es_up['basedir'] . '/photo.jpg', 'image/jpeg' );
$check( 'webp: the site icon\'s sizes stay PNG (iOS ignores WebP touch icons); other photos still convert', empty( $es_fmt_icon['image/png'] ) && ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) || 'image/webp' === ( $es_fmt_photo['image/jpeg'] ?? '' ) ), array( $es_fmt_icon, $es_fmt_photo ) );
delete_option( 'site_icon' );
$es_share = apply_filters( 'rank_math/opengraph/facebook/image_array', array( 'id' => $es_att, 'url' => $es_up['baseurl'] . '/es-test-share-1200x630.webp', 'width' => 1200, 'height' => 630, 'type' => 'image/webp' ) );
$check( 'webp: share images point to the original PNG/JPEG with its real size and type', wp_get_attachment_url( $es_att ) === $es_share['url'] && 'image/png' === $es_share['type'] && 2 === $es_share['width'] && 1 === $es_share['height'], $es_share );
$es_jpg = array( 'id' => $es_att, 'url' => $es_up['baseurl'] . '/photo.jpg', 'type' => 'image/jpeg' );
$check( 'webp: share images that are not WebP are left alone', $es_jpg === apply_filters( 'rank_math/opengraph/twitter/image_array', $es_jpg ) );
wp_delete_attachment( $es_att, true );

// In-text links: off without a map; with one, mentions in long body paragraphs link once each.
$il_two  = get_page_by_path( 'services/service-two' );
$il_raw  = get_post_meta( $il_two->ID, '_elroi_content', true );
$il_long = ' We plan every job with you, write the scope down and keep the site clean every day.';
update_post_meta(
	$il_two->ID,
	'_elroi_content',
	wp_slash(
		wp_json_encode(
			array(
				'sections' => array(
					array(
						'heading'    => 'Kitchen remodeling and more',
						'paragraphs' => array(
							'We handle kitchen remodeling and bathroom remodeling, and roofing too.' . $il_long,
							'Our roofing crews also fix gutters, and kitchen remodeling questions are welcome.' . $il_long,
							'Roofing and painting too.',
							'Ask about [painting](/blog/) and painting the trim of older homes every spring.' . $il_long,
						),
					),
				),
			)
		)
	)
);
$il_url  = get_permalink( $il_two );
$il_html = $render( $il_url );
$check( 'in-text links: none without rows in Site Settings', ! str_contains( $il_html, 'es-inlink' ) );
$il_opt  = elroi_settings_config()['option'];
$il_was  = get_option( $il_opt, array() );
$il_rows = array(
	array( 'words' => 'kitchen remodeling, kitchens', 'to' => '/services/service-one/' ),
	array( 'words' => 'bathroom remodeling', 'to' => '/about/' ),
	array( 'words' => 'roofing', 'to' => '/contact/' ),
	array( 'words' => 'gutters', 'to' => '/services/service-two/' ),
	array( 'words' => 'painting', 'to' => '/blog/' ),
	array( 'words' => 'windows', 'to' => '/services/' ),
	array( 'words' => 'siding', 'to' => '/' ),
);
update_option( $il_opt, array_merge( (array) $il_was, array( 'inlinks' => $il_rows ) ) );
$il_html = $render( $il_url );
preg_match_all( '#<a class="es-inlink" href="([^"]+)">([^<]+)</a>#', $il_html, $il_m );
$il_paths = array_map( fn( $u ) => wp_parse_url( $u, PHP_URL_PATH ), $il_m[1] );
$check( 'in-text links: mentions link to their page, each page once, 2 per paragraph at most (kitchen, bathroom, then roofing in the next paragraph)', array( '/services/service-one/', '/about/', '/contact/' ) === $il_paths, $il_m[0] );
$check( 'in-text links: never to the page itself, never in headings, short lines or where the text already links', ! preg_match( '#<h2[^>]*>[^<]*<a#', $il_html ) && ! str_contains( $il_html, 'es-inlink" href="' . esc_url( home_url( '/blog/' ) ) ) && ! str_contains( $il_html, 'es-inlink" href="' . esc_url( $il_url ) ), $il_m[0] );
$check( 'in-text links: the page still has one h1 and no PHP errors', 1 === substr_count( $il_html, '<h1' ) && ! preg_match( '/(Fatal error|Warning:|Notice:|Deprecated:)/', $il_html ) );
$il_post = wp_insert_post(
	array(
		'post_status'  => 'publish',
		'post_title'   => 'In-text link test',
		'post_name'    => 'inlink-test',
		'post_content' => '<h2>Siding</h2><p>Kitchen remodeling, bathroom remodeling and roofing were all on the list for this house last year.</p>'
			. '<p>The roofing crew also replaced the gutters before the winter storms arrived in the middle of December.</p>'
			. '<ul><li>We also talked about painting the porch and new windows for the back of the house next spring.</li></ul>'
			. '<p>Siding came last, once the rest of the outside of the house was finished and the weather turned warm.</p>',
	)
);
$il_phtml = $render( get_permalink( $il_post ) );
$check( 'in-text links: a blog post gets at most 5, in paragraphs and list items', 5 === substr_count( $il_phtml, 'class="es-inlink"' ) && str_contains( $il_phtml, '>painting</a>' ) && ! str_contains( $il_phtml, '>windows</a>' ) && ! str_contains( $il_phtml, '>Siding</a>' ), substr_count( $il_phtml, 'class="es-inlink"' ) );
update_option( $il_opt, $il_was );
'' === $il_raw ? delete_post_meta( $il_two->ID, '_elroi_content' ) : update_post_meta( $il_two->ID, '_elroi_content', wp_slash( $il_raw ) );

// Score export for Rank Math's analyzer (tests/rank-math/score.js).
$sp_one   = get_page_by_path( 'services/service-one' );
$sp_items = es_seo_papers( array( $sp_one->ID, $il_post ) );
$sp_page  = $sp_items[0] ?? array();
$sp_post  = $sp_items[1] ?? array();
$check( 'seo papers: a designed page is scored on its visible text inside <main>, without scripts or icons', ! empty( $sp_page['designed'] ) && str_contains( $sp_page['content'], 'What is included' ) && str_contains( $sp_page['content'], '<h1' ) && ! preg_match( '/<(script|svg|style)\b/i', $sp_page['content'] ) && ! str_contains( $sp_page['content'], 'aria-hidden="true"' ), array_diff_key( $sp_page, array( 'content' => 1 ) ) + array( 'start' => substr( (string) ( $sp_page['content'] ?? '' ), 0, 300 ) ) );
$check( 'seo papers: a post is scored on its stored content, with its type, slug and address', isset( $sp_post['content'] ) && str_starts_with( $sp_post['content'], '<h2>Siding</h2>' ) && 'post' === $sp_post['type'] && 'inlink-test' === $sp_post['slug'] && get_permalink( $il_post ) === $sp_post['url'], $sp_post );
update_post_meta( $sp_one->ID, 'rank_math_focus_keyword', 'service one, service' );
$sp_kw = es_seo_papers( array( $sp_one->ID ) )[0];
$check( 'seo papers: the focus keyword and the secondary keywords come from Rank Math\'s field', 'service one' === $sp_kw['keyword'] && array( 'service one', 'service' ) === $sp_kw['keywords'], $sp_kw['keywords'] );
delete_post_meta( $sp_one->ID, 'rank_math_focus_keyword' );
$sp_all = wp_list_pluck( es_seo_papers(), 'slug' );
$check( 'seo papers: with no IDs, every published page and post is exported', in_array( 'home', $sp_all, true ) && in_array( 'inlink-test', $sp_all, true ), $sp_all );
wp_delete_post( $il_post, true );

// Service area pages: the kind comes from the page tree; listings come from child pages.
$ar_new = function ( $title, $parent = 0 ) {
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'post_parent' => $parent ) );
	update_post_meta( $id, '_wp_page_template', 'templates/area.php' );
	return $id;
};
$ar_hub    = $ar_new( 'Areas test hub' );
$ar_region = $ar_new( 'Bergen County', $ar_hub );
$ar_fl     = $ar_new( 'Fair Lawn', $ar_region );
$ar_tn     = $ar_new( 'Teaneck', $ar_region );
$ar_region2 = $ar_new( 'Rockland County', $ar_hub );
$ar_ny     = $ar_new( 'Nyack', $ar_region2 );
$check( 'areas: the page tree sets the kind (hub, region, town)', array( 'hub', 'region', 'town' ) === array( es_area_kind( $ar_hub ), es_area_kind( $ar_region ), es_area_kind( $ar_fl ) ) );
$ar_h = $render( get_permalink( $ar_hub ) );
$check( 'areas: the hub lists each region with its towns', str_contains( $ar_h, 'href="' . get_permalink( $ar_region ) . '">Bergen County</a>' ) && str_contains( $ar_h, 'href="' . get_permalink( $ar_fl ) . '">Fair Lawn</a>' ) && str_contains( $ar_h, 'href="' . get_permalink( $ar_ny ) . '">Nyack</a>' ) && str_contains( $ar_h, '2 towns' ) );
$ar_r = $render( get_permalink( $ar_region ) );
$check( 'areas: a region lists its towns A to Z and the service pages', str_contains( $ar_r, 'Towns we serve in Bergen County' ) && strpos( $ar_r, '>Fair Lawn</a>' ) < strpos( $ar_r, '>Teaneck</a>' ) && str_contains( $ar_r, 'Our services in Bergen County' ) && str_contains( $ar_r, '>Service one</a>' ) && ! str_contains( $ar_r, '>Nyack</a>' ) );
$ar_t = $render( get_permalink( $ar_fl ) );
$check( 'areas: a town lists the other towns of its region, not itself', str_contains( $ar_t, 'More towns in Bergen County' ) && str_contains( $ar_t, '>Teaneck</a>' ) && ! str_contains( $ar_t, 'href="' . get_permalink( $ar_fl ) . '">Fair Lawn</a>' ) );
foreach ( array( 'hub' => $ar_h, 'region' => $ar_r, 'town' => $ar_t ) as $ar_k => $ar_html ) {
	$check( "areas: the $ar_k page has one h1, no PHP errors and a breadcrumb trail", 1 === substr_count( $ar_html, '<h1' ) && ! preg_match( '/(Fatal error|Warning:|Notice:|Deprecated:)/', $ar_html ) && str_contains( $ar_html, 'es-crumbs' ) );
}
$check( 'areas: a town page marks up the business serving that town as a City', str_contains( $ar_t, '"areaServed":{"@type":"City","name":"Fair Lawn"}' ) && str_contains( $ar_t, '"parentOrganization":{"@id":"' . home_url( '/#organization' ) . '"}' ) );
$check( 'areas: a region is an AdministrativeArea; no rating without real data', str_contains( $ar_r, '"areaServed":{"@type":"AdministrativeArea","name":"Bergen County"}' ) && ! str_contains( $ar_r, 'aggregateRating' ) && ! str_contains( $ar_r, 'class="es-hero__rating"' ) );
update_option( 'trustindex-google-page-details', array( 'rating_score' => '4.94', 'rating_number' => '73', 'id' => 'ChIJtest' ) );
$ar_t = $render( get_permalink( $ar_fl ) );
$check( 'areas: with the Trustindex Google data, the rating shows under the heading and is marked up', str_contains( $ar_t, 'Rated 4.9 out of 5 from 73 Google reviews' ) && str_contains( $ar_t, 'placeid=ChIJtest' ) && str_contains( $ar_t, '"aggregateRating":{"@type":"AggregateRating","ratingValue":4.9,"reviewCount":73,"bestRating":5}' ), substr( $ar_t, strpos( $ar_t, 'es-hero__' ), 600 ) );
delete_option( 'trustindex-google-page-details' );
foreach ( array( $ar_ny, $ar_region2, $ar_tn, $ar_fl, $ar_region, $ar_hub ) as $ar_id ) {
	wp_delete_post( $ar_id, true );
}

// Typed service areas, other offices.
$check(
	'areas: service area lines become City, AdministrativeArea, State or Place',
	array(
		array( '@type' => 'City', 'name' => 'Teaneck, NJ' ),
		array( '@type' => 'AdministrativeArea', 'name' => 'Bergen County, NJ' ),
		array( '@type' => 'State', 'name' => 'New Jersey' ),
		array( '@type' => 'AdministrativeArea', 'name' => 'Rockland County, NY' ),
		array( '@type' => 'Place', 'name' => 'Morristown, NJ' ),
	) === array_map( 'es_area_node', array( 'City: Teaneck, NJ', 'county:Bergen County, NJ', 'State: New Jersey', 'Rockland County, NY', 'Morristown, NJ' ) )
);
$of_opt = elroi_settings_config()['option'];
$of_was = get_option( $of_opt, array() );
update_option( $of_opt, array_merge( (array) $of_was, array( 'offices' => array( array( 'name' => 'New Jersey office', 'phone' => '(201) 555-0100', 'street' => '155 Willowbrook Blvd', 'city' => 'Wayne', 'region' => 'NJ', 'zip' => '07470' ) ) ) ) );
$of_home = $render( home_url( '/' ) );
$check( 'offices: another office shows in the footer with its phone and address', str_contains( $of_home, 'New Jersey office</p>' ) && str_contains( $of_home, 'tel:+12015550100' ) && str_contains( $of_home, '155 Willowbrook Blvd, Wayne, NJ 07470' ), substr( $of_home, strpos( $of_home, 'es-footer' ), 300 ) );
$check( 'offices: the business node lists it as a department with its address', str_contains( $of_home, '"department":[{"@type":"' ) && str_contains( $of_home, '"@id":"' . home_url( '/#office-1' ) . '"' ) && str_contains( $of_home, '"streetAddress":"155 Willowbrook Blvd"' ) );
update_option( $of_opt, $of_was );

// Widget section on service pages.
$em_one = get_page_by_path( 'services/service-one' );
$em_raw = get_post_meta( $em_one->ID, '_elroi_content', true );
$em_c   = json_decode( $em_raw, true );
$em_c['embed'] = array( 'url' => 'https://widgets.gaf.com/hdz-widget', 'heading' => 'Pick your *shingle color*', 'title' => 'GAF shingle color explorer', 'height' => 90, 'min_height' => 520 );
update_post_meta( $em_one->ID, '_elroi_content', wp_slash( wp_json_encode( $em_c ) ) );
$em_html = $render( get_permalink( $em_one ) );
$check( 'embed: a service page shows the widget lazily, with its title and a width-based height', str_contains( $em_html, '<iframe src="https://widgets.gaf.com/hdz-widget" title="GAF shingle color explorer" class="es-embed__iframe" style="height:max(520px,90cqi)" loading="lazy"' ) && str_contains( $em_html, 'Pick your <em>shingle color</em>' ), substr( $em_html, (int) strpos( $em_html, 'es-embed' ), 500 ) );
$em_c['embed']['url'] = 'https://evil.example.com/x';
update_post_meta( $em_one->ID, '_elroi_content', wp_slash( wp_json_encode( $em_c ) ) );
$check( 'embed: a widget from a site that is not allowed never prints', ! str_contains( $render( get_permalink( $em_one ) ), 'class="es-section es-embed"' ) );
update_post_meta( $em_one->ID, '_elroi_content', wp_slash( $em_raw ) );

// Menus: three levels show labels in the dropdown; with es-mega-menu they open a mega panel.
$mn_menu = wp_create_nav_menu( 'Mega test ' . wp_generate_password( 4, false ) );
$mn_item = function ( $title, $parent = 0, $url = '#', $desc = '' ) use ( $mn_menu ) {
	return wp_update_nav_menu_item( $mn_menu, 0, array( 'menu-item-title' => $title, 'menu-item-url' => $url, 'menu-item-type' => 'custom', 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent, 'menu-item-description' => $desc ) );
};
$mn_top  = $mn_item( 'Services', 0, home_url( '/services/' ), 'Everything we build.' );
$mn_g1   = $mn_item( 'Residential', $mn_top );
$mn_item( 'Kitchens', $mn_g1, home_url( '/kitchens/' ), 'Cabinets, counters and layout.' );
$mn_item( 'Baths', $mn_g1, home_url( '/baths/' ) );
$mn_g2   = $mn_item( 'Commercial', $mn_top );
$mn_item( 'Retail', $mn_g2, home_url( '/retail/' ) );
$mn_top2 = $mn_item( 'Areas' );
$mn_g3   = $mn_item( 'Bergen County', $mn_top2 );
for ( $i = 1; $i <= 14; $i++ ) {
	$mn_item( "Town $i", $mn_g3, home_url( "/town-$i/" ) );
}
$mn_locs = get_theme_mod( 'nav_menu_locations' );
set_theme_mod( 'nav_menu_locations', array_merge( (array) $mn_locs, array( 'primary' => $mn_menu ) ) );
ob_start();
es_render_nav();
$mn_plain = ob_get_clean();
$check( 'menu: without the mega menu, a third level shows its parent as a label in the dropdown', str_contains( $mn_plain, '<li class="es-nav__label">Residential</li>' ) && str_contains( $mn_plain, '>Kitchens</a>' ) && ! str_contains( $mn_plain, 'es-mega' ), $mn_plain );
add_theme_support( 'es-mega-menu' );
ob_start();
es_render_nav();
$mn_mega = ob_get_clean();
$check( 'menu: es-mega-menu turns a three-level item into a mega panel with columns, intro and preview', str_contains( $mn_mega, 'data-es-mega' ) && str_contains( $mn_mega, '<p class="es-mega__label">Residential</p>' ) && str_contains( $mn_mega, '<p class="es-mega__lede">Everything we build.</p>' ) && str_contains( $mn_mega, 'data-desc="Cabinets, counters and layout."' ) && str_contains( $mn_mega, 'data-es-preview-pane' ) && str_contains( $mn_mega, '>All services</a>' ), $mn_mega );
$check( 'menu: a column of more than 11 links makes a dense panel without a preview', (bool) preg_match( '#<div class="es-mega es-mega--dense" id="es-sub-1" hidden data-es-panel>(?:(?!data-es-preview-pane).)*</div></div></li>#s', $mn_mega ) && str_contains( $mn_mega, 'style="--es-span:2"' ) );
$check( 'menu: the button controls its panel', str_contains( $mn_mega, 'aria-controls="es-sub-0"' ) && str_contains( $mn_mega, 'id="es-sub-0" hidden data-es-panel' ) );
ob_start();
es_render_drawer();
$mn_drawer = ob_get_clean();
$check( 'menu: the mobile menu shows group labels and puts long groups in two columns', str_contains( $mn_drawer, '<p class="es-drawer__label">Bergen County</p><ul class="es-drawer__cols">' ) && str_contains( $mn_drawer, '>Kitchens</a>' ), $mn_drawer );
remove_theme_support( 'es-mega-menu' );
set_theme_mod( 'nav_menu_locations', $mn_locs );
wp_delete_nav_menu( $mn_menu );

// Accessibility panel: off by default; with es-a11y-panel, the full panel.
$check( 'a11y panel: off unless the theme opts in', ! es_a11y_on() && ! str_contains( $render( home_url( '/' ) ), 'data-es-a11y' ) );
add_theme_support( 'es-a11y-panel' );
ob_start();
get_template_part( 'parts/a11y' );
$a11y_html = ob_get_clean();
$check( 'a11y panel: button, dialog, 5 profiles, 9 color modes, text sizes and the page structure dialog', str_contains( $a11y_html, 'aria-controls="es-a11y-panel"' ) && 5 === substr_count( $a11y_html, 'data-es-a11y-profile=' ) && 9 === substr_count( $a11y_html, 'data-es-a11y-color=' ) && 4 === substr_count( $a11y_html, 'data-es-a11y-text=' ) && str_contains( $a11y_html, '<dialog class="es-a11y-struct"' ) && str_contains( $a11y_html, 'id="es-cb-protan"' ) );
preg_match_all( '/data-es-a11y-opt="([a-z]+)"/', $a11y_html, $a11y_keys );
preg_match_all( '/class="([^"]+)"/', $a11y_html, $a11y_cls );
$a11y_bad = array_intersect( preg_split( '/\s+/', implode( ' ', $a11y_cls[1] ) ), array_map( fn( $k ) => 'es-a11y-' . $k, $a11y_keys[1] ) );
$check( 'a11y panel: no element class equals a preference class (es-a11y-<key> goes on <html> and would restyle that element)', count( $a11y_keys[1] ) >= 15 && ! $a11y_bad, $a11y_bad );
$check( 'a11y panel: its styles print inline with the theme\'s', in_array( 'es-a11y', es_inline_styles(), true ) );
$check( 'a11y panel: no statement link without an "accessibility" page', '' === es_a11y_statement_url() && ! str_contains( $a11y_html, 'Accessibility statement</a>' ) );
remove_theme_support( 'es-a11y-panel' );

// Hardening: users hidden from visitors, XML-RPC off, browser security headers.
wp_set_current_user( 0 );
$hd_anon = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/users' ) )->get_status();
wp_set_current_user( 1 );
$hd_admin = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/users' ) )->get_status();
$check( 'hardening: visitors cannot list user accounts; logged-in users still can', 404 === $hd_anon && 200 === $hd_admin, array( $hd_anon, $hd_admin ) );
$check( 'hardening: XML-RPC is off and offers no methods', ! apply_filters( 'xmlrpc_enabled', true ) && array() === apply_filters( 'xmlrpc_methods', array( 'wp.getUsersBlogs' => 'x' ) ) );
add_filter( 'es_xmlrpc', '__return_true' );
$check( 'hardening: es_xmlrpc turns XML-RPC back on for a site that needs it', apply_filters( 'xmlrpc_enabled', true ) && array() !== apply_filters( 'xmlrpc_methods', array( 'wp.getUsersBlogs' => 'x' ) ) );
remove_filter( 'es_xmlrpc', '__return_true' );
$hd_head = wp_remote_retrieve_headers( wp_remote_get( home_url( '/' ), array( 'timeout' => 20 ) ) );
$check( 'hardening: pages send nosniff, SAMEORIGIN framing and a referrer policy', 'nosniff' === ( $hd_head['x-content-type-options'] ?? '' ) && 'SAMEORIGIN' === ( $hd_head['x-frame-options'] ?? '' ) && '' !== ( $hd_head['referrer-policy'] ?? '' ), (array) $hd_head );
$check( 'hardening: oEmbed names the business, not the account', get_bloginfo( 'name' ) === ( apply_filters( 'oembed_response_data', array( 'author_name' => 'admin', 'author_url' => 'x' ), get_post( 1 ), 600, 400 )['author_name'] ?? '' ) );

// Lead form: phone numbers are checked; the fine print links the privacy policy.
$check( 'form: es_valid_phone accepts real formats and refuses letters and short numbers', es_valid_phone( '(555) 123-4567' ) && es_valid_phone( '+1 555.123.4567' ) && ! es_valid_phone( '555-1234' ) && ! es_valid_phone( '555 CALL NOW 1234' ) && ! es_valid_phone( '' ) );
$ph_before = count( get_posts( array( 'post_type' => 'es_lead', 'post_status' => 'private', 'numberposts' => -1, 'fields' => 'ids' ) ) );
wp_set_current_user( 0 );
$ph_nonce = wp_create_nonce( 'es_lead' );
wp_set_current_user( 1 );
$ph_res = wp_remote_post( admin_url( 'admin-post.php' ), array( 'timeout' => 20, 'redirection' => 0, 'body' => array( 'action' => 'es_lead', 'es_nonce' => $ph_nonce, 'es_name' => 'Bad Phone', 'es_phone' => '123', 'es_email' => 'bad@example.com', 'es_message' => 'x' ) ) );
$ph_after = count( get_posts( array( 'post_type' => 'es_lead', 'post_status' => 'private', 'numberposts' => -1, 'fields' => 'ids' ) ) );
$check( 'form: a message with a bad phone number is sent back, not stored', $ph_before === $ph_after && str_contains( (string) wp_remote_retrieve_header( $ph_res, 'location' ), 'lead=phone' ), wp_remote_retrieve_header( $ph_res, 'location' ) );
$pp_was  = (int) get_option( 'wp_page_for_privacy_policy' );
$pp_page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Privacy Policy', 'post_name' => 'privacy-policy-test' ) );
update_option( 'wp_page_for_privacy_policy', $pp_page );
$check( 'form: the fine print links the privacy policy page when there is one', str_contains( es_form_fine_print(), 'href="' . esc_url( get_permalink( $pp_page ) ) . '"' ), es_form_fine_print() );
update_option( 'wp_page_for_privacy_policy', $pp_was );
wp_delete_post( $pp_page, true );

// Hero: "Whole photo above the text" shows the photo uncropped on phones.
require_once ABSPATH . 'wp-admin/includes/image.php';
$hb_up  = wp_upload_dir();
$hb_img = imagecreatetruecolor( 600, 400 );
$hb_fn  = trailingslashit( $hb_up['path'] ) . 'es-hero-test.jpg';
imagejpeg( $hb_img, $hb_fn );
$hb_id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'Team photo', 'post_status' => 'inherit' ), $hb_fn );
wp_update_attachment_metadata( $hb_id, wp_generate_attachment_metadata( $hb_id, $hb_fn ) );
update_post_meta( $hb_id, '_wp_attachment_image_alt', 'Team photo' );
$hb_page = get_page_by_path( 'services/service-one' );
$hb_raw  = get_post_meta( $hb_page->ID, '_elroi_content', true );
$hb_c    = json_decode( $hb_raw, true );
$hb_c['hero']['photo']       = $hb_id;
$hb_c['hero']['phone_photo'] = 'Whole photo above the text';
update_post_meta( $hb_page->ID, '_elroi_content', wp_slash( wp_json_encode( $hb_c ) ) );
$hb_html = $render( get_permalink( $hb_page ) );
$check( 'hero: "Whole photo above the text" marks the hero and gives it the photo\'s shape', str_contains( $hb_html, 'es-hero--banner" style="--es-hero-ratio: ' ), substr( $hb_html, (int) strpos( $hb_html, '<header class="es-hero' ), 200 ) );
$hb_c['hero']['phone_photo'] = 'Behind the text';
update_post_meta( $hb_page->ID, '_elroi_content', wp_slash( wp_json_encode( $hb_c ) ) );
$check( 'hero: the default keeps the photo behind the text', ! preg_match( '/<header class="[^"]*es-hero--banner/', $render( get_permalink( $hb_page ) ) ) );
update_post_meta( $hb_page->ID, '_elroi_content', wp_slash( $hb_raw ) );
wp_delete_attachment( $hb_id, true );

// One-command setup (wp es setup / es_site_setup): plans, creates what is missing, never doubles.
$su_dry = es_site_setup( array( 'services' => 'Service one, Test Roofing', 'areas' => 'Test County: Test Town', 'dry_run' => true, 'index' => true ) );
$su_txt = implode( "\n", $su_dry['done'] );
$check( 'setup: a dry run plans the missing pages under their parents and changes nothing', str_contains( $su_txt, 'Create page "Test Roofing" (/services/test-roofing/) with templates/service.php.' ) && str_contains( $su_txt, '(/service-areas/test-county/test-town/)' ) && ! get_page_by_path( 'services/test-roofing' ), $su_dry );
$check( 'setup: pages that exist are reused, not created again', ! str_contains( $su_txt, '"Service one"' ) && str_contains( implode( "\n", $su_dry['skipped'] ), 'Service one' ), $su_dry );
$su_run  = es_site_setup( array( 'services' => 'Service one, Test Roofing', 'areas' => 'Test County: Test Town', 'index' => true ) );
$su_page = get_page_by_path( 'services/test-roofing' );
$su_town = get_page_by_path( 'service-areas/test-county/test-town' );
$check( 'setup: it creates service and area pages with their templates', $su_page && 'templates/service.php' === get_page_template_slug( $su_page ) && $su_town && 'templates/area.php' === get_page_template_slug( $su_town ), $su_run );
$su_again = es_site_setup( array( 'services' => 'Service one, Test Roofing', 'areas' => 'Test County: Test Town', 'index' => true ) );
$check( 'setup: running it again creates nothing', ! preg_grep( '/^Created page/', $su_again['done'] ), $su_again['done'] );
$check( 'setup: it never replaces an existing menu or front page', str_contains( implode( "\n", $su_again['skipped'] ), 'A main menu already exists' ) && (int) get_option( 'page_on_front' ) === (int) get_page_by_path( 'home' )->ID );
foreach ( array( $su_town, get_page_by_path( 'service-areas/test-county' ), get_page_by_path( 'service-areas' ), $su_page ) as $su_p ) {
	if ( $su_p ) {
		wp_delete_post( $su_p->ID, true );
	}
}

$failed = array_values( array_filter( $results, fn( $r ) => ! $r['ok'] ) );
echo wp_json_encode( array( 'passed' => count( $results ) - count( $failed ), 'failed' => count( $failed ), 'fails' => $failed ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
