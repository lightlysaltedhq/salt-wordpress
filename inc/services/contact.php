<?php
/**
 * Contact endpoint orchestrator (spec §9; review SEC01/SEC02/SEC10, A11Y09,
 * PRIV06 code-half), for the contract's form (markup/contact-form.json,
 * SC-025).
 *
 * The form as first drawn carries no credential and no per-visitor value,
 * so a contact page can be cached. Its credentials (the form token, and on
 * the no-JS path the arithmetic challenge and its token) come only from a
 * response no cache keeps:
 *  - with script, sections/contact.js fetches a form token from
 *    GET /salt/v1/contact/credentials and posts to POST /salt/v1/contact;
 *  - without script, the form posts to its own page
 *    (<returnTo>?salt-contact-send=<anchor>). salt_contact_self_post()
 *    handles it on template_redirect and the page draws that form again in
 *    the same response: what was sent, each error, the status, and fresh
 *    credentials. It never redirects. A first post carries no credentials
 *    and is refused that way, with the visitor's entries kept.
 *  - admin-post.php?action=salt_contact, the pre-6.0 no-JS path, stays for
 *    one release as a thin adapter (salt_contact_handle_nojs()), so a cached
 *    old page or a child template still posting to it does not fail.
 *
 * Every path runs ONE handler, salt_contact_handle(), with the contract's
 * field names (name, email, phone, enquiryType, message, website,
 * formToken, challengeToken, challengeAnswer, returnTo). Defence layers on
 * every path (SEC01): rate limit → credentials (the form token's nonce) →
 * honeypot → time trap (the form token's signed time) → validation →
 * CAPTCHA. With a CAPTCHA provider configured the JS path presents the
 * provider's token (verified against siteverify, fail-closed — SEC02); the
 * no-JS page path always answers the server's arithmetic challenge.
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
 * A form token: the signed time token, then the CSRF nonce
 * ("{ts}.{hmac}.{nonce}"). Minted only in a response no cache keeps (the
 * credentials endpoint, the self-posted page), never in the page as first
 * drawn (SC-025). The pre-6.0 form's salt_time and salt_contact_nonce
 * fields join into the same shape.
 *
 * @param int|null $now Injectable clock (tests).
 * @return string
 */
function salt_contact_form_token( ?int $now = null ): string {
	return salt_contact_time_token( $now ) . '.' . wp_create_nonce( 'salt_contact' );
}

/**
 * The allow-listed enquiry types, from the routing options: each route's
 * label and its value. The value is the route's `value` sub-field, or the
 * label's slug for a route saved without one (before 6.0); a value an
 * earlier route already took is suffixed -2, -3 and so on, so each is
 * unique.
 *
 * @return array<int,array{value: string, label: string}> Empty when no routes are configured.
 */
function salt_contact_enquiry_types(): array {
	$routes = salt_option( 'contact_routes' );
	$types  = array();
	$taken  = array();
	foreach ( is_array( $routes ) ? $routes : array() as $route ) {
		$label = is_array( $route ) ? trim( (string) ( $route['enquiry_type'] ?? '' ) ) : '';
		if ( '' === $label ) {
			continue;
		}
		$base  = sanitize_title( (string) ( $route['value'] ?? '' ) );
		$base  = '' !== $base ? $base : sanitize_title( $label );
		$base  = '' !== $base ? $base : 'type';
		$value = $base;
		for ( $n = 2; isset( $taken[ $value ] ); $n++ ) {
			$value = $base . '-' . $n;
		}
		$taken[ $value ] = true;
		$types[]         = array(
			'value' => $value,
			'label' => $label,
		);
	}
	return $types;
}

/**
 * Whether the form asks for a phone number (Site Settings → Contact):
 * 'off' (the default, as before 6.0), 'optional' or 'required'.
 *
 * @return string
 */
function salt_contact_phone_mode(): string {
	$mode = salt_option( 'contact_form_phone' );
	return in_array( $mode, array( 'optional', 'required' ), true ) ? (string) $mode : 'off';
}

/**
 * The page the form is on, as a same-site path with its query (a site on
 * plain permalinks names its pages there), without the contact flags
 * (salt-contact, salt-contact-send): the form's returnTo, and the address
 * it posts to without script. From the request alone, so every visitor to
 * one page gets the same value.
 *
 * @return string
 */
