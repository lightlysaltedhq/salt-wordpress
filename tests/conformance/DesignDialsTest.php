<?php
/**
 * The design dials against the pinned salt-contract's contract/dials.json.
 *
 * salt_design_dials() (inc/colors.php) is a copy of the contract's table, and
 * salt_brand_css() writes a chosen option's tokens after the brand colours.
 * Each option must write exactly the tokens the contract maps, at the value
 * of the rung it points at as assets/src/css/app.css declares that rung
 * (narrow, and wide where app.css steps it at 48rem), and nothing for an
 * option that maps nothing. Reads node_modules, so it lives in the
 * conformance suite.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DesignDialsTest extends SaltTestCase {

	private const DIALS = __DIR__ . '/../../node_modules/@lightlysaltedhq/salt-contract/contract/dials.json';

	private const APP_CSS = __DIR__ . '/../../assets/src/css/app.css';

	private const SEED = '#3b82f6';

	/**
	 * The contract's dials, in its order.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function contract(): array {
		if ( ! is_file( self::DIALS ) ) {
			self::fail( 'contract/dials.json is not installed. Run npm ci; this gate fails rather than skips.' );
		}
		return json_decode( (string) file_get_contents( self::DIALS ), true )['dials'];
	}

	/**
	 * The custom properties app.css declares on :root, outside any @media
	 * ('narrow') and inside `@media (width >= 48rem)` ('wide').
	 *
	 * @return array{narrow: array<string, string>, wide: array<string, string>}
	 */
	private static function app_root(): array {
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( self::APP_CSS ) );

		preg_match( '/@media \(width >= 48rem\)\s*\{\s*:root\s*\{([^{}]*)\}\s*\}/', $css, $wide );
		$top = (string) preg_replace( '/@media[^{]*\{(?:[^{}]|\{[^{}]*\})*\}/', '', $css );
		preg_match_all( '/(?<![\w\]-]):root\s*\{([^{}]*)\}/', $top, $narrow );

		return array(
			'narrow' => self::declarations( implode( ';', $narrow[1] ) ),
			'wide'   => self::declarations( $wide[1] ?? '' ),
		);
	}

	/**
	 * Custom property => value, whitespace collapsed.
	 *
	 * @param string $block Declarations.
	 * @return array<string, string>
	 */
	private static function declarations( string $block ): array {
		preg_match_all( '/(--[\w-]+)\s*:\s*([^;]+)/', $block, $found, PREG_SET_ORDER );
		$out = array();
		foreach ( $found as $declaration ) {
			$out[ $declaration[1] ] = (string) preg_replace( '/\s*,\s*/', ',', (string) preg_replace( '/\s+/', ' ', trim( $declaration[2] ) ) );
		}
		return $out;
	}

	/**
	 * What salt_brand_css() writes after the colour block, split by rule. Fails
	 * on any rule but the three salt_dials_css() writes.
	 *
	 * @param array<string, string> $dials Dial choices.
	 * @return array{root: array<string, string>, wide: array<string, string>, dark: array<string, string>}
	 */
	private static function written( array $dials ): array {
		$base = salt_brand_css( array( 'primary' => self::SEED ) );
		$css  = salt_brand_css( array( 'primary' => self::SEED ) + $dials );
		self::assertStringStartsWith( $base, $css, 'A dial changed the colour block.' );
		$tail = substr( $css, strlen( $base ) );

		$rules = '/^(?::root\{(?<root>[^{}]*)\})?(?:@media \(width>=48rem\)\{:root\{(?<wide>[^{}]*)\}\})?(?:\[data-theme="dark"\]\{(?<dark>[^{}]*)\})?$/';
		self::assertSame( 1, preg_match( $rules, $tail, $found ), "salt_brand_css() wrote rules this test cannot read: {$tail}" );

		return array(
			'root' => self::declarations( $found['root'] ?? '' ),
			'wide' => self::declarations( $found['wide'] ?? '' ),
			'dark' => self::declarations( $found['dark'] ?? '' ),
		);
	}

	/**
	 * Every combination of every dial's options (3^4 for the contract's four).
	 *
	 * @return list<array<string, string>>
	 */
	private static function combinations(): array {
		$combos = array( array() );
		foreach ( salt_design_dials() as $id => $dial ) {
			$next = array();
			foreach ( $combos as $combo ) {
				foreach ( array_keys( $dial['options'] ) as $value ) {
					$next[] = $combo + array( $id => $value );
				}
			}
			$combos = $next;
		}
		return $combos;
	}

	public function test_the_dial_table_is_the_contracts(): void {
		$theme = salt_design_dials();
		$this->assertSame( array_column( self::contract(), 'id' ), array_keys( $theme ), 'The contract\'s dials, in its order.' );

		foreach ( self::contract() as $dial ) {
			$mine = $theme[ $dial['id'] ];
			$this->assertSame( $dial['label'], $mine['label'], "{$dial['id']}: label." );
			$this->assertSame( $dial['description'], $mine['description'], "{$dial['id']}: description." );
			$this->assertSame( $dial['default'], $mine['default'], "{$dial['id']}: default." );
			$this->assertSame( 'admin', $dial['role'], "{$dial['id']}: options-brand.php gates every dial to the admin role." );
			$this->assertSame(
				array_combine( array_column( $dial['options'], 'value' ), array_map( static fn( array $o ): array => array( $o['label'], $o['sets'] ), $dial['options'] ) ),
				array_map( static fn( array $o ): array => array( $o['label'], $o['sets'] ), $mine['options'] ),
				"{$dial['id']}: options, their labels and what each sets, in the contract's order."
			);
			$this->assertSame( $dial['requires'] ?? array(), array_keys( $mine['requires'] ?? array() ), "{$dial['id']}: the tokens it requires." );
			$this->assertSame( array(), $mine['options'][ $dial['default'] ]['sets'], "{$dial['id']}: the default sets nothing, so an unset dial renders as the theme does." );
		}
	}

	/**
	 * One row per option of every dial.
	 *
	 * @return array<string, array{array<string, mixed>, array<string, mixed>}>
	 */
	public static function optionProvider(): array {
		$rows = array();
		foreach ( self::contract() as $dial ) {
			foreach ( $dial['options'] as $option ) {
				$rows[ "{$dial['id']} {$option['value']}" ] = array( $dial, $option );
			}
		}
		return $rows;
	}

	/**
	 * An option writes exactly the tokens the contract maps for it, each at
	 * the value of the rung it points at, plus the tokens its dial requires
	 * while it sets anything; an option that maps nothing writes nothing.
	 *
	 * @param array<string, mixed> $dial   The contract's dial.
	 * @param array<string, mixed> $option One of its options.
	 */
	#[DataProvider( 'optionProvider' )]
	public function test_an_option_writes_exactly_its_tokens_at_the_rungs_value( array $dial, array $option ): void {
		$app     = self::app_root();
		$written = self::written( array( $dial['id'] => $option['value'] ) );

		$want = array(
			'root' => array(),
			'wide' => array(),
			'dark' => array(),
		);
		foreach ( $option['sets'] as $token => $target ) {
			if ( ! str_starts_with( $target, '--' ) ) {
				$want['root'][ $token ] = $target;
				if ( isset( $app['wide'][ $token ] ) ) {
					$want['wide'][ $token ] = $target;
				}
				continue;
			}
			if ( ! isset( $app['narrow'][ $target ] ) ) {
				// A rung app.css does not declare (the shadow family): its value
				// may reference only tokens the dial requires.
				$this->assertArrayHasKey( $token, $written['root'], "{$token} is not written." );
				preg_match_all( '/var\((--[\w-]+)\)/', $written['root'][ $token ], $refs );
				$this->assertNotSame( array(), $refs[1], "{$token}: {$target}'s value references no shadow colour." );
				$this->assertSame( array(), array_diff( $refs[1], $dial['requires'] ?? array() ), "{$token}: {$target}'s value references a token the dial does not require." );
				$want['root'][ $token ] = $written['root'][ $token ];
				continue;
			}
			$want['root'][ $token ] = $app['narrow'][ $target ];
			if ( isset( $app['wide'][ $token ] ) || isset( $app['wide'][ $target ] ) ) {
				$want['wide'][ $token ] = $app['wide'][ $target ] ?? $app['narrow'][ $target ];
			}
		}
		if ( array() !== $option['sets'] ) {
			foreach ( $dial['requires'] ?? array() as $token ) {
				$this->assertArrayHasKey( $token, $written['root'], "{$token} is required while {$dial['id']} is set, in light mode." );
				$this->assertArrayHasKey( $token, $written['dark'], "{$token} is required while {$dial['id']} is set, in dark mode." );
				$want['root'][ $token ] = $written['root'][ $token ];
				$want['dark'][ $token ] = $written['dark'][ $token ];
			}
		}

		$this->assertSame( $want, $written );
	}

	/**
	 * The four dials together: each writes only its own tokens, whatever the
	 * others are on, and the colour block is the same as with no dial.
	 */
	public function test_every_combination_is_its_dials_side_by_side(): void {
		$alone = array();
		foreach ( salt_design_dials() as $id => $dial ) {
			foreach ( array_keys( $dial['options'] ) as $value ) {
				$alone[ $id ][ $value ] = self::written( array( $id => $value ) );
			}
		}

		$combos = self::combinations();
		$this->assertCount( 81, $combos );
		foreach ( $combos as $combo ) {
			$want = array(
				'root' => array(),
				'wide' => array(),
				'dark' => array(),
			);
			foreach ( $combo as $id => $value ) {
				foreach ( $alone[ $id ][ $value ] as $rule => $declarations ) {
					$this->assertSame( array(), array_intersect_key( $want[ $rule ], $declarations ), "{$id} writes a token another dial writes." );
					$want[ $rule ] += $declarations;
				}
			}
			$this->assertSame( $want, self::written( $combo ), 'Dials: ' . http_build_query( $combo, '', ', ' ) );
		}
	}
}
