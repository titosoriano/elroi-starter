<?php
/**
 * A page without a designed template (e.g. a simple text page made in the block editor).
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
get_header();
the_post();
?>
<main id="main" class="es-main">
	<header class="es-hero es-hero--inner"><div class="es-container es-hero__inner"><h1 class="es-hero__title"><?php the_title(); ?></h1></div></header>
	<div class="es-container es-prose"><?php the_content(); ?></div>
</main>
<?php
get_footer();
