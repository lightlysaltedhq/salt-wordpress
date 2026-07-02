<?php
/**
 * CAPTCHA verification + no-JS arithmetic challenge for the contact form
 * (review SEC01/SEC02, PRIV02).
 *
 * Providers: Cloudflare Turnstile (recommended — privacy-preserving, no
 * cookie banner interaction needed) and Google reCAPTCHA v2. The provider
 * choice and its PUBLIC site key are site-configurable ACF options
 * (inc/fields/options-contact.php). SECRET keys are wp-config constants —
 * SALT_TURNSTILE_SECRET / SALT_RECAPTCHA_SECRET — and are NEVER stored in
 * the database (SEC03: secrets live in config custody, options hold only
 * non-secrets).
 *
 * Third-party load rationale (PRIV02): the provider's widget script is an
 * EXPLICITLY ALLOWED external asset, classified 'essential' under
 * salt_consent() — anti-fraud protection that is strictly necessary for
 * the visitor to submit the contact form, not tracking. It loads ONLY when
 * a provider is fully configured AND the current page actually contains
 * the contact section; a site disables the load entirely by unsetting the
 * provider option. The CSP is widened for exactly the active provider's
 * origins via the dedicated salt_captcha_csp_policy() callback below.
 *
 * Fail-closed contract (SEC02): no provider, missing secret or site key,
 * empty token, network error, non-200, unparseable JSON, success !== true,
 * or a hostname mismatch → the token does NOT verify. SEC01: when a
 * provider is configured, no-JS visitors get the tokenless arithmetic
 * challenge (salt_challenge_create()/salt_challenge_verify()) instead of a
 * silent lock-out.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static configuration for a CAPTCHA provider.
 *
 * @param string $provider Provider slug ('turnstile'|'recaptcha').
 * @return array<string,mixed>|null Null for unknown providers.
 */
function salt_captcha_provider_config( string $provider ): ?array {
	$config = array(
		'turnstile' => array(
			'secret_constant' => 'SALT_TURNSTILE_SECRET',
			'site_key_field'  => 'captcha_turnstile_site_key',
			'verify_url'      => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			'script_url'      => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
			'widget_class'    => 'cf-turnstile',
			'csp_origins'     => array( 'https://challenges.cloudflare.com' ),
		),
		'recaptcha' => array(
			'secret_constant' => 'SALT_RECAPTCHA_SECRET',
			'site_key_field'  => 'captcha_recaptcha_site_key',
			'verify_url'      => 'https://www.google.com/recaptcha/api/siteverify',
			'script_url'      => 'https://www.google.com/recaptcha/api.js',
			'widget_class'    => 'g-recaptcha',
			'csp_origins'     => array( 'https://www.google.com', 'https://www.gstatic.com' ),
		),
	);

	return $config[ $provider ] ?? null;
}

/**
 * The active provider's SECRET key, from its wp-config constant (SEC03 —
 * never an option, never the database).
 *
 * @param string $provider Provider slug.
 * @return string Empty string when undefined/blank.
 */
function salt_captcha_secret( string $provider ): string {
	$config = salt_captcha_provider_config( $provider );
	if ( null === $config || ! defined( $config['secret_constant'] ) ) {
		return '';
	}

	return (string) constant( $config['secret_constant'] );
}

/**
 * The active provider's PUBLIC site key, from the options page.
 *
 * @param string $provider Provider slug.
 * @return string Empty string when unset.
 */
function salt_captcha_site_key( string $provider ): string {
	$config = salt_captcha_provider_config( $provider );
	if ( null === $config || ! salt_acf_active() ) {
		return '';
	}

	return (string) ( salt_option( $config['site_key_field'] ) ?? '' );
}

/**
 * Log a captcha configuration problem once per request (fail loud, but
 * not once per form render).
 *
 * @param string $message Log line ('' with $reset to clear).
 * @param bool   $reset   Clear the seen-set (test hermeticity).
 * @return void
 */
function salt_captcha_log_once( string $message, bool $reset = false ): void {
	static $seen = array();

	if ( $reset ) {
		$seen = array();
		return;
	}

	if ( isset( $seen[ $message ] ) ) {
		return;
	}

	$seen[ $message ] = true;
	salt_log( $message );
}

