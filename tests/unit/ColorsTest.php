<?php
/**
 * Tests for inc/colors.php — OKLCH colour math + ramp derivation.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Colour math and ramp derivation tests (spec §13, §16 CI item 3).
 */
final class ColorsTest extends SaltTestCase {

	/**
	 * Ramp steps in ladder order.
	 *
	 * @var int[]
	 */
	private const STEPS = array( 50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950 );

	/**
	 * Split a '#rrggbb' hex into integer channels.
	 *
	 * @param string $hex Hex colour.
	 * @return array{int, int, int}
	 */
	private static function channels( string $hex ): array {
		return array(
			(int) hexdec( substr( $hex, 1, 2 ) ),
			(int) hexdec( substr( $hex, 3, 2 ) ),
			(int) hexdec( substr( $hex, 5, 2 ) ),
		);
	}

	/**
	 * Hexes for the round-trip test.
	 *
	 * @return array<string, array{string}>
	 */
	public static function roundTripProvider(): array {
		return array(
			'red'        => array( '#ff0000' ),
			'green'      => array( '#00ff00' ),
			'blue'       => array( '#0000ff' ),
			'white'      => array( '#ffffff' ),
			'black'      => array( '#000000' ),
			'mid blue'   => array( '#336699' ),
			'brand-ish'  => array( '#4b5563' ),
			'near-white' => array( '#f8fafc' ),
			'near-black' => array( '#0f172a' ),
			'orange'     => array( '#e07b39' ),
			'purple'     => array( '#7c3aed' ),
			'short form' => array( '#a1b' ),
		);
	}

	/**
	 * Round-trip: hex → oklch → hex within ±1 per RGB channel.
	 *
	 * @param string $hex Input hex.
	 */
	#[DataProvider( 'roundTripProvider' )]
	public function test_round_trip_within_one_per_channel( string $hex ): void {
		$oklch = salt_hex_to_oklch( $hex );
		$back  = salt_oklch_to_hex( $oklch['l'], $oklch['c'], $oklch['h'] );

		// Normalise #rgb inputs for comparison.
		$expected = 4 === strlen( $hex )
			? '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3]
			: $hex;

		$want = self::channels( $expected );
		$got  = self::channels( $back );

		foreach ( array( 0, 1, 2 ) as $i ) {
			$this->assertLessThanOrEqual(
				1,
				abs( $want[ $i ] - $got[ $i ] ),
				"Channel {$i} of {$hex} drifted more than ±1 (got {$back})."
			);
		}
	}

	public function test_known_values(): void {
		$white = salt_hex_to_oklch( '#ffffff' );
		$this->assertEqualsWithDelta( 1.0, $white['l'], 0.01, 'White OKLCH lightness should be ~1.' );
		$this->assertEqualsWithDelta( 0.0, $white['c'], 0.01, 'White OKLCH chroma should be ~0.' );

		$black = salt_hex_to_oklch( '#000000' );
		$this->assertEqualsWithDelta( 0.0, $black['l'], 0.01, 'Black OKLCH lightness should be ~0.' );

		$this->assertEqualsWithDelta( 0.2126, salt_relative_luminance( '#ff0000' ), 0.001 );
		$this->assertEqualsWithDelta( 21.0, salt_contrast_ratio( '#ffffff', '#000000' ), 0.01 );
	}

	public function test_out_of_gamut_chroma_is_mapped_to_a_valid_hex(): void {
		$hex = salt_oklch_to_hex( 0.62, 0.4, 145.0 );

		$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/', $hex );

		// The requested chroma (0.4) is far outside sRGB at this L/h; the
		// mapped colour must round-trip to a much smaller chroma.
		$oklch = salt_hex_to_oklch( $hex );
		$this->assertLessThan( 0.4, $oklch['c'] );
	}

	public function test_ramp_has_eleven_steps_with_monotonically_decreasing_luminance(): void {
		$ramp = salt_derive_ramp( '#3b82f6' );

		$this->assertSame( self::STEPS, array_keys( $ramp ) );

		$previous = null;
		foreach ( $ramp as $step => $hex ) {
			$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/', $hex, "Step {$step} is not a valid hex." );
			$luminance = salt_relative_luminance( $hex );
			if ( null !== $previous ) {
				$this->assertLessThan( $previous, $luminance, "Luminance did not decrease at step {$step}." );
			}
			$previous = $luminance;
		}
	}

