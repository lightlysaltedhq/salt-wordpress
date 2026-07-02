<?php
/**
 * HMAC-signed webhook dispatch (spec §9; SEC11 contract).
 *
 * Signing secret: the SALT_WEBHOOK_SECRET wp-config constant — NEVER the
 * database (SEC03). When it is undefined, dispatch is a silent no-op with
 * no targets and a LOGGED no-op when targets exist: salt never sends an
 * unsigned webhook (fail closed).
 *
 * Wire format (per delivery):
 *   body    = {"event": "...", "id": <32-hex>, "timestamp": <unix>, "payload": {...}}
 *   headers = X-Salt-Signature: v1=<hex hmac>, X-Salt-Timestamp,
 *             X-Salt-Event, X-Salt-Id, Content-Type: application/json.
 *
 * RECEIVER VERIFICATION (replay protection is the receiver's job — this is
 * the material salt provides):
 *   1. Recompute hash_hmac('sha256', "{X-Salt-Timestamp}.{X-Salt-Id}.{raw body}",
 *      secret) and compare (constant-time) to the hex after "v1=".
 *   2. Reject when |now - X-Salt-Timestamp| > 300 seconds.
 *   3. Deduplicate on X-Salt-Id: a retry re-signs with a fresh timestamp
 *      but keeps the SAME id and body.
 *
 * Failure logs carry the HTTP status and target host ONLY — never the
 * payload or any PII (SEC11).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The webhook signing secret (wp-config constant; '' = unconfigured).
 *
 * @return string
 */
function salt_webhook_secret(): string {
	return defined( 'SALT_WEBHOOK_SECRET' ) ? (string) SALT_WEBHOOK_SECRET : '';
}

/**
 * Configured target URLs subscribed to an event.
 *
 * HTTPS only (fail closed: signed payloads carry submission data and must
 * not travel in clear); a non-https row is logged and skipped. A row's
 * empty events field defaults to 'contact.submitted'.
 *
 * @param string $event Event name.
 * @return string[] Target URLs.
 */
