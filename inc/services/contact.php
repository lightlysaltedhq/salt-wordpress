<?php
/**
 * Contact endpoint orchestrator (spec §9; review SEC01/SEC02/SEC10, A11Y09,
 * PRIV06 code-half).
 *
 * Two submission paths, ONE handler:
 *  - REST  POST /salt/v1/contact (the progressive JS path — sections/contact.js)
 *  - No-JS POST admin-post.php?action=salt_contact (redirect-back with status)
 *
 * Defence layers on BOTH paths (SEC01): rate limit → nonce → honeypot →
 * time-trap → validation → CAPTCHA. When a CAPTCHA provider is configured,
 * the JS path must present a provider token (verified server-side against
 * siteverify, fail-closed — SEC02) and the no-JS path answers the
 * server-generated arithmetic challenge — so "works with zero JS" never
 * bypasses verification.
 *
 * The form goes LIVE when a recipient is configured (Site Settings →
 * Contact); the 'salt_contact_form_enabled' filter remains a kill-switch
 * (priority 20+).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the live form is configured + enabled.
 *
 * @return bool
 */
function salt_contact_configured(): bool {
	$configured = function_exists( 'salt_contact_recipient' ) && '' !== salt_contact_recipient( '' );
	return (bool) apply_filters( 'salt_contact_form_enabled', $configured );
}

/**
 * Signed render-timestamp token (time-trap: bots submit instantly).
 *
 * @param int|null $now Injectable clock (tests).
 * @return string "{ts}.{hmac}".
 */
function salt_contact_time_token( ?int $now = null ): string {
	// The 'salt_contact_clock' filter exists so snapshot tests can freeze
	// the rendered token; production never hooks it.
	$ts = null === $now ? (int) apply_filters( 'salt_contact_clock', time() ) : $now;
	return $ts . '.' . hash_hmac( 'sha256', (string) $ts, wp_salt( 'nonce' ) );
}

/**
 * Verify the time-trap token: ≥3s old (humans read), ≤4h old (stale form).
 *
 * @param string   $token Submitted token.
 * @param int|null $now   Injectable clock (tests).
 * @return bool
 */
function salt_contact_time_ok( string $token, ?int $now = null ): bool {
	$now   = null === $now ? time() : $now;
	$parts = explode( '.', $token, 2 );
	if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
		return false;
	}
	if ( ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ) ), $parts[1] ) ) {
		return false;
	}
	$age = $now - (int) $parts[0];
	return $age >= 3 && $age <= 4 * HOUR_IN_SECONDS;
}

/**
 * The allow-listed enquiry types (from the routing options).
 *
 * @return string[] Route labels; empty when no routes configured.
 */
function salt_contact_enquiry_types(): array {
	$routes = salt_option( 'contact_routes' );
	$types  = array();
	foreach ( is_array( $routes ) ? $routes : array() as $route ) {
		$label = is_array( $route ) ? trim( (string) ( $route['enquiry_type'] ?? '' ) ) : '';
		if ( '' !== $label ) {
			$types[] = $label;
		}
	}
	return $types;
}

/**
 * Handle a contact submission — the single orchestrator both paths call.
 *
 * @param array<string,mixed> $raw  Raw request params.
 * @param string              $path 'rest'|'nojs'.
 * @param int|null            $now  Injectable clock (tests).
 * @return array{ok: bool, status: int, message: string}
 */
