<?php
/**
 * One-command setup of a fresh WordPress for an ElRoi site:
 *
 *   wp es setup --name="Acme Roofing" --services="Roofing, Siding, Gutters"
 *
 * It does what used to take an hour of clicking, and is safe to run again (it only adds what is
 * missing and never overwrites a page, menu or setting someone has changed):
 * - pages with their templates: Home (front page), Services hub, one Service page per service,
 *   About, Contact, Blog (posts page), and with --areas a Service Areas hub with its regions and
 *   towns (templates/area.php);
 * - Settings > Reading (home and posts page), permalinks /%postname%/, the timezone, comments and
 *   pingbacks off for new posts, the site title, and the company name in Site Settings;
 * - the Main menu (Services with each service under it, About, Blog, Contact; Service Areas with
 *   regions when there are areas) in both menu locations;
 * - WordPress's sample content removed while it is still untouched ("Hello world!", "Sample Page",
 *   the sample comment, Hello Dolly and an unused Akismet); the draft "Privacy Policy" page is kept
 *   and set as the privacy page (write it before launch: the form links it);
 * - search engines asked not to index the site (`blog_public` 0) while it is on a staging
 *   address; pass --index to leave indexing on. Launch means turning it back on.
 * - from WP-CLI only: installs and activates ElRoi Site Editor (latest release) and the
 *   recommended plugins from wordpress.org, unless --no-plugins.
 *
 * Agents without a shell run it through the site's MCP (`novamira/run-wp-cli` with
 * ["es","setup","--name=...","--services=..."]) or call es_site_setup() in PHP. --dry-run shows
 * the plan without changing anything.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Split "A, B, C" (or an array) into clean names.
 *
 * @param string|array $list Names.
 * @return string[]
 */
function es_setup_list( $list ) {
	$items = is_array( $list ) ? $list : preg_split( '/\s*,\s*/', (string) $list );
	return array_values( array_filter( array_map( 'trim', array_map( 'strval', $items ) ), 'strlen' ) );
}

/**
 * Parse --areas: "Bergen County: Fair Lawn, Teaneck | Rockland County: Nyack" into
 * [ 'Bergen County' => [ 'Fair Lawn', 'Teaneck' ], 'Rockland County' => [ 'Nyack' ] ].
 *
 * @param string|array $areas Areas.
 * @return array<string, string[]>
 */
function es_setup_areas( $areas ) {
	if ( is_array( $areas ) ) {
		return $areas;
	}
	$out = array();
	foreach ( array_filter( array_map( 'trim', explode( '|', (string) $areas ) ) ) as $group ) {
		$parts  = array_map( 'trim', explode( ':', $group, 2 ) );
		$region = $parts[0];
		if ( '' !== $region ) {
			$out[ $region ] = es_setup_list( $parts[1] ?? '' );
		}
	}
	return $out;
}

/**
 * Set up the site. Options: name, services (list), areas (see es_setup_areas), services_at_root
 * (service pages at /<slug>/ instead of /services/<slug>/, to keep an old site's URLs), timezone
 * (default America/New_York when WordPress is still on UTC), index (bool, default false), dry_run.
 *
 * @param array $o Options.
 * @return array{done: string[], skipped: string[], todo: string[]}
 */
