<?php
/**
 * Accessibility preferences panel (opt in, per client: add_theme_support( 'es-a11y-panel' )).
 *
 * A round button in the lower left opens the panel ported from Nuvista, with the feature set of
 * the Sienna widget, built in-house: profiles (seizure safe, low vision, ADHD, cognitive, motor),
 * text size 100 to 200%, readable and dyslexia fonts (OpenDyslexic, self-hosted), bold, line
 * height, letter spacing, highlighted headings and links, one color mode at a time (dark, light,
 * high contrast, saturation, monochrome, three color-blindness corrections), reading guide, text
 * magnifier, read aloud, image descriptions, hide images, big cursor, stop animations, strong
 * focus outline and a page structure dialog.
 *
 * It is a convenience, not a compliance tool: compliance comes from the code (WCAG 2.2 AA). Never
 * add a third-party overlay (accessiBe, UserWay and the like; the FTC fined accessiBe in 2025)
 * and never call a site "ADA compliant". There is deliberately no "Blind" profile: it would only
 * switch on read aloud, which talks over real screen readers.
 *
 * Settings stay in the visitor's browser and become classes on <html> (es-a11y-<key>,
 * es-a11y-text-<n>, es-a11y-color-<name>), applied before first paint. Because every pref key
 * becomes an <html> class, never give another element a class es-a11y-<pref key>: on Nuvista a
 * `.nv-a11y-guide` element class once hid the whole page. Keep font sizes in rem so the text size
 * option scales the page. Link the panel to an accessibility statement: a published page with the
 * slug "accessibility", or the `es_a11y_statement_url` filter.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/** Whether the panel is on. */
function es_a11y_on() {
	return current_theme_supports( 'es-a11y-panel' ) && ! is_admin();
}

/** Address of the accessibility statement, or ''. */
function es_a11y_statement_url() {
	$page = get_page_by_path( 'accessibility' );
	$url  = $page && 'publish' === $page->post_status ? get_permalink( $page ) : '';
	return (string) apply_filters( 'es_a11y_statement_url', $url );
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! es_a11y_on() ) {
			return;
		}
		wp_enqueue_style( 'es-a11y', ES_URI . '/assets/css/a11y.css', array(), ES_VERSION );
		wp_enqueue_script( 'es-a11y', ES_URI . '/assets/js/a11y.js', array(), ES_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	},
	25
);

// The panel's stylesheet prints inline with the theme's own (inc/first-paint.php).
add_filter( 'es_inline_styles', fn( $handles ) => array_merge( (array) $handles, array( 'es-a11y' ) ) );

// Saved preferences apply before first paint, so pages never flash in the default look.
add_action(
	'wp_head',
	function () {
		if ( es_a11y_on() ) {
			wp_print_inline_script_tag( "(function(d){try{var p=JSON.parse(localStorage.getItem('es-a11y'))||{};if(p.text)d.classList.add('es-a11y-text-'+p.text);if(p.color)d.classList.add('es-a11y-color-'+p.color);for(var k in p){if(p[k]===true)d.classList.add('es-a11y-'+k);}}catch(e){}})(document.documentElement);" );
		}
	},
	1
);

add_action(
	'wp_footer',
	function () {
		if ( es_a11y_on() ) {
			get_template_part( 'parts/a11y' );
		}
	},
	5
);