function salt_contact_page_path(): string {
	$uri   = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Made a same-site path by salt_contact_return_to() below.
	$parts = wp_parse_url( $uri );
	$path  = is_array( $parts ) ? (string) ( $parts['path'] ?? '/' ) : '/';
	$query = array();
	if ( is_array( $parts ) && isset( $parts['query'] ) ) {
		parse_str( (string) $parts['query'], $query );
		unset( $query['salt-contact'], $query['salt-contact-send'] );
	}
	$path .= array() !== $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '';
	$safe  = salt_contact_return_to( $path );
	return '' !== $safe ? $safe : '/';
}

/**
 * The page path a submission names in returnTo, same-site only: a path on
 * this site (an absolute URL on the home host is made relative first),
 * never a scheme-relative or foreign address. '' when it is none.
 *
 * @param string $raw The submitted returnTo.
 * @return string
 */
function salt_contact_return_to( string $raw ): string {
	$path = salt_contract_url( trim( $raw ) );
	if ( 1 !== preg_match( '#^/(?![/\\\\])#', $path ) || 1 === preg_match( '/[\x00-\x1f\x7f]/', $path ) ) {
		return '';
	}
	return $path;
}

/**
 * Handle a contact submission — the single orchestrator every path calls.
 *
 * @param array<string,mixed> $raw  Raw request params, by the contract's field names.
 * @param string              $path 'rest' (script), 'page' (the self-posted page,
 *                                  no script) or 'legacy' (admin-post.php, the
 *                                  pre-6.0 no-JS form).
 * @param int|null            $now  Injectable clock (tests).
 * @return array{ok: bool, status: int, message: string, errors: array<string,string>, values: array<string,string>}
 *         values: what was sent, cleaned, by field name (name, email, phone,
 *         enquiryType, message), for the form drawn again after a failed send.
 */
