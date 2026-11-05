<?php
/**
 * Theme updates from the public GitHub repository. When a newer release exists, Appearance >
 * Themes and Dashboard > Updates show "New version available" for ElRoi Starter, and every client
 * child theme built on it gets the improvement with one click (or automatically, if "Enable
 * auto-updates" is turned on for ElRoi Starter). Nothing to set up on a site.
 *
 * A release must have a tag like v1.4.0 and an asset named elroi-starter.zip whose top folder is
 * elroi-starter/. The GitHub workflow (.github/workflows/release.yml) builds and attaches it
 * whenever the Version in style.css changes on main.
 *
 * Optional: define( 'ES_GITHUB_TOKEN', 'github_pat_...' ) in wp-config.php (never in a repository)
 * raises GitHub's limit of 60 anonymous requests per hour per server.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ES_REPO' ) ) {
	define( 'ES_REPO', 'titosoriano/elroi-starter' );
}

/**
 * Latest release: version, notes, package URL. Cached for 6 hours (1 hour after a failure); null when unavailable.
 */
function es_latest_release() {
	$cached = get_site_transient( 'es_starter_release' );
	if ( false !== $cached ) {
		return $cached ? $cached : null;
	}
	$headers = array(
		'Accept'               => 'application/vnd.github+json',
		'X-GitHub-Api-Version' => '2022-11-28',
		'User-Agent'           => 'elroi-starter/' . ES_VERSION,
	);
	if ( defined( 'ES_GITHUB_TOKEN' ) && ES_GITHUB_TOKEN ) {
		$headers['Authorization'] = 'Bearer ' . ES_GITHUB_TOKEN;
	}
	$res     = wp_remote_get( 'https://api.github.com/repos/' . ES_REPO . '/releases/latest', array( 'headers' => $headers, 'timeout' => 10 ) );
	$release = '';
	if ( 200 === wp_remote_retrieve_response_code( $res ) ) {
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( 'elroi-starter.zip' === ( $asset['name'] ?? '' ) ) {
				$release = array(
					'version' => ltrim( (string) $data['tag_name'], 'v' ),
					'notes'   => (string) ( $data['body'] ?? '' ),
					'package' => (string) $asset['browser_download_url'],
				);
			}
		}
	}
	set_site_transient( 'es_starter_release', $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release ? $release : null;
}

add_filter(
	'pre_set_site_transient_update_themes',
	function ( $transient ) {
		// Only the real starter folder updates itself; an old-style copy under a client's name never does.
		if ( ! is_object( $transient ) || 'elroi-starter' !== get_template() ) {
			return $transient;
		}
		$release = es_latest_release();
		if ( ! $release || ! version_compare( $release['version'], wp_get_theme( 'elroi-starter' )->get( 'Version' ), '>' ) ) {
			return $transient;
		}
		$transient->response['elroi-starter'] = array(
			'theme'        => 'elroi-starter',
			'new_version'  => $release['version'],
			'url'          => 'https://github.com/' . ES_REPO . '/releases/tag/v' . $release['version'],
			'package'      => $release['package'],
			'requires'     => '6.5',
			'requires_php' => '8.1',
		);
		return $transient;
	}
);

// Clicking "Check again" on Dashboard > Updates asks GitHub again instead of waiting for the cache.
add_action(
	'load-update-core.php',
	function () {
		if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			delete_site_transient( 'es_starter_release' );
		}
	}
);
