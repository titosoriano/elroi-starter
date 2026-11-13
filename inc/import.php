<?php
/**
 * Old-site import: loads a folder written by bin/scrape-site.py into this site.
 *
 *   wp es import ~/export --dry-run     # what would happen
 *   wp es import ~/export               # do it (safe to run again)
 *
 * For each row of map.csv with action "import":
 * - finds the page at new_path or creates it (parents first; a missing parent becomes a General
 *   page, or a Service area page above area pages), with the template for its kind (service, area, contact, about/legal/page, home = the
 *   front page; "blog" rows are skipped: posts come in on their own);
 * - fills the page's form through elroi_update() (cleaned like the form): the top of the page
 *   (label, H1, introduction, photo), the sections (heading, text, checklist, photo, and cards from
 *   the old H3s), and the FAQs. A page whose form already has sections or a heading is left alone unless --force;
 * - copies the SEO title and description into Rank Math when its fields are empty, the description
 *   into the excerpt, and the top photo into the featured image;
 * - a new_path that differs from old_path gets a 301 from the old address (option `es_redirects`,
 *   answered on 404s only, so a real page always wins; Rank Math > Redirections can take them over).
 * Posts (WordPress old sites): created by slug with their date, excerpt, categories, featured image,
 * SEO fields and body blocks; photos inside the body are uploaded and linked; links to the old
 * domain become site-relative. Existing posts are skipped unless --force.
 * Photos come from the export's images/ folder (WebP, already resized), uploaded once each and
 * found again by their source address (`_es_source_url`), so nothing is uploaded twice.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

// 301s for old addresses, only when nothing else answers the request.
add_action(
	'template_redirect',
	function () {
		if ( ! is_404() ) {
			return;
		}
		$map = (array) get_option( 'es_redirects', array() );
		if ( ! $map ) {
			return;
		}
		$path = '/' . trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH ), '/' ) . '/';
		$path = '//' === $path ? '/' : $path;
		if ( isset( $map[ $path ] ) ) {
			wp_safe_redirect( home_url( $map[ $path ] ), 301, 'ElRoi Starter import' );
			exit;
		}
	},
	1
);

/** Template for a map kind. */
function es_import_template( $kind ) {
	$map = array(
		'service' => 'templates/service.php',
		'area'    => 'templates/area.php',
		'contact' => 'templates/contact.php',
	);
	return $map[ $kind ] ?? 'templates/page.php';
}

/**
 * Read an export folder.
 *
 * @param string $dir Folder.
 * @return array|WP_Error { site, rows, images, dir }
 */
