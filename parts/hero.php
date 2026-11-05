<?php
/**
 * Inner-page hero: breadcrumbs, label, H1, introduction, background photo.
 * Args: hero (the hero group), form (bool: show the lead form beside it).
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$h      = (array) ( $args['hero'] ?? array() );
$crumbs = es_breadcrumbs();
?>
<header class="es-hero es-hero--inner<?php echo ! empty( $h['photo'] ) ? ' es-hero--photo' : ''; ?>">
	<?php if ( ! empty( $h['photo'] ) ) : ?>
		<div class="es-hero__media"><?php echo elroi_image( $h['photo'], 'full', array( 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => es_sizes( 'full' ) ) ); // phpcs:ignore ?></div>
	<?php endif; ?>
	<div class="es-container es-hero__inner">
		<?php if ( count( $crumbs ) > 1 ) : ?>
			<nav class="es-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'elroi-starter' ); ?>"><ol>
				<?php foreach ( $crumbs as $i => $c ) : ?>
					<li><?php if ( $i < count( $crumbs ) - 1 ) : ?><a href="<?php echo esc_url( $c[1] ); ?>"><?php echo esc_html( $c[0] ); ?></a><?php else : ?><span aria-current="page"><?php echo esc_html( $c[0] ); ?></span><?php endif; ?></li>
				<?php endforeach; ?>
			</ol></nav>
		<?php endif; ?>
		<?php if ( ! empty( $h['eyebrow'] ) ) : ?>
			<p class="es-eyebrow"><?php echo elroi_text( $h['eyebrow'] ); // phpcs:ignore ?></p>
		<?php endif; ?>
		<h1 class="es-hero__title"><?php echo elroi_heading( ! empty( $h['h1'] ) ? $h['h1'] : es_page_title() ); // phpcs:ignore ?></h1>
		<?php if ( ! empty( $h['lede'] ) ) : ?>
			<p class="es-hero__lede"><?php echo elroi_text( $h['lede'] ); // phpcs:ignore ?></p>
		<?php endif; ?>
		<?php if ( is_page() && apply_filters( 'es_show_updated', true ) ) : ?>
			<p class="es-hero__updated"><?php esc_html_e( 'Updated', 'elroi-starter' ); ?> <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( get_the_modified_date() ); ?></time></p>
		<?php endif; ?>
	</div>
</header>
