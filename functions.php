<?php
/**
 * ElRoi Starter: theme bootstrap.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

define( 'ES_VERSION', '1.5.0' );
define( 'ES_DIR', get_template_directory() );
define( 'ES_URI', get_template_directory_uri() );
// In a client child theme these point at the child; on the starter itself they equal ES_DIR / ES_URI.
define( 'ES_CHILD_DIR', get_stylesheet_directory() );
define( 'ES_CHILD_URI', get_stylesheet_directory_uri() );

require_once ES_DIR . '/inc/setup.php';
require_once ES_DIR . '/inc/helpers.php';
require_once ES_DIR . '/inc/fields.php';
require_once ES_DIR . '/inc/nav.php';
require_once ES_DIR . '/inc/lead-form.php';
require_once ES_DIR . '/inc/form-connect.php';
require_once ES_DIR . '/inc/video.php';
require_once ES_DIR . '/inc/toc.php';
require_once ES_DIR . '/inc/images.php';
require_once ES_DIR . '/inc/seo.php';
require_once ES_DIR . '/inc/motion.php';
require_once ES_DIR . '/inc/speed.php';
require_once ES_DIR . '/inc/backups.php';
require_once ES_DIR . '/inc/updater.php';
