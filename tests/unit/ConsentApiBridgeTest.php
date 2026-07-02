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
		$GLOBALS['salt_test_wp_consent'] = array();
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
}
