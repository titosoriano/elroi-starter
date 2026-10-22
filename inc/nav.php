<?php
/**
 * Navigation from the "Main menu" location (Appearance > Menus): top-level items in the header bar,
 * their children in a dropdown; the same tree in the mobile menu.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menu tree for a location: [ { title, url, current, children[] } ].
 *
 * @param string $location Menu location.
 */
function es_menu_tree( $location ) {
	$locations = get_nav_menu_locations();
	$items     = empty( $locations[ $location ] ) ? array() : wp_get_nav_menu_items( $locations[ $location ] );
	if ( ! $items ) {
		return array();
	}
	_wp_menu_item_classes_by_context( $items );
	$nodes = array();
	foreach ( $items as $item ) {
		$classes             = (array) $item->classes;
		$nodes[ $item->ID ] = array(
			'title'    => $item->title,
			'url'      => $item->url,
			'parent'   => (int) $item->menu_item_parent,
			'current'  => (bool) array_intersect( $classes, array( 'current-menu-item', 'current-menu-ancestor', 'current_page_parent' ) ),
			'children' => array(),
		);
	}
	$tree = array();
	foreach ( $nodes as $id => $node ) {
		if ( $node['parent'] && isset( $nodes[ $node['parent'] ] ) ) {
			$nodes[ $node['parent'] ]['children'][] = &$nodes[ $id ];
		}
	}
	foreach ( $nodes as $id => $node ) {
		if ( ! $node['parent'] ) {
			$tree[] = &$nodes[ $id ];
		}
	}
	return $tree;
}

/**
 * Header navigation (desktop).
 */
function es_render_nav() {
	$tree = es_menu_tree( 'primary' );
	if ( ! $tree ) {
		return;
	}
	echo '<ul class="es-nav">';
	foreach ( $tree as $i => $top ) {
		$current = $top['current'] ? ' aria-current="page"' : '';
		if ( ! $top['children'] ) {
			printf( '<li><a class="es-nav__link" href="%s"%s>%s</a></li>', esc_url( $top['url'] ), $current, esc_html( $top['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			continue;
		}
		$id = 'es-sub-' . $i;
		printf( '<li class="es-nav__has" data-es-dropdown><button class="es-nav__link" type="button" aria-expanded="false" aria-controls="%1$s">%2$s %3$s</button><ul class="es-nav__sub" id="%1$s" hidden>', esc_attr( $id ), esc_html( $top['title'] ), es_icon( 'chevron', 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $top['url'] && '#' !== $top['url'] ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $top['url'] ), esc_html( sprintf( /* translators: %s: menu item */ __( 'All %s', 'elroi-starter' ), strtolower( $top['title'] ) ) ) );
		}
		foreach ( $top['children'] as $child ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $child['url'] ), esc_html( $child['title'] ) );
		}
		echo '</ul></li>';
	}
	echo '</ul>';
}

/**
 * Mobile menu (inside the drawer).
 */
function es_render_drawer() {
	$tree = es_menu_tree( 'primary' );
	echo '<ul class="es-drawer__nav">';
	foreach ( $tree as $top ) {
		if ( ! $top['children'] ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $top['url'] ), esc_html( $top['title'] ) );
			continue;
		}
		printf( '<li><details><summary>%s</summary><ul>', esc_html( $top['title'] ) );
		if ( $top['url'] && '#' !== $top['url'] ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $top['url'] ), esc_html( sprintf( /* translators: %s: menu item */ __( 'All %s', 'elroi-starter' ), strtolower( $top['title'] ) ) ) );
		}
		foreach ( $top['children'] as $child ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $child['url'] ), esc_html( $child['title'] ) );
		}
		echo '</ul></details></li>';
	}
	echo '</ul>';
}
