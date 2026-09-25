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
 * WHAT THIS DOES NOT CHECK
 * ------------------------
 * - The contract's contrast section (`contrast.requirements` and
 *   `contrast.conditionalRequirements`). Those are properties of the rendered
 *   palette, per theme mode and per brand seed, and this suite measures none
 *   of them.
 * - That a required role RESOLVES. The role check finds a declaration such as
 *   `--color-brand-tint:` in app.css and reads nothing after the colon, so a
 *   role pointing at a ramp step that does not exist, or at nothing, passes.
 * - That components consume roles rather than raw ramps. The contract's tier
 *   model says components MUST NOT consume Tier 1 directly (a ramp step such
 *   as `bg-primary-600`); nothing here looks at a template.
 *
 * A green run here says the vocabulary's rules are the ones last read, the
 * derivation reproduces the frozen vectors and every required role is
 * declared. It says nothing about contrast, resolution or component
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
}
