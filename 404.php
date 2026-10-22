<?php
/**
 * Not found.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main" class="es-main">
	<header class="es-hero es-hero--inner">
		<div class="es-container es-hero__inner">
			<p class="es-eyebrow">404</p>
			<h1 class="es-hero__title"><?php esc_html_e( 'We could not find that page.', 'elroi-starter' ); ?></h1>
			<p class="es-hero__lede"><?php esc_html_e( 'It may have moved. Start from the home page or give us a call.', 'elroi-starter' ); ?></p>
			<div class="es-hero__actions"><a class="es-btn es-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go to the home page', 'elroi-starter' ); ?></a></div>
		</div>
	</header>
</main>
<?php
get_footer();
