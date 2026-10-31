<?php
/**
 * One video: a cover with a play button that loads YouTube/Vimeo only when pressed, or the
 * browser's player for MP4/WebM. Without JavaScript the cover links to the video.
 * Args: video (es_video_field() value), class (extra classes).
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$v   = (array) ( $args['video'] ?? array() );
$src = es_video_source( $v['video_url'] ?? '' );
if ( ! $src ) {
	return;
}
$title = ! empty( $v['title'] ) ? $v['title'] : __( 'Video', 'elroi-starter' );
$cover = ! empty( $v['thumb'] ) ? (int) $v['thumb'] : 0;
?>
<figure class="es-video <?php echo esc_attr( (string) ( $args['class'] ?? '' ) ); ?>">
	<?php if ( 'file' === $src['provider'] ) : ?>
		<video class="es-video__frame" controls preload="none" playsinline<?php echo $cover ? ' poster="' . esc_url( wp_get_attachment_image_url( $cover, 'large' ) ) . '"' : ''; ?>>
			<source src="<?php echo esc_url( $src['watch'] ); ?>" type="<?php echo esc_attr( $src['type'] ); ?>">
		</video>
	<?php else : ?>
		<a class="es-video__frame es-video__cover" href="<?php echo esc_url( $src['watch'] ); ?>" data-es-video="<?php echo esc_url( $src['embed'] ); ?>" data-es-video-title="<?php echo esc_attr( wp_strip_all_tags( elroi_text( $title ) ) ); ?>">
			<?php if ( $cover ) : ?>
				<?php echo elroi_image( $cover, 'large', array( 'alt' => '', 'loading' => 'lazy', 'sizes' => '(min-width: 1100px) 1040px, 100vw' ) ); // phpcs:ignore ?>
			<?php elseif ( $src['thumb'] ) : ?>
				<img src="<?php echo esc_url( $src['thumb'] ); ?>" alt="" loading="lazy" width="480" height="360">
			<?php endif; ?>
			<span class="es-video__play" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M8 5v14l11-7z"/></svg></span>
			<span class="es-sr"><?php echo esc_html( sprintf( /* translators: %s: video title */ __( 'Play video: %s', 'elroi-starter' ), wp_strip_all_tags( elroi_text( $title ) ) ) ); ?></span>
		</a>
	<?php endif; ?>
	<?php if ( ! empty( $v['title'] ) || ! empty( $v['description'] ) ) : ?>
		<figcaption class="es-video__caption">
			<?php if ( ! empty( $v['title'] ) ) : ?><strong><?php echo elroi_text( $v['title'] ); // phpcs:ignore ?></strong><?php endif; ?>
			<?php if ( ! empty( $v['description'] ) ) : ?> <span><?php echo elroi_text( $v['description'] ); // phpcs:ignore ?></span><?php endif; ?>
		</figcaption>
	<?php endif; ?>
</figure>
