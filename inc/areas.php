<?php
/**
 * Local SEO: service area pages, other offices, the Google rating and typed service areas.
 *
 * - Service area pages (template "Service area page", templates/area.php): one template for a
 *   hub, its regions (counties) and their towns. The kind comes from the page tree, so the client
 *   only creates pages: a page with area pages under it that have their own children is a hub, a
 *   page with area pages under it is a region, any other is a town (Nuvista: /service-areas/,
 *   /bergen-county/, /bergen-county/fair-lawn/). The hub lists region cards with town chips, a
 *   region an A to Z town index, a town the other towns of its region; every area page lists the
 *   service pages. Each prints a local business node with the place as its area.
 * - Other offices (Site Settings > Contact > Other offices): listed in the footer and printed as
 *   `department` nodes of the business, so every office can rank in its own town.
 * - Google rating: es_google_rating() reads the rating the free "Widgets for Google Reviews"
 *   (Trustindex) plugin syncs from the Google Business Profile, or the `es_google_rating` filter.
 *   Area pages show it under the heading with a link to the reviews and mark it up as
 *   aggregateRating. Nothing prints without real data. Google calls ratings a business marks up
 *   about itself ineligible for stars, though it showed them for Nuvista's old site; never type a
 *   rating by hand.
 * - Typed service areas: a Site Settings line "City: Teaneck, NJ", "County: Bergen County, NJ" or
 *   "State: New Jersey" tells Google what kind of place it is ("... County" alone is understood
 *   too); other lines stay a plain Place.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

const ES_AREA_TEMPLATE = 'templates/area.php';

/**
 * Published area pages directly under a page, A to Z.
 *
 * @param int $parent Parent page ID.
 * @return WP_Post[]
 */
function es_area_children( $parent ) {
	$pages = get_pages(
		array(
			'parent'      => (int) $parent,
			'sort_column' => 'post_title',
			'post_status' => 'publish',
		)
	);
	return array_values( array_filter( $pages, fn( $p ) => ES_AREA_TEMPLATE === get_page_template_slug( $p ) ) );
}

/**
 * The kind of an area page from the page tree: 'hub', 'region' or 'town'.
 *
 * @param int|WP_Post|null $post Area page.
 */
function es_area_kind( $post = null ) {
	$post     = get_post( $post );
	$children = $post ? es_area_children( $post->ID ) : array();
	if ( ! $children ) {
		return 'town';
	}
	foreach ( $children as $child ) {
		if ( es_area_children( $child->ID ) ) {
			return 'hub';
		}
	}
	return 'region';
}

/**
 * Pages built on the service templates (es_service_templates()), in menu order.
 *
 * @return WP_Post[]
 */
