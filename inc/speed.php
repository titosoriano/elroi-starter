<?php
/**
 * Speed settings that keep the design and the animations working.
 *
 * - LiteSpeed Cache (Hostinger and other LiteSpeed hosts): es_speed_status() compares its
 *   settings with the ones that are safe for these themes, and es_speed_apply() sets them
 *   (Tools > Site Health has a button). Page cache, browser cache, purge after updates and CSS/JS
 *   minify go on; combining, delaying JS, "unused CSS", async CSS, guest mode and LiteSpeed's
 *   own lazy load stay off, because each one breaks animations or the first photo's priority.
 * - Pages (HTML) must not be kept in visitors' browsers: some hosts add a one-week
 *   "ExpiresDefault" to .htaccess, so visitors would see old pages for a week after a change.
 *   es_speed_apply() adds a rule that keeps files cached and pages fresh.
 * - Fonts are self-hosted (AGENTS.md); the check reports any Google Fonts file a plugin adds.
 * - The motion scripts are marked so LiteSpeed never delays or re-bundles them, even if someone
 *   turns those options on later.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * LiteSpeed Cache settings for these themes: option id => [ value, why ].
 *
 * @return array<string, array{0: bool|int, 1: string}>
 */
function es_speed_litespeed_settings() {
	return apply_filters(
		'es_speed_litespeed_settings',
		array(
			'cache'          => array( true, __( 'Page cache on: pages are served ready-made.', 'elroi-starter' ) ),
			'cache-browser'  => array( true, __( 'Browser cache on: photos, fonts, CSS and scripts are kept by visitors for a year (their names change with each version).', 'elroi-starter' ) ),
			'purge-upgrade'  => array( true, __( 'Clear the cache after plugin and theme updates, so a starter update shows at once.', 'elroi-starter' ) ),
			'optm-css_min'   => array( true, __( 'Minify CSS.', 'elroi-starter' ) ),
			'optm-js_min'    => array( true, __( 'Minify JS.', 'elroi-starter' ) ),
			'optm-ggfonts_rm' => array( true, __( 'Remove Google Fonts that plugins add (the theme self-hosts its fonts).', 'elroi-starter' ) ),
			'optm-emoji_rm'  => array( true, __( 'Remove the WordPress emoji script.', 'elroi-starter' ) ),
			'optm-css_comb'  => array( false, __( 'CSS combine off: it changes the order of the design files.', 'elroi-starter' ) ),
			'optm-js_comb'   => array( false, __( 'JS combine off: it breaks the order the animation scripts need.', 'elroi-starter' ) ),
			'optm-js_defer'  => array( 0, __( 'Load JS Deferred/Delayed off: the theme already defers its scripts, and "Delayed" holds every animation until the visitor moves the mouse.', 'elroi-starter' ) ),
			'optm-ucss'      => array( false, __( 'Generate UCSS off: it drops styles for classes the animations add later.', 'elroi-starter' ) ),
			'optm-css_async' => array( false, __( 'Load CSS Asynchronously off: pages flash unstyled first.', 'elroi-starter' ) ),
			'media-lazy'     => array( false, __( 'LiteSpeed lazy load off: the theme lazy-loads photos itself and loads the first photo first; LiteSpeed\'s version swaps image addresses and breaks both.', 'elroi-starter' ) ),
			'guest'          => array( false, __( 'Guest mode off: it serves a stripped first view.', 'elroi-starter' ) ),
			'guest_optm'     => array( false, __( 'Guest optimization off (same reason).', 'elroi-starter' ) ),
		)
	);
}

/** Whether LiteSpeed Cache is active. */
function es_speed_litespeed_active() {
	return (bool) apply_filters( 'es_speed_litespeed_active', defined( 'LSCWP_V' ) );
}

/**
 * LiteSpeed settings that differ from es_speed_litespeed_settings(): id => [ now, want, why ].
 */