	/**
	 * THE property test (§16 CI item 3): every hue yields AA-safe ramps.
	 *
	 * Sweeps hue 0..360; for each derived ramp asserts (a) salt_text_on()
	 * passes 4.5:1 on step 600, (b) near-black passes on the light 50
	 * surface, (c) near-white passes on the dark 950 surface.
	 */
	public function test_every_hue_derives_aa_compliant_ramp(): void {
		for ( $hue = 0; $hue <= 360; $hue++ ) {
			$base = salt_oklch_to_hex( 0.62, 0.15, (float) $hue );
			$ramp = salt_derive_ramp( $base );

			$text_on_600 = salt_text_on( $ramp[600] );
			$this->assertGreaterThanOrEqual(
				4.5,
				salt_contrast_ratio( $text_on_600, $ramp[600] ),
				"Hue {$hue}: salt_text_on({$ramp[600]}) = {$text_on_600} fails AA on step 600."
			);

			$this->assertGreaterThanOrEqual(
				4.5,
				salt_contrast_ratio( '#0f172a', $ramp[50] ),
				"Hue {$hue}: near-black fails AA on light surface {$ramp[50]} (step 50)."
			);

			$this->assertGreaterThanOrEqual(
				4.5,
				salt_contrast_ratio( '#f8fafc', $ramp[950] ),
				"Hue {$hue}: near-white fails AA on dark surface {$ramp[950]} (step 950)."
			);
		}
	}

	public function test_text_on_prefers_near_white_and_near_black(): void {
		$this->assertSame( '#f8fafc', salt_text_on( '#000000' ) );
		$this->assertSame( '#0f172a', salt_text_on( '#ffffff' ) );
	}

	public function test_brand_css_empty_primary_uses_neutral_default(): void {
		$css = salt_brand_css( array( 'primary' => '' ) );

		$this->assertSame( salt_brand_css( array( 'primary' => '#4b5563' ) ), $css );

		$neutral_500 = salt_derive_ramp( '#4b5563' )[500];
		$this->assertStringContainsString( "--color-primary-500:{$neutral_500};", $css );
	}

	/**
	 * A dial left unset, on its default, or holding a choice the table does
	 * not have, writes nothing: the site renders as it did before the dials.
	 */
	public function test_brand_css_is_unchanged_by_dials_that_set_nothing(): void {
		$colours = array( 'primary' => '#3b82f6' );
		$plain   = salt_brand_css( $colours );

		$this->assertSame( $plain, salt_brand_css( $colours + array_fill_keys( array_keys( salt_design_dials() ), '' ) ) );
		$this->assertSame( $plain, salt_brand_css( $colours + array_map( static fn( array $dial ): string => $dial['default'], salt_design_dials() ) ) );
		$this->assertSame( $plain, salt_brand_css( $colours + array_fill_keys( array_keys( salt_design_dials() ), 'pill;}body{display:none' ) ) );
		$this->assertSame( '', salt_dials_css( array( 'corners' => 'soft' ) ) );
		$this->assertNotSame( $plain, salt_brand_css( $colours + array( 'corners' => 'square' ) ) );
	}

	public function test_brand_css_fallback_chain_accent_inherits_secondary(): void {
		$css = salt_brand_css(
			array(
				'primary'   => '#123456',
				'secondary' => '#654321',
			)
		);

		foreach ( self::STEPS as $step ) {
			$this->assertSame(
				1,
				preg_match( "/--color-secondary-{$step}:(#[0-9a-f]{6});/", $css, $secondary_match ),
				"Missing --color-secondary-{$step}."
			);
			$this->assertSame(
				1,
				preg_match( "/--color-accent-{$step}:(#[0-9a-f]{6});/", $css, $accent_match ),
				"Missing --color-accent-{$step}."
			);
			$this->assertSame( $secondary_match[1], $accent_match[1], "Accent step {$step} should equal secondary's." );
		}
	}

	public function test_brand_css_structure_and_on_color_vars(): void {
		$css = salt_brand_css( array( 'primary' => '#3b82f6' ) );

		$this->assertStringStartsWith( ':root{', $css );
		$this->assertStringEndsWith( '}', $css );
		$this->assertStringContainsString( '--color-primary-500:', $css );
		$this->assertMatchesRegularExpression( '/--color-on-primary:#[0-9a-f]{6};/', $css );

		// Every role gets all 11 steps + an on-colour.
		foreach ( array( 'primary', 'secondary', 'accent' ) as $role ) {
			$this->assertSame( 11, preg_match_all( "/--color-{$role}-\d+:/", $css ) );
			$this->assertStringContainsString( "--color-on-{$role}:", $css );
		}
	}

	/**
	 * on-brand follows the display mode (lightlysaltedhq/salt-wordpress#3), so primary gets an AA
	 * text colour for each step a mode fills brand with, derived from the
	 * site's own ramp.
	 */
	public function test_brand_css_derives_an_on_colour_per_brand_fill_step(): void {
		$css  = salt_brand_css( array( 'primary' => '#00ff00' ) );
		$ramp = salt_derive_ramp( '#00ff00' );

		foreach ( array( 200, 400, 600, 700 ) as $step ) {
			$this->assertStringContainsString( sprintf( '--color-on-primary-%d:%s;', $step, salt_text_on( $ramp[ $step ] ) ), $css );
		}

		// Dark mode's fill (400) takes the dark candidate: #f8fafc measured 2.35:1 there.
		$this->assertStringContainsString( '--color-on-primary-400:#0f172a;', $css );
		$this->assertSame( 4, preg_match_all( '/--color-on-primary-\d+:/', $css ) );
		$this->assertSame( 0, preg_match_all( '/--color-on-(?:secondary|accent)-\d+:/', $css ) );
	}
}
