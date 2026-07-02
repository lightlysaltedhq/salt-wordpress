<?php
/**
 * Analytics integrations: PostHog (first-party bundled) + GA4 (Consent Mode).
 *
 * CONSENT ARCHITECTURE (deliberate — Consent Mode pattern, NOT hard-gated on
 * salt_consent()): both providers boot in a privacy-default state and the
 * consent.js entry flips them when the CMP grants consent.
 *
 * - GA4 loads gtag.js with Consent Mode v2 defaults DENIED for all four
 *   storage signals BEFORE the tag loads (Advanced mode: cookieless pings
 *   until the CMP updates via gtag('consent','update',…)).
 * - PostHog is bundled first-party (no CDN — the posthog-js library ships
 *   inside assets/dist/analytics/posthog.*.js) and inits with
 *   cookieless_mode 'on_reject': until the visitor grants 'statistics' it
 *   runs cookieless. NOTE: cookieless_mode 'on_reject' requires "Cookieless
 *   server hash mode" enabled in the PostHog project settings (also noted in
 *   the option field instructions).
 *
 * Both integrations are OFF by default; when both are disabled this module
 * emits NOTHING — no output, no enqueues, no CSP changes. Each provider owns
 * a dedicated 'salt_csp_policy' callback (priority 10) appending only its own
 * origins, only while active. Keys/IDs here are public non-secrets (options
 * storage is fine — no SEC03 constant involved).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The canonical PostHog Cloud ingestion hosts.
 *
 * @return array<string,string> Choice => origin.
 */
function salt_analytics_posthog_hosts(): array {
	return array(
		'us' => 'https://us.i.posthog.com',
		'eu' => 'https://eu.i.posthog.com',
	);
}

/**
 * Resolved PostHog client config, or null when the integration is inactive.
 *
 * Fail-closed: disabled toggle, missing key, or an unresolvable host all
 * yield null (misconfiguration logs — fail loud, ARCH05).
 *
 * @return array{key:string,host:string,recording:bool}|null
 */
function salt_analytics_posthog_config(): ?array {
	if ( ! salt_acf_active() || true !== (bool) salt_option( 'posthog_enabled' ) ) {
		return null;
	}

	$key = trim( (string) salt_option( 'posthog_key' ) );
	if ( '' === $key ) {
		salt_log( 'salt_analytics: PostHog is enabled but no project API key is set — integration disabled.' );
		return null;
	}

	$host = salt_analytics_posthog_host();
	if ( null === $host ) {
		return null;
	}

	return array(
		'key'       => $key,
		'host'      => $host,
		'recording' => true === (bool) salt_option( 'posthog_session_recording' ),
	);
}

/**
 * Resolve the configured PostHog api_host (us/eu/custom), or null (logged).
 *
 * @return string|null Origin URL without trailing slash.
 */
function salt_analytics_posthog_host(): ?string {
	$choice = (string) salt_option( 'posthog_host' );
	if ( '' === $choice ) {
		$choice = 'us';
	}

	$hosts = salt_analytics_posthog_hosts();
	if ( isset( $hosts[ $choice ] ) ) {
		return $hosts[ $choice ];
	}

	if ( 'custom' !== $choice ) {
		salt_log( sprintf( 'salt_analytics: unknown PostHog host choice "%s" — integration disabled (fail-closed).', $choice ) );
		return null;
	}

	$custom = trim( (string) salt_option( 'posthog_host_custom' ) );
	$scheme = '' !== $custom ? wp_parse_url( $custom, PHP_URL_SCHEME ) : null;
	$host   = '' !== $custom ? wp_parse_url( $custom, PHP_URL_HOST ) : null;

	if ( '' === $custom || ! in_array( $scheme, array( 'http', 'https' ), true ) || ! is_string( $host ) || '' === $host ) {
		salt_log( 'salt_analytics: PostHog host is set to "custom" but the custom endpoint URL is missing or invalid — integration disabled (fail-closed).' );
		return null;
	}

	return rtrim( $custom, '/' );
}

