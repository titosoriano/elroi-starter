<?php
/**
 * Local Playground content for a client child theme: one published page per registered page
 * template (front-page becomes the home page), a Blog page and a sample post. Idempotent.
 * Add the client's own menu or sample content below when a check needs it.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

$es_page = function ( $slug, $title, $template = '' ) {
	$found = get_page_by_path( $slug );
	$id    = $found ? $found->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ) );
	if ( $template ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}
	return $id;
};

foreach ( elroi_templates() as $template => $cfg ) {
	if ( 'front-page' === $template ) {
		update_option( 'page_on_front', $es_page( 'home', 'Home' ) );
		update_option( 'show_on_front', 'page' );
		continue;
	}
	$slug = sanitize_title( basename( $template, '.php' ) );
	$es_page( 'page' === $slug ? 'general-page' : $slug, $cfg['label'] ?? ucfirst( $slug ), $template );
}
update_option( 'page_for_posts', $es_page( 'blog', 'Blog' ) );

if ( ! get_page_by_path( 'welcome', OBJECT, 'post' ) ) {
	wp_insert_post( array( 'post_status' => 'publish', 'post_title' => 'Welcome to the journal', 'post_name' => 'welcome', 'post_excerpt' => 'A sample post.', 'post_content' => '<!-- wp:paragraph --><p>A sample post.</p><!-- /wp:paragraph --><h2>First part</h2><p>One.</p><h2>Second part</h2><p>Two.</p>' ) );
}
