<?php
/**
 * OKLCH colour math + brand ramp derivation (spec §13).
 *
 * Converts sRGB hex colours to/from OKLCH, computes WCAG 2.x luminance and
 * contrast ratios, derives an 11-step tonal ramp from a single brand colour
 * (SCOPE04), and emits the ':root' CSS custom-property block for the brand
 * palette with a legible neutral default on fresh installs (FAIL03).
 *
 * All conversions use Björn Ottosson's published OKLab matrices. Out-of-gamut
 * OKLCH values are mapped back into sRGB by reducing chroma (binary search),
 * so every derived colour is a real, displayable hex.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse a '#rgb' or '#rrggbb' hex string into gamma-encoded sRGB channels.
 *
 * @param string $hex Hex colour (leading '#' optional, case-insensitive).
 * @return array{float, float, float}|null Channels in 0..1, or null when invalid.
 */
function salt_color_parse_hex( string $hex ): ?array {
	$hex = ltrim( trim( $hex ), '#' );

	if ( 1 === preg_match( '/^[0-9a-f]{3}$/i', $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( 1 !== preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
		return null;
	}

	return array(
		hexdec( substr( $hex, 0, 2 ) ) / 255.0,
		hexdec( substr( $hex, 2, 2 ) ) / 255.0,
		hexdec( substr( $hex, 4, 2 ) ) / 255.0,
	);
}

/**
 * Linearise one gamma-encoded sRGB channel (IEC 61966-2-1).
 *
 * @param float $channel Gamma-encoded channel in 0..1.
 * @return float Linear-light channel.
 */
function salt_color_srgb_to_linear( float $channel ): float {
	return $channel <= 0.04045 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
}

/**
 * Gamma-encode one linear sRGB channel.
 *
 * @param float $channel Linear-light channel.
 * @return float Gamma-encoded channel.
 */
function salt_color_linear_to_srgb( float $channel ): float {
	return $channel <= 0.0031308 ? 12.92 * $channel : 1.055 * pow( $channel, 1.0 / 2.4 ) - 0.055;
}

/**
 * Convert OKLCH to linear sRGB channels (may fall outside 0..1).
 *
 * @param float $l Lightness 0..1.
 * @param float $c Chroma (>= 0).
 * @param float $h Hue in degrees.
 * @return array{float, float, float} Linear r, g, b (unclamped).
 */
function salt_color_oklch_to_linear_rgb( float $l, float $c, float $h ): array {
	$h_rad = deg2rad( $h );
	$a     = $c * cos( $h_rad );
	$b     = $c * sin( $h_rad );

	$l_ = $l + 0.3963377774 * $a + 0.2158037573 * $b;
	$m_ = $l - 0.1055613458 * $a - 0.0638541728 * $b;
	$s_ = $l - 0.0894841775 * $a - 1.2914855480 * $b;

	$l3 = $l_ * $l_ * $l_;
	$m3 = $m_ * $m_ * $m_;
	$s3 = $s_ * $s_ * $s_;

	return array(
		4.0767416621 * $l3 - 3.3077115913 * $m3 + 0.2309699292 * $s3,
		-1.2684380046 * $l3 + 2.6097574011 * $m3 - 0.3413193965 * $s3,
		-0.0041960863 * $l3 - 0.7034186147 * $m3 + 1.7076147010 * $s3,
	);
}

/**
 * Convert an sRGB hex colour to OKLCH.
 *
 * Pipeline: hex → gamma sRGB → linear sRGB → OKLab → OKLCH.
 * Invalid input logs and degrades to black (fail loud, never blank).
 *
 * @param string $hex Hex colour ('#rgb' or '#rrggbb').
 * @return array{l: float, c: float, h: float} l in 0..1, h in degrees 0..360.
 */
function salt_hex_to_oklch( string $hex ): array {
	$rgb = salt_color_parse_hex( $hex );
	if ( null === $rgb ) {
		salt_log( sprintf( 'salt_hex_to_oklch: invalid hex "%s"; using #000000.', $hex ) );
		$rgb = array( 0.0, 0.0, 0.0 );
	}

	$r = salt_color_srgb_to_linear( $rgb[0] );
	$g = salt_color_srgb_to_linear( $rgb[1] );
	$b = salt_color_srgb_to_linear( $rgb[2] );

	$l_ = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
	$m_ = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
	$s_ = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;

	$l_c = pow( max( 0.0, $l_ ), 1.0 / 3.0 );
	$m_c = pow( max( 0.0, $m_ ), 1.0 / 3.0 );
	$s_c = pow( max( 0.0, $s_ ), 1.0 / 3.0 );

	$lab_l = 0.2104542553 * $l_c + 0.7936177850 * $m_c - 0.0040720468 * $s_c;
	$lab_a = 1.9779984951 * $l_c - 2.4285922050 * $m_c + 0.4505937099 * $s_c;
	$lab_b = 0.0259040371 * $l_c + 0.7827717662 * $m_c - 0.8086757660 * $s_c;

	$chroma = sqrt( $lab_a * $lab_a + $lab_b * $lab_b );
	$hue    = fmod( rad2deg( atan2( $lab_b, $lab_a ) ), 360.0 );
	if ( $hue < 0.0 ) {
		$hue += 360.0;
	}

	return array(
		'l' => $lab_l,
		'c' => $chroma,
		'h' => $hue,
	);
}

/**
 * Convert OKLCH to an sRGB hex colour, gamut-mapping by chroma reduction.
 *
 * When the requested colour is outside the sRGB gamut, chroma is reduced by
 * binary search (~20 iterations) until the colour fits; the final channels
 * are clamped so the result is always a valid '#rrggbb'.
 *
 * @param float $l Lightness 0..1.
 * @param float $c Chroma (>= 0).
 * @param float $h Hue in degrees.
 * @return string '#rrggbb' hex colour.
 */
function salt_oklch_to_hex( float $l, float $c, float $h ): string {
	$c   = max( 0.0, $c );
	$eps = 0.000001;

	$in_gamut = static function ( array $rgb ) use ( $eps ): bool {
		foreach ( $rgb as $channel ) {
			if ( $channel < -$eps || $channel > 1.0 + $eps ) {
				return false;
			}
		}
		return true;
	};

	$rgb = salt_color_oklch_to_linear_rgb( $l, $c, $h );

	if ( ! $in_gamut( $rgb ) ) {
		$lo = 0.0;
		$hi = $c;
		for ( $i = 0; $i < 20; $i++ ) {
			$mid = ( $lo + $hi ) / 2.0;
			if ( $in_gamut( salt_color_oklch_to_linear_rgb( $l, $mid, $h ) ) ) {
				$lo = $mid;
			} else {
				$hi = $mid;
			}
		}
		$rgb = salt_color_oklch_to_linear_rgb( $l, $lo, $h );
	}

	$out = '';
	foreach ( $rgb as $channel ) {
		$srgb = salt_color_linear_to_srgb( max( 0.0, min( 1.0, $channel ) ) );
		$byte = (int) round( max( 0.0, min( 1.0, $srgb ) ) * 255.0 );
		$out .= sprintf( '%02x', $byte );
	}

	return '#' . $out;
}

/**
 * WCAG 2.x relative luminance of an sRGB hex colour.
 *
 * Uses the WCAG-specified linearisation (0.03928 / 12.92 breakpoint) and
 * the 0.2126 / 0.7152 / 0.0722 channel weights.
 *
 * @param string $hex Hex colour ('#rgb' or '#rrggbb').
 * @return float Relative luminance 0..1.
 */
function salt_relative_luminance( string $hex ): float {
	$rgb = salt_color_parse_hex( $hex );
	if ( null === $rgb ) {
		salt_log( sprintf( 'salt_relative_luminance: invalid hex "%s"; using #000000.', $hex ) );
		$rgb = array( 0.0, 0.0, 0.0 );
	}

	$linear = array_map(
		static fn( float $channel ): float => $channel <= 0.03928 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 ),
		$rgb
	);

	return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
}

/**
 * WCAG contrast ratio between two hex colours.
 *
 * @param string $hex_a First colour.
 * @param string $hex_b Second colour.
 * @return float Ratio in 1..21 ((L1 + 0.05) / (L2 + 0.05), lighter on top).
 */
function salt_contrast_ratio( string $hex_a, string $hex_b ): float {
	$lum_a = salt_relative_luminance( $hex_a );
	$lum_b = salt_relative_luminance( $hex_b );

	$lighter = max( $lum_a, $lum_b );
	$darker  = min( $lum_a, $lum_b );

	return ( $lighter + 0.05 ) / ( $darker + 0.05 );
}

if ( ! function_exists( 'salt_derive_ramp' ) ) {
	/**
	 * Derive an 11-step tonal ramp from one base colour (pluggable, ARCH07).
	 *
	 * Steps 50..950 sit on a fixed OKLCH lightness ladder; hue is held
	 * constant at the base hue; chroma is the base chroma scaled by a taper
	 * that is 1.0 at 500/600 and falls linearly to 0.25 at the 50 and 950
	 * extremes. Every step is gamut-mapped, so the ramp is always displayable.
	 *
	 * @param string $base_hex Base brand colour ('#rgb' or '#rrggbb').
	 * @return array<int, string> Map of step (50..950) to '#rrggbb'.
	 */
	function salt_derive_ramp( string $base_hex ): array {
		$base = salt_hex_to_oklch( $base_hex );

		$ladder = array(
			50  => 0.97,
			100 => 0.94,
			200 => 0.88,
			300 => 0.80,
			400 => 0.70,
			500 => 0.62,
			600 => 0.53,
			700 => 0.45,
			800 => 0.37,
			900 => 0.28,
			950 => 0.20,
		);

		$ramp = array();
		foreach ( $ladder as $step => $lightness ) {
			if ( $step <= 500 ) {
				$taper = 0.25 + 0.75 * ( $step - 50 ) / ( 500 - 50 );
			} elseif ( $step >= 600 ) {
				$taper = 0.25 + 0.75 * ( 950 - $step ) / ( 950 - 600 );
			} else {
				$taper = 1.0;
			}
			$ramp[ $step ] = salt_oklch_to_hex( $lightness, $base['c'] * $taper, $base['h'] );
		}

		return $ramp;
	}
}

/**
 * Pick a text colour guaranteed to pass WCAG AA (4.5:1) on a background.
 *
 * Prefers the theme's near-white / near-black pair; falls back to whichever
 * of pure white / pure black contrasts more (mathematically >= 4.5 for any
 * background, since max((1.05)/(Y+0.05), (Y+0.05)/0.05) >= sqrt(21) ≈ 4.58).
 *
 * @param string $background_hex Background colour ('#rgb' or '#rrggbb').
 * @return string Text colour hex passing 4.5:1 on the background.
 */
function salt_text_on( string $background_hex ): string {
	foreach ( array( '#f8fafc', '#0f172a' ) as $candidate ) {
		if ( salt_contrast_ratio( $candidate, $background_hex ) >= 4.5 ) {
			return $candidate;
		}
	}

	$white = salt_contrast_ratio( '#ffffff', $background_hex );
	$black = salt_contrast_ratio( '#000000', $background_hex );

	return $white >= $black ? '#ffffff' : '#000000';
}

/**
 * Build the ':root' CSS custom-property block for the brand palette.
 *
 * Accepts 'primary', 'secondary' and 'accent' hex values (each may be empty
 * or absent). Fallback chain: secondary → primary, accent → secondary →
 * primary. An empty or invalid primary falls back to the neutral default
 * '#4b5563' so a fresh install is always legible (FAIL03).
 *
 * Emits, per role, --color-{role}-{step} for all 11 ramp steps plus
 * --color-on-{role} (the AA text colour for step 600). Output order is fixed
 * (primary, secondary, accent; steps ascending) regardless of input order.
 *
 * @param array<string, mixed> $brand Brand colours keyed by role.
 * @return string ':root{...}' CSS block.
 */
function salt_brand_css( array $brand ): string {
	$pick = static function ( string $role ) use ( $brand ): string {
		$value = $brand[ $role ] ?? '';
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}
		if ( null === salt_color_parse_hex( $value ) ) {
			salt_log( sprintf( 'salt_brand_css: invalid %s colour "%s"; falling back.', $role, $value ) );
			return '';
		}
		return trim( $value );
	};

	$primary = $pick( 'primary' );
	if ( '' === $primary ) {
		$primary = '#4b5563';
	}

	$secondary = $pick( 'secondary' );
	if ( '' === $secondary ) {
		$secondary = $primary;
	}

	$accent = $pick( 'accent' );
	if ( '' === $accent ) {
		$accent = $secondary;
	}

	$roles = array(
		'primary'   => $primary,
		'secondary' => $secondary,
		'accent'    => $accent,
	);

	$css = ':root{';
	foreach ( $roles as $role => $hex ) {
		$ramp = salt_derive_ramp( $hex );
		foreach ( $ramp as $step => $step_hex ) {
			$css .= sprintf( '--color-%s-%d:%s;', $role, $step, $step_hex );
		}
		$css .= sprintf( '--color-on-%s:%s;', $role, salt_text_on( $ramp[600] ) );
	}
	$css .= '}';

	return $css;
}
