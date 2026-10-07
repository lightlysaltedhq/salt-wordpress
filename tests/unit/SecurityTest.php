<?php
/**
 * Security baseline: CSP nonce, headers (SEC06), updater token custody
 * (SEC03), ACF guard (DEP01).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class SecurityTest extends SaltTestCase {

	public function test_csp_nonce_is_stable_within_request_and_urlsafe(): void {
		$nonce = salt_csp_nonce();

		$this->assertSame( $nonce, salt_csp_nonce(), 'Nonce must be stable per request.' );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_-]{20,}$/', $nonce );
	}

	public function test_security_headers_ship_the_baseline_set(): void {
		$headers = salt_security_headers();

		$this->assertSame( 'nosniff', $headers['X-Content-Type-Options'] );
		$this->assertSame( 'strict-origin-when-cross-origin', $headers['Referrer-Policy'] );
		$this->assertArrayHasKey( 'Permissions-Policy', $headers );

		$csp = $headers['Content-Security-Policy'];
		$this->assertStringContainsString( "default-src 'self'", $csp );
		$this->assertStringContainsString( "'nonce-" . salt_csp_nonce() . "'", $csp );
		$this->assertStringContainsString( "object-src 'none'", $csp );
		$this->assertStringContainsString( "frame-ancestors 'self'", $csp );
	}

	public function test_csp_contains_no_third_party_origin(): void {
		$csp = salt_security_headers()['Content-Security-Policy'];

		$this->assertDoesNotMatchRegularExpression(
			'#https?://#',
			$csp,
			'PRIV01: no external origin may be allow-listed while salt_consent() is a fail-closed stub.'
		);
	}

	public function test_csp_policy_is_filterable_for_sites(): void {
		add_filter( 'salt_csp_policy', static fn( string $csp ): string => $csp . '; report-uri /csp', 20 );

		$this->assertStringContainsString( 'report-uri /csp', salt_security_headers()['Content-Security-Policy'] );
	}

	// -----------------------------------------------------------------------
	// SEC03 — updater token scoping.
	// -----------------------------------------------------------------------

	public function test_updater_ignores_requests_when_token_undefined(): void {
		$args = salt_updater_authorize_request( array(), 'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/releases' );
		$this->assertArrayNotHasKey( 'headers', $args );
	}

	public function test_updater_scopes_token_to_our_repo_only(): void {
		if ( ! defined( 'SALT_UPDATER_TOKEN' ) ) {
			define( 'SALT_UPDATER_TOKEN', 'test-token-value' );
		}

		$ours = salt_updater_authorize_request( array(), 'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/releases' );
		$this->assertSame( 'token test-token-value', $ours['headers']['Authorization'] );

		$other_repo = salt_updater_authorize_request( array(), 'https://api.github.com/repos/someone/else' );
		$this->assertArrayNotHasKey( 'headers', $other_repo, 'Token must never leak to other repos.' );

		$other_host = salt_updater_authorize_request( array(), 'https://evil.example/repos/lightlysaltedhq/salt-wordpress' );
		$this->assertArrayNotHasKey( 'headers', $other_host, 'Token must never leak to non-GitHub hosts.' );

		$existing = salt_updater_authorize_request(
			array( 'headers' => array( 'Authorization' => 'Bearer keep-me' ) ),
			'https://api.github.com/repos/lightlysaltedhq/salt-wordpress/releases'
		);
		$this->assertSame( 'Bearer keep-me', $existing['headers']['Authorization'], 'Existing auth is never overwritten.' );
	}

	// -----------------------------------------------------------------------
	// DEP01 — ACF guard.
	// -----------------------------------------------------------------------

	public function test_acf_active_mirrors_function_existence(): void {
		$this->assertSame( function_exists( 'acf_add_local_field_group' ), salt_acf_active() );
	}

	public function test_acf_notice_message_covers_absent_outdated_and_ok(): void {
		$this->assertNull( salt_acf_notice_message( true, true, '6.4.1' ), 'Healthy ACF: no notice.' );

		$absent = salt_acf_notice_message( false, false, '' );
		$this->assertIsString( $absent );
		$this->assertStringContainsString( 'not active', $absent );
		$this->assertStringContainsString( 'nothing will fatal', $absent );

		$outdated = salt_acf_notice_message( true, false, '5.9.0' );
		$this->assertIsString( $outdated );
		$this->assertStringContainsString( SALT_MIN_ACF_VERSION, $outdated );
		$this->assertStringContainsString( '5.9.0', $outdated );
	}

	public function test_field_key_convention_violations_are_logged_in_debug(): void {
		salt_assert_field_keys(
			array(
				array(
					'key'  => 'field_wrong_prefix_name',
					'name' => 'x',
				),
			)
		);

		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'DATA02', $this->logs()[0] );
	}
}
