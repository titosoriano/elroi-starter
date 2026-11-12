<?php
/**
 * Lead form: saved as a private "Form messages" post, emailed, and optionally sent to HighLevel.
 *
 * Optional server settings, in wp-config.php (never in the repository):
 *   ELROI_GHL_TOKEN, ELROI_GHL_LOCATION   HighLevel Private Integration token (Contacts and
 *                                         Opportunities) and location ID: upsert the contact, tag
 *                                         it website-lead / website-request / service-<slug>, add
 *                                         a note.
 *   ELROI_GHL_PIPELINE, ELROI_GHL_STAGE   Also open an opportunity in that pipeline stage.
 *   ELROI_GHL_MESSAGE_FIELD               Contact custom field ID that receives the message.
 *   ELROI_TURNSTILE_SITE, ELROI_TURNSTILE_SECRET   Cloudflare Turnstile spam check.
 * Without them the form still works (saved + emailed), with a honeypot field against bots.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		register_post_type(
			'es_lead',
			array(
				'labels'          => array(
					'name'          => __( 'Form messages', 'elroi-starter' ),
					'singular_name' => __( 'Form message', 'elroi-starter' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'menu_icon'       => 'dashicons-email-alt',
				'menu_position'   => 25,
				'supports'        => array( 'title', 'editor', 'custom-fields' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}
);

/**
 * Whether Turnstile is configured.
 */
function es_turnstile_on() {
	return defined( 'ELROI_TURNSTILE_SITE' ) && defined( 'ELROI_TURNSTILE_SECRET' ) && ELROI_TURNSTILE_SITE && ELROI_TURNSTILE_SECRET;
}

add_action( 'admin_post_nopriv_es_lead', 'es_handle_lead' );
add_action( 'admin_post_es_lead', 'es_handle_lead' );

/**
 * Handle a form POST, then redirect back to the form with a status.
 */
