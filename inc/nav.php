<?php
/**
 * Navigation from the "Main menu" location (Appearance > Menus): top-level items in the header bar,
 * their children in a dropdown; the same tree in the mobile menu. A third level shows its parent
 * as a small heading over its links.
 *
 * Mega menu (opt in: add_theme_support( 'es-mega-menu' ) in the child theme): a top-level item
 * whose children have children of their own opens a full-width panel instead, for clients with
 * many services or towns (Nuvista's services and service areas):
 *   Top level item       -> header bar entry; its menu Description is the panel's introduction
 *     Second level item  -> column heading (custom link with URL #)
 *       Third level item -> link; hovering or focusing it shows its page's featured image (or the
 *                           Media Library photo of a CSS class es-img-<attachment ID>) and its
 *                           menu Description in the preview
 * A column with more than 11 links makes the panel "dense": links in compact columns, one per 11
 * links, and no preview. Menu > Screen Options shows the Description and CSS Classes fields.
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
		$classes = (array) $item->classes;
		$image   = 'post_type' === $item->type ? (int) get_post_thumbnail_id( (int) $item->object_id ) : 0;
		foreach ( $classes as $class ) {
			if ( preg_match( '/^es-img-(\d+)$/', (string) $class, $m ) ) {
				$image = (int) $m[1];
			}
		}
		$nodes[ $item->ID ] = array(
			'id'       => (int) $item->ID,
			'title'    => $item->title,
			'url'      => '#' === $item->url ? '' : $item->url,
			'desc'     => trim( (string) $item->description ),
			'image'    => $image,
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
 * Links of a dropdown or panel group: a second-level item's own children, or the item itself.
 *
 * @param array $child Second-level node.
 */
function es_menu_links( $child ) {
	return $child['children'] ? $child['children'] : array( $child );
}

/**
 * Whether a top-level item opens a mega panel.
 *
 * @param array $top Top-level node.
 */
function es_is_mega( $top ) {
	if ( ! current_theme_supports( 'es-mega-menu' ) ) {
		return false;
	}
	foreach ( $top['children'] as $child ) {
		if ( $child['children'] ) {
			return true;
		}
	}
	return false;
}

/**
 * "All services" link text for a top-level item.
 *
 * @param array $top Top-level node.
 */
function es_menu_all( $top ) {
	/* translators: %s: menu item */
	return sprintf( __( 'All %s', 'elroi-starter' ), strtolower( wp_strip_all_tags( $top['title'] ) ) );
}

/**
 * A mega panel.
 *
 * @param array  $top Top-level node.
 * @param string $id  Panel id.
 */
