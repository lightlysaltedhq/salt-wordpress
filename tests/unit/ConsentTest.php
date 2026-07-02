<?php
/**
 * Tests for the fail-closed consent stub (PRIV01).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__, 2 ) . '/inc/services/consent.php';

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversFunction( 'salt_consent' )]
#[CoversFunction( 'salt_consent_categories' )]
final class ConsentTest extends SaltTestCase {

	public function test_categories_are_exactly_the_locked_set(): void {
		$this->assertSame(
			array( 'essential', 'analytics', 'maps', 'embeds', 'marketing' ),
			salt_consent_categories()
		);
	}

	public function test_essential_is_granted(): void {
		$this->assertTrue( salt_consent( 'essential' ) );
	}

	#[DataProvider( 'non_essential_categories' )]
	public function test_every_non_essential_category_is_denied( string $category ): void {
		$this->assertFalse( salt_consent( $category ) );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function non_essential_categories(): array {
		$cases = array();
		foreach ( salt_consent_categories() as $category ) {
			if ( 'essential' === $category ) {
				continue;
			}
			$cases[ $category ] = array( $category );
		}
		return $cases;
	}

	public function test_unknown_category_is_denied(): void {
		$this->assertFalse( salt_consent( 'doubleclick' ) );
	}

	public function test_salt_consent_source_has_no_apply_filters(): void {
		$reflection = new ReflectionFunction( 'salt_consent' );
		$lines      = file( (string) $reflection->getFileName() );
		$source     = implode(
			'',
			array_slice( $lines, (int) $reflection->getStartLine() - 1, (int) $reflection->getEndLine() - (int) $reflection->getStartLine() + 1 )
		);
		$this->assertStringNotContainsString( 'apply_filters', $source );
	}
}
