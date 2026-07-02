<?php
/**
 * salt_webhook_* — signing, fail-closed dispatch, event filtering, retry
 * scheduling and PII-free logging (spec §9; SEC11).
 *
 * ORDER MATTERS: constants cannot be undefined within the PHPUnit process,
 * so the no-secret tests run FIRST (declared order = execution order) and
 * SALT_WEBHOOK_SECRET is defined only from define_secret() onwards. No
 * other test file defines it (MailerTest relies on that too).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/http.php';
require_once __DIR__ . '/../shims/webhooks.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/webhooks.php';

final class WebhooksTest extends SaltTestCase {

	private const TEST_ID = 'aabbccddeeff00112233445566778899';

	protected function setUp(): void {
		parent::setUp();
		salt_test_http_reset();
		$GLOBALS['salt_test_cron'] = array();
	}

	/**
	 * Queue a scripted HTTP response (shared http.php shim semantics).
	 *
	 * @param array|WP_Error $response array{code: int, body?: string} or WP_Error.
	 */
	private function queue_response( $response ): void {
		$GLOBALS['salt_test_http']['responses'][] = $response;
	}

	/**
	 * Define the signing secret (idempotent; see class docblock).
	 *
	 * @return string The secret value.
	 */
	private function define_secret(): string {
		if ( ! defined( 'SALT_WEBHOOK_SECRET' ) ) {
			define( 'SALT_WEBHOOK_SECRET', 'salt-test-webhook-secret' );
		}
		return SALT_WEBHOOK_SECRET;
	}

	/**
	 * Seed one webhook target fixture row.
	 *
	 * @param string $url    Target URL.
	 * @param string $events Events field value ('' = field default path).
	 */
	private function seed_target( string $url = 'https://hooks.example.test/salt', string $events = 'contact.submitted' ): void {
		$existing = $GLOBALS['salt_test_fields']['option']['webhook_targets'] ?? array();

		$existing[] = array(
			'url'    => $url,
			'events' => $events,
		);

		$GLOBALS['salt_test_fields']['option']['webhook_targets'] = $existing;
	}

	/**
	 * The frozen submission payload fixture (PII markers used in log tests).
	 *
	 * @return array<string,string>
	 */
	private function payload(): array {
		return array(
			'name'         => 'Jane Doe',
			'email'        => 'jane@example.test',
			'message'      => 'Top secret enquiry body',
			'enquiry_type' => 'Support',
			'page_url'     => 'https://example.test/contact/',
			'submitted_at' => '2026-07-02T10:00:00+00:00',
			'ip_hash'      => str_repeat( 'cd', 32 ),
		);
	}

	// -----------------------------------------------------------------
	// 1) No secret — MUST run before define_secret() is ever called.
	// -----------------------------------------------------------------

	public function test_no_secret_and_no_targets_is_a_silent_noop(): void {
		salt_webhook_dispatch( 'contact.submitted', $this->payload() );

		$this->assertSame( array(), $GLOBALS['salt_test_http']['requests'] );
		$this->assertSame( array(), $this->logs() );
	}

	public function test_no_secret_with_targets_is_a_logged_noop_never_unsigned(): void {
		$this->seed_target();

		salt_webhook_dispatch( 'contact.submitted', $this->payload() );

		$this->assertSame( array(), $GLOBALS['salt_test_http']['requests'] );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'SALT_WEBHOOK_SECRET', $this->logs()[0] );

		// SEC11: the payload never reaches the logs.
		$this->assertStringNotContainsString( 'jane@example.test', $this->logs()[0] );
		$this->assertStringNotContainsString( 'Top secret enquiry body', $this->logs()[0] );
	}

	// -----------------------------------------------------------------
	// 2) Signing + dispatch (secret defined from here on).
	// -----------------------------------------------------------------

	public function test_sign_is_hmac_sha256_over_timestamp_id_body(): void {
		$secret = $this->define_secret();

		$this->assertSame(
			hash_hmac( 'sha256', '1750000000.' . self::TEST_ID . '.{"a":1}', $secret ),
			salt_webhook_sign( '{"a":1}', 1750000000, self::TEST_ID )
		);
	}

	public function test_dispatch_posts_signed_verifiable_request(): void {
		$secret = $this->define_secret();
		$this->seed_target();
		$this->queue_response( array( 'code' => 200 ) );

		salt_webhook_dispatch( 'contact.submitted', $this->payload(), 1750000000, self::TEST_ID );

		$this->assertCount( 1, $GLOBALS['salt_test_http']['requests'] );
		$request = $GLOBALS['salt_test_http']['requests'][0];

		$this->assertSame( 'https://hooks.example.test/salt', $request['url'] );
		$this->assertSame( 5, $request['args']['timeout'] );
		$this->assertTrue( $request['args']['blocking'] );

		$headers = $request['args']['headers'];
		$body    = (string) $request['args']['body'];

		$this->assertSame( 'application/json; charset=utf-8', $headers['Content-Type'] );
		$this->assertSame( '1750000000', $headers['X-Salt-Timestamp'] );
		$this->assertSame( 'contact.submitted', $headers['X-Salt-Event'] );
		$this->assertSame( self::TEST_ID, $headers['X-Salt-Id'] );

		// The receiver-side verification recipe: recompute the HMAC.
		$expected = hash_hmac( 'sha256', '1750000000.' . self::TEST_ID . '.' . $body, $secret );
		$this->assertSame( 'v1=' . $expected, $headers['X-Salt-Signature'] );

		$decoded = json_decode( $body, true );
		$this->assertSame( 'contact.submitted', $decoded['event'] );
		$this->assertSame( self::TEST_ID, $decoded['id'] );
		$this->assertSame( 1750000000, $decoded['timestamp'] );
		$this->assertSame( $this->payload(), $decoded['payload'] );
	}

	public function test_generated_delivery_ids_are_unique_32_hex(): void {
		$this->define_secret();
		$this->seed_target();
		$this->seed_target( 'https://hooks2.example.test/salt' );
		$this->queue_response( array( 'code' => 200 ) );
		$this->queue_response( array( 'code' => 200 ) );

		salt_webhook_dispatch( 'contact.submitted', $this->payload() );

		$this->assertCount( 2, $GLOBALS['salt_test_http']['requests'] );
		$ids = array_map(
			static fn( array $r ): string => (string) $r['args']['headers']['X-Salt-Id'],
			$GLOBALS['salt_test_http']['requests']
		);
		foreach ( $ids as $id ) {
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $id );
		}
		$this->assertNotSame( $ids[0], $ids[1] );
	}

	// -----------------------------------------------------------------
	// Target selection.
	// -----------------------------------------------------------------

	public function test_target_subscribed_to_other_event_is_not_dispatched(): void {
		$this->define_secret();
		$this->seed_target( 'https://hooks.example.test/salt', 'order.created' );

		salt_webhook_dispatch( 'contact.submitted', $this->payload() );

		$this->assertSame( array(), $GLOBALS['salt_test_http']['requests'] );
	}

	public function test_empty_events_field_defaults_to_contact_submitted(): void {
		$this->define_secret();
		$this->seed_target( 'https://hooks.example.test/salt', '' );
		$this->queue_response( array( 'code' => 200 ) );

		salt_webhook_dispatch( 'contact.submitted', $this->payload() );

		$this->assertCount( 1, $GLOBALS['salt_test_http']['requests'] );
	}

	public function test_non_https_target_is_skipped_and_logged(): void {
		$this->define_secret();
		$this->seed_target( 'http://insecure.example.test/hook' );

		salt_webhook_dispatch( 'contact.submitted', $this->payload() );

		$this->assertSame( array(), $GLOBALS['salt_test_http']['requests'] );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'non-https', $this->logs()[0] );
	}

	// -----------------------------------------------------------------
	// Failure → ONE retry; PII-free logs.
	// -----------------------------------------------------------------

	public function test_failure_schedules_one_retry_and_parks_the_body(): void {
		$this->define_secret();
		$this->seed_target();
		$this->queue_response(
			array(
				'code' => 500,
				'body' => 'upstream broke',
			)
		);

		salt_webhook_dispatch( 'contact.submitted', $this->payload(), 1750000000, self::TEST_ID );

		// Exactly one retry scheduled, +300s, carrying only the id.
		$this->assertCount( 1, $GLOBALS['salt_test_cron'] );
		$cron = $GLOBALS['salt_test_cron'][0];
		$this->assertSame( 'salt_webhook_retry', $cron['hook'] );
		$this->assertSame( 1750000300, $cron['timestamp'] );
		$this->assertSame( array( self::TEST_ID ), $cron['args'] );

		// Pending body parked in a transient keyed by id.
		$pending = get_transient( 'salt_webhook_' . self::TEST_ID );
		$this->assertIsArray( $pending );
		$this->assertSame( 'https://hooks.example.test/salt', $pending['url'] );
		$this->assertSame( 'contact.submitted', $pending['event'] );
		$this->assertJson( $pending['body'] );

		// Log carries status + host only — never payload PII (SEC11).
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'status 500', $this->logs()[0] );
		$this->assertStringContainsString( 'hooks.example.test', $this->logs()[0] );
		foreach ( $this->logs() as $line ) {
			$this->assertStringNotContainsString( 'jane@example.test', $line );
			$this->assertStringNotContainsString( 'Top secret enquiry body', $line );
			$this->assertStringNotContainsString( 'Jane Doe', $line );
		}
	}

	public function test_wp_error_response_also_schedules_a_retry(): void {
		$this->define_secret();
		$this->seed_target();
		$this->queue_response( new WP_Error( 'http_request_failed', 'timed out' ) );

		salt_webhook_dispatch( 'contact.submitted', $this->payload(), 1750000000, self::TEST_ID );

		$this->assertCount( 1, $GLOBALS['salt_test_cron'] );
		$this->assertStringContainsString( 'status 0', $this->logs()[0] );
	}

	public function test_retry_resends_identical_body_and_clears_the_transient(): void {
		$this->define_secret();
		$this->seed_target();
		$this->queue_response( array( 'code' => 503 ) );
		$this->queue_response( array( 'code' => 200 ) );

		salt_webhook_dispatch( 'contact.submitted', $this->payload(), 1750000000, self::TEST_ID );
		$first_body = (string) $GLOBALS['salt_test_http']['requests'][0]['args']['body'];

		// Cron fires: the scripted 200 = success.
		salt_webhook_retry( self::TEST_ID, 1750000300 );

		$this->assertCount( 2, $GLOBALS['salt_test_http']['requests'] );
		$retry = $GLOBALS['salt_test_http']['requests'][1];

		// Same body + id; fresh timestamp; signature re-verifiable.
		$this->assertSame( $first_body, (string) $retry['args']['body'] );
		$this->assertSame( self::TEST_ID, $retry['args']['headers']['X-Salt-Id'] );
		$this->assertSame( '1750000300', $retry['args']['headers']['X-Salt-Timestamp'] );
		$this->assertSame(
			'v1=' . hash_hmac( 'sha256', '1750000300.' . self::TEST_ID . '.' . $first_body, SALT_WEBHOOK_SECRET ),
			$retry['args']['headers']['X-Salt-Signature']
		);

		$this->assertFalse( get_transient( 'salt_webhook_' . self::TEST_ID ) );
		// Still only the ONE originally scheduled retry.
		$this->assertCount( 1, $GLOBALS['salt_test_cron'] );
	}

	public function test_failed_retry_is_not_rescheduled(): void {
		$this->define_secret();
		$this->seed_target();
		$this->queue_response( array( 'code' => 500 ) );
		$this->queue_response( array( 'code' => 500 ) );

		salt_webhook_dispatch( 'contact.submitted', $this->payload(), 1750000000, self::TEST_ID );
		salt_webhook_retry( self::TEST_ID, 1750000300 );

		$this->assertCount( 2, $GLOBALS['salt_test_http']['requests'] );
		$this->assertCount( 1, $GLOBALS['salt_test_cron'] );
		$this->assertFalse( get_transient( 'salt_webhook_' . self::TEST_ID ) );
	}

	public function test_retry_with_missing_transient_logs_and_sends_nothing(): void {
		$this->define_secret();

		salt_webhook_retry( self::TEST_ID );

		$this->assertSame( array(), $GLOBALS['salt_test_http']['requests'] );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'missing or expired', $this->logs()[0] );
	}

	public function test_retry_with_malformed_id_is_refused(): void {
		$this->define_secret();

		salt_webhook_retry( 'not-a-32-hex-id' );

		$this->assertSame( array(), $GLOBALS['salt_test_http']['requests'] );
		$this->assertStringContainsString( 'malformed', $this->logs()[0] );
	}
}