function salt_contact_handle( array $raw, string $path, ?int $now = null ): array {
	$fail = static fn( int $status, string $message ): array => array(
		'ok'      => false,
		'status'  => $status,
		'message' => $message,
	);

	if ( ! salt_contact_configured() ) {
		return $fail( 404, __( 'This form is not accepting submissions.', 'salt' ) );
	}

	// 1. Rate limit (SEC10): 5 submissions / 10 minutes / client.
	if ( ! salt_rate_limit( 'contact', 5, 600 ) ) {
		return $fail( 429, __( 'Too many messages — please wait a few minutes and try again.', 'salt' ) );
	}

	$generic_error = __( 'Your message could not be sent. Please check the form and try again.', 'salt' );

	// 2. CSRF nonce (both paths).
	if ( ! wp_verify_nonce( (string) ( $raw['salt_contact_nonce'] ?? '' ), 'salt_contact' ) ) {
		salt_log( 'contact: nonce failure (' . $path . ').' );
		return $fail( 403, $generic_error );
	}

	// 3. Honeypot: must exist in the payload AND be empty.
	if ( '' !== trim( (string) ( $raw['salt_hp'] ?? 'missing' ) ) ) {
		salt_log( 'contact: honeypot tripped.' );
		// Indistinguishable from success to the bot.
		return array(
			'ok'      => true,
			'status'  => 200,
			'message' => __( 'Thanks — your message has been sent.', 'salt' ),
		);
	}

	// 4. Time-trap.
	if ( ! salt_contact_time_ok( (string) ( $raw['salt_time'] ?? '' ), $now ) ) {
		salt_log( 'contact: time-trap failure (' . $path . ').' );
		return $fail( 400, $generic_error );
	}

	// 5. Validate + sanitise.
	$name    = trim( wp_strip_all_tags( (string) ( $raw['salt_contact_name'] ?? '' ) ) );
	$email   = trim( (string) ( $raw['salt_contact_email'] ?? '' ) );
	$message = trim( sanitize_textarea_field( (string) ( $raw['salt_contact_message'] ?? '' ) ) );
	$type    = trim( wp_strip_all_tags( (string) ( $raw['salt_contact_type'] ?? '' ) ) );

	if ( '' === $name || mb_strlen( $name ) > 200 ) {
		return $fail( 400, __( 'Please tell us your name.', 'salt' ) );
	}
	if ( ! is_email( $email ) ) {
		return $fail( 400, __( 'Please enter a valid email address.', 'salt' ) );
	}
	if ( '' === $message || mb_strlen( $message ) > 5000 ) {
		return $fail( 400, __( 'Please enter a message (up to 5000 characters).', 'salt' ) );
	}
	if ( ! in_array( $type, salt_contact_enquiry_types(), true ) ) {
		$type = 'general';
	}

	// 6. CAPTCHA (SEC01/SEC02) — only when a provider is configured.
	if ( '' !== salt_captcha_provider() ) {
		if ( 'nojs' === $path && isset( $raw['salt_challenge_token'] ) ) {
			// No-JS path: server-generated arithmetic challenge.
			if ( ! salt_challenge_verify( (string) $raw['salt_challenge_token'], (string) ( $raw['salt_challenge_answer'] ?? '' ) ) ) {
				return $fail( 400, __( 'The anti-spam answer was wrong — please try again.', 'salt' ) );
			}
		} else {
			$token  = (string) ( $raw['cf-turnstile-response'] ?? $raw['g-recaptcha-response'] ?? '' );
			$verify = salt_captcha_verify( $token, salt_client_ip() );
			if ( ! $verify['ok'] ) {
				salt_log( 'contact: captcha rejected (' . $verify['reason'] . ').' );
				return $fail( 400, __( 'We could not verify you are human — please try again.', 'salt' ) );
			}
		}
	}

	// 7. Deliver (+ optionally store).
	$submission = array(
		'name'         => $name,
		'email'        => $email,
		'message'      => $message,
		'enquiry_type' => $type,
		'page_url'     => esc_url_raw( (string) ( $raw['salt_page_url'] ?? '' ) ),
		'submitted_at' => gmdate( 'c', null === $now ? time() : $now ),
		'ip_hash'      => hash( 'sha256', salt_client_ip() . wp_salt( 'nonce' ) ),
	);

	$delivery = salt_contact_deliver( $submission );
	salt_submission_store( $submission );

	if ( ! $delivery['sent'] ) {
		return $fail( 500, __( 'Something went wrong sending your message. Please email us directly.', 'salt' ) );
	}

	return array(
		'ok'      => true,
		'status'  => 200,
		'message' => __( 'Thanks — your message has been sent.', 'salt' ),
	);
}

/**
 * REST route (the JS path).
 *
 * @return void
 */
function salt_contact_register_rest(): void {
	register_rest_route(
		'salt/v1',
		'/contact',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => static function ( $request ) {
				$result = salt_contact_handle( (array) $request->get_params(), 'rest' );
				return new WP_REST_Response(
					array(
						'ok'      => $result['ok'],
						'message' => $result['message'],
					),
					$result['status']
				);
			},
		)
	);
}
add_action( 'rest_api_init', 'salt_contact_register_rest' );

/**
 * No-JS path: admin-post handler → redirect back with a status flag the
 * section renders into its aria-live region (A11Y09).
 *
 * @return void
 */
function salt_contact_handle_nojs(): void {
	// Nonce is verified inside salt_contact_handle (step 2).
	$result = salt_contact_handle( wp_unslash( $_POST ), 'nojs' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified + sanitised field-by-field in salt_contact_handle().

	$back = wp_get_referer();
	$back = is_string( $back ) && '' !== $back ? $back : home_url( '/' );

	wp_safe_redirect(
		add_query_arg( 'salt-contact', $result['ok'] ? 'ok' : rawurlencode( $result['message'] ), $back ) . '#salt-contact-form'
	);
	exit;
}
add_action( 'admin_post_salt_contact', 'salt_contact_handle_nojs' );
add_action( 'admin_post_nopriv_salt_contact', 'salt_contact_handle_nojs' );

/**
 * The no-JS status message for the current request ('' when none).
 *
 * @return array{status: ''|'ok'|'error', message: string}
 */
function salt_contact_request_status(): array {
	// Read-only display of a redirect flag — no state change, no nonce needed.
	$flag = isset( $_GET['salt-contact'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['salt-contact'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( '' === $flag ) {
		return array(
			'status'  => '',
			'message' => '',
		);
	}
	if ( 'ok' === $flag ) {
		return array(
			'status'  => 'ok',
			'message' => __( 'Thanks — your message has been sent.', 'salt' ),
		);
	}
	return array(
		'status'  => 'error',
		'message' => rawurldecode( $flag ),
	);
}
