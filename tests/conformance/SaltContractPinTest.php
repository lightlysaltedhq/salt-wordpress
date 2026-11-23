<?php
/**
 * Pin gate: salt reads @lightlysaltedhq/salt-contract at exactly the release
 * candidate it was last checked against.
 *
 * WHERE THE CONTRACT COMES FROM
 * -----------------------------
 * Release candidates of salt-contract are never published (the contract's
 * RELEASE-POLICY §6), so npm has nothing to install by version. The candidate
 * is packed once with `npm pack` from lightlysaltedhq/contracts at the commit
 * below and committed under vendor-contract/; package.json requires that file
 * and package-lock.json records its integrity hash. Packing is deterministic,
 * so anyone can rebuild the tarball from the commit and compare.
 *
 * HOW A BUMP IS HELD UNTIL SOMEONE HAS READ IT
 * --------------------------------------------
 * PINNED, TARBALL and TARBALL_SHA256 move together. A different version, a
 * different file, a changed byte in the tarball, or an installed copy that is
 * not byte-for-byte the tarball's content all fail here, each saying what to
 * do. This is the same rule FoundationsConformanceTest applies to
 * design-foundations; once 1.0.0 is published the pin becomes a bare registry
 * version, as that one is.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SaltContractPinTest extends SaltTestCase {

	private const PACKAGE = '@lightlysaltedhq/salt-contract';

	/**
	 * The candidate salt was last checked against: 1.0.0-rc.9, packed from
	 * the local, unpushed lightlysaltedhq/contracts branch feat/salt-contract-rc9
	 * at 16fa5094ea3a308559eb3edbc98174d45a31422a (salt-contract/). Re-pack and
	 * re-pin from the merged commit once that branch lands.
	 */
	private const PINNED = '1.0.0-rc.9';

	private const TARBALL = 'vendor-contract/lightlysaltedhq-salt-contract-1.0.0-rc.9.tgz';

	private const TARBALL_SHA256 = '9001b5a7ee246762580de98e8b5dd7ebfb597c7b272c97ec1246d7af89806993';

	private const ROOT = __DIR__ . '/../..';

	private const INSTALLED = self::ROOT . '/node_modules/@lightlysaltedhq/salt-contract';

	private const REPACK = "  Rebuild it from the contracts repository at the pinned commit:\n"
		. "    npm pack ./salt-contract --pack-destination <salt-wordpress>/vendor-contract\n"
		. "  then npm install --save-dev --save-exact ./<tarball>, and re-pin PINNED, TARBALL and TARBALL_SHA256 in this file.\n";

	/**
	 * Decode a JSON file or fail naming it.
	 *
	 * @param string $path Path.
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
	 * Every file in the committed tarball, keyed by its path inside the
	 * package, with its bytes.
	 *
	 * @return array<string, string>
	 */
	private static function tarball_files(): array {
		$path = self::ROOT . '/' . self::TARBALL;
		if ( ! is_file( $path ) ) {
			self::fail( self::TARBALL . " is not committed.\n" . self::REPACK );
		}

		$files = array();
		$tar   = new PharData( $path );
		foreach ( new RecursiveIteratorIterator( $tar ) as $file ) {
			$inside = substr( $file->getPathname(), strlen( 'phar://' . $tar->getPath() . '/' ) );
			if ( ! str_starts_with( $inside, 'package/' ) ) {
				self::fail( self::TARBALL . " holds {$inside}, outside package/; it is not an npm pack tarball." );
			}
			$files[ substr( $inside, strlen( 'package/' ) ) ] = (string) file_get_contents( $file->getPathname() );
		}
		ksort( $files );

		return $files;
	}

	/**
	 * package.json requires exactly the committed tarball, and the lockfile
	 * resolves to the same file.
	 */
	public function test_package_json_requires_exactly_the_committed_tarball(): void {
		$want = 'file:' . self::TARBALL;
		$pin  = self::decode( self::ROOT . '/package.json' )['devDependencies'][ self::PACKAGE ] ?? null;

		$this->assertSame(
			$want,
			$pin,
			self::PACKAGE . ' must be a devDependency on ' . $want . ', the candidate salt was checked against; package.json has ' . wp_json_encode( $pin ) . ".\n"
			. "  A version or a range would install whatever npm finds; candidates are not published, so only the committed tarball is the contract.\n"
		);

		$lock = self::decode( self::ROOT . '/package-lock.json' )['packages'][ 'node_modules/' . self::PACKAGE ] ?? array();
		$this->assertSame( $want, $lock['resolved'] ?? null, "package-lock.json does not resolve the contract to {$want}. Fix: npm install, then commit the lockfile." );
		$this->assertSame( self::PINNED, $lock['version'] ?? null, 'package-lock.json records a different contract version. Fix: npm install, then commit the lockfile.' );
	}

	/**
	 * The committed tarball is byte-for-byte the one PINNED was checked against,
	 * and it holds that version of that package.
	 */
	public function test_the_tarball_is_the_pinned_candidate(): void {
		$path = self::ROOT . '/' . self::TARBALL;
		$this->assertFileExists( $path, self::TARBALL . " is not committed.\n" . self::REPACK );

		$hash = hash_file( 'sha256', $path );
		$this->assertSame(
			self::TARBALL_SHA256,
			$hash,
			self::TARBALL . " hashes to {$hash}, not the hash recorded for " . self::PINNED . ".\n"
			. "  The contract changed under salt. Read what changed, bring salt into line, then:\n" . self::REPACK
		);

		$manifest = json_decode( self::tarball_files()['package.json'] ?? '', true );
		$this->assertSame( self::PACKAGE, $manifest['name'] ?? null, self::TARBALL . ' is not ' . self::PACKAGE . '.' );
		$this->assertSame( self::PINNED, $manifest['version'] ?? null, self::TARBALL . ' is not ' . self::PINNED . '.' );
	}

	/**
	 * node_modules holds exactly the tarball's files, unchanged, so every other
	 * check reads the pinned contract. This gate does not skip when the
	 * contract is absent, by design.
	 */
	public function test_the_installed_contract_is_the_tarball_unchanged(): void {
		if ( ! is_file( self::INSTALLED . '/package.json' ) ) {
			self::fail( 'The contract is not installed: ' . self::PACKAGE . ' not found in node_modules. Fix: npm ci' );
		}

		$want     = self::tarball_files();
		$have     = array();
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( self::INSTALLED, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			$have[ substr( $file->getPathname(), strlen( self::INSTALLED . '/' ) ) ] = (string) file_get_contents( $file->getPathname() );
		}
		ksort( $have );

		$problems = array();
		foreach ( array_diff_key( $want, $have ) as $name => $bytes ) {
			$problems[] = "missing: {$name}";
		}
		foreach ( array_diff_key( $have, $want ) as $name => $bytes ) {
			$problems[] = "not in the tarball: {$name}";
		}
		foreach ( array_intersect_key( $have, $want ) as $name => $bytes ) {
			if ( $want[ $name ] !== $bytes ) {
				$problems[] = "changed: {$name}";
			}
		}

		$this->assertSame(
			array(),
			$problems,
			"node_modules does not hold the pinned contract unchanged. Fix: npm ci\n  " . implode( "\n  ", $problems )
		);
	}
}
