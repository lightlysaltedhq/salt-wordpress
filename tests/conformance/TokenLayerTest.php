<?php
/**
 * Token-layer gate: the theme emits every custom property the contract's
 * stylesheet needs from it.
 *
 * salt.css (@lightlysaltedhq/salt-contract styles/salt.css, served beside
 * Tailwind's build) reads only the custom properties contract/token-layer.json
 * names, and defines none of them. A property it reads that no stylesheet
 * declares resolves to nothing, silently: a band with no padding, a heading at
 * body size, a border the colour of the ground. So the list is checked against
 * what the theme actually serves, not against its source.
 *
 * WHAT COUNTS AS EMITTED
 * ----------------------
 * Every custom property declared in the built stylesheet (assets/dist, through
 * the manifest, the file a page loads, after Tailwind has dropped what it
 * drops) or in the `:root` block salt_brand_css() prints in <head>.
 *
 * WHAT THE THEME OWES
 * -------------------
 * The tokens whose `source` is `design-foundations` or `salt` (the theme's to
 * emit), less those marked `optional` (every read has a fallback) and those
 * with `requiredBy` (owed only while a dial is set: salt_brand_css() writes
 * them with the dial, which DesignDialsTest checks; here no dial is set). A
 * text role is five properties, per schema/token-layer.schema.json. Tokens
 * whose source is `markup` or `script` are written on an element or at run
 * time, not by the theme's stylesheet, so they are not looked for here.
 *
 * The check is equality, not inclusion: the token-layer names the theme emits
 * are the required ones plus exactly OPTIONAL_EMITTED. A new required token
 * fails until the theme emits it; an optional one emitted without being
 * listed fails until someone has decided the theme should set it.
 *
 * Lives in the conformance suite because it reads node_modules, and fails
 * rather than skips when the contract is not installed.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TokenLayerTest extends SaltTestCase {

	private const ROOT = __DIR__ . '/../..';

	private const TOKEN_LAYER = self::ROOT . '/node_modules/@lightlysaltedhq/salt-contract/contract/token-layer.json';

	/**
	 * The properties a text role stands for (schema/token-layer.schema.json,
	 * $defs.textRole): --text-<id>, then these suffixes.
	 */
	private const TEXT_ROLE_SUFFIXES = array( '', '--line-height', '--letter-spacing', '--font-weight', '--font-family' );

	/**
	 * Optional tokens the theme sets on purpose (assets/src/css/app.css says
	 * why each other optional one is left out).
	 */
	private const OPTIONAL_EMITTED = array( '--ease-in-out' );

	/**
	 * The theme-sourced tokens in token-layer.json, by name, each with whether
	 * the theme owes it.
	 *
	 * @return array<string, bool> Property => required.
	 */
	private static function theme_tokens(): array {
		if ( ! is_file( self::TOKEN_LAYER ) ) {
			self::fail( 'contract/token-layer.json is not installed. Run npm ci; this gate fails rather than skips.' );
		}
		$layer = json_decode( (string) file_get_contents( self::TOKEN_LAYER ), true );
		if ( ! is_array( $layer ) || ! is_array( $layer['groups'] ?? null ) ) {
			self::fail( 'contract/token-layer.json has no groups to read.' );
		}

		$tokens = array();
		foreach ( $layer['groups'] as $group ) {
			foreach ( (array) ( $group['tokens'] ?? array() ) as $token ) {
				if ( ! in_array( $token['source'] ?? null, array( 'design-foundations', 'salt' ), true ) ) {
					continue;
				}
				$required = empty( $token['optional'] ) && empty( $token['requiredBy'] );
				if ( isset( $token['textRole'] ) ) {
					foreach ( self::TEXT_ROLE_SUFFIXES as $suffix ) {
						$tokens[ '--text-' . $token['textRole'] . $suffix ] = $required;
					}
				} elseif ( isset( $token['name'] ) ) {
					$tokens[ $token['name'] ] = $required;
				} else {
					self::fail( 'contract/token-layer.json has a token with neither `name` nor `textRole`: ' . wp_json_encode( $token ) );
				}
			}
		}

		return $tokens;
	}

	/**
	 * The built stylesheet a page loads for Tailwind's half, through the
	 * manifest.
	 *
	 * @return string
	 */
	private static function built_css(): string {
		$manifest = json_decode( (string) file_get_contents( self::ROOT . '/assets/dist/manifest.json' ), true );
		$file     = self::ROOT . '/assets/dist/' . ( $manifest['app.css'] ?? '' );
		if ( ! is_array( $manifest ) || ! isset( $manifest['app.css'] ) || ! is_file( $file ) ) {
			self::fail( 'assets/dist has no app.css in its manifest. Run npm run build.' );
		}

		return (string) file_get_contents( $file );
	}

	/**
	 * Every custom property declared in some CSS (a name followed by a colon;
	 * a var() reference is never followed by one).
	 *
	 * @param string $css CSS.
	 * @return array<string, true>
	 */
	private static function declared( string $css ): array {
		preg_match_all( '/(?<![\w-])(--[\w-]+)\s*:/', $css, $found );

		return array_fill_keys( $found[1], true );
	}

	/**
	 * The token-layer names the theme emits, given its stylesheet: what
	 * the stylesheet and salt_brand_css() declare, kept to theme_tokens().
	 *
	 * @param string $css The built stylesheet.
	 * @return list<string> Sorted.
	 */
	private static function emitted( string $css ): array {
		$declared = self::declared( $css ) + self::declared( salt_brand_css( array( 'primary' => '#4b5563' ) ) );
		$names    = array_keys( array_intersect_key( self::theme_tokens(), $declared ) );
		sort( $names );

		return $names;
	}

	/**
	 * What emitted() must equal: the required tokens and OPTIONAL_EMITTED.
	 *
	 * @return list<string> Sorted.
	 */
	private static function owed(): array {
		$names = array_merge( array_keys( array_filter( self::theme_tokens() ) ), self::OPTIONAL_EMITTED );
		sort( $names );

		return $names;
	}

	public function test_the_theme_emits_exactly_the_required_token_layer(): void {
		$emitted = self::emitted( self::built_css() );
		$owed    = self::owed();

		$this->assertGreaterThan( 100, count( $owed ), 'token-layer.json yielded too few theme tokens; the reader is wrong, so this check verified nothing.' );
		$this->assertSame(
			$owed,
			$emitted,
			"The theme's token emission is not the contract's token layer.\n"
			. '  Missing (owed, not emitted): ' . implode( ', ', array_diff( $owed, $emitted ) ) . "\n"
			. '  Unexpected (emitted, not owed): ' . implode( ', ', array_diff( $emitted, $owed ) ) . "\n"
			. "  Emit a missing token from assets/src/css/app.css (colour roles in @theme, the rest in its :root block) and rebuild; list an optional one in OPTIONAL_EMITTED once it is meant.\n"
		);
	}

	public function test_a_missing_token_fails_the_check(): void {
		$css = self::built_css();

		foreach ( array( '--color-border-strong', '--text-eyebrow--letter-spacing', '--space-card-row', '--scrim-strong' ) as $token ) {
			$without = (string) preg_replace( '/(?<![\w-])' . preg_quote( $token, '/' ) . '\s*:[^;}]*;?/', '', $css );
			$this->assertNotSame( $css, $without, "{$token} is not declared in the built stylesheet, so removing it proves nothing." );
			$this->assertSame(
				array( $token ),
				array_values( array_diff( self::owed(), self::emitted( $without ) ) ),
				"Removing {$token} from the built stylesheet should make exactly it missing."
			);
		}
	}
}