function es_service_pages() {
	return get_posts(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => 100,
			'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'meta_query'  => array( array( 'key' => '_wp_page_template', 'value' => es_service_templates(), 'compare' => 'IN' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
}

/**
 * Google rating from the Trustindex plugin's synced data, or null when there is none.
 * Filter `es_google_rating` (array( 'value' => 4.9, 'count' => 73, 'url' => '...' ) or null).
 *
 * @return array{value: float, count: int, url: string}|null
 */
function es_google_rating() {
	$d      = get_option( 'trustindex-google-page-details' );
	$rating = null;
	if ( is_array( $d ) && ! empty( $d['rating_score'] ) && ! empty( $d['rating_number'] ) ) {
		$rating = array(
			'value' => round( (float) $d['rating_score'], 1 ),
			'count' => (int) $d['rating_number'],
			'url'   => ! empty( $d['id'] ) ? 'https://search.google.com/local/reviews?placeid=' . rawurlencode( (string) $d['id'] ) : (string) es_setting( 'google' ),
		);
	}
	$rating = apply_filters( 'es_google_rating', $rating );
	return is_array( $rating ) && ! empty( $rating['value'] ) && ! empty( $rating['count'] ) ? $rating + array( 'url' => '' ) : null;
}

/**
 * A service area line as a schema.org place: "City: Teaneck, NJ", "County: ...", "State: ...",
 * a name ending in "County", or a plain Place.
 *
 * @param string $line Line from Site Settings.
 */
function es_area_node( $line ) {
	$line  = trim( (string) $line );
	$types = array( 'city' => 'City', 'town' => 'City', 'county' => 'AdministrativeArea', 'region' => 'AdministrativeArea', 'state' => 'State', 'country' => 'Country' );
	if ( preg_match( '/^(city|town|county|region|state|country)\s*:\s*(.+)$/i', $line, $m ) ) {
		return array( '@type' => $types[ strtolower( $m[1] ) ], 'name' => trim( $m[2] ) );
	}
	if ( preg_match( '/\b(county|parish|borough)\b/i', $line ) ) {
		return array( '@type' => 'AdministrativeArea', 'name' => $line );
	}
	return array( '@type' => 'Place', 'name' => $line );
}

/**
 * The service areas from Site Settings as schema.org places.
 */
function es_area_nodes() {
	return array_map( 'es_area_node', array_values( array_filter( (array) es_setting( 'service_areas' ) ) ) );
}

/**
 * Other offices from Site Settings, each with a one-line address.
 *
 * @return array[]
 */
function es_offices() {
	$out = array();
	foreach ( (array) es_setting( 'offices' ) as $o ) {
		$o            = (array) $o + array_fill_keys( array( 'name', 'street', 'city', 'region', 'zip', 'phone' ), '' );
		$o['address'] = trim( implode( ', ', array_filter( array( $o['street'], $o['city'], trim( $o['region'] . ' ' . $o['zip'] ) ) ) ) );
		if ( $o['address'] || $o['phone'] ) {
			$out[] = $o;
		}
	}
	return $out;
}

/**
 * The other offices as `department` nodes of the business.
 */
function es_office_nodes() {
	$nodes = array();
	foreach ( es_offices() as $i => $o ) {
		$node = array(
			'@type'              => es_setting( 'business_type' ) ? es_setting( 'business_type' ) : 'LocalBusiness',
			'@id'                => home_url( '/#office-' . ( $i + 1 ) ),
			'name'               => trim( es_setting( 'company' ) . ( $o['name'] ? ' ' . $o['name'] : '' ) ),
			'url'                => home_url( '/' ),
			'telephone'          => $o['phone'] ? es_tel( $o['phone'] ) : es_tel( es_setting( 'phone' ) ),
			'parentOrganization' => array( '@id' => home_url( '/#organization' ) ),
		);
		if ( $o['street'] ) {
			$node['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $o['street'],
				'addressLocality' => $o['city'],
				'addressRegion'   => $o['region'],
				'postalCode'      => $o['zip'],
				'addressCountry'  => 'US',
			);
		}
		$nodes[] = array_filter( $node );
	}
	return $nodes;
}

/**
 * The local business node of an area page: the business, serving this place, with its rating.
 * Filter `es_area_schema`.
 */
function es_area_schema() {
	if ( ! is_page() || ES_AREA_TEMPLATE !== get_page_template_slug() ) {
		return null;
	}
	$kind = es_area_kind();
	$node = array(
		'@type'              => es_setting( 'business_type' ) ? es_setting( 'business_type' ) : 'LocalBusiness',
		'@id'                => get_permalink() . '#business',
		'name'               => es_setting( 'company' ),
		'url'                => get_permalink(),
		'telephone'          => es_tel( es_setting( 'phone' ) ),
		'parentOrganization' => array( '@id' => home_url( '/#organization' ) ),
	);
	if ( 'hub' === $kind ) {
		$areas = es_area_nodes();
		if ( $areas ) {
			$node['areaServed'] = $areas;
		}
	} else {
		$node['areaServed'] = array( '@type' => 'town' === $kind ? 'City' : 'AdministrativeArea', 'name' => wp_strip_all_tags( get_the_title() ) );
	}
	if ( es_setting( 'street' ) ) {
		$node['address'] = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => es_setting( 'street' ),
			'addressLocality' => es_setting( 'city' ),
			'addressRegion'   => es_setting( 'region' ),
			'postalCode'      => es_setting( 'zip' ),
			'addressCountry'  => 'US',
		);
	}
	$rating = es_google_rating();
	if ( $rating ) {
		$node['aggregateRating'] = array( '@type' => 'AggregateRating', 'ratingValue' => $rating['value'], 'reviewCount' => $rating['count'], 'bestRating' => 5 );
	}
	return array_filter( (array) apply_filters( 'es_area_schema', $node, $kind ) );
}
