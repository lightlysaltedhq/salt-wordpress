<?php
/**
 * Rate-limit service tests (SEC10).
 *
 * ORDER-SENSITIVE: constants cannot be undefined, so every test relying on
 * SALT_TRUSTED_PROXY_HEADER being ABSENT is declared before the trusted-proxy
 * tests, which define it for the remainder of the process (PHPUnit runs
 * methods in declaration order).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/rate-limit.php';
require_once dirname( __DIR__, 2 ) . '/inc/services/rate-limit.php';

final class RateLimitTest extends SaltTestCase {

	private const IP = '203.0.113.9';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_transients'] = array();
		$_SERVER['REMOTE_ADDR']          = self::IP;
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CF_CONNECTING_IP'] );
	}

	/**
	 * The transient key salt_rate_limit() derives for a bucket + IP.
	 */
	private function key_for( string $bucket, string $ip ): string {
		return 'salt_rl_' . substr( hash( 'sha256', $bucket . '|' . $ip . '|' . wp_salt( 'nonce' ) ), 0, 24 );
	}

	// -----------------------------------------------------------------
	// salt_client_ip() — tests that need SALT_TRUSTED_PROXY_HEADER absent.
	// -----------------------------------------------------------------

	public function test_client_ip_reads_remote_addr_by_default(): void {
		$this->assertSame( self::IP, salt_client_ip() );
	}

	public function test_forwarded_header_ignored_when_constant_not_defined(): void {
		if ( defined( 'SALT_TRUSTED_PROXY_HEADER' ) ) {
			$this->markTestSkipped( 'SALT_TRUSTED_PROXY_HEADER already defined in this process.' );
		}

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';

		$this->assertSame( self::IP, salt_client_ip(), 'Spoofable XFF must not be trusted without explicit opt-in (SEC10).' );
		$this->assertSame( array(), $this->logs() );
	}

	public function test_invalid_remote_addr_resolves_to_zero_ip_and_logs(): void {
		$_SERVER['REMOTE_ADDR'] = 'not-an-ip';

		$this->assertSame( '0.0.0.0', salt_client_ip() );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringContainsString( 'salt_client_ip', $this->logs()[0] );
	}

	public function test_missing_remote_addr_resolves_to_zero_ip_and_logs(): void {
		unset( $_SERVER['REMOTE_ADDR'] );

		$this->assertSame( '0.0.0.0', salt_client_ip() );
		$this->assertCount( 1, $this->logs() );
	}

	// -----------------------------------------------------------------
	// salt_rate_limit().
	// -----------------------------------------------------------------

	public function test_allows_up_to_limit_then_blocks(): void {
		$this->assertTrue( salt_rate_limit( 'contact', 3, 60 ) );
		$this->assertTrue( salt_rate_limit( 'contact', 3, 60 ) );
		$this->assertTrue( salt_rate_limit( 'contact', 3, 60 ) );
		$this->assertFalse( salt_rate_limit( 'contact', 3, 60 ) );
		$this->assertFalse( salt_rate_limit( 'contact', 3, 60 ), 'Stays blocked within the window.' );
		$this->assertCount( 2, $this->logs() );
		$this->assertStringContainsString( '"contact"', $this->logs()[0] );
	}

	public function test_window_expiry_resets_the_counter(): void {
		$this->assertTrue( salt_rate_limit( 'contact', 1, 60 ) );
		$this->assertFalse( salt_rate_limit( 'contact', 1, 60 ) );

		// Rewind the stored window start past the window length.
		$key = $this->key_for( 'contact', self::IP );
		$this->assertArrayHasKey( $key, $GLOBALS['salt_test_transients'] );
		$GLOBALS['salt_test_transients'][ $key ]['value']['start'] -= 61;

		$this->assertTrue( salt_rate_limit( 'contact', 1, 60 ), 'Expired window must restore allowance.' );
	}

	public function test_buckets_are_independent(): void {
		$this->assertTrue( salt_rate_limit( 'contact', 1, 60 ) );
		$this->assertFalse( salt_rate_limit( 'contact', 1, 60 ) );

		$this->assertTrue( salt_rate_limit( 'newsletter', 1, 60 ), 'A throttled bucket must not bleed into another.' );
	}

	public function test_client_ips_are_independent(): void {
		$this->assertTrue( salt_rate_limit( 'contact', 1, 60 ) );
		$this->assertFalse( salt_rate_limit( 'contact', 1, 60 ) );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.42';
		$this->assertTrue( salt_rate_limit( 'contact', 1, 60 ), 'A different client gets its own counter.' );
	}

	public function test_invalid_configuration_fails_closed(): void {
		$this->assertFalse( salt_rate_limit( 'contact', 0, 60 ) );
		$this->assertFalse( salt_rate_limit( 'contact', 5, 0 ) );
		$this->assertFalse( salt_rate_limit( '', 5, 60 ) );
		$this->assertCount( 3, $this->logs() );
	}

	public function test_keys_and_logs_leak_no_raw_ip(): void {
		$this->assertTrue( salt_rate_limit( 'contact', 1, 60 ) );
		$this->assertFalse( salt_rate_limit( 'contact', 1, 60 ) );

		foreach ( array_keys( $GLOBALS['salt_test_transients'] ) as $key ) {
			$this->assertStringStartsWith( 'salt_rl_', $key );
			$this->assertStringNotContainsString( self::IP, $key );
		}

		$this->assertNotEmpty( $this->logs() );
		foreach ( $this->logs() as $line ) {
			$this->assertStringNotContainsString( self::IP, $line, 'Raw IPs must never reach the log channel (SEC10).' );
		}
		$this->assertStringContainsString( $this->key_for( 'contact', self::IP ), $this->logs()[0], 'The hashed key IS logged for correlation.' );
	}

	// -----------------------------------------------------------------
	// salt_rate_limit_headers().
	// -----------------------------------------------------------------

	public function test_rate_limit_headers_guard_headers_sent(): void {
		// PHPUnit has already written to STDOUT, so headers_sent() is true in
		// this process: the guard must make this a silent no-op, not a warning
		// ("Cannot modify header information") — failOnWarning would catch it.
		salt_rate_limit_headers( 30 );
		salt_rate_limit_headers( -5 );
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------
	// Trusted proxy — DEFINES the constant; keep these LAST (see file docblock).
	// -----------------------------------------------------------------

	public function test_trusted_proxy_header_honoured_when_constant_defined(): void {
		if ( ! defined( 'SALT_TRUSTED_PROXY_HEADER' ) ) {
			define( 'SALT_TRUSTED_PROXY_HEADER', 'HTTP_X_FORWARDED_FOR' );
		}
		if ( 'HTTP_X_FORWARDED_FOR' !== SALT_TRUSTED_PROXY_HEADER ) {
			$this->markTestSkipped( 'SALT_TRUSTED_PROXY_HEADER pinned to a different header in this process.' );
		}

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';

		$this->assertSame( '198.51.100.7', salt_client_ip() );
	}

	public function test_xff_takes_first_value_trimmed(): void {
		if ( ! defined( 'SALT_TRUSTED_PROXY_HEADER' ) || 'HTTP_X_FORWARDED_FOR' !== SALT_TRUSTED_PROXY_HEADER ) {
			$this->markTestSkipped( 'Requires SALT_TRUSTED_PROXY_HEADER = HTTP_X_FORWARDED_FOR.' );
		}

		$_SERVER['HTTP_X_FORWARDED_FOR'] = ' 198.51.100.7 , 70.41.3.18, 150.172.238.178 ';

		$this->assertSame( '198.51.100.7', salt_client_ip(), 'First (client) hop wins; whitespace trimmed.' );
	}

	public function test_invalid_trusted_header_value_fails_closed(): void {
		if ( ! defined( 'SALT_TRUSTED_PROXY_HEADER' ) || 'HTTP_X_FORWARDED_FOR' !== SALT_TRUSTED_PROXY_HEADER ) {
			$this->markTestSkipped( 'Requires SALT_TRUSTED_PROXY_HEADER = HTTP_X_FORWARDED_FOR.' );
		}

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '<script>alert(1)</script>';

		$this->assertSame( '0.0.0.0', salt_client_ip() );
		$this->assertCount( 1, $this->logs() );
		$this->assertStringNotContainsString( '<script>', $this->logs()[0], 'Raw header values never reach the log channel.' );
	}

	public function test_missing_trusted_header_fails_closed_not_remote_addr(): void {
		if ( ! defined( 'SALT_TRUSTED_PROXY_HEADER' ) || 'HTTP_X_FORWARDED_FOR' !== SALT_TRUSTED_PROXY_HEADER ) {
			$this->markTestSkipped( 'Requires SALT_TRUSTED_PROXY_HEADER = HTTP_X_FORWARDED_FOR.' );
		}

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$this->assertSame( '0.0.0.0', salt_client_ip(), 'Configured trusted header absent = ambiguous = fail closed, never a silent REMOTE_ADDR fallback.' );
		$this->assertCount( 1, $this->logs() );
	}
}
