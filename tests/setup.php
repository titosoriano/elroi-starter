<?php
/**
 * Demo content for the local Playground: pages for each template, a menu, a sample post.
 * Idempotent.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

$page = function ( $slug, $title, $template = '', $parent = 0 ) {
	$found = get_page_by_path( $parent ? get_post_field( 'post_name', $parent ) . '/' . $slug : $slug );
	$id    = $found ? $found->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_parent' => $parent ) );
	if ( $template ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}
	return $id;
};
$home     = $page( 'home', 'Home' );
$services = $page( 'services', 'Services', 'templates/page.php' );
$one      = $page( 'service-one', 'Service one', 'templates/service.php', $services );
$two      = $page( 'service-two', 'Service two', 'templates/service.php', $services );
$about    = $page( 'about', 'About', 'templates/page.php' );
$contact  = $page( 'contact', 'Contact', 'templates/contact.php' );
$blog     = $page( 'blog', 'Blog' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home );
update_option( 'page_for_posts', $blog );

if ( ! wp_get_nav_menu_object( 'Main' ) ) {
	$menu = wp_create_nav_menu( 'Main' );
	$top  = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $services, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
	foreach ( array( $one, $two ) as $child ) {
		wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $child, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-parent-id' => $top, 'menu-item-status' => 'publish' ) );
	}
	foreach ( array( $about, $blog, $contact ) as $p ) {
		wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $p, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
	}
	set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu, 'footer' => $menu ) );
}

// Sample service content.
if ( ! str_contains( (string) get_post_meta( $one, '_elroi_content', true ), 'our process' ) ) {
	update_post_meta(
		$one,
		'_elroi_content',
		wp_slash(
			wp_json_encode(
				array(
					'hero'     => array( 'eyebrow' => 'Service', 'h1' => 'Service one, *done right.*', 'lede' => 'A clear promise for this service in one sentence.' ),
					'sections' => array(
						array( 'heading' => 'What is included', 'paragraphs' => array( 'Describe the scope in plain words.' ), 'bullets' => array( 'Free estimate', 'Written scope', 'Licensed crews' ) ),
						array( 'heading' => 'How it works', 'cards' => array( array( 'heading' => 'Visit', 'text' => 'We see the space.' ), array( 'heading' => 'Plan', 'text' => 'You get a written scope.' ), array( 'heading' => 'Build', 'text' => 'One team does the work.' ) ) ),
					),
					'faqs'     => array( array( 'q' => 'How long does it take?', 'a' => 'Most projects take two to four weeks. See [our process](/services/).' ) ),
				)
			)
		)
	);
}
if ( ! get_page_by_path( 'welcome', OBJECT, 'post' ) ) {
	wp_insert_post( array( 'post_status' => 'publish', 'post_title' => 'Welcome to the journal', 'post_name' => 'welcome', 'post_excerpt' => 'A sample post.', 'post_content' => '<!-- wp:paragraph --><p>Write posts in the block editor.</p><!-- /wp:paragraph -->' ) );
}
$es_settings = (array) get_option( 'elroi_settings', array() );
if ( empty( $es_settings['service_areas'] ) ) {
	update_option( 'elroi_settings', array_merge( $es_settings, array( 'service_areas' => array( 'Morristown, NJ' ) ) ) );
}
