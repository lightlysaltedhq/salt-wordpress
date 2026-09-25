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
 * The contract's contrast section (`contrast.requirements` and
 * `contrast.conditionalRequirements`). Those are properties of the rendered
 * palette, per theme mode and per brand seed, and this suite measures none of
 * them. A green run here says the derivation and the role vocabulary conform;
 * it says nothing about contrast.
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
	 * The vocabulary's shape as this test ranks it: for each object (a dotted
	 * path from the top of vocabulary.json, '' being the top), every key it may
	 * hold.
	 *
	 * Read here: colorRoles.required, colorRoles.status.required and
	 * typographyRoles.required (each role must be declared in app.css), and
	 * neutralRamp (a ramp must exist). Known and deliberately not read: the `$`
	 * prose keys and `version`; `tiers` and `darkMode`, which describe
	 * architecture rather than list names to find; `adapters`, which salt does
	 * not use; the opt-in `colorRoles.extended` and `typographyRoles.optional`,
	 * none of which salt declares; and `contrast`, which this suite does not
	 * check (see the class docblock).
	 *
	 * Any other key, or any of these gone, fails by name. A contract that grows
	 * a family this test cannot rank has to be read by a person at the bump,
	 * not passed green by a test that never looked at it, and 3.0.0 (the
	 * typography and scale contract) is expected to grow exactly that.
	 */
	private const VOCABULARY_SHAPE = array(
		''                  => array( '$comment', 'version', '$v2', '$noValues', 'tiers', 'colorRoles', 'adapters', 'typographyRoles', 'neutralRamp', 'darkMode', 'contrast' ),
		'colorRoles'        => array( '$rule', 'required', 'status', 'extended' ),
		'colorRoles.status' => array( '$rule', 'required' ),
		'typographyRoles'   => array( '$rule', 'required', 'optional', '$whyOptional' ),
	);

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
			$patterns = array_values( array_filter( array_map( 'strval', array_keys( $exports ) ), static fn( string $key ): bool => str_contains( $key, '*' ) ) );
			if ( array() !== $patterns ) {
				self::fail( self::PACKAGE . " does not list '{$export}' explicitly; its `exports` has the pattern " . implode( ', ', $patterns ) . ', which this test does not expand. It reads explicit subpaths only.' );
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
	 * package.json pins the contract as a bare version, and node_modules holds it.
	 *
	 * Only the bare spelling (2.1.0) is accepted. npm treats '=2.1.0' and
	 * 'v2.1.0' as exact too, but one spelling keeps the lockfile and this check
	 * simple, so those fail here with a message saying what to write instead.
	 *
	 * The second half catches a stale install: bump the pin without running
	 * npm ci and every other test here would quietly read the old contract.
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
	 * The vocabulary holds every key this test ranks, and nothing else.
	 */
	public function test_the_vocabulary_has_the_shape_this_test_ranks(): void {
		$vocab    = self::contract( './vocabulary' );
		$problems = array();

		foreach ( self::VOCABULARY_SHAPE as $path => $known ) {
			$node  = self::dig( $vocab, $path );
			$where = '' === $path ? 'the top level' : "'{$path}'";

			if ( ! is_array( $node ) || ( array() !== $node && array_is_list( $node ) ) ) {
				$problems[] = "vocabulary.json has no object at {$where}.";
				continue;
			}

			foreach ( array_diff( array_keys( $node ), $known ) as $key ) {
				$problems[] = "vocabulary.json has a key this test does not rank at {$where}: '{$key}'. Read what it asks of salt, then check it here or list it in VOCABULARY_SHAPE as deliberately unread.";
			}
			foreach ( array_diff( $known, array_keys( $node ) ) as $key ) {
				$problems[] = "vocabulary.json no longer has '{$key}' at {$where}. Find where it went before trusting a green run.";
			}
		}

		$this->assertSame( array(), $problems );
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
