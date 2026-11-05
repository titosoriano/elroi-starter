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

$failed = array_values( array_filter( $results, fn( $r ) => ! $r['ok'] ) );
echo wp_json_encode( array( 'passed' => count( $results ) - count( $failed ), 'failed' => count( $failed ), 'fails' => $failed ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