function es_site_setup( $o = array() ) {
	$o   = wp_parse_args(
		$o,
		array(
			'name'             => '',
			'services'         => array(),
			'areas'            => array(),
			'services_at_root' => false,
			'timezone'         => 'America/New_York',
			'index'            => false,
			'dry_run'          => false,
		)
	);
	$dry = (bool) $o['dry_run'];
	$log = array( 'done' => array(), 'skipped' => array(), 'todo' => array() );
	$say = function ( $kind, $line ) use ( &$log, $dry ) {
		$log[ $kind ][] = ( $dry && 'done' === $kind ? '(dry run) ' : '' ) . $line;
	};
	$ids = array();

	// Pages. Found by path, so a page someone already made (or renamed) is reused, never doubled.
	$paths = array();
	$page  = function ( $key, $title, $slug, $template = '', $parent = 0, $parent_key = '' ) use ( &$ids, &$paths, $dry, $say ) {
		$base  = $parent ? trim( (string) wp_make_link_relative( get_permalink( $parent ) ), '/' ) : ( $paths[ $parent_key ] ?? '' );
		$path  = ( '' !== $base ? $base . '/' : '' ) . $slug;
		$paths[ $key ] = $path;
		$found = get_page_by_path( $path );
		if ( $found ) {
			$ids[ $key ] = $found->ID;
			$say( 'skipped', "Page \"{$found->post_title}\" (/{$path}/) already exists." );
			return $found->ID;
		}
		if ( $dry ) {
			$ids[ $key ] = 0;
			$say( 'done', "Create page \"$title\" (/$path/)" . ( $template ? " with $template" : '' ) . '.' );
			return 0;
		}
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_parent' => (int) $parent ) );
		if ( $template ) {
			update_post_meta( $id, '_wp_page_template', $template );
		}
		$ids[ $key ] = $id;
		$say( 'done', "Created page \"$title\" (/" . trim( (string) wp_make_link_relative( get_permalink( $id ) ), '/' ) . '/)' . ( $template ? " with $template" : '' ) . '.' );
		return $id;
	};

	// Permalinks first, so the paths above and the menu use the final URLs.
	if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		if ( ! $dry ) {
			global $wp_rewrite;
			$wp_rewrite->set_permalink_structure( '/%postname%/' );
			flush_rewrite_rules( false );
		}
		$say( 'done', 'Set permalinks to /%postname%/.' );
	}

	$home     = $page( 'home', 'Home', 'home' );
	$services = $page( 'services', 'Services', 'services', 'templates/page.php' );
	$svc_ids  = array();
	foreach ( es_setup_list( $o['services'] ) as $name ) {
		$svc_ids[] = $page( 'svc-' . sanitize_title( $name ), $name, sanitize_title( $name ), 'templates/service.php', $o['services_at_root'] ? 0 : $services, $o['services_at_root'] ? '' : 'services' );
	}
	$page( 'about', 'About', 'about', 'templates/page.php' );
	$page( 'contact', 'Contact', 'contact', 'templates/contact.php' );
	$blog = $page( 'blog', 'Blog', 'blog' );

	$regions = array();
	$areas   = es_setup_areas( $o['areas'] );
	if ( $areas ) {
		$hub = $page( 'areas', 'Service Areas', 'service-areas', 'templates/area.php' );
		foreach ( $areas as $region => $towns ) {
			$rkey      = 'region-' . sanitize_title( $region );
			$rid       = $page( $rkey, $region, sanitize_title( $region ), 'templates/area.php', $hub, 'areas' );
			$regions[] = $rid;
			foreach ( $towns as $town ) {
				$page( 'town-' . sanitize_title( $region . '-' . $town ), $town, sanitize_title( $town ), 'templates/area.php', $rid, $rkey );
			}
		}
	}

	// Reading.
	if ( 'page' !== get_option( 'show_on_front' ) || ! get_option( 'page_on_front' ) ) {
		if ( ! $dry ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home );
			update_option( 'page_for_posts', $blog );
		}
		$say( 'done', 'Set Home as the front page and Blog as the posts page.' );
	} else {
		$say( 'skipped', 'A front page is already set (Settings > Reading).' );
	}

	// General settings.
	if ( '' !== $o['name'] && in_array( get_option( 'blogname' ), array( '', 'My WordPress Website', 'My WordPress Site', 'My Blog' ), true ) ) {
		$dry || update_option( 'blogname', $o['name'] );
		$say( 'done', 'Set the site title to "' . $o['name'] . '".' );
	}
	if ( '' !== $o['name'] ) {
		$set = (array) get_option( 'elroi_settings', array() );
		if ( empty( $set['company'] ) ) {
			$dry || update_option( 'elroi_settings', array_merge( $set, array( 'company' => $o['name'] ) ) );
			$say( 'done', 'Set the company name in Site Settings.' );
		}
	}
	if ( $o['timezone'] && '' === (string) get_option( 'timezone_string' ) && 0.0 === (float) get_option( 'gmt_offset' ) ) {
		$dry || update_option( 'timezone_string', $o['timezone'] );
		$say( 'done', 'Set the timezone to ' . $o['timezone'] . '.' );
	}
	if ( 'closed' !== get_option( 'default_comment_status' ) || 'closed' !== get_option( 'default_ping_status' ) ) {
		if ( ! $dry ) {
			update_option( 'default_comment_status', 'closed' );
			update_option( 'default_ping_status', 'closed' );
		}
		$say( 'done', 'Turned comments and pingbacks off for new posts.' );
	}
	if ( ! $o['index'] && '1' === (string) get_option( 'blog_public' ) ) {
		$dry || update_option( 'blog_public', '0' );
		$say( 'done', 'Asked search engines not to index the site while it is being built.' );
		$log['todo'][] = 'At launch: Settings > Reading > untick "Discourage search engines" (or wp option update blog_public 1).';
	}

	// WordPress's sample content, only while untouched.
	foreach ( array( array( 'hello-world', 'post' ), array( 'sample-page', 'page' ) ) as $sample ) {
		$p = get_page_by_path( $sample[0], OBJECT, $sample[1] );
		if ( $p && $p->post_modified_gmt === $p->post_date_gmt ) {
			$dry || wp_delete_post( $p->ID, true );
			$say( 'done', "Deleted WordPress's sample {$sample[1]} \"{$p->post_title}\"." );
		}
	}
	$comment = get_comment( 1 );
	if ( $comment && 'https://wordpress.org/' === $comment->comment_author_url ) {
		$dry || wp_delete_comment( 1, true );
		$say( 'done', "Deleted WordPress's sample comment." );
	}
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	foreach ( array( 'hello.php', 'hello-dolly/hello.php', 'akismet/akismet.php' ) as $file ) {
		if ( isset( get_plugins()[ $file ] ) && ! is_plugin_active( $file ) ) {
			if ( ! $dry ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				delete_plugins( array( $file ) );
			}
			$say( 'done', 'Deleted the unused plugin ' . get_plugins()[ $file ]['Name'] . '.' );
		}
	}

	// Privacy page: WordPress's draft, set as the privacy page.
	$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( ! $privacy || ! get_post( $privacy ) ) {
		$draft = get_page_by_path( 'privacy-policy' );
		if ( $draft ) {
			$dry || update_option( 'wp_page_for_privacy_policy', $draft->ID );
			$say( 'done', 'Set "' . $draft->post_title . '" as the privacy policy page.' );
			$privacy = $draft->ID;
		}
	}
	if ( ! $privacy || 'publish' !== get_post_status( $privacy ) ) {
		$log['todo'][] = 'Write and publish the Privacy Policy page before launch (the contact form links it).';
	}

	// Main menu.
	if ( wp_get_nav_menu_object( 'Main' ) || ! empty( get_nav_menu_locations()['primary'] ) ) {
		$say( 'skipped', 'A main menu already exists (Appearance > Menus).' );
	} elseif ( $dry ) {
		$say( 'done', 'Create the Main menu: Services (with each service), ' . ( $areas ? 'Service Areas (with each region), ' : '' ) . 'About, Blog, Contact' );
	} else {
		$menu = wp_create_nav_menu( 'Main' );
		$item = function ( $page_id, $parent = 0 ) use ( $menu ) {
			return wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $page_id, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-parent-id' => $parent, 'menu-item-status' => 'publish' ) );
		};
		$top = $item( $services );
		foreach ( $svc_ids as $sid ) {
			$item( $sid, $top );
		}
		if ( $areas ) {
			$atop = $item( $ids['areas'] );
			foreach ( $regions as $rid ) {
				$item( $rid, $atop );
			}
		}
		foreach ( array( 'about', 'blog', 'contact' ) as $key ) {
			$item( $ids[ $key ] );
		}
		set_theme_mod( 'nav_menu_locations', array_merge( (array) get_nav_menu_locations(), array( 'primary' => $menu, 'footer' => $menu ) ) );
		$say( 'done', 'Created the Main menu in the header and footer.' );
	}

	$log['todo'][] = 'Fill in Site Settings (Business, Contact, Numbers, Contact form, Social) and each page\'s form.';
	if ( function_exists( 'elroi_recommended_missing' ) && elroi_recommended_missing() ) {
		$log['todo'][] = 'Missing recommended plugins: ' . implode( ', ', array_keys( elroi_recommended_missing() ) ) . ' (Plugins > ElRoi recommended).';
	}
	$log['todo'][] = 'Security: two-factor on every administrator, Kadence Security set up (starter AGENTS.md > Security), UpdraftPlus schedule and remote storage.';
	return $log;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Set up a fresh WordPress for an ElRoi site: pages with templates, reading settings,
	 * permalinks, menu, sample content removed, recommended plugins. Safe to run again.
	 *
	 * ## OPTIONS
	 *
	 * [--name=<name>]
	 * : The business name (site title and Site Settings > Company).
	 *
	 * [--services=<list>]
	 * : Service pages to create, comma-separated: "Roofing, Siding, Gutters".
	 *
	 * [--areas=<list>]
	 * : Service area pages: "Bergen County: Fair Lawn, Teaneck | Rockland County: Nyack".
	 *
	 * [--services-at-root]
	 * : Put service pages at /<slug>/ (keeps an old site's URLs) instead of /services/<slug>/.
	 *
	 * [--timezone=<zone>]
	 * : Timezone when WordPress is still on UTC. Default America/New_York.
	 *
	 * [--index]
	 * : Leave search engine indexing on (default: off while the site is being built).
	 *
	 * [--no-plugins]
	 * : Do not install ElRoi Site Editor and the recommended plugins.
	 *
	 * [--dry-run]
	 * : Show what would change, change nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp es setup --name="Acme Roofing" --services="Roofing, Siding, Gutters"
	 *     wp es setup --name="Acme" --services="Roofing" --areas="Bergen County: Fair Lawn, Teaneck" --dry-run
	 */
	WP_CLI::add_command(
		'es setup',
		function ( $args, $assoc ) {
			$dry     = ! empty( $assoc['dry-run'] );
			$plugins = ! $dry && ! ( isset( $assoc['plugins'] ) && false === $assoc['plugins'] );
			if ( $plugins && ! function_exists( 'elroi_register_template' ) ) {
				WP_CLI::log( 'Installing ElRoi Site Editor (latest release)...' );
				WP_CLI::runcommand( 'plugin install https://github.com/titosoriano/elroi-site-editor/releases/latest/download/elroi-site-editor.zip --activate', array( 'exit_error' => false ) );
			}
			$log = es_site_setup(
				array(
					'name'             => $assoc['name'] ?? '',
					'services'         => $assoc['services'] ?? '',
					'areas'            => $assoc['areas'] ?? '',
					'services_at_root' => ! empty( $assoc['services-at-root'] ),
					'timezone'         => $assoc['timezone'] ?? 'America/New_York',
					'index'            => ! empty( $assoc['index'] ),
					'dry_run'          => $dry,
				)
			);
			if ( $plugins ) {
				// Ask a fresh WordPress process: the plugin may have been installed a moment ago.
				$tmp = wp_tempnam( 'es-setup' ) . '.php';
				file_put_contents( $tmp, '<?php echo function_exists( "elroi_recommended_missing" ) ? wp_json_encode( elroi_recommended_missing() ) : "{}";' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$r = WP_CLI::runcommand( 'eval-file ' . escapeshellarg( $tmp ), array( 'exit_error' => false, 'return' => 'all' ) );
				wp_delete_file( $tmp );
				// Take the JSON line only: some PHP versions print notices into the output.
				$json    = preg_match( '/^[\[{].*$/m', (string) $r->stdout, $m ) ? $m[0] : '';
				$missing = (array) json_decode( $json, true );
				if ( ! $missing && 0 !== $r->return_code ) {
					$log['todo'][] = 'Could not read the recommended plugins: ' . trim( $r->stderr ) . ' Check Plugins > ElRoi recommended.';
				}
				foreach ( $missing as $p ) {
					if ( '' === ( $p['cli'] ?? '' ) ) {
						$log['todo'][] = $p['name'] . ': install it from ' . $p['source'] . '.';
						continue;
					}
					WP_CLI::log( 'Running: ' . $p['cli'] );
					$r = WP_CLI::runcommand( substr( $p['cli'], 3 ), array( 'exit_error' => false, 'return' => 'all' ) );
					if ( 0 === $r->return_code ) {
						$log['done'][] = 'Installed and activated ' . $p['name'] . '. Setup: ' . $p['setup'];
					} else {
						$log['todo'][] = 'Could not install ' . $p['name'] . ': ' . trim( $r->stderr );
					}
				}
				$log['todo'] = array_values( array_filter( $log['todo'], fn( $t ) => ! str_starts_with( $t, 'Missing recommended plugins' ) ) );
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
			$dry ? WP_CLI::success( 'Dry run: nothing changed.' ) : WP_CLI::success( 'Site set up.' );
		}
	);
}
