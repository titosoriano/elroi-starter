<?php
/**
 * Small helpers: content access that works even before the plugin is active, icons, buttons.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Content of the current page from ElRoi Site Editor (defaults filled in), or [] without the plugin.
 */
function es_content() {
	return function_exists( 'elroi_content' ) ? elroi_content() : array();
}

/**
 * One Site Setting, or '' without the plugin.
 *
 * @param string $key Setting key.
 */
function es_setting( $key ) {
	return function_exists( 'elroi_setting' ) ? elroi_setting( $key ) : '';
}

/**
 * Inline SVG icon (decorative).
 *
 * @param string $name Icon name.
 * @param int    $size Pixel size.
 */
function es_icon( $name, $size = 20 ) {
	$paths = array(
		'phone'   => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2"/>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'pin'     => '<path d="M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
		'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'check'   => '<path d="m5 12 5 5 9-10"/>',
		'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
		'chevron' => '<path d="m6 9 6 6 6-6"/>',
		'star'    => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
	);
	return sprintf(
		'<svg class="es-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
		(int) $size,
		$paths[ $name ] ?? ''
	);
}

/**
 * A button link, or '' when the label or link is empty.
 *
 * @param string $label   Label.
 * @param string $link    Site path or URL.
 * @param string $variant primary | ghost | light.
 */
function es_button( $label, $link, $variant = 'primary' ) {
	if ( '' === trim( (string) $label ) || '' === trim( (string) $link ) ) {
		return '';
	}
	return sprintf( '<a class="es-btn es-btn--%s" href="%s">%s</a>', esc_attr( $variant ), elroi_link( $link ), elroi_text( $label ) );
}

/**
 * tel: link for a phone label.
 *
 * @param string $label Phone as shown.
 */
function es_tel( $label ) {
	return function_exists( 'elroi_tel' ) ? elroi_tel( $label ) : preg_replace( '/[^\d+]/', '', (string) $label );
}

/**
 * Social links that are filled in, as [ label => url ].
 */
function es_social_links() {
	$out = array();
	foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'google' => 'Google reviews' ) as $key => $label ) {
		$url = es_setting( $key );
		if ( $url ) {
			$out[ $label ] = $url;
		}
	}
	return $out;
}

/**
 * The page's estimate target: this page's form if it has one, otherwise the contact page.
 */
function es_form_href() {
	$contact = get_pages(
		array(
			'meta_key'   => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => 'templates/contact.php', // phpcs:ignore WordPress.DB.SlowDBQuery
			'number'     => 1,
		)
	);
	if ( is_front_page() || is_page_template( array( 'templates/service.php', 'templates/contact.php' ) ) ) {
		return '#lead-form';
	}
	return $contact ? esc_url( get_permalink( $contact[0] ) . '#lead-form' ) : '#lead-form';
}

/**
 * Text with `[words](/path)` links (ElRoi Site Editor 1.1+; plain escaped text before that).
 *
 * @param mixed $text Text.
 */
function es_rich( $text ) {
	return function_exists( 'elroi_rich' ) ? elroi_rich( $text ) : ( function_exists( 'elroi_text' ) ? elroi_text( $text ) : esc_html( (string) $text ) );
}

/**
 * Plain words for structured data: placeholders filled in, highlight marks and link syntax removed.
 *
 * @param mixed $text Text.
 */
function es_plain( $text ) {
	if ( function_exists( 'elroi_plain' ) ) {
		return elroi_plain( $text );
	}
	return wp_strip_all_tags( html_entity_decode( function_exists( 'elroi_text' ) ? elroi_text( $text ) : (string) $text ) );
}
