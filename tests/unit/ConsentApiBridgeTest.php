<?php
/**
 * WP Consent API bridge behaviour — isolated in its own file because the
 * shimmed wp_has_consent() exists for the whole suite process once loaded,
 * and ConsentTest asserts the no-Consent-API paths.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/consent-api.php';

final class ConsentApiBridgeTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_wp_consent']      = array();
		$GLOBALS['salt_test_wp_consent_type'] = 'optin';
	}

	protected function tearDown(): void {
		unset( $GLOBALS['salt_test_wp_consent_type'] );
		parent::tearDown();
	}

	public function test_consent_api_verdict_grants(): void {
		$GLOBALS['salt_test_wp_consent'] = array( 'statistics' => true );

		$this->assertTrue( salt_consent( 'analytics' ) );
	}

	public function test_consent_api_without_record_denies(): void {
		$this->assertFalse( salt_consent( 'analytics' ) );
	}

	public function test_filter_cannot_out_vote_the_consent_api(): void {
		add_filter( 'salt_consent_state', static fn(): bool => true, 20 );

		$this->assertFalse( salt_consent( 'marketing' ), 'Once the Consent API is active it is authoritative.' );
	}

	public function test_category_remap_filter_applies(): void {
		add_filter( 'salt_consent_api_category', static fn( string $api, string $salt ): string => 'maps' === $salt ? 'preferences' : $api, 20, 2 );
		$GLOBALS['salt_test_wp_consent'] = array( 'preferences' => true );

		$this->assertTrue( salt_consent( 'maps' ), 'Sites can remap salt categories onto their CMP taxonomy.' );
	}

	public function test_no_consent_type_fails_closed(): void {
		// The real API grants every category while no cookie plugin has set
		// a consent type; Salt must not read that as consent.
		unset( $GLOBALS['salt_test_wp_consent_type'] );
		$this->assertTrue( wp_has_consent( 'statistics' ), 'The shim behaves as the real API does.' );

		$this->assertFalse( salt_consent( 'analytics' ) );
		$this->assertFalse( salt_consent( 'marketing' ) );
	}
}
