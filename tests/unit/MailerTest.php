<?php
/**
 * salt_contact_recipient() / salt_contact_deliver() — routing, header
 * hardening, auto-reply gating and failure behaviour (spec §9; SEC11).
 *
 * NOTE: no test in this file may define SALT_WEBHOOK_SECRET — the
 * "no dispatch without secret" behaviour is asserted here and in
 * WebhooksTest, which defines the constant only in its later tests
 * (constants cannot be undefined within the PHPUnit process).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/http.php';
require_once __DIR__ . '/../shims/mail.php';
require_once __DIR__ . '/../shims/webhooks.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/webhooks.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/mailer.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/options-contact.php';

final class MailerTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_http_reset();
		$GLOBALS['salt_test_mail']       = array();
		$GLOBALS['salt_test_mail_fail']  = false;
		$GLOBALS['salt_test_bloginfo']   = array( 'name' => 'Salt Fixture Site' );
		$GLOBALS['salt_test_cron']       = array();
		$GLOBALS['salt_test_acf_groups'] = array();
	}

	protected function tearDown(): void {
		// Never leak the bloginfo fixture into other suites' shims.
		unset( $GLOBALS['salt_test_bloginfo'] );
		parent::tearDown();
	}

	/**
	 * Seed the contact options fixtures.
	 *
	 * @param array<string,mixed> $options Field name => value.
	 */
	private function seed_options( array $options ): void {
		foreach ( $options as $name => $value ) {
			$GLOBALS['salt_test_fields']['option'][ $name ] = $value;
		}
	}

	/**
	 * A frozen-contract submission fixture.
	 *
	 * @param array<string,string> $overrides Overrides.
	 * @return array<string,string>
	 */
	private function submission( array $overrides = array() ): array {
		return array_merge(
			array(
				'name'         => 'Jane Doe',
				'email'        => 'jane@example.test',
				'message'      => "Hello there.\nSecond line of the enquiry.",
				'enquiry_type' => 'Support',
				'page_url'     => 'https://example.test/contact/',
				'submitted_at' => '2026-07-02T10:00:00+00:00',
				'ip_hash'      => str_repeat( 'ab', 32 ),
			),
			$overrides
		);
	}

	// -----------------------------------------------------------------
	// Routing.
	// -----------------------------------------------------------------

	public function test_typed_route_wins_over_fallback(): void {
		$this->seed_options(
			array(
				'contact_recipient' => 'hello@example.test',
				'contact_routes'    => array(
					array(
						'enquiry_type' => 'Support',
						'recipient'    => 'support@example.test',
					),
					array(
						'enquiry_type' => 'Sales',
						'recipient'    => 'sales@example.test',
					),
				),
			)
		);

		$this->assertSame( 'support@example.test', salt_contact_recipient( 'support' ) );
		$this->assertSame( 'sales@example.test', salt_contact_recipient( 'Sales' ) );
	}

	public function test_unmatched_type_falls_back_to_default_recipient(): void {
		$this->seed_options(
			array(
				'contact_recipient' => 'hello@example.test',
				'contact_routes'    => array(
					array(
						'enquiry_type' => 'Support',
						'recipient'    => 'support@example.test',
					),
				),
			)
		);

		$this->assertSame( 'hello@example.test', salt_contact_recipient( 'careers' ) );
		$this->assertSame( 'hello@example.test', salt_contact_recipient( '' ) );
	}

	public function test_route_with_invalid_recipient_email_is_ignored(): void {
		$this->seed_options(
			array(
				'contact_recipient' => 'hello@example.test',
				'contact_routes'    => array(
					array(
						'enquiry_type' => 'Support',
						'recipient'    => 'not-an-email',
					),
				),
			)
		);

		$this->assertSame( 'hello@example.test', salt_contact_recipient( 'support' ) );
	}

	public function test_nothing_configured_returns_empty_string(): void {
		$this->assertSame( '', salt_contact_recipient( 'support' ) );
		$this->assertSame( '', salt_contact_recipient( '' ) );
	}

	// -----------------------------------------------------------------
	// Delivery.
	// -----------------------------------------------------------------

	public function test_deliver_sends_routed_mail_with_labelled_body_and_reply_to(): void {
		$this->seed_options(
			array(
				'contact_recipient' => 'hello@example.test',
				'contact_routes'    => array(
					array(
						'enquiry_type' => 'Support',
						'recipient'    => 'support@example.test',
					),
				),
			)
		);

		$result = salt_contact_deliver( $this->submission() );

		$this->assertTrue( $result['sent'] );
		$this->assertSame( array(), $result['errors'] );
		$this->assertCount( 1, $GLOBALS['salt_test_mail'] );

		$mail = $GLOBALS['salt_test_mail'][0];
		$this->assertSame( 'support@example.test', $mail['to'] );
		$this->assertStringContainsString( 'Salt Fixture Site', $mail['subject'] );
		$this->assertStringContainsString( 'Support', $mail['subject'] );
		$this->assertStringContainsString( 'Jane Doe', $mail['message'] );
		$this->assertStringContainsString( 'jane@example.test', $mail['message'] );
		$this->assertStringContainsString( 'Second line of the enquiry.', $mail['message'] );
		$this->assertStringContainsString( 'https://example.test/contact/', $mail['message'] );
		$this->assertContains( 'Reply-To: jane@example.test', $mail['headers'] );
	}

	public function test_deliver_without_recipient_fails_loud_without_sending(): void {
		$result = salt_contact_deliver( $this->submission() );

		$this->assertFalse( $result['sent'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertSame( array(), $GLOBALS['salt_test_mail'] );
		$this->assertNotEmpty( $this->logs() );
	}

	public function test_header_injection_attempt_is_neutralised(): void {
		$this->seed_options( array( 'contact_recipient' => 'hello@example.test' ) );

		salt_contact_deliver(
			$this->submission(
				array(
					'name'         => "Evil\r\nBcc: x@y.test",
					'email'        => "evil@x.test\r\nBcc: y@z.test",
					'enquiry_type' => "Support\r\nBcc: z@w.test",
				)
			)
		);

		$this->assertCount( 1, $GLOBALS['salt_test_mail'] );
		$mail = $GLOBALS['salt_test_mail'][0];

		// Single-line subject: no CR/LF survives.
		$this->assertDoesNotMatchRegularExpression( '/[\r\n]/', $mail['subject'] );

		// No injected header; the CR/LF email failed is_email so no Reply-To either.
		$this->assertSame( array( 'Content-Type: text/plain; charset=UTF-8' ), $mail['headers'] );
		foreach ( $mail['headers'] as $header ) {
			$this->assertStringNotContainsStringIgnoringCase( 'bcc', $header );
			$this->assertDoesNotMatchRegularExpression( '/[\r\n]/', $header );
		}
	}

	public function test_reply_to_only_when_submitter_email_is_valid(): void {
		$this->seed_options( array( 'contact_recipient' => 'hello@example.test' ) );

		salt_contact_deliver( $this->submission( array( 'email' => 'not-an-email' ) ) );

		$this->assertCount( 1, $GLOBALS['salt_test_mail'] );
		$this->assertSame( array( 'Content-Type: text/plain; charset=UTF-8' ), $GLOBALS['salt_test_mail'][0]['headers'] );
	}

	// -----------------------------------------------------------------
	// Auto-reply.
	// -----------------------------------------------------------------

	public function test_no_autoreply_when_disabled(): void {
		$this->seed_options(
			array(
				'contact_recipient'      => 'hello@example.test',
				'contact_autoreply'      => 0,
				'contact_autoreply_body' => 'Thanks {name}!',
			)
		);

		salt_contact_deliver( $this->submission() );

		$this->assertCount( 1, $GLOBALS['salt_test_mail'] );
	}

	public function test_autoreply_sent_with_placeholders_replaced(): void {
		$this->seed_options(
			array(
				'contact_recipient'         => 'hello@example.test',
				'contact_autoreply'         => 1,
				'contact_autoreply_subject' => 'We got your message',
				'contact_autoreply_body'    => "Hi {name},\nThanks for contacting {site}.",
			)
		);

		$result = salt_contact_deliver( $this->submission() );

		$this->assertTrue( $result['sent'] );
		$this->assertCount( 2, $GLOBALS['salt_test_mail'] );

		$reply = $GLOBALS['salt_test_mail'][1];
		$this->assertSame( 'jane@example.test', $reply['to'] );
		$this->assertSame( 'We got your message', $reply['subject'] );
		$this->assertStringContainsString( 'Hi Jane Doe,', $reply['message'] );
		$this->assertStringContainsString( 'Thanks for contacting Salt Fixture Site.', $reply['message'] );
		$this->assertStringNotContainsString( '{name}', $reply['message'] );
		$this->assertStringNotContainsString( '{site}', $reply['message'] );
	}

	public function test_autoreply_not_sent_to_invalid_email(): void {
		$this->seed_options(
			array(
				'contact_recipient'      => 'hello@example.test',
				'contact_autoreply'      => 1,
				'contact_autoreply_body' => 'Thanks {name}!',
			)
		);

		salt_contact_deliver( $this->submission( array( 'email' => 'not-an-email' ) ) );

		$this->assertCount( 1, $GLOBALS['salt_test_mail'] );
	}

	// -----------------------------------------------------------------
	// Failure + logging (no PII).
	// -----------------------------------------------------------------

	public function test_wp_mail_failure_returns_sent_false_and_logs_without_pii(): void {
		$this->seed_options( array( 'contact_recipient' => 'hello@example.test' ) );
		$GLOBALS['salt_test_mail_fail'] = true;

		$result = salt_contact_deliver( $this->submission() );

		$this->assertFalse( $result['sent'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertNotEmpty( $this->logs() );

		foreach ( $this->logs() as $line ) {
			$this->assertStringNotContainsString( 'jane@example.test', $line );
			$this->assertStringNotContainsString( 'Second line of the enquiry', $line );
			$this->assertStringNotContainsString( 'Jane Doe', $line );
		}
	}

	// -----------------------------------------------------------------
	// Webhook hand-off.
	// -----------------------------------------------------------------

	public function test_deliver_dispatches_webhook_which_fails_closed_without_secret(): void {
		// SALT_WEBHOOK_SECRET is not defined at this point in the suite:
		// a configured target must produce the logged fail-closed no-op.
		$this->seed_options(
			array(
				'contact_recipient' => 'hello@example.test',
				'webhook_targets'   => array(
					array(
						'url'    => 'https://hooks.example.test/salt',
						'events' => 'contact.submitted',
					),
				),
			)
		);

		salt_contact_deliver( $this->submission() );

		$this->assertSame( array(), $GLOBALS['salt_test_http']['requests'] );
		$secret_logs = array_filter(
			$this->logs(),
			static fn( string $line ): bool => str_contains( $line, 'SALT_WEBHOOK_SECRET' )
		);
		$this->assertNotEmpty( $secret_logs );
	}

	// -----------------------------------------------------------------
	// Options registration (DATA02 keys).
	// -----------------------------------------------------------------

	public function test_contact_options_group_registers_with_canonical_keys(): void {
		salt_register_options_contact_fields();

		$group = null;
		foreach ( $GLOBALS['salt_test_acf_groups'] as $candidate ) {
			if ( 'group_salt_options_contact' === ( $candidate['key'] ?? '' ) ) {
				$group = $candidate;
			}
		}

		$this->assertNotNull( $group );

		$names = array_column( $group['fields'], 'name', 'key' );
		$this->assertSame( 'contact_recipient', $names['field_salt_options_contact_recipient'] );
		$this->assertSame( 'contact_routes', $names['field_salt_options_contact_routes'] );
		$this->assertSame( 'contact_autoreply', $names['field_salt_options_contact_autoreply'] );
		$this->assertSame( 'contact_autoreply_subject', $names['field_salt_options_contact_autoreply_subject'] );
		$this->assertSame( 'contact_autoreply_body', $names['field_salt_options_contact_autoreply_body'] );
		$this->assertSame( 'webhook_targets', $names['field_salt_options_contact_webhook_targets'] );
	}
}
