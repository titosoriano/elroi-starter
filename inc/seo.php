<?php
/**
 * Structured data (JSON-LD) from Site Settings and page content: the business on the home page,
 * breadcrumbs on inner pages, and FAQPage wherever a page has questions. Titles, descriptions,
 * Open Graph and the sitemap are left to an SEO plugin (Rank Math). The business node uses the
 * same @id as Rank Math's Organization (home URL + #organization), so Google merges the two.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * The business as a schema.org node.
 */
function es_business_schema() {
	$node = array(
		'@type'       => es_setting( 'business_type' ) ? es_setting( 'business_type' ) : 'LocalBusiness',
		'@id'         => home_url( '/#organization' ),
		'name'        => es_setting( 'company' ),
		'url'         => home_url( '/' ),
		'description' => es_setting( 'description' ),
		'telephone'   => es_tel( es_setting( 'phone' ) ),
		'email'       => es_setting( 'email' ),
	);
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
	$same = array_values( es_social_links() );
	if ( $same ) {
		$node['sameAs'] = $same;
	}
	$logo = get_theme_mod( 'custom_logo' );
	if ( $logo ) {
		$node['logo'] = wp_get_attachment_image_url( $logo, 'full' );
	}
	return array_filter( $node );
}

/**
 * Breadcrumb trail for the current page: [ [ name, url ] ].
 */
function es_breadcrumbs() {
	if ( is_front_page() ) {
		return array();
	}
	$trail = array( array( __( 'Home', 'elroi-starter' ), home_url( '/' ) ) );
	if ( is_singular( 'post' ) && get_option( 'page_for_posts' ) ) {
		$trail[] = array( get_the_title( get_option( 'page_for_posts' ) ), get_permalink( get_option( 'page_for_posts' ) ) );
	}
	if ( is_singular() ) {
		foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $a ) {
			$trail[] = array( get_the_title( $a ), get_permalink( $a ) );
		}
		$trail[] = array( get_the_title(), get_permalink() );
	} elseif ( is_home() ) {
		$trail[] = array( single_post_title( '', false ), get_permalink( get_option( 'page_for_posts' ) ) );
	}
	return $trail;
}

add_action(
	'wp_head',
	function () {
		if ( ! function_exists( 'elroi_content' ) ) {
			return;
		}
		$graph = array();
		if ( is_front_page() ) {
			$graph[] = es_business_schema();
		}
		$crumbs = es_breadcrumbs();
		if ( count( $crumbs ) > 1 ) {
			$items = array();
			foreach ( $crumbs as $i => $c ) {
				$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags( $c[0] ), 'item' => $c[1] );
			}
			$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
		}
		$faqs = is_page() ? ( elroi_content()['faqs'] ?? array() ) : array();
		$faqs = array_filter( (array) $faqs, fn( $f ) => ! empty( $f['q'] ) && ! empty( $f['a'] ) );
		if ( $faqs ) {
			$graph[] = array(
				'@type'      => 'FAQPage',
				'mainEntity' => array_values(
					array_map(
						fn( $f ) => array(
							'@type'          => 'Question',
							'name'           => $f['q'],
							'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( html_entity_decode( elroi_text( $f['a'] ) ) ) ),
						),
						$faqs
					)
				),
			);
		}
		if ( $graph ) {
			printf( "<script type=\"application/ld+json\">%s</script>\n", wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
	}
);
