<?php
/**
 * Optional motion layer: GSAP + ScrollTrigger and Lenis smooth scroll, self-hosted in
 * assets/vendor/. Off by default; a client theme turns it on in inc/setup.php or functions.php:
 *
 *     add_theme_support( 'es-motion', array( 'load' => 'auto' ) );  // only on pages that animate
 *     add_theme_support( 'es-motion' );                              // on every page
 *     add_theme_support( 'es-motion', array( 'smooth' => false ) );  // no smooth scroll
 *
 * With 'load' => 'auto' the libraries (about 45 KB compressed) load only on pages whose HTML
 * uses a motion hook: the starter's (data-es-split, data-es-rise, data-es-parallax,
 * data-es-shutter, data-es-inview-play), a plain data-es-motion attribute, or the child's own
 * attributes listed in 'markers'. Elsewhere `ESMotion` is a stand-in that does nothing, so the
 * client's script still runs. The layer adds ESMotion.scene() for custom scenes written in the
 * client's own script (enqueue it in the footer with 'es-motion' as a dependency). See
 * AGENTS.md > Motion.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Motion layer settings, or null when the theme has not turned it on.
 *
 * @return array{smooth: bool, duration: float, load: string, markers: string[]}|null
 */
function es_motion_options() {
	if ( ! current_theme_supports( 'es-motion' ) ) {
		return null;
	}
	$support = get_theme_support( 'es-motion' );
	$args    = is_array( $support ) && isset( $support[0] ) && is_array( $support[0] ) ? $support[0] : array();
	$opts    = wp_parse_args(
		$args,
		array(
			'smooth'   => true,     // Lenis smooth scrolling (the only scroll engine when on).
			'duration' => 1.15,     // Lenis glide length in seconds.
			'load'     => 'always', // 'auto': only on pages that use a motion hook. 'always' keeps older child themes unchanged.
			'markers'  => array(),  // The child's own attributes that need the layer, e.g. array( 'data-reveal' ).
		)
	);
	$opts['load']    = 'auto' === $opts['load'] ? 'auto' : 'always';
	$opts['markers'] = array_values( array_filter( array_map( 'strval', (array) $opts['markers'] ) ) );
	/**
	 * Filters the motion layer settings, e.g. to turn smooth scrolling off on one page.
	 *
	 * @param array $opts Settings.
	 */
	return apply_filters( 'es_motion_options', $opts );
}

/**
 * The attributes that mean "this page animates".
 *
 * @param array|null $opts Motion settings (default: the theme's).
 * @return string[]
 */
function es_motion_markers( $opts = null ) {
	$opts = $opts ? $opts : es_motion_options();
	$base = array( 'data-es-split', 'data-es-rise', 'data-es-parallax', 'data-es-shutter', 'data-es-inview-play', 'data-es-motion' );
	return array_values( array_unique( array_merge( $base, $opts ? $opts['markers'] : array() ) ) );
}

/**
 * Whether a page's HTML uses a motion hook (an attribute name followed by =, space, > or /).
 *
 * @param string   $html    Page HTML.
 * @param string[] $markers Attribute names.
 */
function es_motion_html_uses( $html, $markers ) {
	foreach ( $markers as $m ) {
		if ( '' !== $m && preg_match( '/\s' . preg_quote( $m, '/' ) . '[\s=>\/]/', $html ) ) {
			return true;
		}
	}
	return false;
}

// The libraries are downloaded by bin/vendor.sh: tell the administrator when they are missing.
add_action(
	'admin_notices',
	function () {
		if ( null !== es_motion_options() && ! file_exists( ES_DIR . '/assets/vendor/gsap.min.js' ) && current_user_can( 'switch_themes' ) ) {
			echo '<div class="notice notice-warning"><p><strong>The theme\'s animations are off:</strong> assets/vendor/ is missing its scripts. Rebuild the theme zip with bin/build.sh (it runs bin/vendor.sh) and upload it again.</p></div>';
		}
	}
);

/**
 * Register the layer's files (enqueued by es_motion_enqueue()).
 *
 * @param array $opts Motion settings.
 */
