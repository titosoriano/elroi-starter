<?php
/**
 * Structured data (JSON-LD) from Site Settings and page content: the business, breadcrumbs on
 * inner pages, and FAQPage wherever a page has questions. Titles, descriptions, Open Graph and
 * the sitemap are left to Rank Math.
 *
 * With Rank Math's Schema module on, everything goes into Rank Math's single graph: the business
 * details from Site Settings complete Rank Math's own Organization node (same @id, home URL +
 * #organization), FAQPage joins the graph, and our breadcrumbs are added only when Rank Math's
 * breadcrumbs are off (no duplicate BreadcrumbList). Without Rank Math the theme prints its own.
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
	$areas = array_values( array_filter( (array) es_setting( 'service_areas' ) ) );
	if ( $areas ) {
		$node['areaServed'] = array_map( fn( $a ) => array( '@type' => 'Place', 'name' => $a ), $areas );
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

/**
 * FAQPage node for the current page, or null.
 */
function es_faq_schema() {
	if ( ! is_page() || ! function_exists( 'elroi_content' ) ) {
		return null;
	}
	$faqs = array_filter( (array) ( elroi_content()['faqs'] ?? array() ), fn( $f ) => ! empty( $f['q'] ) && ! empty( $f['a'] ) );
	if ( ! $faqs ) {
		return null;
	}
	return array(
		'@type'      => 'FAQPage',
		'@id'        => get_permalink() . '#faq',
		'mainEntity' => array_values(
			array_map(
				fn( $f ) => array(
					'@type'          => 'Question',
					'name'           => es_plain( $f['q'] ),
					'acceptedAnswer' => array( '@type' => 'Answer', 'text' => es_plain( $f['a'] ) ),
				),
				$faqs
			)
		),
	);
}

/**
 * BreadcrumbList node for the current page, or null on the home page.
 */
function es_breadcrumb_schema() {
	$crumbs = es_breadcrumbs();
	if ( count( $crumbs ) < 2 ) {
		return null;
	}
	$items = array();
	foreach ( $crumbs as $i => $c ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags( $c[0] ), 'item' => $c[1] );
	}
	return array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
}

/**
 * Whether Rank Math prints the structured data (its Schema module is on).
 */
function es_rank_math_schema() {
	return defined( 'RANK_MATH_VERSION' ) && class_exists( '\RankMath\Helper' ) && \RankMath\Helper::is_module_active( 'rich-snippet' );
}

add_filter(
	'rank_math/json_ld',
	function ( $data ) {
		if ( ! function_exists( 'elroi_content' ) || ! is_array( $data ) ) {
			return $data;
		}
		$biz = es_business_schema();
		if ( isset( $data['publisher'] ) && is_array( $data['publisher'] ) && str_ends_with( (string) ( $data['publisher']['@id'] ?? '' ), '#organization' ) ) {
			// Rank Math's own logo and image objects win; every other detail comes from Site Settings.
			$data['publisher'] = array_merge( $data['publisher'], array_diff_key( $biz, array_intersect_key( $data['publisher'], array( 'logo' => 1, 'image' => 1 ) ) ) );
		} elseif ( is_front_page() ) {
			$data['es_business'] = $biz;
		}
		$faq = es_faq_schema();
		if ( $faq ) {
			$data['es_faq'] = $faq;
		}
		if ( ! isset( $data['BreadcrumbList'] ) && ! ( class_exists( '\RankMath\Helper' ) && \RankMath\Helper::is_breadcrumbs_enabled() ) ) {
			$crumbs = es_breadcrumb_schema();
			if ( $crumbs ) {
				$data['es_breadcrumbs'] = $crumbs;
			}
		}
		return $data;
	},
	100
);

add_action(
	'wp_head',
	function () {
		if ( ! function_exists( 'elroi_content' ) || es_rank_math_schema() ) {
			return;
		}
		$graph = array_values( array_filter( array( is_front_page() ? es_business_schema() : null, es_breadcrumb_schema(), es_faq_schema() ) ) );
		if ( $graph ) {
			printf( "<script type=\"application/ld+json\">%s</script>\n", wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
	}
);
