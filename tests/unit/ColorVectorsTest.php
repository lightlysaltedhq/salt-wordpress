<?php
/**
 * PHP side of the PHP↔JS colour-math parity gate (spec §13).
 *
 * tests/fixtures/color-vectors.json was generated ONCE from inc/colors.php
 * and committed. This test asserts PHP still reproduces every vector;
 * bin/check-color-parity.mjs holds the JS port
 * (assets/src/js/admin/contrast-checker.js) to the same fixture. If either
 * side's math changes, its gate trips — the fixture is never regenerated
 * casually (that is a reviewed colour-engine change).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ColorVectorsTest extends SaltTestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/color-vectors.json';

	/**
	 * Rounding drift allowance: fixture floats are PHP round() output parsed
	 * back through json_decode — identical doubles in practice, but allow a
	 * sub-rounding epsilon rather than betting on last-ulp identity.
	 */
	private const EPSILON = 1.0E-9;

	/**
	 * Decode the committed fixture.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function vectors(): array {
		$raw = file_get_contents( self::FIXTURE );
		if ( false === $raw ) {
			self::fail( 'tests/fixtures/color-vectors.json is missing or unreadable.' );
		}

		$vectors = json_decode( $raw, true );
		if ( ! is_array( $vectors ) ) {
			self::fail( 'tests/fixtures/color-vectors.json is not valid JSON.' );
		}

		return $vectors;
	}

	/**
	 * One data-provider row per fixture vector.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function vectorProvider(): array {
		$rows = array();
		foreach ( self::vectors() as $vector ) {
			$rows[ (string) $vector['input'] ] = array( $vector );
		}
		return $rows;
	}

	public function test_fixture_has_a_meaningful_vector_set(): void {
		$vectors = self::vectors();

		$this->assertGreaterThanOrEqual( 8, count( $vectors ) );

		foreach ( $vectors as $vector ) {
			foreach ( array( 'input', 'oklch', 'ramp', 'luminance', 'textOn', 'contrastWhite' ) as $key ) {
				$this->assertArrayHasKey( $key, $vector, 'Vector ' . $vector['input'] . ' is missing ' . $key );
			}
			$this->assertCount( 11, $vector['ramp'] );
		}
	}

	/**
	 * PHP reproduces the committed vector exactly (at fixture precision).
	 *
	 * @param array<string, mixed> $vector Fixture vector.
	 */
	#[DataProvider( 'vectorProvider' )]
	public function test_php_reproduces_the_fixture( array $vector ): void {
		$input = $vector['input'];

		$oklch = salt_hex_to_oklch( $input );
		$this->assertEqualsWithDelta( $vector['oklch']['l'], round( $oklch['l'], 4 ), self::EPSILON, 'oklch.l' );
		$this->assertEqualsWithDelta( $vector['oklch']['c'], round( $oklch['c'], 4 ), self::EPSILON, 'oklch.c' );
		$this->assertEqualsWithDelta( $vector['oklch']['h'], round( $oklch['h'], 4 ), self::EPSILON, 'oklch.h' );

		// json_decode() int-casts the numeric step keys, so this is a strict
		// key+value+order comparison against salt_derive_ramp() output.
		$this->assertSame( $vector['ramp'], salt_derive_ramp( $input ) );

		$this->assertEqualsWithDelta(
			$vector['luminance'],
			round( salt_relative_luminance( $input ), 6 ),
			self::EPSILON,
			'luminance'
		);

		$this->assertSame( $vector['textOn'], salt_text_on( $input ) );

		$this->assertEqualsWithDelta(
			$vector['contrastWhite'],
			round( salt_contrast_ratio( $input, '#ffffff' ), 3 ),
			self::EPSILON,
			'contrastWhite'
		);

		// No vector may rely on the invalid-hex fallback path.
		$this->assertSame( array(), $this->logs() );
	}
}
