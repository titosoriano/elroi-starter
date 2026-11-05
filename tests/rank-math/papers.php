<?php
/**
 * Local Playground: the same export as `wp es seo-papers`, at /es-tests/rank-math/papers.php
 * (curl it into papers.json).
 *
 * @package ElRoiStarter
 */

require dirname( __DIR__, 2 ) . '/wp-load.php';
header( 'Content-Type: application/json' );
echo wp_json_encode( es_seo_papers(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
