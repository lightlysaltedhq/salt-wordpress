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
 * The age of a time-trap token in seconds, or null when it is not one this
 * site signed.
 *
 * @param string   $token Submitted token.
 * @param int|null $now   Injectable clock (tests).
 * @return int|null
 */
function salt_contact_time_age( string $token, ?int $now = null ): ?int {
	$parts = explode( '.', $token, 2 );
	if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
		return null;
	}
	if ( ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ) ), $parts[1] ) ) {
		return null;
	}
	return ( null === $now ? time() : $now ) - (int) $parts[0];
}

/**
 * Verify the time-trap token: ≥3s old (humans read), ≤4h old (stale form).
 *
 * @param string   $token Submitted token.
 * @param int|null $now   Injectable clock (tests).
 * @return bool
 */
function salt_contact_time_ok( string $token, ?int $now = null ): bool {
	$age = salt_contact_time_age( $token, $now );
	return null !== $age && $age >= 3 && $age <= 4 * HOUR_IN_SECONDS;
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
 * WordPress's public query vars: the query a page's own address may
 * carry (?page_id=7 on plain permalinks, ?s= on a search). The request's
 * WP object holds them, as filtered when it parsed the request; the core
 * set when there is none.
 *
 * @return string[]
 */
function salt_contact_public_query_vars(): array {
	$vars = isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->public_query_vars ) && is_array( $GLOBALS['wp']->public_query_vars )
		? $GLOBALS['wp']->public_query_vars
		: array( 'm', 'p', 'posts', 'w', 'cat', 'withcomments', 'withoutcomments', 's', 'search', 'exact', 'sentence', 'calendar', 'page', 'paged', 'more', 'tb', 'pb', 'author', 'order', 'orderby', 'year', 'monthnum', 'day', 'hour', 'minute', 'second', 'name', 'category_name', 'tag', 'feed', 'author_name', 'pagename', 'page_id', 'error', 'attachment', 'attachment_id', 'subpost', 'subpost_id', 'preview', 'robots', 'favicon', 'taxonomy', 'term', 'cpage', 'post_type', 'embed' );
	return array_map( 'strval', $vars );
}

/**
 * The page the form is on, as a same-site path: the form's returnTo, and
 * the address it posts to without script. From the request alone, so every
 * visitor to one page gets the same value (salt_contact_return_to() keeps
 * only WordPress's own query vars of it).
 *
 * @return string
 */
function salt_contact_page_path(): string {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Made a same-site path by salt_contact_return_to().
	$safe = salt_contact_return_to( $uri );
	return '' !== $safe ? $safe : '/';
}

/**
 * The page path a submission names in returnTo, same-site only: a path on
 * this site (an absolute URL on the home host is made relative first),
 * never a scheme-relative or foreign address, with only WordPress's public
 * query vars kept (a page's own address, never a requester's tracking or
 * other parameters, which would reach the cached page and the email).
 * '' when it is none.
 *
 * @param string $raw The submitted returnTo, or the request's address.
 * @return string
 */
