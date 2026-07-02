<?php
/**
 * Rate limiting — abuse throttling for public endpoints (SEC10).
 *
 * Transient-backed sliding-window counters keyed by a salted hash of
 * bucket + client IP, so no raw IP ever reaches the database or the log
 * channel. Client IP resolution NEVER trusts forwarded headers unless the
 * site has explicitly opted in via the SALT_TRUSTED_PROXY_HEADER wp-config
 * constant (SEC03: infrastructure trust lives in config, not the DB).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the client IP for rate limiting (SEC10 contract).
 *
 * Trust model:
 * - Default: REMOTE_ADDR only. X-Forwarded-For and friends are
 *   client-controlled and trivially spoofable, so they are IGNORED.
 * - ONLY when the wp-config constant SALT_TRUSTED_PROXY_HEADER is defined
 *   (e.g. 'HTTP_CF_CONNECTING_IP' behind Cloudflare, or
 *   'HTTP_X_FORWARDED_FOR' behind a proxy the site controls) is that
 *   $_SERVER key read instead. Defining the constant is the site's
 *   assertion that the edge strips/overwrites the header, making it
 *   trustworthy. For list-valued headers (X-Forwarded-For) the FIRST
 *   entry — the original client as seen by the trusted edge — is used.
 * - Anything that does not validate as an IP (v4 or v6) resolves to
 *   '0.0.0.0' and is logged: throttling then degrades to a shared bucket
 *   rather than being bypassable (fail closed).
 *
 * @return string Validated IP address, or '0.0.0.0' when unresolvable.
 */
function salt_client_ip(): string {
	$source = 'REMOTE_ADDR';

	if ( defined( 'SALT_TRUSTED_PROXY_HEADER' ) && is_string( SALT_TRUSTED_PROXY_HEADER ) && '' !== SALT_TRUSTED_PROXY_HEADER ) {
		$source = SALT_TRUSTED_PROXY_HEADER;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised below: strict FILTER_VALIDATE_IP allow-list, invalid input never propagates.
	$raw = isset( $_SERVER[ $source ] ) ? (string) wp_unslash( $_SERVER[ $source ] ) : '';

	// List-valued forwarded headers: the FIRST value is the client.
	if ( str_contains( $raw, ',' ) ) {
		$raw = explode( ',', $raw )[0];
	}
	$raw = trim( $raw );

	$ip = filter_var( $raw, FILTER_VALIDATE_IP );
	if ( false === $ip ) {
		salt_log( sprintf( 'salt_client_ip: invalid or missing IP in %s — using 0.0.0.0 (fail-closed shared bucket).', $source ) );
		return '0.0.0.0';
	}

	return $ip;
}

/**
 * Check-and-count a request against a rate-limit bucket.
 *
 * Sliding-window-ish counter in a transient keyed by
 * 'salt_rl_' + sha256( bucket | client IP | wp_salt('nonce') ) truncated —
 * the raw IP is never persisted or logged. The counter is incremented
 * BEFORE the verdict, so bursts racing the limit cannot earn a free pass.
 *
 * @param string $bucket         Endpoint/action identifier (e.g. 'contact_submit').
 * @param int    $limit          Max allowed requests per window (>= 1).
 * @param int    $window_seconds Window length in seconds (>= 1).
 * @return bool True = allowed; false = throttled (or invalid args — fail closed).
 */
function salt_rate_limit( string $bucket, int $limit, int $window_seconds ): bool {
	if ( '' === $bucket || $limit < 1 || $window_seconds < 1 ) {
		salt_log( sprintf( 'salt_rate_limit: invalid configuration for bucket "%s" (limit %d, window %d) — denied (fail-closed).', $bucket, $limit, $window_seconds ) );
		return false;
	}

	$key = 'salt_rl_' . substr( hash( 'sha256', $bucket . '|' . salt_client_ip() . '|' . wp_salt( 'nonce' ) ), 0, 24 );
	$now = time();

	$state = get_transient( $key );
	if (
		! is_array( $state )
		|| ! isset( $state['count'], $state['start'] )
		|| ( $now - (int) $state['start'] ) >= $window_seconds
	) {
		// No state, corrupt state, or expired window: start a fresh window.
		$state = array(
			'count' => 0,
			'start' => $now,
		);
	}

	// Increment BEFORE deciding — no race-window free pass.
	$state['count'] = (int) $state['count'] + 1;
	set_transient( $key, $state, $window_seconds );

	if ( $state['count'] > $limit ) {
		salt_log( sprintf( 'salt_rate_limit: bucket "%s" over limit (%d/%d in %ds) — key %s.', $bucket, $state['count'], $limit, $window_seconds, $key ) );
		return false;
	}

	return true;
}

/**
 * Send the Retry-After header for a throttled response.
 *
 * @param int $retry_after Seconds until the client may retry.
 * @return void
 */
function salt_rate_limit_headers( int $retry_after ): void {
	if ( headers_sent() ) {
		return;
	}

	header( 'Retry-After: ' . max( 0, $retry_after ) );
}