/**
 * The validated GA4 measurement ID, or null when the integration is inactive.
 *
 * Validated on OUTPUT against ^G-[A-Z0-9]+$ — an invalid ID never reaches
 * markup, a URL, or the CSP (skip + log).
 *
 * @return string|null
 */
function salt_analytics_ga4_id(): ?string {
	if ( ! salt_acf_active() || true !== (bool) salt_option( 'ga4_enabled' ) ) {
		return null;
	}

	$ga4_id = trim( (string) salt_option( 'ga4_id' ) );
	if ( 1 !== preg_match( '/^G-[A-Z0-9]+$/', $ga4_id ) ) {
		salt_log( sprintf( 'salt_analytics: GA4 measurement ID "%s" is invalid (expected G-XXXXXXXXXX) — GA4 output skipped.', $ga4_id ) );
		return null;
	}

	return $ga4_id;
}

/**
 * Which analytics providers are active on this request.
 *
 * @return array{posthog:bool,ga4:bool}
 */
function salt_analytics_active(): array {
	return array(
		'posthog' => null !== salt_analytics_posthog_config(),
		'ga4'     => null !== salt_analytics_ga4_id(),
	);
}

/**
 * Build the GA4 head snippet (pure — unit-tested on the string).
 *
 * Order is the Consent Mode v2 contract: dataLayer bootstrap + consent
 * defaults DENIED (with wait_for_update) in a nonce'd inline script, THEN
 * the async gtag.js loader, then gtag('js')/gtag('config').
 *
 * @param string $ga4_id Validated measurement ID (^G-[A-Z0-9]+$).
 * @return string HTML.
 */
function salt_analytics_ga4_snippet( string $ga4_id ): string {
	$nonce  = esc_attr( salt_csp_nonce() );
	$id     = (string) wp_json_encode( $ga4_id );
	$loader = esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga4_id ) );

	$bootstrap = 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
		. "gtag('consent','default',{ad_storage:'denied',analytics_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',wait_for_update:500});";

	$config = "gtag('js',new Date());gtag('config'," . $id . ');';

	return sprintf(
		'<script nonce="%1$s">%2$s</script>' . "\n"
		. '<script async src="%3$s"></script>' . "\n" // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Consent Mode v2 contract: the loader must sit BETWEEN the two inline nonce'd scripts, an ordering wp_enqueue_script() cannot express.
		. '<script nonce="%1$s">%4$s</script>' . "\n",
		$nonce,
		$bootstrap,
		$loader,
		$config
	);
}

/**
 * Output the GA4 snippet ('wp_head', priority 1 — the consent defaults must
 * precede every other script on the page).
 *
 * @return void
 */
