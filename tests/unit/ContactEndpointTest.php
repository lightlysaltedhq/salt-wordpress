<?php
/**
 * Contact endpoint orchestrator (SEC01/SEC02/SEC10, PRIV06): the full
 * defence-layer matrix on both submission paths.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

foreach ( glob( __DIR__ . '/../shims/*.php' ) as $salt_shim_file ) {
	require_once $salt_shim_file;
}
unset( $salt_shim_file );

final class ContactEndpointTest extends SaltTestCase {

	private const NOW = 1783000000;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_transients']            = array();
		$GLOBALS['salt_test_http']                  = array();
		$GLOBALS['salt_test_mail']                  = array();
		$_SERVER['REMOTE_ADDR']                     = '203.0.113.7';
		$GLOBALS['salt_test_fields']['option']['contact_recipient'] = 'hello@example.test';
	}

	/**
	 * A fully valid submission payload (JS/rest path, no captcha).
	 *
	 * @return array<string,string>
	 */
	private function valid_payload(): array {
		return array(
			'salt_contact_nonce'   => wp_create_nonce( 'salt_contact' ),
			'salt_hp'              => '',
			'salt_time'            => salt_contact_time_token( self::NOW - 10 ),
			'salt_contact_name'    => 'Ada Lovelace',
			'salt_contact_email'   => 'ada@example.org',
			'salt_contact_message' => 'I would like a website.',
			'salt_page_url'        => 'https://example.test/contact/',
		);
	}

	public function test_unconfigured_form_is_a_404(): void {
		unset( $GLOBALS['salt_test_fields']['option']['contact_recipient'] );

		$result = salt_contact_handle( $this->valid_payload(), 'rest', self::NOW );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 404, $result['status'] );
	}

	public function test_kill_switch_filter_wins_over_configuration(): void {
		add_filter( 'salt_contact_form_enabled', static fn(): bool => false, 20 );

		$this->assertFalse( salt_contact_configured() );
	}

	public function test_happy_path_delivers_and_reports_ok(): void {
		$result = salt_contact_handle( $this->valid_payload(), 'rest', self::NOW );

		$this->assertTrue( $result['ok'], 'Expected success: ' . $result['message'] );
		$this->assertSame( 200, $result['status'] );
		$this->assertNotEmpty( $GLOBALS['salt_test_mail'], 'Delivery reached wp_mail.' );
	}

	public function test_bad_nonce_is_rejected(): void {
		$payload                       = $this->valid_payload();
		$payload['salt_contact_nonce'] = 'forged';

		$result = salt_contact_handle( $payload, 'rest', self::NOW );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 403, $result['status'] );
		$this->assertSame( array(), $GLOBALS['salt_test_mail'] );
	}

	public function test_honeypot_gets_fake_success_and_no_delivery(): void {
		$payload            = $this->valid_payload();
		$payload['salt_hp'] = 'http://spam.example';

		$result = salt_contact_handle( $payload, 'rest', self::NOW );

		$this->assertTrue( $result['ok'], 'Bots must see success (no oracle).' );
		$this->assertSame( array(), $GLOBALS['salt_test_mail'], 'Nothing delivered.' );
	}

	public function test_missing_honeypot_field_is_treated_as_bot(): void {
		$payload = $this->valid_payload();
		unset( $payload['salt_hp'] );

		$result = salt_contact_handle( $payload, 'rest', self::NOW );

		$this->assertSame( array(), $GLOBALS['salt_test_mail'] );
	}

	public function test_too_fast_submission_fails_the_time_trap(): void {
		$payload              = $this->valid_payload();
		$payload['salt_time'] = salt_contact_time_token( self::NOW - 1 );

		$result = salt_contact_handle( $payload, 'rest', self::NOW );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 400, $result['status'] );
	}

	public function test_stale_and_tampered_time_tokens_fail(): void {
		$stale              = $this->valid_payload();
		$stale['salt_time'] = salt_contact_time_token( self::NOW - 5 * HOUR_IN_SECONDS );
		$this->assertFalse( salt_contact_handle( $stale, 'rest', self::NOW )['ok'] );

		$tampered              = $this->valid_payload();
		$tampered['salt_time'] = ( self::NOW - 10 ) . '.deadbeef';
		$this->assertFalse( salt_contact_handle( $tampered, 'rest', self::NOW )['ok'] );
	}

	public function test_validation_rejects_bad_email_and_empty_fields(): void {
		$bad_email                        = $this->valid_payload();
		$bad_email['salt_contact_email']  = 'not-an-email';
		$this->assertSame( 400, salt_contact_handle( $bad_email, 'rest', self::NOW )['status'] );

		$no_name                       = $this->valid_payload();
		$no_name['salt_contact_name']  = '<script>x</script>';
		$result                        = salt_contact_handle( $no_name, 'rest', self::NOW );
		$this->assertFalse( $result['ok'], 'Tag-only name strips to empty and fails validation.' );
	}

	public function test_rate_limit_blocks_the_sixth_attempt(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			salt_contact_handle( $this->valid_payload(), 'rest', self::NOW );
		}
		$result = salt_contact_handle( $this->valid_payload(), 'rest', self::NOW );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 429, $result['status'] );
	}

	public function test_unknown_enquiry_type_defaults_to_general(): void {
		$payload                      = $this->valid_payload();
		$payload['salt_contact_type'] = 'not-a-configured-route';

		$this->assertTrue( salt_contact_handle( $payload, 'rest', self::NOW )['ok'] );
		$mail = end( $GLOBALS['salt_test_mail'] );
		$this->assertStringContainsString( 'general', strtolower( (string) wp_json_encode( $mail ) ) );
	}

	public function test_captcha_required_on_rest_path_when_provider_configured(): void {
		$GLOBALS['salt_test_fields']['option']['captcha_provider']     = 'turnstile';
		$GLOBALS['salt_test_fields']['option']['captcha_turnstile_site_key'] = '1x0000';
		if ( ! defined( 'SALT_TURNSTILE_SECRET' ) ) {
			define( 'SALT_TURNSTILE_SECRET', 'test-secret' );
		}

		// No token → fail closed, no delivery.
		$result = salt_contact_handle( $this->valid_payload(), 'rest', self::NOW );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( array(), $GLOBALS['salt_test_mail'] );

		// Scripted siteverify success → delivered.
		$GLOBALS['salt_test_http']['responses'][] = array(
			'code' => 200,
			'body' => wp_json_encode(
				array(
					'success'  => true,
					'hostname' => 'example.test',
				)
			),
		);
		$payload                          = $this->valid_payload();
		$payload['cf-turnstile-response'] = 'token-abc';
		$this->assertTrue( salt_contact_handle( $payload, 'rest', self::NOW )['ok'] );
	}

	public function test_nojs_path_accepts_the_arithmetic_challenge(): void {
		$GLOBALS['salt_test_fields']['option']['captcha_provider']     = 'turnstile';
		$GLOBALS['salt_test_fields']['option']['captcha_turnstile_site_key'] = '1x0000';
		if ( ! defined( 'SALT_TURNSTILE_SECRET' ) ) {
			define( 'SALT_TURNSTILE_SECRET', 'test-secret' );
		}

		$challenge = salt_challenge_create();
		preg_match( '/(\d+)\D+(\d+)/', $challenge['question'], $m );

		$payload                          = $this->valid_payload();
		$payload['salt_challenge_token']  = $challenge['token'];
		$payload['salt_challenge_answer'] = (string) ( (int) $m[1] + (int) $m[2] );

		$this->assertTrue( salt_contact_handle( $payload, 'nojs', self::NOW )['ok'] );

		// Wrong answer on a fresh challenge fails.
		$challenge2                        = salt_challenge_create();
		$payload2                          = $this->valid_payload();
		$payload2['salt_challenge_token']  = $challenge2['token'];
		$payload2['salt_challenge_answer'] = '999';
		$this->assertFalse( salt_contact_handle( $payload2, 'nojs', self::NOW )['ok'] );
	}

	public function test_submission_stored_only_when_toggle_on(): void {
		$GLOBALS['salt_test_fields']['option']['submissions_store'] = true;

		salt_contact_handle( $this->valid_payload(), 'rest', self::NOW );

		$stored = array_filter(
			$GLOBALS['salt_test_posts'],
			static fn( WP_Post $p ): bool => 'salt_submission' === $p->post_type
		);
		$this->assertNotEmpty( $stored, 'Submission persisted when storage is on.' );
	}
}