function es_motion_register( $opts ) {
	$args = array(
		'strategy'  => 'defer',
		'in_footer' => true,
	);
	wp_register_script( 'es-gsap', ES_URI . '/assets/vendor/gsap.min.js', array(), '3.15.0', $args );
	wp_register_script( 'es-scrolltrigger', ES_URI . '/assets/vendor/ScrollTrigger.min.js', array( 'es-gsap' ), '3.15.0', $args );
	$deps = array( 'es-gsap', 'es-scrolltrigger' );
	if ( $opts['smooth'] ) {
		wp_register_script( 'es-lenis', ES_URI . '/assets/vendor/lenis.min.js', array(), '1.3.27', $args );
		$deps[] = 'es-lenis';
	}
	wp_register_script( 'es-motion', ES_URI . '/assets/js/motion.js', $deps, ES_VERSION, $args );
	wp_add_inline_script(
		'es-motion',
		'window.ESMotionConfig = ' . wp_json_encode(
			array(
				'smooth'   => (bool) $opts['smooth'],
				'duration' => (float) $opts['duration'],
			)
		) . ';',
		'before'
	);
	wp_register_style( 'es-motion', ES_URI . '/assets/css/motion.css', array( 'es-site' ), ES_VERSION );
}

/** Load the layer on this page. */
function es_motion_enqueue() {
	wp_enqueue_script( 'es-motion' );
	wp_enqueue_style( 'es-motion' );
}

/**
 * The page does not animate: `es-motion` becomes a small stand-in, so scripts that depend on it
 * still run (ESMotion.scene() does nothing, ESMotion.scrollTo() scrolls natively).
 */
function es_motion_stub() {
	wp_deregister_script( 'es-motion' );
	wp_register_script( 'es-motion', false, array(), ES_VERSION, array( 'in_footer' => true ) );
	wp_add_inline_script(
		'es-motion',
		'window.ESMotion=window.ESMotion||{enabled:false,reduced:matchMedia("(prefers-reduced-motion: reduce)").matches,lenis:null,scene:function(){},scrollTo:function(t){var r=this.reduced?"auto":"smooth";if(typeof t==="number"){scrollTo({top:t,behavior:r});return;}var e=typeof t==="string"?document.querySelector(t):t;if(e)e.scrollIntoView({behavior:r});}};'
	);
}

/**
 * Decide for this page from its HTML (auto mode) and load the layer or the stand-in.
 *
 * @param string $html Page HTML so far.
 * @return bool Whether the layer loads.
 */
function es_motion_decide( $html ) {
	$opts = es_motion_options();
	if ( null === $opts ) {
		return false;
	}
	$used = es_motion_html_uses( $html, es_motion_markers( $opts ) );
	/**
	 * Filters whether this page loads the motion layer (auto mode).
	 *
	 * @param bool   $used Whether the page's HTML uses a motion hook.
	 * @param string $html The page's HTML up to the footer.
	 */
	$used = (bool) apply_filters( 'es_motion_used', $used, $html );
	$used ? es_motion_enqueue() : es_motion_stub();
	return $used;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		$opts = es_motion_options();
		if ( null === $opts ) {
			return;
		}
		es_motion_register( $opts );
		if ( 'always' === $opts['load'] ) {
			es_motion_enqueue();
		}
	},
	30
);

// Auto mode: read the page as it is printed (from the end of <head> to the footer), then decide.
// Footer scripts and late styles are printed after this, so the files still load in time.
$GLOBALS['es_motion_buffer'] = 0;
add_action(
	'wp_head',
	function () {
		$opts = es_motion_options();
		if ( $opts && 'auto' === $opts['load'] && ! is_admin() && ! wp_doing_ajax() ) {
			ob_start();
			$GLOBALS['es_motion_buffer'] = ob_get_level();
		}
	},
	PHP_INT_MAX
);
add_action(
	'wp_footer',
	function () {
		$level = $GLOBALS['es_motion_buffer'];
		if ( ! $level ) {
			return;
		}
		$GLOBALS['es_motion_buffer'] = 0;
		if ( ob_get_level() !== $level ) {
			es_motion_enqueue(); // Someone else changed the buffers: load the layer to be safe.
			return;
		}
		$html = (string) ob_get_contents();
		ob_end_flush();
		es_motion_decide( $html );
	},
	0
);