function salt_contact_handle( array $raw, string $path, ?int $now = null ): array {
	$field  = static fn( string $name ): string => is_scalar( $raw[ $name ] ?? null ) ? (string) $raw[ $name ] : '';
	$values = array(
		'name'        => trim( wp_strip_all_tags( $field( 'name' ) ) ),
		'email'       => trim( sanitize_text_field( $field( 'email' ) ) ),
		'phone'       => trim( sanitize_text_field( $field( 'phone' ) ) ),
		'enquiryType' => trim( sanitize_text_field( $field( 'enquiryType' ) ) ),
		'message'     => trim( sanitize_textarea_field( $field( 'message' ) ) ),
	);
	$fail   = static fn( int $status, string $message, array $errors = array() ): array => array(
		'ok'      => false,
		'status'  => $status,
		'message' => $message,
		'errors'  => $errors,
		'values'  => $values,
	);
	$sent   = static fn(): array => array(
		'ok'      => true,
		'status'  => 200,
		'message' => __( 'Thanks — your message has been sent.', 'salt' ),
		'errors'  => array(),
		'values'  => array(),
	);

	if ( ! salt_contact_configured() ) {
		return $fail( 404, __( 'This form is not accepting submissions.', 'salt' ) );
	}

	// 1. Rate limit (SEC10): 5 submissions / 10 minutes / client.
	if ( ! salt_rate_limit( 'contact', 5, 600 ) ) {
		return $fail( 429, __( 'Too many messages — please wait a few minutes and try again.', 'salt' ) );
	}

	$generic_error = __( 'Your message could not be sent. Please check the form and try again.', 'salt' );

	// 2. Credentials (SC-025): a form token, and on the page path the
	// challenge too. A send without them (a no-JS visitor's first, always)
	// is refused, and the form comes back with them and the entries kept.
	$token = explode( '.', $field( 'formToken' ) );
	if ( 3 !== count( $token ) || ( 'page' === $path && '' === $field( 'challengeToken' ) ) ) {
		return $fail( 400, __( 'Please check your details and send the form again.', 'salt' ) );
	}

	// 3. CSRF nonce (every path).
	if ( ! wp_verify_nonce( $token[2], 'salt_contact' ) ) {
		salt_log( 'contact: nonce failure (' . $path . ').' );
		return $fail( 403, $generic_error );
	}

	// 4. Honeypot: must exist in the payload AND be empty.
	if ( ! isset( $raw['website'] ) || '' !== trim( $field( 'website' ) ) ) {
		salt_log( 'contact: honeypot tripped.' );
		// Indistinguishable from success to the bot.
		return $sent();
	}

	// 5. Time-trap (the form token's signed time).
	if ( ! salt_contact_time_ok( $token[0] . '.' . $token[1], $now ) ) {
		salt_log( 'contact: time-trap failure (' . $path . ').' );
		return $fail( 400, $generic_error );
	}

	// 6. Validate: every field's error at once, so the form marks them all.
	$errors = array();
	if ( '' === $values['name'] || mb_strlen( $values['name'] ) > 200 ) {
		$errors['name'] = __( 'Please tell us your name.', 'salt' );
	}
	if ( ! is_email( $values['email'] ) ) {
		$errors['email'] = __( 'Please enter a valid email address.', 'salt' );
	}
	$phone_mode = salt_contact_phone_mode();
	if ( 'off' === $phone_mode ) {
		$values['phone'] = '';
	} elseif ( '' === $values['phone'] ) {
		if ( 'required' === $phone_mode ) {
			$errors['phone'] = __( 'Please enter your phone number.', 'salt' );
		}
	} elseif ( 1 !== preg_match( '/^\+?[0-9 ().\-]{6,30}$/', $values['phone'] ) || strlen( (string) preg_replace( '/\D/', '', $values['phone'] ) ) < 6 ) {
		$errors['phone'] = __( 'Please enter a phone number using digits, spaces and a leading +.', 'salt' );
	}
	$types = salt_contact_enquiry_types();
	$type  = 'general';
	if ( '' !== $values['enquiryType'] || count( $types ) > 1 ) {
		$match = array_values( array_filter( $types, static fn( array $t ): bool => $t['value'] === $values['enquiryType'] ) );
		if ( array() === $match ) {
			$errors['enquiryType'] = __( 'Please choose what your enquiry is about.', 'salt' );
		} else {
			$type = $match[0]['label'];
		}
	}
	if ( '' === $values['message'] || mb_strlen( $values['message'] ) > 5000 ) {
		$errors['message'] = __( 'Please enter a message (up to 5000 characters).', 'salt' );
	}
	if ( array() !== $errors ) {
		return $fail(
			400,
			1 === count( $errors ) ? reset( $errors ) : __( 'Please correct the fields marked.', 'salt' ),
			$errors
		);
	}

	// 7. CAPTCHA (SEC01/SEC02). The page path always answers the server's
	// arithmetic challenge; otherwise only with a provider configured, by
	// its token (or, on the pre-6.0 no-JS form, the challenge).
	$provider = '' !== salt_captcha_provider();
	if ( 'page' === $path || ( $provider && 'legacy' === $path && '' !== $field( 'challengeToken' ) ) ) {
		if ( ! salt_challenge_verify( $field( 'challengeToken' ), $field( 'challengeAnswer' ) ) ) {
			$message = __( 'The anti-spam answer was wrong — please try again.', 'salt' );
			return $fail( 400, $message, array( 'challengeAnswer' => $message ) );
		}
	} elseif ( $provider ) {
		$captcha = $field( 'cf-turnstile-response' );
		$captcha = '' !== $captcha ? $captcha : $field( 'g-recaptcha-response' );
		$verify  = salt_captcha_verify( $captcha, salt_client_ip() );
		if ( ! $verify['ok'] ) {
			salt_log( 'contact: captcha rejected (' . $verify['reason'] . ').' );
			return $fail( 400, __( 'We could not verify you are human — please try again.', 'salt' ) );
		}
	}

	// 8. Deliver (+ optionally store). The phone is personal data: it goes
	// in only when one was given.
	$return_to  = salt_contact_return_to( $field( 'returnTo' ) );
	$submission = array(
		'name'         => $values['name'],
		'email'        => $values['email'],
		'message'      => $values['message'],
		'enquiry_type' => $type,
		'page_url'     => '' !== $return_to ? esc_url_raw( home_url( $return_to ) ) : '',
		'submitted_at' => gmdate( 'c', null === $now ? time() : $now ),
		'ip_hash'      => hash( 'sha256', salt_client_ip() . wp_salt( 'nonce' ) ),
	);
	if ( '' !== $values['phone'] ) {
		$submission['phone'] = $values['phone'];
	}

	$delivery = salt_contact_deliver( $submission );
	salt_submission_store( $submission );

	if ( ! $delivery['sent'] ) {
		return $fail( 500, __( 'Something went wrong sending your message. Please email us directly.', 'salt' ) );
	}

	return $sent();
}