/**
 * Reset per-request captcha state (test hermeticity companion — see
 * CONVENTIONS.md "State").
 *
 * @return void
 */
function salt_captcha_reset(): void {
	salt_captcha_log_once( '', true );
}

/**
 * The configured-and-usable CAPTCHA provider.
 *
 * Returns '' when unconfigured: no provider selected, or a provider is
 * selected but its secret constant or site key is missing (logged once —
 * a half-configured provider must not silently drop protection nor render
 * a broken widget; the contact form falls back to the no-JS challenge).
 *
 * @return string ''|'turnstile'|'recaptcha'.
 */
function salt_captcha_provider(): string {
	if ( ! salt_acf_active() ) {
		return '';
	}

	$provider = (string) ( salt_option( 'captcha_provider' ) ?? '' );
	if ( '' === $provider ) {
		return '';
	}

	$config = salt_captcha_provider_config( $provider );
	if ( null === $config ) {
		salt_captcha_log_once( sprintf( 'salt_captcha_provider: unknown provider "%s" — treating as unconfigured (fail-closed).', $provider ) );
		return '';
	}

	if ( '' === salt_captcha_secret( $provider ) ) {
		salt_captcha_log_once( sprintf( 'salt_captcha_provider: "%1$s" selected but the %2$s constant is not defined in wp-config (SEC03) — treating as unconfigured.', $provider, $config['secret_constant'] ) );
		return '';
	}

	if ( '' === salt_captcha_site_key( $provider ) ) {
		salt_captcha_log_once( sprintf( 'salt_captcha_provider: "%s" selected but its site key option is empty — treating as unconfigured.', $provider ) );
		return '';
	}

	return $provider;
}

/**
 * Verify a CAPTCHA token against the provider's siteverify API (SEC02).
 *
 * FAIL CLOSED: every ambiguous outcome (unconfigured, empty token, network
 * error, non-200, unparseable JSON, success !== true, hostname mismatch)
 * returns ok:false.
 *
 * @param string $token     The client-side widget token.
 * @param string $client_ip The submitter's IP (forwarded as remoteip).
 * @return array{ok: bool, reason: string}
 */
