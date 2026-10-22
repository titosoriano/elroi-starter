<?php
/**
 * Document head and site header.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$es_phone = es_setting( 'phone' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="es-skip" href="#main"><?php esc_html_e( 'Skip to content', 'elroi-starter' ); ?></a>

<header class="es-header" data-es-header>
	<div class="es-container es-header__bar">
		<a class="es-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php if ( has_custom_logo() ) : ?>
				<?php echo wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'medium', false, array( 'class' => 'es-brand__logo', 'alt' => es_setting( 'company' ) ) ); ?>
			<?php else : ?>
				<span class="es-brand__name"><?php echo esc_html( es_setting( 'company' ) ? es_setting( 'company' ) : get_bloginfo( 'name' ) ); ?></span>
			<?php endif; ?>
		</a>
		<nav class="es-header__nav" aria-label="<?php esc_attr_e( 'Main', 'elroi-starter' ); ?>"><?php es_render_nav(); ?></nav>
		<div class="es-header__actions">
			<?php if ( $es_phone ) : ?>
				<a class="es-header__phone" href="tel:<?php echo esc_attr( es_tel( $es_phone ) ); ?>"><?php echo es_icon( 'phone', 18 ); // phpcs:ignore ?><span><?php echo esc_html( $es_phone ); ?></span></a>
			<?php endif; ?>
			<a class="es-btn es-btn--primary es-header__cta" href="<?php echo es_form_href(); // phpcs:ignore ?>"><?php esc_html_e( 'Free estimate', 'elroi-starter' ); ?></a>
			<button class="es-header__menu" type="button" aria-expanded="false" aria-controls="es-drawer" data-es-menu><?php echo es_icon( 'menu', 24 ); // phpcs:ignore ?><span class="es-sr"><?php esc_html_e( 'Menu', 'elroi-starter' ); ?></span></button>
		</div>
	</div>
</header>

<div class="es-drawer" id="es-drawer" hidden data-es-drawer>
	<div class="es-drawer__panel">
		<button class="es-drawer__close" type="button" data-es-menu-close><?php echo es_icon( 'close', 24 ); // phpcs:ignore ?><span class="es-sr"><?php esc_html_e( 'Close menu', 'elroi-starter' ); ?></span></button>
		<nav aria-label="<?php esc_attr_e( 'Main', 'elroi-starter' ); ?>"><?php es_render_drawer(); ?></nav>
		<?php if ( $es_phone ) : ?>
			<a class="es-btn es-btn--ghost es-drawer__phone" href="tel:<?php echo esc_attr( es_tel( $es_phone ) ); ?>"><?php echo es_icon( 'phone', 18 ); // phpcs:ignore ?> <?php echo esc_html( $es_phone ); ?></a>
		<?php endif; ?>
		<a class="es-btn es-btn--primary" href="<?php echo es_form_href(); // phpcs:ignore ?>"><?php esc_html_e( 'Free estimate', 'elroi-starter' ); ?></a>
	</div>
</div>