/**
 * REST routes (the JS path): the credentials, then the send. Neither
 * response may be cached (SC-025).
 *
 * @return void
 */
function salt_contact_register_rest(): void {
	register_rest_route(
		'salt/v1',
		'/contact/credentials',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => 'salt_contact_rest_credentials',
		)
	);
	register_rest_route(
		'salt/v1',
		'/contact',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'salt_contact_rest_send',
		)
	);
}
add_action( 'rest_api_init', 'salt_contact_register_rest' );

/**
 * GET /salt/v1/contact/credentials: a fresh form token, rate limited
 * (SEC10: 20 / 10 minutes / client), never cached.
 *
 * @return WP_REST_Response
 */
function salt_contact_rest_credentials(): WP_REST_Response {
	if ( ! salt_contact_configured() ) {
		$response = new WP_REST_Response( array( 'ok' => false ), 404 );
	} elseif ( ! salt_rate_limit( 'contact-credentials', 20, 600 ) ) {
		$response = new WP_REST_Response( array( 'ok' => false ), 429 );
	} else {
		$response = new WP_REST_Response(
			array(
				'ok'        => true,
				'formToken' => salt_contact_form_token(),
			),
			200
		);
	}
	$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
	return $response;
}

/**
 * POST /salt/v1/contact: one send, never cached.
 *
 * @param WP_REST_Request $request The request.
 * @return WP_REST_Response
 */
function salt_contact_rest_send( $request ): WP_REST_Response {
	$result   = salt_contact_handle( (array) $request->get_params(), 'rest' );
	$response = new WP_REST_Response(
		array(
			'ok'      => $result['ok'],
			'message' => $result['message'],
			'errors'  => (object) $result['errors'],
		),
		$result['status']
	);
	$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
	return $response;
}

/**
 * The forms answered in this response (the self-posted page), by the
 * contact band's anchor: set by salt_contact_self_post(), read by the
 * contact resolver. The 'salt_contact_form_states' filter is a test seam
 * (the contract adapter seeds a fixture's route.form through it).
 *
 * @param string|null              $anchor The answered band's anchor, to record its state.
 * @param array<string,mixed>|null $state  Its state.
 * @return array<string,array<string,mixed>>
 */
function salt_contact_form_states( ?string $anchor = null, ?array $state = null ): array {
	static $states = array();
	if ( null !== $anchor && null !== $state ) {
		$states[ $anchor ] = $state;
	}
	$given = apply_filters( 'salt_contact_form_states', $states );
	return is_array( $given ) ? $given : $states;
}

/**
 * A form's state after a send, as the page draws it again: invalid with
 * what was sent, its errors and the status, or sent and empty; either way
 * with fresh credentials (a challenge and the form token, SC-025), minted
 * for this response alone.
 *
 * @param array{ok: bool, message: string, errors: array<string,string>, values: array<string,string>} $result salt_contact_handle().
 * @return array{state: string, values: array<string,string>, errors: array<string,string>, status: array{tone: string, message: string}, challenge: array{question: string, token: string}, formToken: string}
 */
function salt_contact_response_state( array $result ): array {
	return array(
		'state'     => $result['ok'] ? 'sent' : 'invalid',
		'values'    => $result['ok'] ? array() : $result['values'],
		'errors'    => $result['ok'] ? array() : $result['errors'],
		'status'    => array(
			'tone'    => $result['ok'] ? 'sent' : 'problem',
			'message' => $result['message'],
		),
		'challenge' => salt_challenge_create(),
		'formToken' => salt_contact_form_token(),
	);
}

/**
 * The no-JS path: a contact form posted to its own page
 * (?salt-contact-send=<anchor>). Handled before the page draws, which then
 * draws that form again in its answer; never a redirect, and never cached
 * (nocache_headers(); a page cache serves GETs, never a POST).
 *
 * @return void
 */