function salt_captcha_verify( string $token, string $client_ip ): array {
	$provider = salt_captcha_provider();
	if ( '' === $provider ) {
		return array(
			'ok'     => false,
			'reason' => 'unconfigured',
		);
	}

	if ( '' === trim( $token ) ) {
		return array(
			'ok'     => false,
			'reason' => 'empty-token',
		);
	}

	$config   = salt_captcha_provider_config( $provider );
	$response = wp_remote_post(
		$config['verify_url'],
		array(
			'timeout' => 5,
			'body'    => array(
				'secret'   => salt_captcha_secret( $provider ),
				'response' => $token,
				'remoteip' => $client_ip,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		salt_log( sprintf( 'salt_captcha_verify: %1$s siteverify request failed — %2$s (fail-closed).', $provider, $response->get_error_message() ) );
		return array(
			'ok'     => false,
			'reason' => 'network-error',
		);
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		salt_log( sprintf( 'salt_captcha_verify: %1$s siteverify returned HTTP %2$d (fail-closed).', $provider, $code ) );
		return array(
			'ok'     => false,
			'reason' => 'bad-status',
		);
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) ) {
		salt_log( sprintf( 'salt_captcha_verify: %s siteverify body was not valid JSON (fail-closed).', $provider ) );
		return array(
			'ok'     => false,
			'reason' => 'bad-json',
		);
	}

	if ( true !== ( $data['success'] ?? false ) ) {
		return array(
			'ok'     => false,
			'reason' => 'not-verified',
		);
	}

	// When the provider echoes the solving hostname, it must be OUR host —
	// a token solved on another site must not verify here.
	if ( isset( $data['hostname'] ) && is_string( $data['hostname'] ) && '' !== $data['hostname'] ) {
		$home_host = strtolower( (string) ( wp_parse_url( home_url() )['host'] ?? '' ) );
		if ( strtolower( $data['hostname'] ) !== $home_host ) {
			salt_log( sprintf( 'salt_captcha_verify: %1$s token was solved on "%2$s", expected "%3$s" — rejected (fail-closed).', $provider, $data['hostname'], $home_host ) );
			return array(
				'ok'     => false,
				'reason' => 'hostname-mismatch',
			);
		}
	}

	return array(
		'ok'     => true,
		'reason' => 'verified',
	);
}

/**
 * The provider's client widget container markup.
 *
 * @return string Empty string when no provider is active.
 */
function salt_captcha_widget_html(): string {
	$provider = salt_captcha_provider();
	if ( '' === $provider ) {
		return '';
	}

	$config = salt_captcha_provider_config( $provider );

	return sprintf(
		'<div class="%1$s" data-sitekey="%2$s"></div>',
		esc_attr( $config['widget_class'] ),
		esc_attr( salt_captcha_site_key( $provider ) )
	);
}

/**
 * Enqueue the active provider's widget script — ONLY on pages containing
 * the contact section ('wp_enqueue_scripts').
 *
 * This is the module's explicitly allowed third-party load, classified
 * 'essential' (anti-fraud, strictly necessary for the form — PRIV02).
 * Sites disable it by unsetting the provider option.
 *
 * @return void
 */
function salt_captcha_enqueue(): void {
	$provider = salt_captcha_provider();
	if ( '' === $provider || ! salt_consent( 'essential' ) ) {
		return;
	}

	if ( ! in_array( 'contact', salt_page_section_slugs(), true ) ) {
		return;
	}

	wp_enqueue_script(
		'salt-captcha',
		salt_captcha_provider_config( $provider )['script_url'],
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Third-party evergreen endpoint; the provider versions it.
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'salt_captcha_enqueue' );

/**
 * Widen the CSP for exactly the active provider's origins — script-src and
 * frame-src only, and only while a provider is fully configured
 * ('salt_csp_policy', priority 10).
 *
 * @param string $csp The composed policy string.
 * @return string
 */
function salt_captcha_csp_policy( string $csp ): string {
	$provider = salt_captcha_provider();
	if ( '' === $provider ) {
		return $csp;
	}

	$extra      = implode( ' ', salt_captcha_provider_config( $provider )['csp_origins'] );
	$directives = array_map( 'trim', explode( ';', $csp ) );

	foreach ( array( 'script-src', 'frame-src' ) as $directive ) {
		$appended = false;
		foreach ( $directives as $i => $existing ) {
			if ( $existing === $directive || str_starts_with( $existing, $directive . ' ' ) ) {
				$directives[ $i ] = $existing . ' ' . $extra;
				$appended         = true;
				break;
			}
		}
		if ( ! $appended ) {
			$directives[] = $directive . " 'self' " . $extra;
		}
	}

	return implode( '; ', $directives );
}
add_filter( 'salt_csp_policy', 'salt_captcha_csp_policy' );

/**
 * Create a no-JS arithmetic challenge (SEC01 — the tokenless path when a
 * provider is configured and the visitor has no JavaScript).
 *
 * The answer never travels to the client: it is stored server-side in a
 * transient keyed by a random token, TTL 900s, single-use.
 *
 * @return array{question: string, token: string}
 */
function salt_challenge_create(): array {
	$a     = random_int( 1, 9 );
	$b     = random_int( 1, 9 );
	$token = bin2hex( random_bytes( 16 ) );

	set_transient( 'salt_challenge_' . $token, (string) ( $a + $b ), 900 );

	return array(
		'question' => sprintf(
			/* translators: 1: first number. 2: second number. */
			__( 'What is %1$d plus %2$d?', 'salt' ),
			$a,
			$b
		),
		'token'    => $token,
	);
}

/**
 * Verify a no-JS challenge answer. Single-use: the stored answer is burned
 * on first read, right or wrong.
 *
 * @param string $token  The challenge token from salt_challenge_create().
 * @param string $answer The visitor's answer.
 * @return bool False on missing/expired token, malformed input, or a wrong answer.
 */
function salt_challenge_verify( string $token, string $answer ): bool {
	if ( 1 !== preg_match( '/^[0-9a-f]{32}$/', $token ) ) {
		return false;
	}

	$stored = get_transient( 'salt_challenge_' . $token );
	delete_transient( 'salt_challenge_' . $token );

	if ( ! is_string( $stored ) && ! is_int( $stored ) ) {
		return false;
	}

	$answer = trim( $answer );
	if ( 1 !== preg_match( '/^-?[0-9]+$/', $answer ) ) {
		return false;
	}

	return (int) $stored === (int) $answer;
}
