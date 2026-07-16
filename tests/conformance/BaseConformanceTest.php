<?php
/**
 * Conformance gate: salt conforms to base-design-system.
 *
 * Salt is one of two implementations of a shared contract; the other is
 * ls-design-system (Python). They share RULES — the tier model, the role
 * vocabulary, the OKLCH derivation spec — and deliberately share NO colour
 * values. This test proves the rules half.
 *
 * HOW THIS DIFFERS FROM ColorVectorsTest
 * --------------------------------------
 * ColorVectorsTest holds PHP to tests/fixtures/color-vectors.json — salt's OWN
 * fixture, which also pins oklch/luminance/textOn/contrastWhite and keeps the JS
 * port (assets/src/js/admin/contrast-checker.js) in step. That is salt-internal
 * parity and stays exactly as it is.
 *
 * This test holds PHP to ../base-design-system/contract/vectors.json — the
 * CROSS-REPO contract, frozen from the agreement of both implementations. The
 * two fixtures overlap only on ramps, and that overlap is the point: if salt's
 * colour maths ever drifts, both trip.
 *
 * Base does not pin textOn: its candidates (#f8fafc / #0f172a) are two palette
 * values, and a contract that holds none cannot mandate them. Base states
 * contrast as a PROPERTY instead ("on-brand reaches 4.5:1 against brand"), which
 * salt satisfies with its own colours. salt's own fixture still pins textOn.
 *
 * FAILS CLOSED. If the contract is absent this test FAILS rather than skips —
 * salt cannot claim to conform to something it cannot read. Both of this fleet's
 * gates once returned 0 on skip, so CI without their dependencies passed green
 * while verifying nothing (fixed in ls-design-system@eb3011b). A gate that passes
 * when it did not run is worse than no gate.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BaseConformanceTest extends SaltTestCase {

	/**
	 * The contract, as a sibling checkout. CI places it here explicitly; see
	 * .github/workflows/ci.yml.
	 */
	private const CONTRACT = __DIR__ . '/../../../base-design-system/contract';

	/**
	 * Decode a contract file, failing loudly if the contract is not present.
	 *
	 * @param string $name File name within contract/.
	 * @return array<string, mixed>
	 */
	private static function contract( string $name ): array {
		$path = self::CONTRACT . '/' . $name;

		if ( ! file_exists( $path ) ) {
			self::fail(
				"The contract is not here — {$path} not found.\n"
				. "  salt CONFORMS to base-design-system; it cannot claim that with the contract absent.\n"
				. "  This gate does not skip, by design.\n"
				. '  Fix: git clone git@github.com:otigs/base-design-system.git ../base-design-system'
			);
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $decoded ) ) {
			self::fail( "{$path} is not valid JSON." );
		}

		return $decoded;
	}

	/**
	 * One data-provider row per frozen vector.
	 *
	 * @return array<string, array{string, array<string, string>}>
	 */
	public static function vectorProvider(): array {
		$rows = array();
		foreach ( self::contract( 'vectors.json' )['vectors'] as $vector ) {
			$rows[ (string) $vector['seed'] ] = array( $vector['seed'], $vector['ramp'] );
		}
		return $rows;
	}

	/**
	 * salt_derive_ramp() reproduces a frozen vector byte-for-byte.
	 *
	 * If this fails, salt has drifted from the contract. Do NOT re-freeze the
	 * vectors to make it pass — the implementation is wrong by definition.
	 *
	 * @param string                $seed Seed hex.
	 * @param array<string, string> $want Expected 11-step ramp.
	 */
	#[DataProvider( 'vectorProvider' )]
	public function test_derivation_matches_the_contract( string $seed, array $want ): void {
		$got = salt_derive_ramp( $seed );

		// json_decode() int-casts the numeric step keys; normalise both sides to
		// string keys so this compares values, not PHP's array-key coercion.
		$normalise = static function ( array $ramp ): array {
			$out = array();
			foreach ( $ramp as $step => $hex ) {
				$out[ (string) $step ] = strtolower( (string) $hex );
			}
			ksort( $out, SORT_NUMERIC );
			return $out;
		};

		$this->assertSame( $normalise( $want ), $normalise( $got ), "ramp drift for seed {$seed}" );

		// No vector may rely on the invalid-hex fallback path.
		$this->assertSame( array(), $this->logs() );
	}

	/**
	 * The contract still covers what it claims to — a fixture set that quietly
	 * shrank would let drift through while still reporting green.
	 */
	public function test_the_contract_is_a_meaningful_vector_set(): void {
		$vectors = self::contract( 'vectors.json' );

		$this->assertGreaterThanOrEqual( 15, count( $vectors['vectors'] ) );
		$this->assertSame( 165, $vectors['counts']['assertions'] );

		foreach ( $vectors['vectors'] as $vector ) {
			$this->assertCount( 11, $vector['ramp'], "{$vector['seed']} is not an 11-step ladder" );
			$this->assertNotEmpty( $vector['exercises'], "{$vector['seed']} does not say what it exercises" );
		}
	}

	/**
	 * Every colour role the contract requires exists in the theme's @theme block.
	 *
	 * Reads app.css rather than the computed stylesheet on purpose: @theme is
	 * where salt DECLARES its roles, and a role missing there is missing full stop.
	 * Values are not asserted — the contract has no opinion on them, and that is
	 * the entire design. salt's dark surface is neutral-950 where
	 * ls-design-system's is neutral-800, and both conform.
	 */
	public function test_every_required_role_exists(): void {
		$vocab = self::contract( 'vocabulary.json' );
		$css   = (string) file_get_contents( __DIR__ . '/../../assets/src/css/app.css' );

		$roles = array();
		foreach ( $vocab['colorRoles']['required'] as $role ) {
			$roles[] = $role['id'];
		}
		$roles = array_merge( $roles, $vocab['colorRoles']['status']['required'] );

		foreach ( $roles as $role ) {
			$prop = '--color-' . str_replace( '.', '-', $role );
			$this->assertMatchesRegularExpression(
				'/^\s*' . preg_quote( $prop, '/' ) . '\s*:/m',
				$css,
				"required role '{$role}' ({$prop}) is not declared in app.css"
			);
		}

		foreach ( $vocab['typographyRoles']['required'] as $role ) {
			$this->assertMatchesRegularExpression(
				'/^\s*' . preg_quote( $role['css'], '/' ) . '\s*:/m',
				$css,
				"required typography role '{$role['id']}' ({$role['css']}) is not declared in app.css"
			);
		}
	}

	/**
	 * A neutral ramp exists. Its LENGTH is deliberately not asserted.
	 *
	 * The contract mandates that a neutral ramp exists and nothing more. salt's is
	 * 11 steps and its dark surface is neutral-950 (app.css:116, pinned verbatim by
	 * ContrastCheckerTest); ls-design-system's is 10 and stops at 900. A contract
	 * that standardised either would break the other — see
	 * base-design-system/contract/vocabulary.json#neutralRamp.
	 */
	public function test_a_neutral_ramp_exists(): void {
		$css = (string) file_get_contents( __DIR__ . '/../../assets/src/css/app.css' );

		$this->assertSame(
			1,
			preg_match_all( '/^\s*--color-neutral-\d+\s*:/m', $css ) > 0 ? 1 : 0,
			'the contract requires a neutral ramp to exist'
		);
	}
}
