<?php
/**
 * Structured data (JSON-LD) from Site Settings and page content: the business, breadcrumbs on
 * inner pages, Service on service pages, WebPage with its dates, FAQPage wherever a page has questions, and VideoObject for every video field (inc/video.php). Titles, descriptions, Open Graph and
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
	/**
	 * Client child themes add or change details here (extra profiles, offers, a logo fallback).
	 *
	 * @param array $node The business node.
	 */
	return array_filter( (array) apply_filters( 'es_business_schema', $node ) );
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
 * Page templates that describe one service (a child adds its own with `es_service_templates`).
 *
 * @return string[]
 */
function es_service_templates() {
	return (array) apply_filters( 'es_service_templates', array( 'templates/service.php' ) );
}

/**
 * Service node for a service page, or null: what the page offers, by whom and where. The name is
 * the page's H1 (or full title), the type its short name, the description its introduction (or
 * excerpt), the provider the business and the area its service areas.
 */
function es_service_schema() {
	if ( ! is_page() || ! in_array( get_page_template_slug(), es_service_templates(), true ) || ! function_exists( 'elroi_content' ) ) {
		return null;
	}
	$hero = (array) ( elroi_content()['hero'] ?? array() );
	$name = es_plain( ! empty( $hero['h1'] ) ? $hero['h1'] : es_page_title() );
	$node = array(
		'@type'       => 'Service',
		'@id'         => get_permalink() . '#service',
		'name'        => wp_strip_all_tags( $name ),
		'serviceType' => wp_strip_all_tags( get_the_title() ),
		'description' => es_plain( ! empty( $hero['lede'] ) ? $hero['lede'] : get_the_excerpt() ),
		'url'         => get_permalink(),
		'provider'    => array( '@id' => home_url( '/#organization' ) ),
	);
	$areas = array_values( array_filter( (array) es_setting( 'service_areas' ) ) );
	if ( $areas ) {
		$node['areaServed'] = array_map( fn( $a ) => array( '@type' => 'Place', 'name' => $a ), $areas );
	}
	if ( has_post_thumbnail() ) {
		$node['image'] = get_the_post_thumbnail_url( null, 'full' );
	}
	return array_filter( (array) apply_filters( 'es_service_schema', $node ) );
}

/**
 * WebPage node with published and updated dates, for the theme's own graph (Rank Math prints its
 * own WebPage with the same dates). Search engines and AI answers use the dates for freshness.
 */
function es_webpage_schema() {
	if ( ! is_singular() ) {
		return null;
	}
	$id = get_queried_object_id();
	return array(
		'@type'         => 'WebPage',
		'@id'           => get_permalink( $id ) . '#webpage',
		'url'           => get_permalink( $id ),
		'name'          => wp_strip_all_tags( es_page_title( $id ) ),
		'datePublished' => get_the_date( 'c', $id ),
		'dateModified'  => get_the_modified_date( 'c', $id ),
		'inLanguage'    => get_bloginfo( 'language' ),
		'publisher'     => array( '@id' => home_url( '/#organization' ) ),
	);
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
		$service = es_service_schema();
		if ( $service ) {
			$data['es_service'] = $service;
		}
		foreach ( es_videos_schema() as $i => $video ) {
			$data[ 'es_video_' . $i ] = $video;
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
		$graph = array_values( array_filter( array_merge( array( is_front_page() ? es_business_schema() : null, es_webpage_schema(), es_breadcrumb_schema(), es_faq_schema(), es_service_schema() ), es_videos_schema() ) ) );
		if ( $graph ) {
			printf( "<script type=\"application/ld+json\">%s</script>\n", wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
	}
);
