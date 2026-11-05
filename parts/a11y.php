<?php
/**
 * Accessibility preferences panel (opt in: add_theme_support( 'es-a11y-panel' ); inc/a11y.php).
 *
 * A small convenience on top of a site built to WCAG 2.2 AA, not a compliance tool and not an
 * overlay: nothing is loaded from another company and nothing rewrites the page. Settings stay
 * in this browser (localStorage "es-a11y") and become classes on <html> (assets/js/a11y.js).
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

// Profiles switch on a set of the options below. Keys match the prefs in assets/js/a11y.js.
$es_a11y_profiles = array(
	'seizure'   => array( __( 'Seizure safe', 'elroi-starter' ), __( 'Stops motion and softens colors', 'elroi-starter' ), array( 'motion' => true, 'color' => 'lsat' ) ),
	'vision'    => array( __( 'Low vision', 'elroi-starter' ), __( 'Larger text, more contrast, big cursor', 'elroi-starter' ), array( 'text' => '150', 'color' => 'contrast', 'readable' => true, 'focus' => true, 'cursor' => true ) ),
	'adhd'      => array( __( 'ADHD friendly', 'elroi-starter' ), __( 'Stops motion, adds a reading guide', 'elroi-starter' ), array( 'motion' => true, 'guide' => true ) ),
	'cognitive' => array( __( 'Cognitive and learning', 'elroi-starter' ), __( 'Clear font, marked headings and links', 'elroi-starter' ), array( 'readable' => true, 'titles' => true, 'links' => true, 'guide' => true ) ),
	'motor'     => array( __( 'Motor impaired', 'elroi-starter' ), __( 'Strong focus, big cursor, no motion', 'elroi-starter' ), array( 'focus' => true, 'cursor' => true, 'motion' => true ) ),
);

$es_a11y_text = array(
	'readable'   => array( __( 'Readable font', 'elroi-starter' ), __( 'Plain sans-serif everywhere', 'elroi-starter' ) ),
	'dyslexic'   => array( __( 'Dyslexia font', 'elroi-starter' ), __( 'OpenDyslexic letter shapes', 'elroi-starter' ) ),
	'bold'       => array( __( 'Bold text', 'elroi-starter' ), __( 'Heavier body text', 'elroi-starter' ) ),
	'lineheight' => array( __( 'Line height', 'elroi-starter' ), __( 'More space between lines', 'elroi-starter' ) ),
	'spacing'    => array( __( 'Letter spacing', 'elroi-starter' ), __( 'More space between letters and words', 'elroi-starter' ) ),
	'titles'     => array( __( 'Highlight headings', 'elroi-starter' ), __( 'Outline every heading', 'elroi-starter' ) ),
	'links'      => array( __( 'Highlight links', 'elroi-starter' ), __( 'Underline and mark every link', 'elroi-starter' ) ),
);

$es_a11y_colors = array(
	'dark'     => __( 'Dark contrast', 'elroi-starter' ),
	'light'    => __( 'Light contrast', 'elroi-starter' ),
	'contrast' => __( 'High contrast', 'elroi-starter' ),
	'hsat'     => __( 'High saturation', 'elroi-starter' ),
	'lsat'     => __( 'Low saturation', 'elroi-starter' ),
	'mono'     => __( 'Monochrome', 'elroi-starter' ),
	'protan'   => __( 'Protanopia (red)', 'elroi-starter' ),
	'deutan'   => __( 'Deuteranopia (green)', 'elroi-starter' ),
	'tritan'   => __( 'Tritanopia (blue)', 'elroi-starter' ),
);

$es_a11y_tools = array(
	'guide'     => array( __( 'Reading guide', 'elroi-starter' ), __( 'A bar that follows the pointer', 'elroi-starter' ) ),
	'magnifier' => array( __( 'Text magnifier', 'elroi-starter' ), __( 'Enlarge the text under the pointer', 'elroi-starter' ) ),
	'speak'     => array( __( 'Read aloud', 'elroi-starter' ), __( 'Speak text you click or tab to', 'elroi-starter' ) ),
	'tooltips'  => array( __( 'Image descriptions', 'elroi-starter' ), __( 'Show alt text on hover', 'elroi-starter' ) ),
	'noimg'     => array( __( 'Hide images', 'elroi-starter' ), __( 'Remove photos and video', 'elroi-starter' ) ),
	'cursor'    => array( __( 'Big cursor', 'elroi-starter' ), __( 'A larger mouse pointer', 'elroi-starter' ) ),
	'motion'    => array( __( 'Stop animations', 'elroi-starter' ), __( 'Pause moving content and video', 'elroi-starter' ) ),
	'focus'     => array( __( 'Strong focus outline', 'elroi-starter' ), __( 'Thicker outline on the selected item', 'elroi-starter' ) ),
);

$es_a11y_tile = function ( $key, $name, $desc ) {
	?>
	<li>
		<button class="es-a11y__tile" type="button" aria-pressed="false" data-es-a11y-opt="<?php echo esc_attr( $key ); ?>">
			<span class="es-a11y__opt-name"><?php echo esc_html( $name ); ?></span>
			<span class="es-a11y__opt-desc"><?php echo esc_html( $desc ); ?></span>
		</button>
	</li>
	<?php
};
?>
<div class="es-a11y" data-es-a11y>
	<button class="es-a11y__toggle" type="button" aria-expanded="false" aria-controls="es-a11y-panel" data-es-a11y-toggle>
		<?php echo es_icon( 'access', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<span class="es-a11y__sr"><?php esc_html_e( 'Accessibility options', 'elroi-starter' ); ?></span>
	</button>
	<div class="es-a11y__panel" id="es-a11y-panel" role="dialog" aria-modal="false" aria-labelledby="es-a11y-title" hidden data-es-a11y-panel>
		<div class="es-a11y__head">
			<h2 class="es-a11y__title" id="es-a11y-title"><?php esc_html_e( 'Accessibility options', 'elroi-starter' ); ?></h2>
			<button class="es-a11y__close" type="button" data-es-a11y-close>
				<?php echo es_icon( 'close', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="es-a11y__sr"><?php esc_html_e( 'Close accessibility options', 'elroi-starter' ); ?></span>
			</button>
		</div>

		<section class="es-a11y__group" aria-labelledby="es-a11y-h-profiles">
			<h3 class="es-a11y__label" id="es-a11y-h-profiles"><?php esc_html_e( 'Profiles', 'elroi-starter' ); ?></h3>
			<ul class="es-a11y__tiles">
				<?php foreach ( $es_a11y_profiles as $es_key => $es_profile ) : ?>
					<li>
						<button class="es-a11y__tile" type="button" aria-pressed="false" data-es-a11y-profile="<?php echo esc_attr( wp_json_encode( $es_profile[2] ) ); ?>">
							<span class="es-a11y__opt-name"><?php echo esc_html( $es_profile[0] ); ?></span>
							<span class="es-a11y__opt-desc"><?php echo esc_html( $es_profile[1] ); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="es-a11y__group" aria-labelledby="es-a11y-h-text">
			<h3 class="es-a11y__label" id="es-a11y-h-text"><?php esc_html_e( 'Text', 'elroi-starter' ); ?></h3>
			<div class="es-a11y__sizes" role="group" aria-label="<?php esc_attr_e( 'Text size', 'elroi-starter' ); ?>">
				<?php foreach ( array( '' => '100%', '125' => '125%', '150' => '150%', '200' => '200%' ) as $es_size => $es_label ) : ?>
					<button type="button" aria-pressed="false" data-es-a11y-text="<?php echo esc_attr( $es_size ); ?>"><?php echo esc_html( $es_label ); ?></button>
				<?php endforeach; ?>
			</div>
			<ul class="es-a11y__tiles">
				<?php
				foreach ( $es_a11y_text as $es_key => $es_opt ) {
					$es_a11y_tile( $es_key, $es_opt[0], $es_opt[1] );
				}
				?>
			</ul>
		</section>

		<section class="es-a11y__group" aria-labelledby="es-a11y-h-color">
			<h3 class="es-a11y__label" id="es-a11y-h-color"><?php esc_html_e( 'Color (pick one)', 'elroi-starter' ); ?></h3>
			<ul class="es-a11y__tiles es-a11y__tiles--color">
				<?php foreach ( $es_a11y_colors as $es_key => $es_label ) : ?>
					<li>
						<button class="es-a11y__tile" type="button" aria-pressed="false" data-es-a11y-color="<?php echo esc_attr( $es_key ); ?>">
							<span class="es-a11y__opt-name"><?php echo esc_html( $es_label ); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="es-a11y__group" aria-labelledby="es-a11y-h-tools">
			<h3 class="es-a11y__label" id="es-a11y-h-tools"><?php esc_html_e( 'Reading and navigation', 'elroi-starter' ); ?></h3>
			<ul class="es-a11y__tiles">
				<?php
				foreach ( $es_a11y_tools as $es_key => $es_opt ) {
					$es_a11y_tile( $es_key, $es_opt[0], $es_opt[1] );
				}
				?>
			</ul>
			<button class="es-a11y__struct-open" type="button" aria-haspopup="dialog" data-es-a11y-struct-open><?php esc_html_e( 'Page structure: headings, regions, links', 'elroi-starter' ); ?></button>
		</section>

		<div class="es-a11y__foot">
			<button class="es-a11y__reset" type="button" data-es-a11y-reset><?php esc_html_e( 'Reset all', 'elroi-starter' ); ?></button>
			<?php $es_statement = es_a11y_statement_url(); ?>
			<?php if ( $es_statement ) : ?>
				<a href="<?php echo esc_url( $es_statement ); ?>"><?php esc_html_e( 'Accessibility statement', 'elroi-starter' ); ?></a>
			<?php endif; ?>
		</div>
		<p class="es-a11y__note"><?php esc_html_e( 'Saved in this browser only.', 'elroi-starter' ); ?></p>
		<p class="es-a11y__live es-a11y__sr" aria-live="polite" data-es-a11y-live></p>
	</div>
</div>

<dialog class="es-a11y-struct" aria-labelledby="es-a11y-struct-title" data-es-a11y-struct>
	<div class="es-a11y__head">
		<h2 class="es-a11y__title" id="es-a11y-struct-title"><?php esc_html_e( 'Page structure', 'elroi-starter' ); ?></h2>
		<button class="es-a11y__close" type="button" data-es-a11y-struct-close>
			<?php echo es_icon( 'close', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="es-a11y__sr"><?php esc_html_e( 'Close page structure', 'elroi-starter' ); ?></span>
		</button>
	</div>
	<details open>
		<summary><?php esc_html_e( 'Headings', 'elroi-starter' ); ?></summary>
		<ul data-es-a11y-struct-list="headings"></ul>
	</details>
	<details>
		<summary><?php esc_html_e( 'Page regions', 'elroi-starter' ); ?></summary>
		<ul data-es-a11y-struct-list="landmarks"></ul>
	</details>
	<details>
		<summary><?php esc_html_e( 'Links', 'elroi-starter' ); ?></summary>
		<ul data-es-a11y-struct-list="links"></ul>
	</details>
</dialog>

<div class="es-a11y-guidebar" aria-hidden="true" data-es-a11y-guide></div>
<div class="es-a11y-tip" aria-hidden="true" data-es-a11y-tip></div>

<?php // Color-blindness correction filters (daltonization matrices), used by html.es-a11y-color-protan and friends. ?>
<svg class="es-a11y__sr" aria-hidden="true" focusable="false" width="0" height="0">
	<filter id="es-cb-protan" color-interpolation-filters="linearRGB"><feColorMatrix type="matrix" values="1 0 0 0 0 0.479 0.477 0.044 0 0 0.597 -0.689 1.091 0 0 0 0 0 1 0"/></filter>
	<filter id="es-cb-deutan" color-interpolation-filters="linearRGB"><feColorMatrix type="matrix" values="1 0 0 0 0 0.163 0.725 0.112 0 0 0.455 -0.645 1.191 0 0 0 0 0 1 0"/></filter>
	<filter id="es-cb-tritan" color-interpolation-filters="linearRGB"><feColorMatrix type="matrix" values="0.741 -0.407 0.666 0 0 0.075 0.585 0.340 0 0 0 0 1 0 0 0 0 0 1 0"/></filter>
</svg>
