<?php
/**
 * Connecting the contact form later.
 *
 * Sites are designed with the built-in form first; the real destination comes at the end. Until
 * then the built-in form already keeps every message (Form messages in wp-admin), so no lead is
 * lost while the site is being finished. Later, one of:
 *
 * - **A CRM form:** Form messages > Connect a form > "Form from my CRM", paste the embed code or a
 *   shortcode (HighLevel, HubSpot, Jotform, Fluent Forms...). Every place that shows the form
 *   (es_lead_form()) then shows the CRM form inside the same designed box, with the same title,
 *   text and #lead-form anchor. Switch back any time; the code is kept.
 * - **HighLevel through the built-in form:** the wp-config.php constants in inc/lead-form.php.
 * - **Email:** an SMTP plugin (FluentSMTP, WP Mail SMTP...) so the built-in form's emails arrive.
 *
 * Tools > Site Health shows whether the form is connected to something besides wp-admin.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Form connection: mode (builtin | embed) and the CRM embed code.
 */
function es_form_connection() {
	$o = (array) get_option( 'es_form', array() );
	return array(
		'mode'  => ( $o['mode'] ?? '' ) === 'embed' ? 'embed' : 'builtin',
		'embed' => (string) ( $o['embed'] ?? '' ),
	);
}

/**
 * The CRM form's HTML when the site uses one, or ''. Filter: `es_form_embed`.
 */
function es_form_embed_html() {
	$c    = es_form_connection();
	$html = 'embed' === $c['mode'] && '' !== trim( $c['embed'] ) ? do_shortcode( $c['embed'] ) : '';
	return (string) apply_filters( 'es_form_embed', $html );
}

/**
 * Whether an SMTP or transactional mail plugin is active.
 */
function es_mail_plugin_active() {
	$known = defined( 'FLUENTMAIL' ) || defined( 'WPMS_PLUGIN_VER' ) || defined( 'POST_SMTP_VER' ) || class_exists( 'EasyWPSMTP' ) || defined( 'SENDGRID_CATEGORY' ) || defined( 'MAILGUN_VERSION' ) || defined( 'BREVO_PLUGIN_VERSION' );
	return (bool) apply_filters( 'es_mail_plugin_active', $known );
}

/**
 * Where form messages go: list of plain descriptions (empty: only wp-admin).
 */
function es_form_destinations() {
	$out = array();
	if ( '' !== es_form_embed_html() ) {
		$out[] = 'your CRM form';
	}
	if ( defined( 'ELROI_GHL_TOKEN' ) && defined( 'ELROI_GHL_LOCATION' ) && ELROI_GHL_TOKEN && ELROI_GHL_LOCATION ) {
		$out[] = 'HighLevel';
	}
	if ( es_mail_plugin_active() ) {
		$out[] = 'email through your mail plugin';
	}
	return $out;
}

/**
 * Clean the saved form connection. Embed codes need <script> and <iframe>, so only people
 * allowed to post raw HTML keep scripts; everyone else keeps iframes and ordinary markup.
 *
 * @param mixed $in Submitted value.
 */
function es_form_sanitize( $in ) {
	$in    = (array) $in;
	$embed = trim( (string) ( $in['embed'] ?? '' ) );
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		$embed = wp_kses(
			$embed,
			wp_kses_allowed_html( 'post' ) + array(
				'iframe' => array( 'src' => true, 'width' => true, 'height' => true, 'style' => true, 'title' => true, 'id' => true, 'class' => true, 'allow' => true, 'loading' => true, 'frameborder' => true, 'data-*' => true ),
			)
		);
	}
	return array(
		'mode'  => 'embed' === ( $in['mode'] ?? '' ) ? 'embed' : 'builtin',
		'embed' => $embed,
	);
}

add_action(
	'admin_init',
	function () {
		register_setting(
			'es_form',
			'es_form',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'es_form_sanitize',
			)
		);
	}
);

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'edit.php?post_type=es_lead',
			__( 'Connect a form', 'elroi-starter' ),
			__( 'Connect a form', 'elroi-starter' ),
			'manage_options',
			'es-form-connect',
			'es_form_connect_page'
		);
	}
);

/**
 * The "Connect a form" screen.
 */
function es_form_connect_page() {
	$c    = es_form_connection();
	$dest = es_form_destinations();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Connect a form', 'elroi-starter' ); ?></h1>
		<p style="max-width:46rem">Every page that shows the contact form uses what you choose here. The built-in form keeps every message under <strong>Form messages</strong>, so nothing is lost while the site is being finished.</p>
		<p><strong>Right now messages go to:</strong> <?php echo esc_html( $dest ? 'Form messages in wp-admin, and ' . implode( ', ', $dest ) : 'Form messages in wp-admin only' ); ?>.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'es_form' ); ?>
			<fieldset style="margin:1.5rem 0">
				<p><label><input type="radio" name="es_form[mode]" value="builtin" <?php checked( 'builtin', $c['mode'] ); ?>> <strong>Built-in form</strong>: name, phone, email, service and message, in the site's design.</label></p>
				<p><label><input type="radio" name="es_form[mode]" value="embed" <?php checked( 'embed', $c['mode'] ); ?>> <strong>Form from my CRM</strong>: the code below, shown inside the same box.</label></p>
			</fieldset>
			<p><label for="es-form-embed"><strong>CRM embed code or shortcode</strong></label></p>
			<textarea id="es-form-embed" name="es_form[embed]" rows="8" class="large-text code" placeholder="&lt;iframe src=&quot;https://...&quot;&gt;&lt;/iframe&gt; or [fluentform id=&quot;1&quot;]"><?php echo esc_textarea( $c['embed'] ); ?></textarea>
			<p class="description">Paste what your CRM gives you under "Embed" or "Share" (HighLevel, HubSpot, Jotform and others), or a form plugin's shortcode. The code is kept if you switch back to the built-in form.</p>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

add_filter(
	'site_status_tests',
	function ( $tests ) {
		$tests['direct']['es_form_delivery'] = array(
			'label' => __( 'Contact form delivery', 'elroi-starter' ),
			'test'  => 'es_form_health_test',
		);
		return $tests;
	}
);

/**
 * Site Health: is the contact form connected to something besides wp-admin?
 */
function es_form_health_test() {
	$dest = es_form_destinations();
	$link = admin_url( 'edit.php?post_type=es_lead&page=es-form-connect' );
	$base = array(
		'label'       => $dest ? 'The contact form is connected' : 'The contact form only saves messages in wp-admin',
		'status'      => $dest ? 'good' : 'recommended',
		'badge'       => array(
			'label' => 'Contact form',
			'color' => 'blue',
		),
		'description' => '<p>' . esc_html( $dest ? 'Messages go to ' . implode( ', ', $dest ) . '.' : 'Every message is kept under Form messages, but no one is told about it. Paste your CRM form, connect HighLevel, or add an SMTP plugin so the emails arrive.' ) . '</p>',
		'actions'     => '<p><a href="' . esc_url( $link ) . '">Connect a form</a></p>',
		'test'        => 'es_form_delivery',
	);
	return $base;
}
