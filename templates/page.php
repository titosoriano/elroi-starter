<?php
/**
 * Template Name: General page
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$c = es_content();
get_header();
?>
<main id="main" class="es-main">
	<?php get_template_part( 'parts/hero', null, array( 'hero' => $c['hero'] ) ); ?>
	<?php get_template_part( 'parts/sections', null, array( 'sections' => $c['sections'] ) ); ?>
	<?php get_template_part( 'parts/faq', null, array( 'faqs' => $c['faqs'] ) ); ?>
</main>
<?php
get_footer();