function es_render_mega( $top, $id ) {
	$spans = array_map( fn( $g ) => max( 1, (int) ceil( count( es_menu_links( $g ) ) / 11 ) ), $top['children'] );
	$dense = max( $spans ) > 1;
	$first = null;
	foreach ( $top['children'] as $g ) {
		foreach ( es_menu_links( $g ) as $link ) {
			if ( ! $first || ( ! $first['image'] && $link['image'] ) ) {
				$first = $link;
			}
		}
	}
	printf( '<div class="es-mega%s" id="%s" hidden data-es-panel>', $dense ? ' es-mega--dense' : '', esc_attr( $id ) );
	echo '<div class="es-container es-mega__inner"><div class="es-mega__intro">';
	printf( '<p class="es-eyebrow">%s</p>', esc_html( $top['title'] ) );
	if ( $top['desc'] ) {
		printf( '<p class="es-mega__lede">%s</p>', esc_html( $top['desc'] ) );
	}
	if ( $top['url'] ) {
		printf( '<a class="es-mega__all" href="%s">%s</a>', esc_url( $top['url'] ), esc_html( es_menu_all( $top ) ) );
	}
	printf( '</div><div class="es-mega__groups" style="--es-cols:%d">', $dense ? (int) array_sum( $spans ) : count( $top['children'] ) );
	foreach ( $top['children'] as $i => $g ) {
		printf( '<div class="es-mega__group" style="--es-span:%d"><p class="es-mega__label">%s</p><ul>', $dense ? (int) $spans[ $i ] : 1, esc_html( $g['title'] ) );
		foreach ( es_menu_links( $g ) as $link ) {
			$src = $link['image'] && ! $dense ? (string) wp_get_attachment_image_url( $link['image'], 'medium_large' ) : '';
			printf(
				'<li><a href="%s"%s data-es-preview data-title="%s" data-desc="%s" data-src="%s">%s</a></li>',
				esc_url( $link['url'] ? $link['url'] : home_url( '/' ) ),
				$link['current'] ? ' aria-current="page"' : '',
				esc_attr( wp_strip_all_tags( $link['title'] ) ),
				esc_attr( $link['desc'] ),
				esc_url( $src ),
				esc_html( $link['title'] )
			);
		}
		echo '</ul></div>';
	}
	echo '</div>';
	if ( ! $dense && $first ) {
		$src = $first['image'] ? (string) wp_get_attachment_image_url( $first['image'], 'medium_large' ) : '';
		echo '<div class="es-mega__preview" aria-hidden="true" data-es-preview-pane>';
		printf( '<div class="es-mega__plate">%s</div>', $src ? '<img src="' . esc_url( $src ) . '" alt="" loading="lazy" decoding="async">' : '' );
		printf( '<p class="es-mega__ptitle" data-es-ptitle>%s</p><p class="es-mega__pdesc" data-es-pdesc>%s</p>', esc_html( $first['title'] ), esc_html( $first['desc'] ) );
		echo '</div>';
	}
	echo '</div></div>';
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
			printf( '<li><a class="es-nav__link" href="%s"%s>%s</a></li>', esc_url( $top['url'] ? $top['url'] : home_url( '/' ) ), $current, esc_html( $top['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			continue;
		}
		$id   = 'es-sub-' . $i;
		$mega = es_is_mega( $top );
		printf( '<li class="es-nav__has%1$s" data-es-dropdown%2$s><button class="es-nav__link" type="button" aria-expanded="false" aria-controls="%3$s">%4$s %5$s</button>', $mega ? ' es-nav__has--mega' : '', $mega ? ' data-es-mega' : '', esc_attr( $id ), esc_html( $top['title'] ), es_icon( 'chevron', 16 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $mega ) {
			es_render_mega( $top, $id );
			echo '</li>';
			continue;
		}
		printf( '<ul class="es-nav__sub" id="%s" hidden data-es-panel>', esc_attr( $id ) );
		if ( $top['url'] ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $top['url'] ), esc_html( es_menu_all( $top ) ) );
		}
		foreach ( $top['children'] as $child ) {
			if ( $child['children'] ) {
				printf( '<li class="es-nav__label">%s</li>', esc_html( $child['title'] ) );
			}
			foreach ( es_menu_links( $child ) as $link ) {
				printf( '<li><a href="%s">%s</a></li>', esc_url( $link['url'] ? $link['url'] : home_url( '/' ) ), esc_html( $link['title'] ) );
			}
		}
		echo '</ul></li>';
	}
	echo '</ul>';
}

/**
 * Mobile menu (inside the drawer). Third-level links sit under their parent as a small heading;
 * groups of more than 10 links use two columns.
 */
function es_render_drawer() {
	$tree = es_menu_tree( 'primary' );
	echo '<ul class="es-drawer__nav">';
	foreach ( $tree as $top ) {
		if ( ! $top['children'] ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $top['url'] ? $top['url'] : home_url( '/' ) ), esc_html( $top['title'] ) );
			continue;
		}
		printf( '<li><details><summary>%s</summary>', esc_html( $top['title'] ) );
		if ( $top['url'] ) {
			printf( '<ul><li><a href="%s">%s</a></li></ul>', esc_url( $top['url'] ), esc_html( es_menu_all( $top ) ) );
		}
		$flat = array();
		foreach ( $top['children'] as $child ) {
			if ( ! $child['children'] ) {
				$flat[] = $child;
				continue;
			}
			if ( $flat ) {
				es_render_drawer_links( $flat );
				$flat = array();
			}
			printf( '<p class="es-drawer__label">%s</p>', esc_html( $child['title'] ) );
			es_render_drawer_links( $child['children'] );
		}
		if ( $flat ) {
			es_render_drawer_links( $flat );
		}
		echo '</details></li>';
	}
	echo '</ul>';
}

/**
 * A list of drawer links.
 *
 * @param array[] $links Nodes.
 */
function es_render_drawer_links( $links ) {
	printf( '<ul%s>', count( $links ) > 10 ? ' class="es-drawer__cols"' : '' );
	foreach ( $links as $link ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $link['url'] ? $link['url'] : home_url( '/' ) ), esc_html( $link['title'] ) );
	}
	echo '</ul>';
}