function salt_contact_self_post(): void {
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : '';
	if ( 'POST' !== $method || ! isset( $_GET['salt-contact-send'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only; the form token's nonce is verified in salt_contact_handle().
		return;
	}
	$anchor = sanitize_title( wp_unslash( (string) $_GET['salt-contact-send'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
	if ( '' === $anchor ) {
		return;
	}
	nocache_headers();
	$result = salt_contact_handle( wp_unslash( $_POST ), 'page' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified + sanitised field-by-field in salt_contact_handle().
	if ( ! headers_sent() && 200 !== $result['status'] ) {
		status_header( $result['status'] );
	}
	salt_contact_form_states( $anchor, salt_contact_response_state( $result ) );
}
add_action( 'template_redirect', 'salt_contact_self_post', 1 );

/**
 * The pre-6.0 no-JS path (admin-post.php?action=salt_contact), kept for
 * one release as a thin adapter onto salt_contact_handle(), so a cached
 * old page or a child template still posting the old field names does not
 * fail. It redirects back with a status flag, as before. To be removed in
 * a later release (changelog).
 *
 * @return void
 */
function salt_contact_handle_nojs(): void {
	// Nonce is verified inside salt_contact_handle (step 3).
	$post   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Mapped here, verified + sanitised field-by-field in salt_contact_handle().
	$result = salt_contact_handle( salt_contact_legacy_fields( is_array( $post ) ? $post : array() ), 'legacy' );

	$back = wp_get_referer();
	$back = is_string( $back ) && '' !== $back ? $back : home_url( '/' );

	wp_safe_redirect( add_query_arg( 'salt-contact', $result['ok'] ? 'ok' : 'error', $back ) );
	exit;
}
add_action( 'admin_post_salt_contact', 'salt_contact_handle_nojs' );
add_action( 'admin_post_nopriv_salt_contact', 'salt_contact_handle_nojs' );

/**
 * A pre-6.0 form's fields under the contract's names: salt_contact_* to
 * name, email, message and enquiryType (an old form sends a route's
 * label, mapped to its value), salt_hp to website, salt_time and
 * salt_contact_nonce joined into formToken, salt_challenge_* to
 * challengeToken and challengeAnswer, salt_page_url to returnTo.
 *
 * @param array<string,mixed> $post The old form's fields.
 * @return array<string,mixed>
 */
function salt_contact_legacy_fields( array $post ): array {
	$get   = static fn( string $name ): string => is_scalar( $post[ $name ] ?? null ) ? (string) $post[ $name ] : '';
	$label = $get( 'salt_contact_type' );
	$type  = '';
	foreach ( salt_contact_enquiry_types() as $enquiry_type ) {
		if ( strtolower( $enquiry_type['label'] ) === strtolower( trim( $label ) ) ) {
			$type = $enquiry_type['value'];
			break;
		}
	}
	$fields = array(
		'name'            => $get( 'salt_contact_name' ),
		'email'           => $get( 'salt_contact_email' ),
		'message'         => $get( 'salt_contact_message' ),
		'enquiryType'     => $type,
		'formToken'       => $get( 'salt_time' ) . '.' . $get( 'salt_contact_nonce' ),
		'challengeToken'  => $get( 'salt_challenge_token' ),
		'challengeAnswer' => $get( 'salt_challenge_answer' ),
		'returnTo'        => $get( 'salt_page_url' ),
	);
	if ( isset( $post['salt_hp'] ) ) {
		$fields['website'] = $get( 'salt_hp' );
	}
	foreach ( array( 'cf-turnstile-response', 'g-recaptcha-response' ) as $captcha ) {
		if ( isset( $post[ $captcha ] ) ) {
			$fields[ $captcha ] = $get( $captcha );
		}
	}
	return $fields;
}

/**
 * The pre-6.0 no-JS path's status flag for the current request, from
 * ?salt-contact=ok|error: a fixed message, never text from the address.
 *
 * @return array{status: ''|'ok'|'error', message: string}
 */
function salt_contact_request_status(): array {
	// Read-only display of a redirect flag — no state change, no nonce needed.
	$flag = isset( $_GET['salt-contact'] ) ? sanitize_key( wp_unslash( (string) $_GET['salt-contact'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
		'message' => __( 'Your message could not be sent. Please check the form and try again.', 'salt' ),
	);
}
