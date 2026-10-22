<?php
/**
 * Shown while ElRoi Site Editor is not active.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
status_header( 503 );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title><?php bloginfo( 'name' ); ?></title></head>
<body style="font-family:system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem;line-height:1.5">
	<h1><?php bloginfo( 'name' ); ?></h1>
	<p>This website is being updated. Please check back soon.</p>
	<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
		<p><strong>Administrator:</strong> activate the ElRoi Site Editor plugin (<a href="https://github.com/titosoriano/elroi-site-editor/releases/latest">download</a>).</p>
	<?php endif; ?>
</body>
</html>
