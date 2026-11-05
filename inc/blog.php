<?php
/**
 * Blog: page sizes that fill whole rows, reading time, related posts, posts credited to the
 * business.
 *
 * - The post grid has 3 columns on desktop, so every page holds whole rows: blog page 1 shows the
 *   newest post as a feature plus 9 cards (10 posts), every later page and every category, tag or
 *   date archive 12. Later blog pages use an offset, and the page count is corrected, so the last
 *   page is never empty and no post sits alone on a page of its own (Nuvista's blog had one post
 *   alone on page 3 before this). Settings > Reading's "Blog pages show at most" no longer
 *   applies to these views. Filter `es_blog_page_sizes` ( array( 'first' => 10, 'per' => 12 ) ;
 *   return false to use Settings > Reading again). With 'first' equal to 'per', no feature.
 * - Posts show their reading time and, when changed on a later day, the date they were updated;
 *   "Keep reading" lists posts from the same category first.
 * - Rank Math names the WordPress user as each post's author (often "admin"); posts are credited
 *   to the business instead (filter `es_post_author_business`, false keeps the user).
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Posts on the first blog page and on every other page, or null to use Settings > Reading.
 *
 * @return array{first:int, per:int}|null
 */
function es_blog_page_sizes() {
	$sizes = apply_filters( 'es_blog_page_sizes', array( 'first' => 10, 'per' => 12 ) );
	if ( ! is_array( $sizes ) || empty( $sizes['per'] ) ) {
		return null;
	}
	$per = max( 1, (int) $sizes['per'] );
	return array( 'first' => max( 1, (int) ( $sizes['first'] ?? $per ) ), 'per' => $per );
}

/** Whether the current blog page opens with the newest post as a feature. */
function es_blog_has_feature() {
	$sizes = es_blog_page_sizes();
	return $sizes && $sizes['first'] !== $sizes['per'] && is_home() && ! is_paged();
}

add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() || ! $q->is_main_query() || $q->is_search() || $q->is_feed() || ! ( $q->is_home() || $q->is_archive() ) ) {
			return;
		}
		$sizes = es_blog_page_sizes();
		if ( ! $sizes ) {
			return;
		}
		$paged = max( 1, (int) $q->get( 'paged' ) );
		if ( $q->is_home() ) {
			$q->set( 'posts_per_page', 1 === $paged ? $sizes['first'] : $sizes['per'] );
			if ( $paged > 1 ) {
				$q->set( 'offset', $sizes['first'] + ( $paged - 2 ) * $sizes['per'] );
			}
		} else {
			$q->set( 'posts_per_page', $sizes['per'] );
		}
	}
);

/**
 * Number of blog pages for a post count: page 1 is shorter when it holds the feature, which
 * WordPress's own found / per-page count gets wrong.
 *
 * @param int $found Posts found.
 */
function es_blog_page_count( $found ) {
	$sizes = es_blog_page_sizes();
	if ( ! $sizes ) {
		return 0;
	}
	return $found <= $sizes['first'] ? 1 : 1 + (int) ceil( ( $found - $sizes['first'] ) / $sizes['per'] );
}

// With an offset, WordPress still counts every post (found_posts) but divides by the page size
// alone; set the real page count.
add_action(
	'wp',
	function () {
		global $wp_query;
		if ( ! is_admin() && $wp_query->is_main_query() && $wp_query->is_home() && es_blog_page_sizes() ) {
			$wp_query->max_num_pages = es_blog_page_count( (int) $wp_query->found_posts );
		}
	}
);

/**
 * Estimated reading time in minutes (about 230 words a minute).
 *
 * @param int|WP_Post|null $post Post.
 */
function es_reading_time( $post = null ) {
	$words = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $post ) ) );
	return max( 1, (int) round( $words / 230 ) );
}

/**
 * Whether the post was changed on a later day than it was published.
 *
 * @param int|WP_Post|null $post Post.
 */
function es_was_updated( $post = null ) {
	return get_the_modified_date( 'Ymd', $post ) > get_the_date( 'Ymd', $post );
}

/**
 * Posts to read next: the same category first, then the newest others.
 *
 * @param int|WP_Post|null $post  Current post.
 * @param int              $count How many.
 * @return WP_Post[]
 */
function es_related_posts( $post = null, $count = 3 ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array();
	}
	$cats    = wp_get_post_categories( $post->ID, array( 'exclude' => (int) get_option( 'default_category' ) ) );
	$related = $cats ? get_posts( array( 'category__in' => $cats, 'post__not_in' => array( $post->ID ), 'numberposts' => $count ) ) : array();
	if ( count( $related ) < $count ) {
		$related = array_merge(
			$related,
			get_posts( array( 'post__not_in' => array_merge( array( $post->ID ), wp_list_pluck( $related, 'ID' ) ), 'numberposts' => $count - count( $related ) ) )
		);
	}
	return (array) apply_filters( 'es_related_posts', $related, $post, $count );
}

/**
 * One post card (blog grid, archives, "Keep reading").
 *
 * @param WP_Post $post    Post.
 * @param string  $heading Heading tag for the title.
 * @param bool    $feature The large first card of the blog.
 */
function es_post_card( $post, $heading = 'h2', $feature = false ) {
	$heading = in_array( $heading, array( 'h2', 'h3' ), true ) ? $heading : 'h2';
	printf( '<li class="es-card%s">', $feature ? ' es-card--feature' : '' );
	if ( has_post_thumbnail( $post ) ) {
		echo get_the_post_thumbnail(
			$post,
			$feature ? 'large' : 'medium_large',
			array(
				'class'   => 'es-card__img',
				'loading' => $feature ? 'eager' : 'lazy',
				'sizes'   => $feature ? es_sizes( 'half' ) : es_sizes( 'card' ),
			) + ( $feature ? array( 'fetchpriority' => 'high' ) : array() )
		);
	}
	echo '<div class="es-card__body">';
	printf(
		'<p class="es-eyebrow"><time datetime="%s">%s</time> · %s</p>',
		esc_attr( get_the_date( 'c', $post ) ),
		esc_html( get_the_date( '', $post ) ),
		/* translators: %d: minutes. */
		esc_html( sprintf( _n( '%d min read', '%d min read', es_reading_time( $post ), 'elroi-starter' ), es_reading_time( $post ) ) )
	);
	printf( '<%1$s class="es-card__title"><a href="%2$s">%3$s</a></%1$s>', $heading, esc_url( get_permalink( $post ) ), esc_html( wp_strip_all_tags( get_the_title( $post ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- tag from the allow-list above.
	printf( '<p>%s</p>', esc_html( get_the_excerpt( $post ) ) );
	echo '</div></li>';
}

add_filter(
	'rank_math/json_ld',
	function ( $data ) {
		if ( ! is_singular( 'post' ) || ! is_array( $data ) || ! apply_filters( 'es_post_author_business', true ) ) {
			return $data;
		}
		$org = array( '@id' => home_url( '/#organization' ) );
		foreach ( $data as $key => $entity ) {
			if ( is_array( $entity ) && 'Person' === ( $entity['@type'] ?? '' ) ) {
				unset( $data[ $key ] );
			}
		}
		foreach ( $data as $key => $entity ) {
			if ( is_array( $entity ) && isset( $entity['author'] ) ) {
				$data[ $key ]['author'] = $org;
			}
		}
		return $data;
	},
	99
);
