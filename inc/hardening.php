<?php
/**
 * Security hardening that works with any security plugin (Kadence Security is the recommended
 * one) and with Cloudflare in front of the site. Learned on Nuvista (2026-11):
 *
 * - User accounts are hidden from visitors: the REST users endpoint (which listed the "admin"
 *   login name to anyone), oEmbed author names and feed authors. Logged-in users and
 *   application-password requests (the site's MCP, the block editor) still see users.
 * - XML-RPC is off. Cloudflare's block at the edge is not enough: a request sent straight to the
 *   server's IP skips it. Nothing ElRoi installs uses XML-RPC (the plugin, the MCP and CRMs use the
 *   REST API). A site that needs it (Jetpack) turns it back on with
 *   add_filter( 'es_xmlrpc', '__return_true' ).
 * - Browser security headers on every front-end page (`es_security_headers` filter: change or
 *   remove one, e.g. drop X-Frame-Options when another site must frame this one). HSTS is not
 *   sent here: add it at the host or Cloudflare once the final domain is live.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

// REST API: no user listing or user lookup for visitors.
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( is_user_logged_in() || ! apply_filters( 'es_hide_users', true ) ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}
);

// oEmbed answers and feeds name the business, not the WordPress account.
add_filter(
	'oembed_response_data',
	function ( $data ) {
		if ( apply_filters( 'es_hide_users', true ) ) {
			unset( $data['author_url'] );
			$data['author_name'] = get_bloginfo( 'name' );
		}
		return $data;
	}
);
add_filter(
	'the_author',
	function ( $name ) {
		return is_feed() && apply_filters( 'es_hide_users', true ) ? get_bloginfo( 'name' ) : $name;
	}
);

// XML-RPC off unless a site asks for it.
add_filter(
	'xmlrpc_enabled',
	function ( $on ) {
		return (bool) apply_filters( 'es_xmlrpc', false ) && $on;
	}
);
add_filter(
	'xmlrpc_methods',
	function ( $methods ) {
		return apply_filters( 'es_xmlrpc', false ) ? $methods : array();
	}
);
add_filter(
	'wp_headers',
	function ( $headers ) {
		if ( ! apply_filters( 'es_xmlrpc', false ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}
);

/**
 * The browser security headers sent on front-end pages.
 *
 * @return array<string, string>
 */
function es_security_headers() {
	return (array) apply_filters(
		'es_security_headers',
		array(
			'X-Content-Type-Options' => 'nosniff',
			'X-Frame-Options'        => 'SAMEORIGIN',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'Permissions-Policy'     => 'camera=(), microphone=(), payment=(), usb=()',
		)
	);
}

add_action(
	'send_headers',
	function () {
		if ( is_admin() || headers_sent() ) {
			return;
		}
		foreach ( es_security_headers() as $name => $value ) {
			if ( '' !== (string) $value ) {
				header( $name . ': ' . $value );
			}
		}
	}
);
