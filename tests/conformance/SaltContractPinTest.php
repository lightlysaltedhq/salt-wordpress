<?php
/**
 * The public contract release Salt was checked against. Update the exact pin
 * and content digest together after reviewing and testing a new release.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SaltContractPinTest extends SaltTestCase {

	private const PACKAGE = '@lightlysaltedhq/salt-contract';
	private const PINNED  = '3.0.0';
	private const ROOT    = __DIR__ . '/../..';

	/** SHA-256 of the sorted path, NUL, hex file SHA-256 and LF for every file in the public pack. */
	private const CONTENT_SHA256 = '67b8fe87862cd38b82bea0aa01ee5526e37ce2a4a1b4da4afd3587381afa9aec';

	/** A range, candidate file or alternate registry is not the checked release. */
	public function test_exact_public_registry_pin(): void {
		$manifest = json_decode( file_get_contents( self::ROOT . '/package.json' ), true, 512, JSON_THROW_ON_ERROR );
		$lock     = json_decode( file_get_contents( self::ROOT . '/package-lock.json' ), true, 512, JSON_THROW_ON_ERROR );
		$package  = $lock['packages'][ 'node_modules/' . self::PACKAGE ];

		$this->assertSame( self::PINNED, $manifest['devDependencies'][ self::PACKAGE ] );
		$this->assertSame( self::PINNED, $package['version'] );
		$this->assertSame( 'https://registry.npmjs.org/' . self::PACKAGE . '/-/salt-contract-' . self::PINNED . '.tgz', $package['resolved'] );
	}

	/** npm ci checks the tarball integrity; also catch local edits, missing files and extra files after install. */
	public function test_installed_contract_matches_the_public_pack(): void {
		$dir = self::ROOT . '/node_modules/' . self::PACKAGE;
		$this->assertDirectoryExists( $dir, 'Install the pinned contract with npm ci.' );

		$files = array();
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			$name           = str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), strlen( $dir ) + 1 ) );
			$files[ $name ] = $name . "\0" . hash_file( 'sha256', $file->getPathname() ) . "\n";
		}
		ksort( $files, SORT_STRING );
		$this->assertSame( self::CONTENT_SHA256, hash( 'sha256', implode( '', $files ) ), 'Installed contract differs from the checked release. Run npm ci.' );
	}
}
