<?php
/**
 * Consent bridge (PRIV01): fail-closed default, WP Consent API bridge,
 * filter bridge — every ambiguous path denies.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

final class ConsentTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Another test file shims wp_has_consent() into the process; this
		// class asserts the NO-Consent-API paths, so switch the bridge off.
		add_filter( 'salt_consent_api_active', static fn(): bool => false, 20 );
	}

	public function test_categories_are_exactly_the_locked_set(): void {
		$this->assertSame(
			array( 'essential', 'analytics', 'maps', 'embeds', 'marketing' ),
			salt_consent_categories()
		);
	}

	public function test_essential_is_always_granted(): void {
		$this->assertTrue( salt_consent( 'essential' ) );
	}

	/**
	 * Non-essential categories.
	 *
	 * @return array<string,array{string}>
	 */
	public static function non_essential_categories(): array {
		return array(
			'analytics' => array( 'analytics' ),
			'maps'      => array( 'maps' ),
			'embeds'    => array( 'embeds' ),
			'marketing' => array( 'marketing' ),
		);
	}

	#[DataProvider( 'non_essential_categories' )]
	public function test_denied_by_default_with_no_cmp( string $category ): void {
		$this->assertFalse( salt_consent( $category ), 'No CMP wired ⇒ hard deny (fail-closed).' );
	}

	public function test_unknown_category_denied_and_logged(): void {
		$this->assertFalse( salt_consent( 'doubleclick' ) );
		$this->assertNotEmpty( $this->logs() );
	}

	public function test_filter_bridge_grants_only_on_explicit_true(): void {
		add_filter( 'salt_consent_state', static fn( bool $granted, string $category ): bool => 'analytics' === $category, 20, 2 );

		$this->assertTrue( salt_consent( 'analytics' ), 'CMP glue granting via the filter bridge.' );
		$this->assertFalse( salt_consent( 'marketing' ), 'Other categories stay denied.' );
	}

	public function test_filter_returning_truthy_non_bool_is_denied(): void {
		add_filter( 'salt_consent_state', static fn(): string => 'yes', 20 );

		$this->assertFalse( salt_consent( 'analytics' ), 'Only strict true grants — sloppy glue fails closed.' );
	}

	public function test_category_map_defaults(): void {
		$this->assertSame( 'functional', salt_consent_api_category( 'essential' ) );
		$this->assertSame( 'statistics', salt_consent_api_category( 'analytics' ) );
		$this->assertSame( 'marketing', salt_consent_api_category( 'maps' ) );
		$this->assertSame( 'marketing', salt_consent_api_category( 'embeds' ) );
	}
}