function es_handle_lead() {
	$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$back = remove_query_arg( 'lead', $back );
	$done = function ( $code ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'lead', $code, $back ) . '#lead-form' );
		exit;
	};

	if ( ! isset( $_POST['es_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['es_nonce'] ) ), 'es_lead' ) ) {
		$done( 'expired' );
	}
	if ( ! empty( $_POST['es_website'] ) ) { // Honeypot: people never fill it.
		$done( 'sent' );
	}
	if ( es_turnstile_on() ) {
		$res = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => ELROI_TURNSTILE_SECRET,
					'response' => sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ?? '' ) ),
					'remoteip' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
				),
			)
		);
		$ok  = ! is_wp_error( $res ) && ! empty( json_decode( wp_remote_retrieve_body( $res ), true )['success'] );
		if ( ! $ok ) {
			$done( 'robot' );
		}
	}

	$lead = array(
		'name'    => sanitize_text_field( wp_unslash( $_POST['es_name'] ?? '' ) ),
		'phone'   => sanitize_text_field( wp_unslash( $_POST['es_phone'] ?? '' ) ),
		'email'   => sanitize_email( wp_unslash( $_POST['es_email'] ?? '' ) ),
		'service' => sanitize_text_field( wp_unslash( $_POST['es_service'] ?? '' ) ),
		'message' => sanitize_textarea_field( wp_unslash( $_POST['es_message'] ?? '' ) ),
		'page'    => $back,
	);
	if ( '' === $lead['name'] || '' === $lead['phone'] || ! is_email( $lead['email'] ) ) {
		$done( 'missing' );
	}
	if ( ! es_valid_phone( $lead['phone'] ) ) {
		$done( 'phone' );
	}
	if ( ! in_array( $lead['service'], (array) es_setting( 'form_services' ), true ) ) {
		$lead['service'] = '';
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'es_lead',
			'post_status'  => 'private',
			'post_title'   => $lead['name'] . ( $lead['service'] ? ', ' . $lead['service'] : '' ),
			'post_content' => $lead['message'],
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		$done( 'error' );
	}
	foreach ( array( 'name', 'phone', 'email', 'service', 'page' ) as $k ) {
		update_post_meta( $post_id, 'es_' . $k, $lead[ $k ] );
	}

	$to = es_setting( 'form_email' ) ? es_setting( 'form_email' ) : get_option( 'admin_email' );
	wp_mail(
		apply_filters( 'es_lead_recipient', $to ),
		sprintf( 'New website message: %s', $lead['name'] ),
		implode( "\n", array( 'Name: ' . $lead['name'], 'Phone: ' . $lead['phone'], 'Email: ' . $lead['email'], 'Service: ' . ( $lead['service'] ? $lead['service'] : 'Not specified' ), 'Page: ' . $lead['page'], '', $lead['message'], '', 'Admin: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) ),
		array( 'Reply-To: ' . $lead['name'] . ' <' . $lead['email'] . '>' )
	);

	es_lead_to_highlevel( $post_id, $lead );
	$done( 'sent' );
}

/**
 * Send a lead to HighLevel. Failures never block the visitor; they are kept in es_ghl_error.
 *
 * @param int   $post_id Lead post ID.
 * @param array $lead    name, phone, email, service, message, page.
 */
function es_lead_to_highlevel( $post_id, $lead ) {
	if ( ! defined( 'ELROI_GHL_TOKEN' ) || ! defined( 'ELROI_GHL_LOCATION' ) || ! ELROI_GHL_TOKEN || ! ELROI_GHL_LOCATION ) {
		return;
	}
	$call = function ( $method, $path, $body ) {
		$res = wp_remote_request(
			'https://services.leadconnectorhq.com' . $path,
			array(
				'method'  => $method,
				'timeout' => 8,
				'headers' => array(
					'Authorization' => 'Bearer ' . ELROI_GHL_TOKEN,
					'Version'       => '2021-07-28',
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code > 299 ) {
			return new WP_Error( 'ghl_' . $code, $path . ': ' . substr( wp_remote_retrieve_body( $res ), 0, 300 ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		return is_array( $data ) ? $data : array();
	};

	$parts   = preg_split( '/\s+/', trim( $lead['name'] ), 2 );
	$digits  = preg_replace( '/\D/', '', $lead['phone'] );
	$fields  = defined( 'ELROI_GHL_MESSAGE_FIELD' ) && $lead['message'] ? array( array( 'id' => ELROI_GHL_MESSAGE_FIELD, 'field_value' => $lead['message'] ) ) : array();
	$contact = $call(
		'POST',
		'/contacts/upsert',
		array(
			'locationId'   => ELROI_GHL_LOCATION,
			'firstName'    => $parts[0],
			'lastName'     => $parts[1] ?? '',
			'email'        => $lead['email'],
			'phone'        => 10 === strlen( $digits ) ? '+1' . $digits : $lead['phone'],
			'source'       => 'Website form',
			'customFields' => $fields,
		)
	);
	if ( is_wp_error( $contact ) || empty( $contact['contact']['id'] ) ) {
		update_post_meta( $post_id, 'es_ghl_error', is_wp_error( $contact ) ? $contact->get_error_message() : 'No contact ID returned' );
		return;
	}
	$cid = $contact['contact']['id'];
	update_post_meta( $post_id, 'es_ghl_contact', $cid );

	// "website-request" is removed and added again so a "Tag added" workflow fires on every message.
	$tags  = array_filter( array( 'website-lead', 'website-request', $lead['service'] ? 'service-' . sanitize_title( $lead['service'] ) : '' ) );
	$steps = array(
		array( 'DELETE', "/contacts/$cid/tags", array( 'tags' => array( 'website-request' ) ) ),
		array( 'POST', "/contacts/$cid/tags", array( 'tags' => array_values( $tags ) ) ),
		array( 'POST', "/contacts/$cid/notes", array( 'body' => "Website message\nService: " . ( $lead['service'] ? $lead['service'] : 'Not specified' ) . "\nPage: {$lead['page']}\n\n" . ( $lead['message'] ? $lead['message'] : '(no message)' ) ) ),
	);
	if ( defined( 'ELROI_GHL_PIPELINE' ) && defined( 'ELROI_GHL_STAGE' ) ) {
		$steps[] = array(
			'POST',
			'/opportunities/',
			array(
				'locationId'      => ELROI_GHL_LOCATION,
				'pipelineId'      => ELROI_GHL_PIPELINE,
				'pipelineStageId' => ELROI_GHL_STAGE,
				'contactId'       => $cid,
				'name'            => $lead['name'] . ( $lead['service'] ? ', ' . $lead['service'] : '' ),
				'status'          => 'open',
				'source'          => 'Website form',
			),
		);
	}
	$errors = array();
	foreach ( $steps as $step ) {
		$res = $call( ...$step );
		if ( is_wp_error( $res ) && ! str_contains( $res->get_error_message(), 'OPPORTUNITY_NO_DUPLICATE' ) ) {
			$errors[] = $res->get_error_message();
		} elseif ( ! is_wp_error( $res ) && ! empty( $res['opportunity']['id'] ) ) {
			update_post_meta( $post_id, 'es_ghl_opportunity', $res['opportunity']['id'] );
		}
	}
	if ( $errors ) {
		update_post_meta( $post_id, 'es_ghl_error', implode( "\n", $errors ) );
	}
}

/**
 * Whether a phone number looks real: only digits, spaces and + ( ) . - with 10 to 15 digits.
 * site.js applies the same rule as the visitor types; this re-checks it on the server so bots and
 * typos never reach the CRM.
 *
 * @param string $phone Phone number as typed.
 */
function es_valid_phone( $phone ) {
	if ( preg_match( '/[^0-9+().\s-]/', (string) $phone ) ) {
		return false;
	}
	$digits = strlen( preg_replace( '/\D/', '', (string) $phone ) );
	return $digits >= 10 && $digits <= 15;
}

/**
 * The line under the form: required-field note and, when the site has a privacy policy page
 * (Settings > Privacy), a link to it.
 */
function es_form_fine_print() {
	$url  = get_privacy_policy_url();
	$text = esc_html__( '* Required. We only use your details to answer you.', 'elroi-starter' );
	if ( $url ) {
		/* translators: %s: link to the privacy policy. */
		$text .= ' ' . sprintf( esc_html__( 'See our %s.', 'elroi-starter' ), '<a href="' . esc_url( $url ) . '">' . esc_html__( 'privacy policy', 'elroi-starter' ) . '</a>' );
	}
	return (string) apply_filters( 'es_form_fine_print', $text, $url );
}

/**
 * The form.
 *
 * @param string $title Title above the form.
 * @param string $text  Line under the title.
 */
function es_lead_form( $title = '', $text = '' ) {
	$state = isset( $_GET['lead'] ) ? sanitize_key( wp_unslash( $_GET['lead'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$notes = array(
		'sent'    => array( 'ok', sprintf( 'Thank you. Your message is in, and we reply within %s hours.', es_setting( 'response_hours' ) ) ),
		'missing' => array( 'err', 'Please add your name, phone number and a valid email so we can reach you.' ),
		'phone'   => array( 'err', 'Please check your phone number: use 10 digits, for example (555) 123-4567.' ),
		'expired' => array( 'err', 'The form expired. Please send it again.' ),
		'robot'   => array( 'err', 'We could not confirm you are not a robot. Please try again.' ),
		'error'   => array( 'err', 'Something went wrong. Please call us instead.' ),
	);
	$embed = es_form_embed_html();
	if ( '' !== $embed ) {
		?>
		<div class="es-form es-form--embed" id="lead-form">
			<?php if ( $title ) : ?>
				<h2 class="es-form__title"><?php echo elroi_text( $title ); // phpcs:ignore ?></h2>
			<?php endif; ?>
			<?php if ( $text ) : ?>
				<p class="es-form__text"><?php echo elroi_text( $text ); // phpcs:ignore ?></p>
			<?php endif; ?>
			<div class="es-form__embed"><?php echo $embed; // phpcs:ignore -- saved by someone allowed to post raw HTML. ?></div>
		</div>
		<?php
		return;
	}
	if ( es_turnstile_on() ) {
		wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, array( 'strategy' => 'defer' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}
	?>
	<form class="es-form" id="lead-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<?php if ( $title ) : ?>
			<h2 class="es-form__title"><?php echo elroi_text( $title ); // phpcs:ignore ?></h2>
		<?php endif; ?>
		<?php if ( $text ) : ?>
			<p class="es-form__text"><?php echo elroi_text( $text ); // phpcs:ignore ?></p>
		<?php endif; ?>
		<?php if ( isset( $notes[ $state ] ) ) : ?>
			<p class="es-form__notice es-form__notice--<?php echo esc_attr( $notes[ $state ][0] ); ?>" role="<?php echo 'ok' === $notes[ $state ][0] ? 'status' : 'alert'; ?>" tabindex="-1" data-es-notice><?php echo esc_html( $notes[ $state ][1] ); ?></p>
		<?php endif; ?>
		<input type="hidden" name="action" value="es_lead">
		<input type="hidden" name="es_nonce" value="<?php echo esc_attr( wp_create_nonce( 'es_lead' ) ); ?>">
		<div class="es-form__hp" aria-hidden="true"><label for="es-website">Website</label><input type="text" id="es-website" name="es_website" tabindex="-1" autocomplete="off"></div>
		<div class="es-form__row">
			<p class="es-field"><label for="es-name">Full name <span aria-hidden="true">*</span></label><input type="text" id="es-name" name="es_name" autocomplete="name" required></p>
			<p class="es-field"><label for="es-phone">Phone <span aria-hidden="true">*</span></label><input type="tel" id="es-phone" name="es_phone" autocomplete="tel" inputmode="tel" pattern="[0-9+\(\)\.\s\-]{10,25}" required data-es-phone></p>
		</div>
		<div class="es-form__row">
			<p class="es-field"><label for="es-email">Email <span aria-hidden="true">*</span></label><input type="email" id="es-email" name="es_email" autocomplete="email" required></p>
			<?php $services = (array) es_setting( 'form_services' ); ?>
			<?php if ( $services ) : ?>
				<p class="es-field"><label for="es-service">Service</label><select id="es-service" name="es_service"><option value="">Choose one</option><?php foreach ( $services as $s ) : ?><option><?php echo esc_html( $s ); ?></option><?php endforeach; ?></select></p>
			<?php endif; ?>
		</div>
		<p class="es-field"><label for="es-message">How can we help?</label><textarea id="es-message" name="es_message" rows="4"></textarea></p>
		<?php if ( es_turnstile_on() ) : ?>
			<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( ELROI_TURNSTILE_SITE ); ?>" data-appearance="interaction-only"></div>
		<?php endif; ?>
		<button class="es-btn es-btn--primary es-form__submit" type="submit">Send</button>
		<p class="es-form__fine"><?php echo es_form_fine_print(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in es_form_fine_print() ?></p>
	</form>
	<?php
}
