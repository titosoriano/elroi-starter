<?php
/**
 * Videos: an editable video field, a fast player, and VideoObject structured data so Google can
 * show the video in its video results.
 *
 * Register a video with `es_video_field()` (or a repeater of `es_video_fields()`), print it with
 * get_template_part( 'parts/video', null, array( 'video' => $c['video'] ) ). Every value that has
 * a `video_url` anywhere in the page's fields becomes a VideoObject (seo.php), so the markup
 * follows the content with nothing else to wire.
 *
 * YouTube and Vimeo links show their thumbnail and load the player only when the visitor
 * presses play (fast pages, no third-party cookies before that). MP4/WebM links (for example a
 * file in the Media Library) play in the browser's own player.
 *
 * Google needs a title, a thumbnail and an upload date: the thumbnail comes from the field, or
 * from YouTube; the date from the field, or the page's publish date. A Vimeo video without a
 * thumbnail photo gets no structured data.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fields of one video.
 */
function es_video_fields() {
	return array(
		'title'       => array( 'label' => 'Video title', 'half' => true ),
		'video_url'   => array( 'type' => 'url', 'label' => 'Video link', 'half' => true, 'help' => 'A YouTube or Vimeo link, or the link of an MP4 file from the Media Library.' ),
		'description' => array( 'type' => 'textarea', 'label' => 'What the video shows', 'help' => 'One or two sentences. Google shows it with the video.' ),
		'thumb'       => array( 'type' => 'image', 'label' => 'Cover photo', 'help' => 'Optional for YouTube (its own cover is used). Needed for Vimeo and MP4 videos.' ),
		'date'        => array( 'label' => 'Date published (YYYY-MM-DD)', 'half' => true, 'help' => 'Optional. Empty: the page\'s publish date.' ),
	);
}

/**
 * A video as one group field.
 *
 * @param string $label Label in the form.
 */
function es_video_field( $label = 'Video (optional)' ) {
	return array(
		'type'   => 'group',
		'label'  => $label,
		'fields' => es_video_fields(),
	);
}

/**
 * What a video link points to: provider, id, embed URL, watch URL, thumbnail; or null.
 *
 * @param string $url Link.
 */
function es_video_source( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return null;
	}
	if ( preg_match( '#(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})#i', $url, $m ) ) {
		return array(
			'provider' => 'youtube',
			'id'       => $m[1],
			'embed'    => 'https://www.youtube-nocookie.com/embed/' . $m[1],
			'watch'    => 'https://www.youtube.com/watch?v=' . $m[1],
			'thumb'    => 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg',
		);
	}
	if ( preg_match( '#vimeo\.com/(?:video/)?(\d+)#i', $url, $m ) ) {
		return array(
			'provider' => 'vimeo',
			'id'       => $m[1],
			'embed'    => 'https://player.vimeo.com/video/' . $m[1],
			'watch'    => 'https://vimeo.com/' . $m[1],
			'thumb'    => '',
		);
	}
	if ( preg_match( '#\.(mp4|webm|mov|m4v)(\?.*)?$#i', $url, $m ) ) {
		return array(
			'provider' => 'file',
			'id'       => '',
			'embed'    => '',
			'watch'    => $url,
			'thumb'    => '',
			'type'     => 'video/' . ( 'mov' === strtolower( $m[1] ) ? 'quicktime' : ( 'm4v' === strtolower( $m[1] ) ? 'mp4' : strtolower( $m[1] ) ) ),
		);
	}
	return null;
}

/**
 * Every video in a page's fields (any value with a video_url), in order.
 *
 * @param int|WP_Post|null $post Page.
 */
function es_page_videos( $post = null ) {
	$post = get_post( $post );
	if ( ! $post || ! function_exists( 'elroi_content' ) ) {
		return array();
	}
	$found = array();
	$walk  = function ( $v ) use ( &$walk, &$found ) {
		if ( ! is_array( $v ) ) {
			return;
		}
		if ( ! empty( $v['video_url'] ) && is_string( $v['video_url'] ) ) {
			$found[] = $v;
			return;
		}
		foreach ( $v as $x ) {
			$walk( $x );
		}
	};
	$walk( elroi_content( $post ) );
	return $found;
}

/**
 * VideoObject for one video value, or null when Google's required details are missing.
 *
 * @param array   $v    Video value (title, video_url, description, thumb, date).
 * @param WP_Post $post Page it is on.
 * @param int     $n    Position on the page.
 */
function es_video_schema( $v, $post, $n = 1 ) {
	$src  = es_video_source( $v['video_url'] ?? '' );
	$name = es_plain( $v['title'] ?? '' );
	if ( ! $src || '' === $name ) {
		return null;
	}
	$thumb = ! empty( $v['thumb'] ) ? wp_get_attachment_image_url( (int) $v['thumb'], 'full' ) : $src['thumb'];
	if ( ! $thumb ) {
		return null;
	}
	$date = '';
	if ( ! empty( $v['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( $v['date'] ) ) ) {
		$d    = date_create_immutable( trim( $v['date'] ) . ' 12:00:00', wp_timezone() );
		$date = $d ? $d->format( 'c' ) : '';
	}
	$node = array(
		'@type'        => 'VideoObject',
		'@id'          => get_permalink( $post ) . '#video-' . (int) $n,
		'name'         => $name,
		'description'  => ! empty( $v['description'] ) ? es_plain( $v['description'] ) : $name,
		'thumbnailUrl' => array( $thumb ),
		'uploadDate'   => $date ? $date : get_post_time( 'c', false, $post ),
	);
	if ( 'file' === $src['provider'] ) {
		$node['contentUrl'] = $src['watch'];
	} else {
		$node['embedUrl'] = $src['embed'];
		$node['url']      = $src['watch'];
	}
	return $node;
}

/**
 * VideoObjects of the current page.
 */
function es_videos_schema() {
	if ( ! is_singular( 'page' ) ) {
		return array();
	}
	$post  = get_post();
	$nodes = array();
	foreach ( es_page_videos( $post ) as $i => $v ) {
		$node = es_video_schema( $v, $post, $i + 1 );
		if ( $node ) {
			$nodes[] = $node;
		}
	}
	return $nodes;
}
