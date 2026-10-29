<?php
/**
 * Optional motion layer: GSAP + ScrollTrigger and Lenis smooth scroll, self-hosted in
 * assets/vendor/. Off by default; a client theme turns it on in inc/setup.php or functions.php:
 *
 *     add_theme_support( 'es-motion' );                              // everything on
 *     add_theme_support( 'es-motion', array( 'smooth' => false ) );  // no smooth scroll
 *
 * The layer adds declarative hooks (data-es-split, data-es-rise, data-es-parallax,
 * data-es-shutter, data-es-inview-play) and ESMotion.scene() for custom scenes written in the
 * client's own script (enqueue it with 'es-motion' as a dependency). See AGENTS.md > Motion.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Motion layer settings, or null when the theme has not turned it on.
 *
 * @return array{smooth: bool, duration: float}|null
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
			'smooth'   => true,  // Lenis smooth scrolling (the only scroll engine when on).
			'duration' => 1.15,  // Lenis glide length in seconds.
		)
	);
	/**
	 * Filters the motion layer settings, e.g. to turn smooth scrolling off on one page.
	 *
	 * @param array $opts Settings.
	 */
	return apply_filters( 'es_motion_options', $opts );
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

add_action(
	'wp_enqueue_scripts',
	function () {
		$opts = es_motion_options();
		if ( null === $opts ) {
			return;
		}
		$args = array(
			'strategy'  => 'defer',
			'in_footer' => true,
		);
		wp_enqueue_script( 'es-gsap', ES_URI . '/assets/vendor/gsap.min.js', array(), '3.15.0', $args );
		wp_enqueue_script( 'es-scrolltrigger', ES_URI . '/assets/vendor/ScrollTrigger.min.js', array( 'es-gsap' ), '3.15.0', $args );
		$deps = array( 'es-gsap', 'es-scrolltrigger' );
		if ( $opts['smooth'] ) {
			wp_enqueue_script( 'es-lenis', ES_URI . '/assets/vendor/lenis.min.js', array(), '1.3.27', $args );
			$deps[] = 'es-lenis';
		}
		wp_enqueue_script( 'es-motion', ES_URI . '/assets/js/motion.js', $deps, ES_VERSION, $args );
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
		wp_enqueue_style( 'es-motion', ES_URI . '/assets/css/motion.css', array( 'es-site' ), ES_VERSION );
	},
	30
);