function es_speed_litespeed_diff() {
	$out = array();
	if ( ! es_speed_litespeed_active() ) {
		return $out;
	}
	foreach ( es_speed_litespeed_settings() as $id => $rule ) {
		$now = get_option( 'litespeed.conf.' . $id, null );
		if ( null !== $now && (int) $now !== (int) $rule[0] ) {
			$out[ $id ] = array( $now, $rule[0], $rule[1] );
		}
	}
	return $out;
}

/** Path of the site's .htaccess. */
function es_speed_htaccess_file() {
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	return apply_filters( 'es_speed_htaccess_file', get_home_path() . '.htaccess' );
}

/**
 * Whether .htaccess keeps pages in visitors' browsers: an active ExpiresDefault with no
 * ExpiresByType rule for text/html.
 *
 * @param string|null $file .htaccess path.
 */
function es_speed_html_cached( $file = null ) {
	$file = $file ? $file : es_speed_htaccess_file();
	if ( ! is_readable( $file ) ) {
		return false;
	}
	$rules = preg_replace( '/^\s*#.*$/m', '', (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	return (bool) preg_match( '/^\s*ExpiresDefault\s/mi', $rules ) && ! preg_match( '/^\s*ExpiresByType\s+text\/html\s/mi', $rules );
}

/**
 * Add the "pages stay fresh" rule to .htaccess.
 *
 * @param string|null $file .htaccess path.
 */
function es_speed_fix_html_cache( $file = null ) {
	$file = $file ? $file : es_speed_htaccess_file();
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	return insert_with_markers(
		$file,
		'ElRoi Starter',
		array(
			'# Pages are always checked for changes; photos, fonts, CSS and scripts stay cached.',
			'<IfModule mod_expires.c>',
			'	ExpiresByType text/html "access plus 0 seconds"',
			'</IfModule>',
		)
	);
}

/**
 * Everything the speed check found: [ ok, problems[], litespeed (diff) ].
 */
function es_speed_status() {
	$out = array(
		'ok'        => true,
		'problems'  => array(),
		'litespeed' => es_speed_litespeed_diff(),
		'fonts'     => array_values( (array) get_option( 'es_speed_remote_fonts', array() ) ),
	);
	foreach ( $out['litespeed'] as $id => $d ) {
		/* translators: 1: LiteSpeed option id, 2: reason. */
		$out['problems'][] = sprintf( __( 'LiteSpeed Cache "%1$s": %2$s', 'elroi-starter' ), $id, $d[2] );
	}
	if ( es_speed_html_cached() ) {
		$out['problems'][] = __( 'The server tells browsers to keep pages for days (ExpiresDefault in .htaccess), so visitors may see an old page after a change.', 'elroi-starter' );
	}
	foreach ( $out['fonts'] as $url ) {
		/* translators: %s: font stylesheet address. */
		$out['problems'][] = sprintf( __( 'A plugin loads fonts from Google (%s). Self-host fonts in the theme, or turn on LiteSpeed Cache > Page Optimization > Remove Google Fonts.', 'elroi-starter' ), $url );
	}
	$out['ok'] = ! $out['problems'];
	return $out;
}

/**
 * Apply the recommended LiteSpeed settings and the .htaccess rule. Returns what changed.
 */
function es_speed_apply() {
	$done = array();
	$diff = es_speed_litespeed_diff();
	if ( $diff && class_exists( '\LiteSpeed\Conf' ) ) {
		$set = array();
		foreach ( $diff as $id => $d ) {
			$set[ $id ] = $d[1];
		}
		\LiteSpeed\Conf::cls()->update_confs( $set ); // Rewrites LiteSpeed's .htaccess block and purges as needed.
		$done = array_keys( $set );
	}
	if ( es_speed_html_cached() && es_speed_fix_html_cache() ) {
		$done[] = 'htaccess: pages stay fresh';
	}
	return $done;
}

// Remember Google Fonts that something enqueues on the front end (checked by Site Health).
add_action(
	'wp_print_styles',
	function () {
		if ( is_admin() ) {
			return;
		}
		$found = array();
		foreach ( wp_styles()->queue as $handle ) {
			$src = (string) ( wp_styles()->registered[ $handle ]->src ?? '' );
			if ( preg_match( '#fonts\.(googleapis|gstatic)\.com#', $src ) ) {
				$found[] = strtok( $src, '?' );
			}
		}
		if ( array_values( (array) get_option( 'es_speed_remote_fonts', array() ) ) !== $found ) {
			update_option( 'es_speed_remote_fonts', $found, false );
		}
	},
	999
);

/**
 * Script handles LiteSpeed must leave alone: the motion layer and every script that depends on it.
 *
 * @return array{optimize: string[], defer: string[]} Handles to skip for minify/combine, and for delay/defer.
 */
function es_speed_protected_scripts() {
	$vendor = array( 'es-gsap', 'es-scrolltrigger', 'es-lenis' );
	$defer  = array_merge( $vendor, array( 'es-motion' ) );
	foreach ( wp_scripts()->registered as $handle => $s ) {
		if ( array_intersect( (array) $s->deps, $defer ) ) {
			$defer[] = $handle;
		}
	}
	return apply_filters(
		'es_speed_protected_scripts',
		array(
			'optimize' => $vendor, // Already minified.
			'defer'    => array_values( array_unique( $defer ) ),
		)
	);
}

add_filter(
	'script_loader_tag',
	function ( $tag, $handle ) {
		static $p = null;
		$p = $p ? $p : es_speed_protected_scripts();
		if ( ! in_array( $handle, $p['defer'], true ) ) {
			return $tag;
		}
		$attrs = ' data-no-defer="1"' . ( in_array( $handle, $p['optimize'], true ) ? ' data-no-optimize="1"' : '' );
		return preg_replace( '/<script(?=[^>]*\ssrc=)/', '<script' . $attrs, $tag, 1 );
	},
	10,
	2
);

/** Site Health test. */
function es_speed_health_test() {
	$s      = es_speed_status();
	$result = array(
		'label'       => __( 'Speed settings keep the design and animations working', 'elroi-starter' ),
		'status'      => 'good',
		'badge'       => array( 'label' => __( 'Performance', 'elroi-starter' ), 'color' => 'blue' ),
		'description' => '<p>' . esc_html__( 'Cache settings match the ones recommended for this theme, pages stay fresh in visitors\' browsers, and no fonts load from Google.', 'elroi-starter' ) . '</p>',
		'actions'     => '',
		'test'        => 'es_speed',
	);
	if ( ! $s['ok'] ) {
		$result['label']       = __( 'Speed settings need attention', 'elroi-starter' );
		$result['status']      = 'recommended';
		$result['description'] = '<p>' . implode( '</p><p>', array_map( 'esc_html', $s['problems'] ) ) . '</p>';
		if ( current_user_can( 'manage_options' ) && ( $s['litespeed'] || es_speed_html_cached() ) ) {
			$result['actions'] = '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=es_speed_apply' ), 'es_speed_apply' ) ) . '">' . esc_html__( 'Apply the recommended settings', 'elroi-starter' ) . '</a>';
		}
	}
	return $result;
}

add_filter(
	'site_status_tests',
	function ( $tests ) {
		$tests['direct']['es_speed'] = array(
			'label' => __( 'Speed settings', 'elroi-starter' ),
			'test'  => 'es_speed_health_test',
		);
		return $tests;
	}
);

add_action(
	'admin_post_es_speed_apply',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only administrators can change these settings.', 'elroi-starter' ) );
		}
		check_admin_referer( 'es_speed_apply' );
		es_speed_apply();
		wp_safe_redirect( admin_url( 'site-health.php' ) );
		exit;
	}
);
