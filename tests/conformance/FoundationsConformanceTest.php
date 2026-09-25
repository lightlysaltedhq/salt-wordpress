<?php
/**
 * Conformance gate: salt conforms to @lightlysaltedhq/design-foundations.
 *
 * design-foundations is the Lightly Salted design-system contract: the tier
 * model, the role vocabulary, the OKLCH derivation spec and the frozen reference
 * vectors. It holds RULES and deliberately no colour values, so every product
 * that conforms supplies its own palette. It was otigs/base-design-system until
 * 18/08/2026, when that repository was folded into lightlysaltedhq/design-system
 * as foundations/ and archived; the published package is its public contract.
 *
 * WHERE THE CONTRACT COMES FROM
 * -----------------------------
 * node_modules, installed by npm from the EXACT pin in package.json (and the
 * integrity hash in package-lock.json). Every file is found through the
 * package's own `exports` map rather than a hard-coded path inside it, so a
 * release that moves a file is read correctly and one that drops an export
 * fails here, naming the export. The pin is exact on purpose: the contract
 * moves by a deliberate bump in a reviewed PR, never by a range floating to
 * whatever was published last.
 *
 * HOW A BUMP IS HELD UNTIL SOMEONE HAS READ IT
 * --------------------------------------------
 * PINNED records the version salt was last checked against, and
 * VOCABULARY_SHA256 the SHA-256 of that version's vocabulary.json in
 * canonical form: keys sorted at every depth, `$`-prefixed prose keys and the
 * top-level `version` removed. Any other change to the vocabulary, at any
 * depth, fails with the npm diff command to read; the fix is to bring salt
 * into line and then re-pin both constants.
 *
 * The contract is pinned whole rather than checked key by key on purpose.
 * Key-by-key checks were tried on this suite first, and two review rounds
 * each found a shape they missed one level below the last fix. A hash has no
 * level to miss. The role checks below still run on top of it, because a
 * re-pinned contract must still be one whose every required role salt
 * declares.
 *
 * HOW THIS DIFFERS FROM ColorVectorsTest
 * --------------------------------------
 * ColorVectorsTest holds PHP to tests/fixtures/color-vectors.json — salt's OWN
 * fixture, which also pins oklch/luminance/textOn/contrastWhite and keeps the JS
 * port (assets/src/js/admin/contrast-checker.js) in step. That is salt-internal
 * parity and stays exactly as it is.
 *
 * This test holds PHP to the contract's vectors — the CROSS-REPO truth, frozen
 * from the agreement of two independent implementations. The two fixtures
 * overlap only on ramps, and that overlap is the point: if salt's colour maths
 * ever drifts, both trip.
 *
 * CONTRAST
 * --------
 * Every rule in `contrast.requirements`, and every rule in
 * `contrast.conditionalRequirements` whose `when` roles app.css declares (in
 * 2.1.0 that is one: `ink` on `brand.tint`), is evaluated in every display
 * mode a visitor can combine (MODES: light or dark, with or without high
 * contrast, with or without grayscale), at rest and on hover (HOVER), for
 * each of the contract's frozen seeds as salt_brand_css() emits it and for
 * the compiled defaults a site with no brand set renders. Ratios come from
 * salt_derive_ramp(), salt_text_on() and salt_contrast_ratio(), salt's own
 * maths. Contrast failures turned up three times before this existed
 * (otigs/salt#3, otigs/salt#4, and a photo hero on a client site).
 *
 * The values come from app.css read as a table of rules, not from a model
 * of the browser. colour_rules() says what it accepts and refuses the rest,
 * and tokens() refuses to rank two equally specific rules that disagree:
 * that tie is what a browser settles by source order, and it is how #4
 * shipped.
 *
 * WHAT THIS DOES NOT CHECK
 * ------------------------
 * - Pairs the contract has no rule for. salt also paints `text-brand` on
 *   `surface` (links, eyebrows) and a `focus` ring that needs 3:1; neither
 *   is measured.
 * - Brands outside the frozen seeds. On-brand at rest holds for any seed by
 *   construction (salt_text_on()); hover and every other pair are measured
 *   for these seeds only.
 * - Hover anywhere HOVER does not name. It is declared from the templates,
 *   not read from them.
 * - The built stylesheet. Tailwind drops theme variables that nothing
 *   reads, so a role measured here from app.css can be absent from
 *   assets/dist. Today `accent` is declared there only under grayscale, and
 *   `on-accent` only arrives with salt_brand_css()'s block when a brand is
 *   set (measured in Chrome 148.0.7778.96 against app.59614ed624.css).
 * - That surface.alt, border and focus RESOLVE. The role check finds a
 *   declaration such as `--color-border:` in app.css and reads nothing after
 *   the colon; only the roles a contrast pair names are resolved.
 * - That components consume roles rather than raw ramps. The contract's tier
 *   model says components MUST NOT consume Tier 1 directly (a ramp step such
 *   as `bg-primary-600`); nothing here looks at a template.
 *
 * A green run here says the vocabulary's rules are the ones last read, the
 * derivation reproduces the frozen vectors, every required role is declared
 * and the contract's contrast rules hold in every display mode for the
 * frozen seeds. It says nothing about other pairs, other brands or component
 * discipline.
 *
 * FAILS CLOSED. If the package or any file it exports is absent, this test
 * FAILS rather than skips — salt cannot claim to conform to something it cannot
 * read. Both of this fleet's gates once returned 0 on skip, so CI without their
 * dependencies passed green while verifying nothing (fixed in
 * ls-design-system@eb3011b). A gate that passes when it did not run is worse
 * than no gate.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FoundationsConformanceTest extends SaltTestCase {

	private const PACKAGE = '@lightlysaltedhq/design-foundations';

	private const PACKAGE_DIR = __DIR__ . '/../../node_modules/@lightlysaltedhq/design-foundations';

	/**
	 * Every export salt resolves. vocabulary and vectors are read below;
	 * derivation and the token-file schema are resolved only, so a release that
	 * drops or renames one fails at the bump, by name, rather than on the day
	 * something here starts to need it.
	 */
	private const EXPORTS = array( './vocabulary', './vectors', './derivation', './schema/token-file' );

	/**
	 * The contract version salt was last checked against, and the SHA-256 of that
	 * version's vocabulary.json in canonical form (see vocabulary_hash()). They
	 * move together, and only after someone has read what changed: package.json
	 * pinning anything else fails, and so does an installed vocabulary whose rules
	 * hash differently, each with the npm diff command to read.
	 */
	private const PINNED = '2.1.0';

	private const VOCABULARY_SHA256 = '845fa7153858d66cd544b9e9911a1ff800567e6a6c7af5b18b53cd0ea38d155c';

	private const APP_CSS = __DIR__ . '/../../assets/src/css/app.css';

	/**
	 * The mode table: each display mode <html> can carry, as the attribute
	 * selector app.css writes it with. salt_theme_init_js() sets these before
	 * paint and assets/src/js/display-prefs.js toggles them, so a visitor can
	 * hold any combination, and every one of the 2^3 is evaluated. A colour
	 * token declared under any other selector fails the suite: nothing here
	 * could say when it applies.
	 */
	private const MODES = array(
		'dark'          => '[data-theme="dark"]',
		'high contrast' => '[data-contrast="high"]',
		'grayscale'     => '[data-grayscale]',
	);

	/**
	 * What a fill becomes on hover. Each element painted `bg-brand
	 * text-on-brand` that changes on hover goes to `hover:bg-brand-strong`
	 * (button.php, contact.php, booking.php, map.php), so on-brand is measured
	 * on brand.strong as well. No other contract pair changes on hover in
	 * salt's templates.
	 */
	private const HOVER = array( 'brand' => 'brand.strong' );

	/**
	 * Decode a JSON file, failing by name if it is absent or not an object.
	 *
	 * @param string $path File path.
	 * @return array<string, mixed>
	 */
	private static function decode( string $path ): array {
		if ( ! is_file( $path ) ) {
			self::fail( "{$path} not found." );
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $decoded ) ) {
			self::fail( "{$path} is not valid JSON." );
		}

		return $decoded;
	}

	/**
	 * The installed package's own package.json.
	 *
	 * @return array<string, mixed>
	 */
	private static function manifest(): array {
		$path = self::PACKAGE_DIR . '/package.json';

		if ( ! is_file( $path ) ) {
			self::fail(
				'The contract is not installed — ' . self::PACKAGE . " not found at {$path}.\n"
				. "  salt CONFORMS to it; it cannot claim that with the contract absent.\n"
				. "  This gate does not skip, by design.\n"
				. '  Fix: npm ci'
			);
		}

		return self::decode( $path );
	}

	/**
	 * Resolve one export to its file in node_modules, the way Node would for
	 * the one shape this test reads: an `exports` map with an explicit subpath
	 * key such as './vectors', whose target is a plain relative string.
	 *
	 * Anything else would need a resolver this test does not have: `exports`
	 * given as a string or a conditions object, a subpath reached only through
	 * a pattern key such as './*', or a target that is itself a conditions
	 * object ({"import": ..., "require": ...}). Each fails naming the shape it
	 * met rather than guessing a branch, and never reports the export missing.
	 *
	 * @param string $export Export subpath, e.g. './vectors'.
	 * @return string Path of the file the export maps to.
	 */
	private static function resolve( string $export ): string {
		$exports = self::manifest()['exports'] ?? null;

		if ( null === $exports ) {
			self::fail( self::PACKAGE . " has no exports map, so it does not export '{$export}', which salt reads." );
		}

		if ( ! is_array( $exports ) || array() === $exports || array_is_list( $exports ) || ! str_starts_with( (string) array_key_first( $exports ), '.' ) ) {
			$shape = match ( true ) {
				is_string( $exports )     => 'a string',
				! is_array( $exports )    => 'a ' . gettype( $exports ),
				array() === $exports      => 'an empty map',
				array_is_list( $exports ) => 'an array of fallbacks',
				default                   => 'a conditions object',
			};
			self::fail( self::PACKAGE . " declares `exports` as {$shape} (" . wp_json_encode( $exports ) . "). This test reads explicit subpaths only, such as '{$export}'." );
		}

		if ( ! array_key_exists( $export, $exports ) ) {
			// Only a pattern that could match this subpath is named: its text before
			// the `*` a prefix, its text after a suffix, and the subpath at least as
			// long as the key (Node's own matching rule). An unrelated pattern such
			// as './schema/*' must not hide a dropped './vocabulary'.
			$patterns = array();
			foreach ( array_map( 'strval', array_keys( $exports ) ) as $key ) {
				$star = strpos( $key, '*' );
				if ( false !== $star && strlen( $export ) >= strlen( $key ) && str_starts_with( $export, substr( $key, 0, $star ) ) && str_ends_with( $export, substr( $key, $star + 1 ) ) ) {
					$patterns[] = $key;
				}
			}
			if ( array() !== $patterns ) {
				self::fail( self::PACKAGE . " does not list '{$export}' explicitly; it is reachable only through the pattern " . implode( ', ', $patterns ) . ', which this test does not expand. It reads explicit subpaths only.' );
			}
			self::fail( self::PACKAGE . " does not export '{$export}', which salt reads. Check the release notes of the version pinned in package.json." );
		}

		$target = $exports[ $export ];
		if ( ! is_string( $target ) || ! str_starts_with( $target, './' ) || in_array( '..', explode( '/', $target ), true ) ) {
			self::fail( self::PACKAGE . " maps '{$export}' to a target this test does not read (" . wp_json_encode( $target ) . '). It reads a plain relative string only, such as "./contract/vectors.json".' );
		}

		$path = self::PACKAGE_DIR . '/' . substr( $target, 2 );
		if ( ! is_file( $path ) ) {
			self::fail( self::PACKAGE . " exports '{$export}' as {$target}, and that file is not in the installed package ({$path})." );
		}

		return $path;
	}

	/**
	 * Decode the contract file behind one export.
	 *
	 * @param string $export Export subpath, e.g. './vocabulary'.
	 * @return array<string, mixed>
	 */
	private static function contract( string $export ): array {
		return self::decode( self::resolve( $export ) );
	}

	/**
	 * The value at a dotted path, or null if any step of it is absent.
	 *
	 * @param array<string, mixed> $data Decoded document.
	 * @param string               $path Dotted path; '' is the document itself.
	 * @return mixed
	 */
	private static function dig( array $data, string $path ) {
		$node = $data;
		foreach ( '' === $path ? array() : explode( '.', $path ) as $key ) {
			$node = is_array( $node ) ? ( $node[ $key ] ?? null ) : null;
		}
		return $node;
	}

	/**
	 * A required-role list from the vocabulary, failing by name if it is
	 * absent, not a list, or empty. An empty list would make the role check
	 * vacuous: nothing to look for, so nothing missing, so green.
	 *
	 * @param array<string, mixed> $vocab Decoded vocabulary.
	 * @param string               $path  Dotted path of the list.
	 * @return list<mixed>
	 */
	private static function required( array $vocab, string $path ): array {
		$list = self::dig( $vocab, $path );

		if ( ! is_array( $list ) || array() === $list || ! array_is_list( $list ) ) {
			self::fail( "vocabulary.json '{$path}' is missing, empty or not a list, so there is nothing for salt to be checked against. Read the release before trusting a green run." );
		}

		return $list;
	}

	/**
	 * A decoded JSON value in canonical form: object keys sorted at every depth
	 * and every `$`-prefixed key dropped, list order kept.
	 *
	 * Objects are decoded as stdClass, not arrays, so an empty object and an
	 * empty list, or an object keyed "0", "1", stay distinct from a list.
	 *
	 * @param mixed $node Value from json_decode() without the associative flag.
	 * @return mixed
	 */
	private static function canonical( $node ) {
		if ( is_array( $node ) ) {
			return array_map( static fn( $item ) => self::canonical( $item ), $node );
		}
		if ( ! $node instanceof stdClass ) {
			return $node;
		}

		$props = array();
		foreach ( get_object_vars( $node ) as $key => $value ) {
			if ( ! str_starts_with( (string) $key, '$' ) ) {
				$props[ (string) $key ] = self::canonical( $value );
			}
		}
		ksort( $props, SORT_STRING );

		return (object) $props;
	}

	/**
	 * SHA-256 of the installed vocabulary.json's normative content: canonical
	 * form (above) with the top-level `version` removed, encoded as compact JSON
	 * with slashes and Unicode unescaped and zero fractions kept (3.0 stays 3.0).
	 * That is byte-for-byte what Python's json.dumps(..., separators=(',', ':'),
	 * ensure_ascii=False) writes for the same sorted, filtered tree, so the hash
	 * can be checked without PHP.
	 *
	 * `$` keys are the contract's own prose convention ($rule, $why, $note), and
	 * `version` is bookkeeping, so rewording either never trips this. Everything
	 * else counts, including each role's `what` text: deciding which unprefixed
	 * strings are normative would be modelling the contract, and a re-pin after
	 * reading a wording change costs one line.
	 *
	 * @return string
	 */
	private static function vocabulary_hash(): string {
		$vocab = json_decode( (string) file_get_contents( self::resolve( './vocabulary' ) ) );
		if ( ! $vocab instanceof stdClass ) {
			self::fail( 'vocabulary.json is not a JSON object.' );
		}

		unset( $vocab->version );

		return hash( 'sha256', (string) json_encode( self::canonical( $vocab ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION ) );
	}

	/**
	 * Every rule in app.css that declares a colour token: the @theme block at
	 * rank 0, and one rule per combination of MODES at a rank equal to the
	 * number of modes it names. Every attribute selector weighs the same, so
	 * that rank is the rule's specificity.
	 *
	 * Only those two shapes are read. A colour token declared under any other
	 * selector (a selector list, `:root[...]`, a mode spelled differently from
	 * MODES), inside a nested block such as @media or @layer, with
	 * !important, twice in one rule, or in a second rule for a combination
	 * that already has one, fails here by name. Each of those would make the
	 * outcome depend on something rank cannot see, so the suite refuses it
	 * rather than guess.
	 *
	 * @return list<array{selector: string, modes: list<string>, rank: int, set: array<string, string>}>
	 */
	private static function colour_rules(): array {
		// Comments go first, but not the `/*` in `@source "../../../*.php"`: a
		// match that ignored strings would eat everything up to the next `*/`.
		$css = (string) preg_replace_callback(
			'#("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')|/\*.*?\*/#s',
			static fn( array $found ): string => $found[1] ?? '',
			(string) file_get_contents( self::APP_CSS )
		);

		$length = strlen( $css );
		$rules  = array();
		$depth  = 0;
		$start  = 0;
		$open   = 0;

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $css[ $i ];

			if ( '"' === $char || "'" === $char ) {
				$close = $i + 1;
				while ( $close < $length && $css[ $close ] !== $char ) {
					$close += '\\' === $css[ $close ] ? 2 : 1;
				}
				if ( $close >= $length ) {
					self::fail( 'app.css has an unterminated string at byte ' . $i . '.' );
				}
				$i = $close;
			} elseif ( '{' === $char ) {
				if ( 0 === $depth ) {
					$open = $i + 1;
				}
				++$depth;
			} elseif ( '}' === $char ) {
				--$depth;
				if ( 0 === $depth ) {
					$rule = self::colour_rule( trim( substr( $css, $start, $open - 1 - $start ) ), substr( $css, $open, $i - $open ) );
					if ( null !== $rule ) {
						if ( array_key_exists( $rule['selector'], $rules ) ) {
							self::fail( "app.css declares colour tokens in two `{$rule['selector']}` rules. Which one wins would come down to source order; merge them." );
						}
						$rules[ $rule['selector'] ] = $rule;
					}
					$start = $i + 1;
				}
			} elseif ( ';' === $char && 0 === $depth ) {
				$start = $i + 1;
			}
		}

		if ( 0 !== $depth ) {
			self::fail( 'app.css has unbalanced braces, so this suite cannot read its rules.' );
		}

		$rules = array_values( $rules );
		if ( 1 !== count( array_filter( $rules, static fn( array $rule ): bool => 0 === $rule['rank'] ) ) ) {
			self::fail( 'app.css has no @theme block declaring colour tokens, so there is no base palette to evaluate.' );
		}

		return $rules;
	}

	/**
	 * One top-level app.css rule read as a row of the mode table, or null
	 * when it declares no colour token.
	 *
	 * @param string $prelude Selector or at-rule before the brace.
	 * @param string $body    Everything between the braces.
	 * @return array{selector: string, modes: list<string>, rank: int, set: array<string, string>}|null
	 */
	private static function colour_rule( string $prelude, string $body ): ?array {
		$prelude = (string) preg_replace( '/\s+/', ' ', $prelude );

		// A `;` or `:` inside a string is not a declaration boundary. Blank each
		// string's contents (either quote, escapes respected) before anything
		// splits the body, or `--x-note: "x;--color-on-brand:#0f172a;"` would
		// read as an on-brand declaration no browser applies.
		$body = (string) preg_replace_callback(
			'/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/s',
			static fn( array $found ): string => $found[0][0] . str_repeat( ' ', strlen( $found[0] ) - 2 ) . $found[0][0],
			$body
		);

		// Chromium reads `--color-on\-brand` as --color-on-brand, so an escape
		// can set a colour token the pattern below never sees. Any property
		// spelled with a backslash is refused, nested blocks included, rather
		// than decoding CSS escapes here.
		if ( 1 === preg_match( '/(?:^|[;{])\s*((?=[-\\\\])[^\s;{}:]*\\\\[^\s;{}:]*)\s*:/', $body, $escaped ) ) {
			self::fail( "app.css declares `{$escaped[1]}` under `{$prelude}`, a property spelled with a backslash escape. A browser decodes the escape, so it can set a colour token this suite does not recognise (Chromium reads --color-on\\-brand as --color-on-brand). Spell the property without escapes." );
		}

		if ( 0 === preg_match( '/(?:^|[;{\s])(--color-[\w-]+)\s*:/', $body, $first ) ) {
			return null;
		}

		if ( str_contains( $body, '{' ) ) {
			self::fail( "app.css declares {$first[1]} inside a block nested under `{$prelude}`. This suite reads colour tokens from @theme and the display-mode rules only, and cannot rank a nested declaration against them. Move it into one of those." );
		}

		$modes = array();
		if ( '@theme' !== $prelude ) {
			preg_match_all( '/\[[^\]]*\]/', $prelude, $attributes );
			foreach ( $attributes[0] as $attribute ) {
				$mode = array_search( $attribute, self::MODES, true );
				if ( false !== $mode && ! in_array( $mode, $modes, true ) ) {
					$modes[] = $mode;
				}
			}

			if ( array() === $modes || implode( '', $attributes[0] ) !== $prelude || count( $modes ) !== count( $attributes[0] ) ) {
				self::fail(
					"app.css declares {$first[1]} under `{$prelude}`, which is neither @theme nor a combination of the display modes as the mode table spells them ("
					. implode( ', ', self::MODES ) . "). This suite cannot say when that rule applies or what it beats, so it refuses to guess.\n"
					. '  If it is a new mode, add it to MODES in ' . __FILE__ . '; otherwise move the declaration into the rule for the combination it belongs to.'
				);
			}

			// One key per combination, whatever order the attributes were written in.
			$modes = array_values( array_intersect( array_keys( self::MODES ), $modes ) );
		}

		$set = array();
		foreach ( explode( ';', $body ) as $declaration ) {
			if ( '' === trim( $declaration ) ) {
				continue;
			}

			$parts = explode( ':', $declaration, 2 );
			$prop  = trim( $parts[0] );
			if ( ! str_starts_with( $prop, '--color-' ) ) {
				continue;
			}

			$value = trim( $parts[1] ?? '' );
			if ( 1 === preg_match( '/!\s*important/i', $value ) ) {
				self::fail( "app.css marks {$prop} !important under `{$prelude}`. !important overrides every rank this suite reads, so it cannot evaluate the modes around it." );
			}
			if ( array_key_exists( $prop, $set ) ) {
				self::fail( "app.css declares {$prop} twice under `{$prelude}`." );
			}
			$set[ $prop ] = $value;
		}

		return array(
			'selector' => '@theme' === $prelude ? '@theme' : implode( '', array_map( static fn( string $mode ): string => self::MODES[ $mode ], $modes ) ),
			'modes'    => $modes,
			'rank'     => count( $modes ),
			'set'      => $set,
		);
	}

	/**
	 * The `:root` block salt_brand_css() prints for one primary seed, as
	 * property => value. It is unlayered and prints after the stylesheet, so
	 * it beats @theme. It would tie with a one-mode rule on specificity, so
	 * test_colour_tokens_are_declared_only_where_they_can_be_ranked() refuses
	 * any property the two share.
	 *
	 * @param string $seed Primary brand colour.
	 * @return array<string, string>
	 */
	private static function runtime( string $seed ): array {
		static $cache = array();

		if ( ! isset( $cache[ $seed ] ) ) {
			$css = salt_brand_css( array( 'primary' => $seed ) );
			if ( 1 !== preg_match( '/^:root\{((?:--color-[\w-]+:#[0-9a-f]{6};)+)\}$/', $css, $block ) ) {
				self::fail( "salt_brand_css() printed a block this suite cannot read: {$css}" );
			}

			$cache[ $seed ] = array();
			foreach ( explode( ';', rtrim( $block[1], ';' ) ) as $declaration ) {
				list( $prop, $value )    = explode( ':', $declaration, 2 );
				$cache[ $seed ][ $prop ] = $value;
			}
		}

		return $cache[ $seed ];
	}

	/**
	 * Every colour token's value in one combination of display modes: @theme,
	 * then the runtime block, then each applicable mode rule, the highest rank
	 * winning. Two rules of equal rank that set a token differently cannot be
	 * ranked: a browser would take whichever comes later in app.css. That
	 * token is left null, which measure() reports as unrankable, and the tie
	 * is described in $ties.
	 *
	 * @param list<array{selector: string, modes: list<string>, rank: int, set: array<string, string>}> $rules   colour_rules().
	 * @param list<string>                                                                          $combo   Mode names in force.
	 * @param array<string, string>                                                                 $runtime runtime(), or array() for the compiled defaults.
	 * @param array<string, string>                                                                 $ties    Receives one description per unrankable token.
	 * @return array<string, string|null>
	 */
	private static function tokens( array $rules, array $combo, array $runtime, array &$ties ): array {
		$tokens = array();
		$best   = array();

		foreach ( $rules as $rule ) {
			if ( 0 === $rule['rank'] ) {
				$tokens = array_merge( $rule['set'], $runtime );
			}
		}

		foreach ( $rules as $rule ) {
			if ( 0 === $rule['rank'] || array() !== array_diff( $rule['modes'], $combo ) ) {
				continue;
			}
			foreach ( $rule['set'] as $prop => $value ) {
				if ( ! isset( $best[ $prop ] ) || $rule['rank'] > $best[ $prop ]['rank'] ) {
					$best[ $prop ] = array(
						'rank' => $rule['rank'],
						'by'   => array(),
					);
				}
				if ( $rule['rank'] === $best[ $prop ]['rank'] ) {
					$best[ $prop ]['by'][ $rule['selector'] ] = $value;
				}
			}
		}

		foreach ( $best as $prop => $winner ) {
			if ( 1 === count( array_unique( $winner['by'] ) ) ) {
				$tokens[ $prop ] = reset( $winner['by'] );
				continue;
			}

			$tokens[ $prop ] = null;
			$claims          = array();
			foreach ( $winner['by'] as $selector => $value ) {
				$claims[] = "{$selector} sets it to {$value}";
			}
			$ties[ $prop ] = "{$prop}: " . implode( ', ', $claims ) . ', at the same specificity, and no rule for this combination settles it. A browser takes whichever comes later in app.css; add a rule for the combination instead.';
		}

		return $tokens;
	}

	/**
	 * The hex colour a role resolves to, following var() chains, or null and
	 * the reason it cannot be measured.
	 *
	 * @param array<string, string|null> $tokens tokens().
	 * @param string                     $role   Contract role id, e.g. 'brand.tint'.
	 * @return array{0: string|null, 1: string}
	 */
	private static function measure( array $tokens, string $role ): array {
		$prop = '--color-' . str_replace( '.', '-', $role );

		for ( $hop = 0; $hop < 12; $hop++ ) {
			if ( ! array_key_exists( $prop, $tokens ) ) {
				return array( null, "{$prop} is not declared" );
			}
			if ( null === $tokens[ $prop ] ) {
				return array( null, "{$prop} cannot be ranked" );
			}

			$value = $tokens[ $prop ];
			if ( 1 === preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ) {
				return array( strtolower( $value ), '' );
			}
			if ( 1 !== preg_match( '/^var\(\s*(--[\w-]+)\s*\)$/', $value, $reference ) ) {
				return array( null, "{$prop} is `{$value}`, which is not a colour this suite can measure" );
			}
			$prop = $reference[1];
		}

		return array( null, "the var() chain from --color-{$role} is longer than 12 steps" );
	}

	/**
	 * package.json pins the contract as a bare version, that version is PINNED,
	 * and node_modules holds it.
	 *
	 * Only the bare spelling (2.1.0) is accepted. npm treats '=2.1.0' and
	 * 'v2.1.0' as exact too, but one spelling keeps the lockfile and this check
	 * simple, so those fail here with a message saying what to write instead.
	 *
	 * Matching PINNED makes a bump fail until the diff has been read, even when
	 * the vocabulary hash happens not to change. The last check catches a stale
	 * install: bump the pin without running npm ci and every other test here
	 * would quietly read the old contract.
	 */
	public function test_the_contract_is_pinned_exactly_and_installed_at_the_pin(): void {
		$salt = self::decode( __DIR__ . '/../../package.json' );
		$pin  = $salt['devDependencies'][ self::PACKAGE ] ?? null;

		$this->assertIsString( $pin, self::PACKAGE . ' is not a devDependency in package.json.' );
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/',
			$pin,
			self::PACKAGE . " is required as '{$pin}'. Write the bare version, e.g. '2.1.0': this check accepts no other spelling, and a range would float."
		);
		$this->assertSame(
			self::PINNED,
			$pin,
			"package.json pins {$pin}, but salt was last checked against " . self::PINNED . ".\n"
			. "  Read what changed:\n"
			. '    npm diff --diff=' . self::PACKAGE . '@' . self::PINNED . ' --diff=' . self::PACKAGE . "@{$pin}\n"
			. "  Update salt to conform, then re-pin PINNED and VOCABULARY_SHA256 in this file.\n"
		);
		$this->assertSame(
			$pin,
			self::manifest()['version'] ?? null,
			"package.json pins {$pin} but node_modules holds a different version. Fix: npm ci"
		);
	}

	/**
	 * Every export salt relies on resolves to a JSON document in the installed package.
	 */
	public function test_every_export_resolves(): void {
		foreach ( self::EXPORTS as $export ) {
			$this->assertNotEmpty( self::contract( $export ), "'{$export}' resolved to an empty document" );
		}
	}

	/**
	 * One data-provider row per frozen vector.
	 *
	 * @return array<string, array{string, array<string, string>}>
	 */
	public static function vectorProvider(): array {
		$rows = array();
		foreach ( self::contract( './vectors' )['vectors'] as $vector ) {
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
		$vectors = self::contract( './vectors' );

		$this->assertGreaterThanOrEqual( 15, count( $vectors['vectors'] ) );
		$this->assertSame( 165, $vectors['counts']['assertions'] );

		foreach ( $vectors['vectors'] as $vector ) {
			$this->assertCount( 11, $vector['ramp'], "{$vector['seed']} is not an 11-step ladder" );
			$this->assertNotEmpty( $vector['exercises'], "{$vector['seed']} does not say what it exercises" );
		}
	}

	/**
	 * The contract's rules are the ones salt was last checked against.
	 *
	 * Any normative change at any depth, a new family, a new key on a role, a
	 * moved or emptied list, a changed selector, fails here until someone has
	 * read the diff, brought salt into line and re-pinned the hash.
	 */
	public function test_the_contract_rules_are_the_ones_salt_was_checked_against(): void {
		$installed = (string) ( self::manifest()['version'] ?? '' );
		$hash      = self::vocabulary_hash();

		$this->assertSame(
			self::VOCABULARY_SHA256,
			$hash,
			"The contract's rules changed. The installed " . self::PACKAGE . " {$installed} vocabulary hashes to {$hash}, not the hash recorded for " . self::PINNED . ".\n"
			. "  Read what changed:\n"
			. '    npm diff --diff=' . self::PACKAGE . '@' . self::PINNED . ' --diff=' . self::PACKAGE . "@{$installed}\n"
			. "  Update salt to conform, then re-pin PINNED and VOCABULARY_SHA256 in this file.\n"
		);
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
		$vocab = self::contract( './vocabulary' );
		$css   = (string) file_get_contents( __DIR__ . '/../../assets/src/css/app.css' );

		$roles = array();
		foreach ( self::required( $vocab, 'colorRoles.required' ) as $role ) {
			$roles[] = $role['id'];
		}
		$roles = array_merge( $roles, self::required( $vocab, 'colorRoles.status.required' ) );

		foreach ( $roles as $role ) {
			$prop = '--color-' . str_replace( '.', '-', $role );
			$this->assertMatchesRegularExpression(
				'/^\s*' . preg_quote( $prop, '/' ) . '\s*:/m',
				$css,
				"required role '{$role}' ({$prop}) is not declared in app.css"
			);
		}

		foreach ( self::required( $vocab, 'typographyRoles.required' ) as $role ) {
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
	 * that standardised either would break the other — see the vocabulary's
	 * `neutralRamp`.
	 */
	public function test_a_neutral_ramp_exists(): void {
		$css = (string) file_get_contents( __DIR__ . '/../../assets/src/css/app.css' );

		$this->assertSame(
			1,
			preg_match_all( '/^\s*--color-neutral-\d+\s*:/m', $css ) > 0 ? 1 : 0,
			'the contract requires a neutral ramp to exist'
		);
	}

	/**
	 * One row per combination of MODES, named for what a visitor sees.
	 *
	 * @return array<string, array{list<string>}>
	 */
	public static function displayModeProvider(): array {
		$names = array_keys( self::MODES );
		$rows  = array();
		$every = 1 << count( $names );

		for ( $mask = 0; $mask < $every; $mask++ ) {
			$combo = array();
			foreach ( $names as $bit => $name ) {
				if ( $mask & ( 1 << $bit ) ) {
					$combo[] = $name;
				}
			}

			$label                            = in_array( 'dark', $combo, true ) ? $combo : array_merge( array( 'light' ), $combo );
			$rows[ implode( ' + ', $label ) ] = array( $combo );
		}

		return $rows;
	}

	/**
	 * The contract's contrast rules hold in one combination of display modes,
	 * for every frozen seed and for the compiled defaults, at rest and on
	 * hover. Every failure in the combination is reported together, one line
	 * per rule, with its worst palette and the colours that produced it.
	 *
	 * @param list<string> $combo Mode names in force.
	 */
	#[DataProvider( 'displayModeProvider' )]
	public function test_the_contract_contrast_rules_hold_in_every_display_mode( array $combo ): void {
		$vocab = self::contract( './vocabulary' );
		$rules = self::colour_rules();

		$declared = array();
		foreach ( $rules as $rule ) {
			$declared += $rule['set'];
		}

		// A conditional rule binds salt once app.css declares every role in its
		// `when`. Undeclared roles skip it, as the contract's own evaluator does.
		$checks  = array();
		$skipped = 0;
		foreach ( array( 'contrast.requirements', 'contrast.conditionalRequirements.rules' ) as $path ) {
			foreach ( self::required( $vocab, $path ) as $rule ) {
				$pair = $rule['pair'] ?? null;
				$when = $rule['when'] ?? array();
				if ( ! is_array( $pair ) || ! array_is_list( $pair ) || 2 !== count( $pair ) || ! is_numeric( $rule['minRatio'] ?? null ) || ! is_array( $when ) ) {
					self::fail( "vocabulary.json '{$path}' has a rule this suite cannot read: " . wp_json_encode( $rule ) );
				}

				foreach ( $when as $role ) {
					if ( ! array_key_exists( '--color-' . str_replace( '.', '-', (string) $role ), $declared ) ) {
						++$skipped;
						continue 2;
					}
				}

				list( $fg, $bg ) = $pair;
				$min             = (float) $rule['minRatio'];
				$checks[]        = array( "{$fg} on {$bg}, at rest", $fg, $bg, $min );
				if ( isset( self::HOVER[ $bg ] ) ) {
					$checks[] = array( "{$fg} on {$bg}, on hover (" . self::HOVER[ $bg ] . ')', $fg, self::HOVER[ $bg ], $min );
				}
			}
		}

		$palettes = array( 'compiled defaults' => array() );
		foreach ( self::contract( './vectors' )['vectors'] as $vector ) {
			$palettes[ strtolower( (string) $vector['seed'] ) ] = self::runtime( (string) $vector['seed'] );
		}
		$palettes['#4b5563'] ??= self::runtime( '#4b5563' );

		$ties     = array();
		$failures = array();
		foreach ( $palettes as $palette => $runtime ) {
			$tokens = self::tokens( $rules, $combo, $runtime, $ties );

			foreach ( $checks as list( $label, $fg_role, $bg_role, $min ) ) {
				list( $fg, $fg_why ) = self::measure( $tokens, $fg_role );
				list( $bg, $bg_why ) = self::measure( $tokens, $bg_role );

				if ( null === $fg || null === $bg ) {
					$failures[ $label ]['unverified'][ trim( "{$fg_why}; {$bg_why}", '; ' ) ][] = $palette;
					continue;
				}

				$ratio = salt_contrast_ratio( $fg, $bg );
				if ( $ratio < $min ) {
					$failures[ $label ]['below'][] = array( $ratio, $palette, $fg, $bg, $min );
				}
			}
		}

		$report = array();
		foreach ( $ties as $tie ) {
			$report[] = "  UNRANKABLE {$tie}";
		}
		foreach ( $failures as $label => $failure ) {
			if ( isset( $failure['below'] ) ) {
				usort( $failure['below'], static fn( array $a, array $b ): int => $a[0] <=> $b[0] );
				$worst    = $failure['below'][0];
				$best     = $failure['below'][ count( $failure['below'] ) - 1 ];
				$report[] = sprintf(
					'  FAIL %s: below %s:1 for %d of %d palettes, from %.2f:1 (%s: %s on %s) to %.2f:1 (%s)',
					$label,
					$worst[4],
					count( $failure['below'] ),
					count( $palettes ),
					$worst[0],
					$worst[1],
					$worst[2],
					$worst[3],
					$best[0],
					$best[1]
				);
			}
			foreach ( $failure['unverified'] ?? array() as $why => $which ) {
				$report[] = sprintf( '  UNVERIFIED %s for %d of %d palettes: %s', $label, count( $which ), count( $palettes ), $why );
			}
		}

		$this->assertNotEmpty( $checks, 'No contrast rule applies, so a green run would verify nothing.' );
		if ( array() !== $report ) {
			$this->fail(
				sprintf(
					"%s: the contract's contrast rules do not hold (%d checks per palette; %d conditional rules skipped because app.css does not declare their roles).\n%s\n"
					. '  A palette is a frozen seed as salt_brand_css() emits it, or the compiled defaults of a site with no brand set.',
					$this->dataName(),
					count( $checks ),
					$skipped,
					implode( "\n", $report )
				)
			);
		}
	}

	/**
	 * app.css declares colour tokens only in shapes the contrast checks can
	 * rank (colour_rules() fails on any other), and no mode rule sets a token
	 * salt_brand_css() prints in its `:root` block. `:root` and a one-mode rule
	 * have the same specificity, so which wins would come down to where the
	 * block prints in <head>.
	 */
	public function test_colour_tokens_are_declared_only_where_they_can_be_ranked(): void {
		$runtime = self::runtime( '#4b5563' );

		foreach ( self::colour_rules() as $rule ) {
			if ( 0 === $rule['rank'] ) {
				continue;
			}

			$shared = array_keys( array_intersect_key( $rule['set'], $runtime ) );
			$this->assertSame(
				array(),
				$shared,
				"{$rule['selector']} sets " . implode( ', ', $shared ) . ', which salt_brand_css() also prints in <head>. They tie on specificity, so print order would decide. Point the mode at a token the runtime block does not set.'
			);
		}
	}

	/**
	 * Every per-site token app.css reads is one salt_brand_css() derives from
	 * the site's own brand. One it does not derive keeps whatever app.css
	 * compiles in on every configured site, and the contrast checks cannot see
	 * that when the compiled value happens to pass for every frozen seed, as
	 * --color-on-primary-400's #0f172a does.
	 */
	public function test_every_per_site_token_app_css_reads_is_derived_per_site(): void {
		$runtime = self::runtime( '#4b5563' );
		$read    = 0;

		foreach ( self::colour_rules() as $rule ) {
			foreach ( $rule['set'] as $prop => $value ) {
				preg_match_all( '/var\(\s*(--color-(?:(?:primary|secondary|accent)-\d+|on-(?:primary|secondary|accent)(?:-\d+)?))\s*\)/', $value, $references );
				foreach ( $references[1] as $reference ) {
					++$read;
					$this->assertArrayHasKey(
						$reference,
						$runtime,
						"{$rule['selector']} sets {$prop} from {$reference}, which salt_brand_css() does not derive per site. Every configured site would keep the compiled value there, or none, whatever its brand. Emit it from salt_brand_css() in inc/colors.php."
					);
				}
			}
		}

		$this->assertGreaterThan( 0, $read, 'app.css reads no per-site token, so this check verified nothing.' );
	}

	/**
	 * The mode table is what the page actually sets: each MODES selector is an
	 * attribute salt_theme_init_js() writes on <html> before paint. A mode the
	 * script spells differently would never match its CSS, and every result
	 * above would describe a page nobody sees.
	 */
	public function test_the_mode_table_is_what_the_page_sets(): void {
		$script = salt_theme_init_js(
			array(
				'dark'                  => true,
				'prefs'                 => true,
				'motion_default_reduce' => false,
			)
		);

		foreach ( self::MODES as $mode => $selector ) {
			if ( 1 !== preg_match( '/^\[([\w-]+)(?:="([^"]*)")?\]$/', $selector, $attribute ) ) {
				self::fail( "MODES spells {$mode} as {$selector}; this check reads [name] or [name=\"value\"] only." );
			}

			$this->assertStringContainsString(
				sprintf( 'd.setAttribute("%s","%s")', $attribute[1], $attribute[2] ?? '' ),
				$script,
				"salt_theme_init_js() never sets {$selector}, so the {$mode} rules in app.css apply to no page."
			);
		}
	}
}