function salt_webhook_targets( string $event ): array {
	if ( ! salt_acf_active() ) {
		return array();
	}

	$rows = salt_option( 'webhook_targets' );
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$targets = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$url = trim( (string) ( $row['url'] ?? '' ) );
		if ( '' === $url ) {
			continue;
		}

		if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
			salt_log( sprintf( 'webhooks: non-https target "%s" skipped (fail closed).', (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
			continue;
		}

		$events_raw = trim( (string) ( $row['events'] ?? '' ) );
		$events     = '' === $events_raw
			? array( 'contact.submitted' )
			: array_map( 'trim', explode( ',', $events_raw ) );

		if ( in_array( $event, $events, true ) ) {
			$targets[] = $url;
		}
	}

	return $targets;
}

/**
 * Sign a webhook body: HMAC-SHA256 over "{timestamp}.{id}.{body}".
 *
 * @param string $body      Exact JSON body as sent.
 * @param int    $timestamp Unix timestamp (also sent as X-Salt-Timestamp).
 * @param string $id        Delivery id (also sent as X-Salt-Id).
 * @return string Hex digest, or '' when no secret is configured.
 */
function salt_webhook_sign( string $body, int $timestamp, string $id ): string {
	$secret = salt_webhook_secret();
	if ( '' === $secret ) {
		return '';
	}

	return hash_hmac( 'sha256', $timestamp . '.' . $id . '.' . $body, $secret );
}

/**
 * Dispatch an event to every subscribed target (fire-and-forget for the
 * caller; per-target failures are logged and retried ONCE via cron).
 *
 * @param string              $event   Event name (e.g. 'contact.submitted').
 * @param array<string,mixed> $payload Event payload.
 * @param int|null            $now     Timestamp override (tests).
 * @param string|null         $id      Delivery id override (tests).
 * @return void
 */
function salt_webhook_dispatch( string $event, array $payload, ?int $now = null, ?string $id = null ): void {
	$targets = salt_webhook_targets( $event );
	if ( array() === $targets ) {
		return;
	}

	if ( '' === salt_webhook_secret() ) {
		salt_log(
			sprintf(
				'webhooks: SALT_WEBHOOK_SECRET is not defined — %d "%s" target(s) skipped (fail closed: unsigned dispatch refused).',
				count( $targets ),
				$event
			)
		);
		return;
	}

	$timestamp = $now ?? time();

	foreach ( $targets as $url ) {
		$delivery_id = $id ?? bin2hex( random_bytes( 16 ) );
		$body        = (string) wp_json_encode(
			array(
				'event'     => $event,
				'id'        => $delivery_id,
				'timestamp' => $timestamp,
				'payload'   => $payload,
			)
		);

		salt_webhook_send( $url, $event, $body, $timestamp, $delivery_id, true );
	}
}

/**
 * POST one signed delivery; on failure log (status + host only) and,
 * when allowed, park the body in a transient and schedule ONE retry.
 *
 * @param string $url         Target URL.
 * @param string $event       Event name.
 * @param string $body        Exact JSON body.
 * @param int    $timestamp   Signing timestamp.
 * @param string $id          Delivery id (32 hex).
 * @param bool   $allow_retry Whether a retry may be scheduled.
 * @return bool Whether the target answered 2xx.
 */
function salt_webhook_send( string $url, string $event, string $body, int $timestamp, string $id, bool $allow_retry ): bool {
	$response = wp_remote_post(
		$url,
		array(
			'timeout'  => 5,
			'blocking' => true,
			'headers'  => array(
				'Content-Type'     => 'application/json; charset=utf-8',
				'X-Salt-Signature' => 'v1=' . salt_webhook_sign( $body, $timestamp, $id ),
				'X-Salt-Timestamp' => (string) $timestamp,
				'X-Salt-Event'     => $event,
				'X-Salt-Id'        => $id,
			),
			'body'     => $body,
		)
	);

	$is_error = is_wp_error( $response );
	$code     = $is_error ? 0 : (int) wp_remote_retrieve_response_code( $response );

	if ( ! $is_error && $code >= 200 && $code < 300 ) {
		return true;
	}

	// Status + host ONLY — never the payload (SEC11).
	salt_log(
		sprintf(
			'webhooks: delivery %s to %s failed (status %d)%s.',
			$id,
			(string) wp_parse_url( $url, PHP_URL_HOST ),
			$code,
			$allow_retry ? ' — one retry scheduled in 300s' : ' — retry exhausted, giving up'
		)
	);

	if ( $allow_retry ) {
		set_transient(
			'salt_webhook_' . $id,
			array(
				'url'   => $url,
				'event' => $event,
				'body'  => $body,
			),
			3600
		);
		wp_schedule_single_event( $timestamp + 300, 'salt_webhook_retry', array( $id ) );
	}

	return false;
}

/**
 * Cron handler: re-send a parked delivery ONCE.
 *
 * Re-signs with a fresh timestamp (the receiver's 300s skew window) but
 * the SAME id and body — receivers deduplicate on X-Salt-Id.
 *
 * @param string   $id  Delivery id from the scheduled event args.
 * @param int|null $now Timestamp override (tests).
 * @return void
 */
function salt_webhook_retry( string $id, ?int $now = null ): void {
	if ( 1 !== preg_match( '/^[0-9a-f]{32}$/', $id ) ) {
		salt_log( 'webhooks: retry refused — malformed delivery id (fail closed).' );
		return;
	}

	$key     = 'salt_webhook_' . $id;
	$pending = get_transient( $key );
	delete_transient( $key );

	if ( ! is_array( $pending ) || '' === (string) ( $pending['url'] ?? '' ) || '' === (string) ( $pending['body'] ?? '' ) ) {
		salt_log( sprintf( 'webhooks: retry %s skipped — pending delivery missing or expired.', $id ) );
		return;
	}

	salt_webhook_send(
		(string) $pending['url'],
		(string) ( $pending['event'] ?? '' ),
		(string) $pending['body'],
		$now ?? time(),
		$id,
		false
	);
}
add_action( 'salt_webhook_retry', 'salt_webhook_retry' );