function salt_contact_return_to( string $raw ): string {
	$url = salt_contract_url( trim( $raw ) );
	if ( 1 !== preg_match( '#^/(?![/\\\\])#', $url ) || 1 === preg_match( '/[\x00-\x1f\x7f]/', $url ) ) {
		return '';
	}
	$parts = wp_parse_url( $url );
	$path  = is_array( $parts ) ? (string) ( $parts['path'] ?? '/' ) : '/';
	$query = array();
	if ( is_array( $parts ) && isset( $parts['query'] ) ) {
		parse_str( (string) $parts['query'], $query );
		$query = array_filter( array_intersect_key( $query, array_flip( salt_contact_public_query_vars() ) ), 'is_scalar' );
	}
	return $path . ( array() !== $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' );
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

	$too_many      = __( 'Too many messages — please wait a few minutes and try again.', 'salt' );
	$generic_error = __( 'Your message could not be sent. Please check the form and try again.', 'salt' );
	$provider      = '' !== salt_captcha_provider();
	$captcha_token = $field( 'cf-turnstile-response' );
	$captcha_token = '' !== $captcha_token ? $captcha_token : $field( 'g-recaptcha-response' );

	// 1. Credentials (SC-025): a form token, and on the page path the
	// arithmetic challenge or, with a provider configured, its token (a
	// visitor whose script fell back to the native post). A send without
	// them (a no-JS visitor's first, always) is refused, and the form comes
	// back with them and the entries kept. That refusal mints credentials,
	// so it counts against the credentials bucket (SEC10: 20 / 10 minutes /
	// client), not against the visitor's five sends.
	$token = explode( '.', $field( 'formToken' ) );
	if ( 3 !== count( $token ) || ( 'page' === $path && '' === $field( 'challengeToken' ) && ! ( $provider && '' !== $captcha_token ) ) ) {
		if ( ! salt_rate_limit( 'contact-credentials', 20, 600 ) ) {
			return $fail( 429, $too_many );
		}
		return $fail( 400, __( 'Please check your details and send the form again.', 'salt' ) );
	}

	// 2. Rate limit (SEC10): 5 sends / 10 minutes / client.
	if ( ! salt_rate_limit( 'contact', 5, 600 ) ) {
		return $fail( 429, $too_many );
	}

	// 3. CSRF nonce (every path).
	if ( ! wp_verify_nonce( $token[2], 'salt_contact' ) ) {
		salt_log( 'contact: nonce failure (' . $path . ').' );
		return $fail( 403, $generic_error );
	}

	// 4. Honeypot: must exist in the payload, be a string AND be empty
	// (website[]=spam is an array, never an empty field: a bot).
	if ( ! isset( $raw['website'] ) || ! is_string( $raw['website'] ) || '' !== trim( $raw['website'] ) ) {
		salt_log( 'contact: honeypot tripped.' );
		// Indistinguishable from success to the bot.
		return $sent();
	}

	// 5. Time-trap (the form token's signed time). A token over 4 hours old
	// is a visitor's form left open, not a bot: 403, like a refused nonce,
	// so the script takes a fresh one and sends again (the page path draws
	// the form again with fresh credentials), and nothing is logged. One too
	// young, or not signed here, stays a 400.
	$time = $token[0] . '.' . $token[1];
	if ( ! salt_contact_time_ok( $time, $now ) ) {
		if ( ( salt_contact_time_age( $time, $now ) ?? 0 ) > 4 * HOUR_IN_SECONDS ) {
			return $fail( 403, __( 'The form had been open too long. Please send it again.', 'salt' ) );
		}
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
	$match = array_values( array_filter( $types, static fn( array $t ): bool => $t['value'] === $values['enquiryType'] ) );
	if ( array() !== $match ) {
		$type = $match[0]['label'];
	} elseif ( 'legacy' !== $path && ( '' !== $values['enquiryType'] || count( $types ) > 1 ) ) {
		// An unknown value is a field error, never the first route. The
		// pre-6.0 form keeps its old rule: an unmatched label is 'general'.
		$errors['enquiryType'] = __( 'Please choose what your enquiry is about.', 'salt' );
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

	// 7. CAPTCHA (SEC01/SEC02). A posted arithmetic challenge is checked
	// on the page path always and on the pre-6.0 form with a provider;
	// otherwise, with a provider configured, its token (the script path, and
	// a scripted visitor's native fallback on the page path).
	if ( '' !== $field( 'challengeToken' ) && ( 'page' === $path || ( $provider && 'legacy' === $path ) ) ) {
		if ( ! salt_challenge_verify( $field( 'challengeToken' ), $field( 'challengeAnswer' ) ) ) {
			$message = __( 'The anti-spam answer was wrong — please try again.', 'salt' );
			return $fail( 400, $message, array( 'challengeAnswer' => $message ) );
		}
	} elseif ( $provider ) {
		$verify = salt_captcha_verify( $captcha_token, salt_client_ip() );
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
 * WordPress's no-cache headers on a REST response, leaving out those core
 * sets to false or '' to mean "send none" (Last-Modified), so no empty
 * header goes out.
 *
 * @param WP_REST_Response $response The response.
 * @return WP_REST_Response
 */
function salt_contact_nocache( WP_REST_Response $response ): WP_REST_Response {
	foreach ( wp_get_nocache_headers() as $name => $value ) {
		if ( false !== $value && '' !== $value ) {
			$response->header( $name, (string) $value );
		}
	}
	return $response;
}

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
	return salt_contact_nocache( $response );
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
	return salt_contact_nocache( $response );
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
 * @param array{ok: bool, status: int, message: string, errors: array<string,string>, values: array<string,string>} $result salt_contact_handle().
 * @return array{state: string, values: array<string,string>, errors: array<string,string>, status: array{tone: string, message: string}, challenge?: array{question: string, token: string}, formToken?: string}
 */
function salt_contact_response_state( array $result ): array {
	$state = array(
		'state'  => $result['ok'] ? 'sent' : 'invalid',
		'values' => $result['ok'] ? array() : $result['values'],
		'errors' => $result['ok'] ? array() : $result['errors'],
		'status' => array(
			'tone'    => $result['ok'] ? 'sent' : 'problem',
			'message' => $result['message'],
		),
	);
	// A refused client (429) gets no fresh credentials: nothing is minted
	// until the limit lifts, and the form is drawn without them.
	if ( 429 !== ( $result['status'] ?? 0 ) ) {
		$state['challenge'] = salt_challenge_create();
		$state['formToken'] = salt_contact_form_token();
	}
	return $state;
}

/**
 * Whether this request is a contact form posted to its own page
 * (POST ?salt-contact-send=<anchor>, with the form's returnTo in the body):
 * the anchor, or '' when it is not. The flag stays in an answered page's
 * address, so another form on that page posts there too; without the
 * contact form's own field it is not a contact send, and spends nothing.
 *
 * @return string
 */
function salt_contact_self_post_anchor(): string {
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : '';
	if ( 'POST' !== $method || ! isset( $_GET['salt-contact-send'], $_POST['returnTo'] ) || ! is_scalar( $_GET['salt-contact-send'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- Routing only; the form token's nonce is verified in salt_contact_handle().
		return '';
	}
	return sanitize_title( wp_unslash( (string) $_GET['salt-contact-send'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
}

/**
 * The fields a self-posted contact form sent: set aside before WordPress
 * parses the request (salt_contact_self_post_aside()), read by
 * salt_contact_self_post().
 *
 * @param array<string,mixed>|null $fields The fields, to set them aside.
 * @return array<string,mixed>|null Null when none were set aside.
 */
function salt_contact_self_post_fields( ?array $fields = null ): ?array {
	static $aside = null;
	if ( null !== $fields ) {
		$aside = $fields;
	}
	return $aside;
}

/**
 * The posted fields taken out of $_POST while WordPress parses a
 * self-posted contact form's request, as they were in $_POST, for
 * salt_contact_self_post_restore() to put back.
 *
 * @param array<string,mixed>|null $removed The fields taken out, to hold them.
 * @return array<string,mixed>
 */
function salt_contact_self_post_removed( ?array $removed = null ): array {
	static $held = array();
	if ( null !== $removed ) {
		$held = $removed;
	}
	return $held;
}

/**
 * Keep a self-posted contact form's fields out of WordPress's query
 * ('do_parse_request', last, and only when WordPress goes on to parse the
 * request). WP::parse_request() reads every public query var from $_POST
 * before the address, so a posted `name` (a contact field and a public
 * query var) would become a post slug ahead of the page's own and the page
 * would answer 404, with no form. The posted fields are set aside for
 * salt_contact_self_post(), and salt_contact_self_post_strip() takes each
 * public query var out of $_POST once WordPress has its final list, so the
 * address and its rewrite rule alone decide the query (a single post keeps
 * its permalink's own name). They are put back first thing on
 * 'parse_request', before anything there (the REST API) reads $_POST.
 *
 * @param bool $parse Whether WordPress parses the request.
 * @return bool
 */
function salt_contact_self_post_aside( $parse ) {
	if ( ! $parse || '' === salt_contact_self_post_anchor() ) {
		return $parse;
	}
	$posted = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Set aside as sent; verified + sanitised field-by-field in salt_contact_handle().
	salt_contact_self_post_fields( is_array( $posted ) ? $posted : array() );
	add_filter( 'query_vars', 'salt_contact_self_post_strip', PHP_INT_MAX );
	return $parse;
}
add_filter( 'do_parse_request', 'salt_contact_self_post_aside', PHP_INT_MAX );

/**
 * Take the public query vars out of a self-posted form's $_POST ('query_vars',
 * last, once: WP::parse_request() applies it just before it reads $_POST),
 * holding what it takes for salt_contact_self_post_restore(). The list
 * passes through unchanged.
 *
 * @param mixed $vars WordPress's public query vars.
 * @return mixed
 */
function salt_contact_self_post_strip( $vars ) {
	remove_filter( 'query_vars', 'salt_contact_self_post_strip', PHP_INT_MAX );
	$removed = array();
	foreach ( is_array( $vars ) ? $vars : array() as $var ) {
		if ( is_string( $var ) && array_key_exists( $var, $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Held, not read; put back on 'parse_request'.
			$removed[ $var ] = $_POST[ $var ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- As above.
			unset( $_POST[ $var ] );
		}
	}
	salt_contact_self_post_removed( $removed );
	return $vars;
}

/**
 * Put back the fields salt_contact_self_post_strip() took out of $_POST,
 * exactly as they were, and nothing else ('parse_request', priority 0:
 * before core's rest_api_loaded, so a REST request with the flag in its
 * address still reads every field it was sent).
 *
 * @return void
 */
function salt_contact_self_post_restore(): void {
	foreach ( salt_contact_self_post_removed() as $var => $value ) {
		$_POST[ $var ] = $value; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Restoring the request as it arrived.
	}
	salt_contact_self_post_removed( array() );
}
add_action( 'parse_request', 'salt_contact_self_post_restore', 0 );

/**
 * The no-JS path: a contact form posted to its own page
 * (?salt-contact-send=<anchor>). Handled before the page draws, which then
 * draws that form again in its answer; never a redirect, and never cached
 * (nocache_headers(); a page cache serves GETs, never a POST). With the
 * form off, nothing is handled and nothing minted: the page draws its
 * closed message.
 *
 * @return void
 */
function salt_contact_self_post(): void {
	$anchor = salt_contact_self_post_anchor();
	if ( '' === $anchor || ! salt_contact_configured() ) {
		return;
	}
	nocache_headers();
	$fields = salt_contact_self_post_fields();
	$fields = null !== $fields ? $fields : wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified + sanitised field-by-field in salt_contact_handle().
	$result = salt_contact_handle( is_array( $fields ) ? $fields : array(), 'page' );
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
