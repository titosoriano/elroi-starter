<?php
/**
 * Theme supports, menus, assets and head cleanup.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		add_editor_style( array( 'assets/css/tokens.css', 'assets/css/editor.css' ) );
		register_nav_menus(
			array(
				'primary' => __( 'Main menu', 'elroi-starter' ),
				'footer'  => __( 'Footer menu', 'elroi-starter' ),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'es-tokens', ES_URI . '/assets/css/tokens.css', array(), ES_VERSION );
		wp_enqueue_style( 'es-site', ES_URI . '/assets/css/site.css', array( 'es-tokens' ), ES_VERSION );
		wp_enqueue_script( 'es-site', ES_URI . '/assets/js/site.js', array(), ES_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		// Designed pages are not block content: skip the block library CSS there.
		if ( ! is_singular( 'post' ) ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'global-styles' );
			wp_dequeue_style( 'classic-theme-styles' );
		}
	},
	20
);

// Quieter <head>.
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

// Without the plugin the site cannot be edited: tell the administrator.
add_action(
	'admin_notices',
	function () {
		if ( ! function_exists( 'elroi_register_template' ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p><strong>ElRoi Starter needs the ElRoi Site Editor plugin.</strong> Download it from <a href="https://github.com/titosoriano/elroi-site-editor/releases/latest">github.com/titosoriano/elroi-site-editor</a>, then upload and activate it.</p></div>';
		}
	}
);

// Without the plugin the designed templates have no content: show a short notice page instead of
// a broken one.
add_filter(
	'template_include',
	function ( $template ) {
		return function_exists( 'elroi_content' ) ? $template : ES_DIR . '/parts/needs-plugin.php';
	},
	99
);