function salt_output_ga4_snippet(): void {
	$ga4_id = salt_analytics_ga4_id();
	if ( null === $ga4_id ) {
		return;
	}

	echo salt_analytics_ga4_snippet( $ga4_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed literal script; the only variable part is the ^G-[A-Z0-9]+$-validated ID, JSON- and URL-encoded inside the builder.
}
add_action( 'wp_head', 'salt_output_ga4_snippet', 1 );

/**
 * Output the PostHog config data block ('wp_head').
 *
 * Deliberately UN-nonced: <script type="application/json"> is a
 * non-executable data block — the browser never runs it, and CSP script-src
 * only governs executable scripts, so no nonce is needed (adding one would
 * only imply the block is executable).
 *
 * @return void
 */
function salt_output_posthog_config(): void {
	$config = salt_analytics_posthog_config();
	if ( null === $config ) {
		return;
	}

	printf(
		'<script type="application/json" id="salt-posthog-config">%s</script>' . "\n",
		wp_json_encode( $config, JSON_HEX_TAG ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON data block; JSON_HEX_TAG escapes <> so the payload cannot break out of the script element.
	);
}
add_action( 'wp_head', 'salt_output_posthog_config', 1 );

/**
 * Enqueue the built analytics entries ('wp_enqueue_scripts').
 *
 * All handles use $ver = null — the content hash in the manifest filename is
 * the cache-buster (ARCH03). posthog.js only when PostHog is active; the
 * consent wiring + events adapter when EITHER provider is active (they
 * typeof-guard each provider, so a single-provider page loads clean).
 *
 * @return void
 */
function salt_analytics_enqueue(): void {
	$active = salt_analytics_active();

	if ( ! $active['posthog'] && ! $active['ga4'] ) {
		return;
	}

	$defer = array(
		'strategy'  => 'defer',
		'in_footer' => true,
	);
	$deps  = array();

	if ( $active['posthog'] ) {
		$src = salt_asset( 'analytics/posthog.js' );
		if ( null !== $src ) {
			wp_enqueue_script( 'salt-analytics-posthog', $src, array(), null, $defer );
			$deps = array( 'salt-analytics-posthog' );
		}
	}

	foreach ( array( 'consent', 'events' ) as $entry ) {
		$src = salt_asset( 'analytics/' . $entry . '.js' );
		if ( null !== $src ) {
			wp_enqueue_script( 'salt-analytics-' . $entry, $src, $deps, null, $defer );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'salt_analytics_enqueue' );

/**
 * Append sources to named CSP directives (module-internal helper).
 *
 * @param string                       $csp       The composed policy string.
 * @param array<string,array<string>> $additions Directive => origins to append.
 * @return string
 */
function salt_analytics_csp_append( string $csp, array $additions ): string {
	$directives = array_filter( array_map( 'trim', explode( ';', $csp ) ) );

	foreach ( $additions as $directive => $sources ) {
		$appended = false;
		foreach ( $directives as $i => $existing ) {
			if ( $existing === $directive || str_starts_with( $existing, $directive . ' ' ) ) {
				$directives[ $i ] = $existing . ' ' . implode( ' ', $sources );
				$appended         = true;
				break;
			}
		}
		if ( ! $appended ) {
			$directives[] = $directive . ' ' . implode( ' ', $sources );
		}
	}

	return implode( '; ', $directives );
}

/**
 * CSP extension for GA4 ('salt_csp_policy', priority 10) — appends only the
 * Google origins, only while GA4 is active.
 *
 * @param string $csp Composed policy.
 * @return string
 */
function salt_analytics_ga4_csp( string $csp ): string {
	if ( null === salt_analytics_ga4_id() ) {
		return $csp;
	}

	return salt_analytics_csp_append(
		$csp,
		array(
			'script-src'  => array( 'https://www.googletagmanager.com' ),
			'connect-src' => array( 'https://*.google-analytics.com', 'https://www.googletagmanager.com' ),
			'img-src'     => array( 'https://*.google-analytics.com' ),
		)
	);
}
add_filter( 'salt_csp_policy', 'salt_analytics_ga4_csp' );

/**
 * CSP extension for PostHog ('salt_csp_policy', priority 10) — the bundle is
 * first-party, so only the configured ingestion origin joins connect-src,
 * only while PostHog is active.
 *
 * @param string $csp Composed policy.
 * @return string
 */
function salt_analytics_posthog_csp( string $csp ): string {
	$config = salt_analytics_posthog_config();
	if ( null === $config ) {
		return $csp;
	}

	$scheme = wp_parse_url( $config['host'], PHP_URL_SCHEME );
	$host   = wp_parse_url( $config['host'], PHP_URL_HOST );
	$port   = wp_parse_url( $config['host'], PHP_URL_PORT );

	if ( ! is_string( $scheme ) || ! is_string( $host ) || '' === $host ) {
		return $csp; // Unreachable after config validation; fail closed anyway.
	}

	$origin = $scheme . '://' . $host . ( is_int( $port ) ? ':' . $port : '' );

	return salt_analytics_csp_append( $csp, array( 'connect-src' => array( $origin ) ) );
}
add_filter( 'salt_csp_policy', 'salt_analytics_posthog_csp' );