function es_import_load( $dir ) {
	$dir = untrailingslashit( $dir );
	if ( ! is_readable( "$dir/site.json" ) ) {
		return new WP_Error( 'es_import', "No site.json in $dir (run bin/scrape-site.py first)." );
	}
	$site = json_decode( (string) file_get_contents( "$dir/site.json" ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! is_array( $site ) ) {
		return new WP_Error( 'es_import', "$dir/site.json is not valid JSON." );
	}
	$rows = array();
	if ( is_readable( "$dir/map.csv" ) ) {
		$fh   = fopen( "$dir/map.csv", 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$head = fgetcsv( $fh, 0, ',', '"', '\\' );
		while ( $head && ( $r = fgetcsv( $fh, 0, ',', '"', '\\' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			if ( count( $r ) === count( $head ) ) {
				$rows[] = array_combine( $head, $r );
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	$images = is_readable( "$dir/images/images.json" ) ? (array) json_decode( (string) file_get_contents( "$dir/images/images.json" ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return compact( 'site', 'rows', 'images', 'dir' );
}

/** A path as /a/b/ (or / for the home page). */
function es_import_path( $path ) {
	$path = '/' . trim( (string) $path, '/' ) . '/';
	return '//' === $path ? '/' : $path;
}

/**
 * Attachment ID for a photo of the export: uploaded once, found again by its source address.
 *
 * @param array  $ex  Export (es_import_load).
 * @param string $url Source address.
 * @param string $alt Alt text when the photo is new.
 * @param bool   $dry Dry run (returns 0 for photos not uploaded yet).
 */
function es_import_image( $ex, $url, $alt = '', $dry = false ) {
	if ( ! $url ) {
		return 0;
	}
	$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_es_source_url', 'meta_value' => $url ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $found ) {
		return (int) $found[0];
	}
	$info = $ex['images'][ $url ] ?? null;
	$file = $info ? $ex['dir'] . '/images/' . basename( $info['file'] ) : '';
	if ( $dry || ! $file || ! is_readable( $file ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( basename( $file ) );
	copy( $file, $tmp );
	$alt   = '' !== trim( (string) $alt ) ? $alt : (string) ( $info['alt'] ?? '' );
	$title = '' !== $alt ? wp_trim_words( $alt, 10, '' ) : ucwords( trim( preg_replace( '/[-_]+/', ' ', preg_replace( '/-[0-9a-f]{6}$/', '', pathinfo( $file, PATHINFO_FILENAME ) ) ) ) );
	$id    = media_handle_sideload( array( 'name' => basename( $file ), 'tmp_name' => $tmp ), 0, $title );
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	update_post_meta( $id, '_es_source_url', esc_url_raw( $url ) );
	update_post_meta( $id, '_es_photo_src', basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
	if ( '' !== $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
	}
	return (int) $id;
}

/**
 * The form values for one old page.
 *
 * @param array  $ex   Export.
 * @param array  $p    Page from site.json.
 * @param string $kind Map kind.
 * @param bool   $dry  Dry run.
 */
function es_import_page_changes( $ex, $p, $kind, $dry ) {
	$alt  = function ( $src ) use ( $p ) {
		foreach ( (array) ( $p['images'] ?? array() ) as $im ) {
			if ( $im['src'] === $src ) {
				return $im['alt'];
			}
		}
		return '';
	};
	$img  = fn( $src ) => $src ? es_import_image( $ex, $src, $alt( $src ), $dry ) : 0;
	$hero = array_filter(
		array(
			'eyebrow' => (string) ( $p['eyebrow'] ?? '' ),
			'h1'      => (string) ( $p['h1'] ?? '' ),
			'lede'    => (string) ( $p['lede'] ?? '' ),
			'photo'   => $img( $p['hero_photo'] ?? '' ),
		)
	);
	if ( 'home' === $kind ) {
		return array( 'hero' => $hero );
	}
	$sections = array();
	$intro    = array_values( array_diff( (array) ( $p['intro'] ?? array() ), array( $p['lede'] ?? '', $p['eyebrow'] ?? '' ) ) );
	if ( $intro || ! empty( $p['intro_bullets'] ) ) {
		$sections[] = array_filter( array( 'paragraphs' => $intro, 'bullets' => (array) ( $p['intro_bullets'] ?? array() ) ) );
	}
	foreach ( (array) ( $p['sections'] ?? array() ) as $s ) {
		$cards = array();
		foreach ( (array) ( $s['cards'] ?? array() ) as $c ) {
			$text    = trim( implode( "\n\n", array_merge( (array) ( $c['paragraphs'] ?? array() ), array_map( fn( $b ) => '• ' . $b, (array) ( $c['bullets'] ?? array() ) ) ) ) );
			$cards[] = array_filter( array( 'heading' => (string) ( $c['heading'] ?? '' ), 'text' => $text, 'image' => $img( $c['image'] ?? '' ) ) );
		}
		$sections[] = array_filter(
			array(
				'heading'    => (string) ( $s['heading'] ?? '' ),
				'paragraphs' => (array) ( $s['paragraphs'] ?? array() ),
				'bullets'    => (array) ( $s['bullets'] ?? array() ),
				'image'      => $img( $s['image'] ?? '' ),
				'cards'      => $cards,
			)
		);
	}
	$out = array( 'hero' => $hero );
	if ( 'contact' !== $kind ) {
		$out['sections'] = array_values( array_filter( $sections ) );
	}
	if ( ! empty( $p['faqs'] ) ) {
		$out['faqs'] = array_map( fn( $f ) => array( 'q' => (string) $f['q'], 'a' => (string) $f['a'] ), (array) $p['faqs'] );
	}
	return $out;
}

/**
 * SEO fields, excerpt and featured image of an imported page or post (only where empty).
 *
 * @param int    $id    Post ID.
 * @param string $title SEO title.
 * @param string $desc  SEO description.
 * @param int    $thumb Featured image ID.
 * @param bool   $force Replace what is there.
 */
function es_import_seo( $id, $title, $desc, $thumb, $force ) {
	if ( $title && ( $force || '' === (string) get_post_meta( $id, 'rank_math_title', true ) ) ) {
		update_post_meta( $id, 'rank_math_title', sanitize_text_field( $title ) );
	}
	if ( $desc && ( $force || '' === (string) get_post_meta( $id, 'rank_math_description', true ) ) ) {
		update_post_meta( $id, 'rank_math_description', sanitize_text_field( $desc ) );
	}
	if ( $desc && '' === (string) get_post_field( 'post_excerpt', $id ) ) {
		wp_update_post( array( 'ID' => $id, 'post_excerpt' => sanitize_text_field( $desc ) ) );
	}
	if ( $thumb && ( $force || ! has_post_thumbnail( $id ) ) ) {
		set_post_thumbnail( $id, $thumb );
	}
}

/** Links to the old site become site-relative. */
function es_import_local_links( $html, $source ) {
	$host = preg_quote( preg_replace( '/^www\./', '', (string) wp_parse_url( $source, PHP_URL_HOST ) ), '#' );
	return preg_replace_callback( '#href="https?://(?:www\.)?' . $host . '(/[^"]*)?"#i', fn( $m ) => 'href="' . ( $m[1] ?? '/' ) . '"', $html );
}

/**
 * Import an export folder. Options: dry_run, force, only ('pages'|'posts'|''), limit (items per
 * run, 0 = all).
 *
 * @param string $dir  Export folder.
 * @param array  $o    Options.
 * @return array|WP_Error { done[], skipped[], todo[] }
 */
function es_import( $dir, $o = array() ) {
	$o  = wp_parse_args( $o, array( 'dry_run' => false, 'force' => false, 'only' => '', 'limit' => 0 ) );
	$ex = es_import_load( $dir );
	if ( is_wp_error( $ex ) ) {
		return $ex;
	}
	if ( ! function_exists( 'elroi_update' ) ) {
		return new WP_Error( 'es_import', 'ElRoi Site Editor is not active: run wp es setup first.' );
	}
	$dry   = (bool) $o['dry_run'];
	$log   = array( 'done' => array(), 'skipped' => array(), 'todo' => array() );
	$say   = function ( $k, $line ) use ( &$log, $dry ) {
		$log[ $k ][] = ( $dry && 'done' === $k ? '(dry run) ' : '' ) . $line;
	};
	$count = 0;
	$full  = fn() => $o['limit'] && $count >= (int) $o['limit'];
	$redir = (array) get_option( 'es_redirects', array() );
	$by    = array();
	foreach ( (array) ( $ex['site']['pages'] ?? array() ) as $p ) {
		$by[ es_import_path( $p['path'] ) ] = $p;
	}

	if ( 'posts' !== $o['only'] ) {
		$rows = array_filter( $ex['rows'], fn( $r ) => 'import' === ( $r['action'] ?? '' ) && 'blog' !== ( $r['kind'] ?? '' ) && isset( $by[ es_import_path( $r['old_path'] ) ] ) );
		usort( $rows, fn( $a, $b ) => substr_count( es_import_path( $a['new_path'] ), '/' ) <=> substr_count( es_import_path( $b['new_path'] ), '/' ) );
		$planned = array();
		foreach ( $rows as $r ) {
			if ( $full() ) {
				$log['todo'][] = 'Stopped at --limit: run the import again to continue.';
				break;
			}
			$p    = $by[ es_import_path( $r['old_path'] ) ];
			$kind = (string) $r['kind'];
			$new  = es_import_path( $r['new_path'] ?: $r['old_path'] );
			$old  = es_import_path( $r['old_path'] );
			$tpl  = es_import_template( $kind );

			if ( '/' === $new || 'home' === $kind ) {
				$page = get_post( (int) get_option( 'page_on_front' ) );
				if ( ! $page ) {
					$say( 'skipped', "Home: no front page is set (run wp es setup), so \"$old\" was not imported." );
					continue;
				}
			} else {
				$page = get_page_by_path( trim( $new, '/' ) );
			}
			if ( ! $page ) {
				// Parents first: a missing one becomes a General page named after its slug.
				$parts  = array_values( array_filter( explode( '/', $new ) ) );
				$slug   = array_pop( $parts );
				$parent = 0;
				$walk   = '';
				foreach ( $parts as $seg ) {
					$walk .= '/' . $seg;
					$pp    = get_page_by_path( ltrim( $walk, '/' ) );
					if ( ! $pp && ! isset( $planned[ $walk ] ) ) {
						$planned[ $walk ] = true;
						// Under an area page the parent is a region (Service area page); otherwise a General page.
						$ptpl = 'area' === $kind ? 'templates/area.php' : 'templates/page.php';
						$say( 'done', 'Create parent page ' . $walk . '/ (' . ( 'area' === $kind ? 'Service area page' : 'General page' ) . ').' );
						if ( ! $dry ) {
							$pid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => ucwords( str_replace( '-', ' ', $seg ) ), 'post_name' => $seg, 'post_parent' => $parent ) );
							update_post_meta( $pid, '_wp_page_template', $ptpl );
							$pp = get_post( $pid );
						}
					}
					$parent = $pp ? $pp->ID : 0;
				}
				$title = '' !== trim( (string) ( $r['title'] ?? '' ) ) ? $r['title'] : ( $p['h1'] ?: $slug );
				$say( 'done', "Create page \"$title\" ($new) with $tpl." );
				if ( $dry ) {
					++$count;
					if ( $new !== $old ) {
						$say( 'done', "Redirect $old to $new." );
					}
					continue;
				}
				$id   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => sanitize_text_field( $title ), 'post_name' => $slug, 'post_parent' => $parent ) );
				$page = get_post( $id );
				update_post_meta( $id, '_wp_page_template', $tpl );
			} elseif ( 'home' !== $kind && ! get_page_template_slug( $page ) ) {
				$dry || update_post_meta( $page->ID, '_wp_page_template', $tpl );
			}

			$has = json_decode( (string) get_post_meta( $page->ID, '_elroi_content', true ), true );
			if ( ! $o['force'] && ( ! empty( $has['sections'] ) || ! empty( $has['hero']['h1'] ) ) ) {
				$say( 'skipped', "\"{$page->post_title}\" ($new) already has content (--force replaces it)." );
			} else {
				$changes = es_import_page_changes( $ex, $p, $kind, $dry );
				$n       = count( $changes['sections'] ?? array() );
				$say( 'done', "Fill \"{$page->post_title}\" ($new): top of page, $n sections, " . count( $changes['faqs'] ?? array() ) . ' FAQs.' );
				if ( ! $dry ) {
					elroi_update( $page->ID, $changes );
					es_import_seo( $page->ID, (string) ( $p['seo_title'] ?? '' ), (string) ( $p['seo_description'] ?? '' ), (int) ( $changes['hero']['photo'] ?? 0 ), (bool) $o['force'] );
				}
				++$count;
			}
			if ( $new !== $old && '/' !== $old && ( $redir[ $old ] ?? '' ) !== $new ) {
				$redir[ $old ] = $new;
				$say( 'done', "Redirect $old to $new." );
			}
		}
	}

	if ( 'pages' !== $o['only'] ) {
		$cats = array();
		foreach ( (array) ( $ex['site']['categories'] ?? array() ) as $c ) {
			$term = get_term_by( 'slug', $c['slug'], 'category' );
			if ( ! $term && ! $dry ) {
				$made = wp_insert_term( $c['name'], 'category', array( 'slug' => $c['slug'], 'description' => $c['description'] ?? '' ) );
				$term = is_wp_error( $made ) ? null : get_term( $made['term_id'] );
			}
			$cats[ $c['slug'] ] = $term ? (int) $term->term_id : 0;
		}
		$source = (string) ( $ex['site']['source'] ?? '' );
		foreach ( (array) ( $ex['site']['posts'] ?? array() ) as $post ) {
			if ( $full() ) {
				$log['todo'][] = 'Stopped at --limit: run the import again to continue.';
				break;
			}
			$exists = get_page_by_path( $post['slug'], OBJECT, 'post' );
			if ( $exists && ! $o['force'] ) {
				$say( 'skipped', "Post \"{$exists->post_title}\" already exists." );
				continue;
			}
			$say( 'done', ( $exists ? 'Replace' : 'Create' ) . " post \"{$post['title']}\" (" . count( (array) $post['blocks'] ) . ' blocks, ' . count( (array) $post['images'] ) . ' photos).' );
			++$count;
			$old_path = es_import_path( (string) wp_parse_url( (string) $post['url'], PHP_URL_PATH ) );
			if ( $old_path !== '/' . $post['slug'] . '/' ) {
				$redir[ $old_path ] = '/' . $post['slug'] . '/';
			}
			if ( $dry ) {
				continue;
			}
			$blocks = array();
			foreach ( (array) $post['blocks'] as $b ) {
				if ( is_array( $b ) ) {
					$im  = $post['images'][ $b['img'] ] ?? null;
					$aid = $im ? es_import_image( $ex, $im['src'], $im['alt'] ) : 0;
					if ( ! $aid ) {
						continue;
					}
					$src = (string) wp_get_attachment_image_url( $aid, 'large' );
					$alt = (string) get_post_meta( $aid, '_wp_attachment_image_alt', true );
					$cap = '' !== ( $b['caption'] ?? '' ) ? '<figcaption class="wp-element-caption">' . esc_html( $b['caption'] ) . '</figcaption>' : '';
					$blocks[] = '<!-- wp:image {"id":' . $aid . ',"sizeSlug":"large","linkDestination":"none"} -->' . "\n" . '<figure class="wp-block-image size-large"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . $aid . '"/>' . $cap . "</figure>\n<!-- /wp:image -->";
				} else {
					$blocks[] = es_import_local_links( (string) $b, $source );
				}
			}
			$data = array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => sanitize_text_field( $post['title'] ),
				'post_name'     => sanitize_title( $post['slug'] ),
				'post_date'     => ! empty( $post['date'] ) ? str_replace( 'T', ' ', substr( (string) $post['date'], 0, 19 ) ) : current_time( 'mysql' ),
				'post_excerpt'  => sanitize_text_field( $post['excerpt'] ?? '' ),
				'post_content'  => wp_kses_post( implode( "\n\n", $blocks ) ),
				'post_category' => array_values( array_filter( array_map( fn( $s ) => $cats[ $s ] ?? 0, (array) $post['categories'] ) ) ),
			);
			if ( $exists ) {
				$data['ID'] = $exists->ID;
			}
			$id    = wp_insert_post( wp_slash( $data ) );
			$thumb = ! empty( $post['featured']['src'] ) ? es_import_image( $ex, $post['featured']['src'], $post['featured']['alt'] ?? '' ) : 0;
			es_import_seo( $id, (string) ( $post['seo_title'] ?? '' ), (string) ( $post['seo_description'] ?? '' ), $thumb, (bool) $o['force'] );
		}
	}

	if ( ! $dry ) {
		update_option( 'es_redirects', $redir, false );
	}
	$log['todo'][] = 'Read every imported page: words are copied as they were. Fix facts with the client and follow the copy rules (starter AGENTS.md > Rules).';
	$log['todo'][] = 'Add new pages to the menu (Appearance > Menus), and run the Rank Math scores (tests/rank-math).';
	return $log;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Import a folder written by bin/scrape-site.py: pages (per map.csv), blog posts, photos,
	 * SEO fields and redirects. Safe to run again.
	 *
	 * ## OPTIONS
	 *
	 * <dir>
	 * : The export folder (on the server).
	 *
	 * [--dry-run]
	 * : Show what would happen, change nothing.
	 *
	 * [--force]
	 * : Replace the content of pages and posts that already have some.
	 *
	 * [--only=<what>]
	 * : pages or posts.
	 *
	 * [--limit=<n>]
	 * : Stop after n pages and posts (run again to continue).
	 *
	 * ## EXAMPLES
	 *
	 *     wp es import ~/export --dry-run
	 *     wp es import ~/export
	 */
	WP_CLI::add_command(
		'es import',
		function ( $args, $assoc ) {
			$log = es_import(
				$args[0],
				array(
					'dry_run' => ! empty( $assoc['dry-run'] ),
					'force'   => ! empty( $assoc['force'] ),
					'only'    => $assoc['only'] ?? '',
					'limit'   => (int) ( $assoc['limit'] ?? 0 ),
				)
			);
			if ( is_wp_error( $log ) ) {
				WP_CLI::error( $log->get_error_message() );
			}
			foreach ( $log['done'] as $line ) {
				WP_CLI::log( '  + ' . $line );
			}
			foreach ( $log['skipped'] as $line ) {
				WP_CLI::log( '  = ' . $line );
			}
			WP_CLI::log( '' );
			foreach ( $log['todo'] as $line ) {
				WP_CLI::log( 'Next: ' . $line );
			}
			empty( $assoc['dry-run'] ) ? WP_CLI::success( 'Imported.' ) : WP_CLI::success( 'Dry run: nothing changed.' );
		}
	);
}
