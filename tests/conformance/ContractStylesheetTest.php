<?php
/**
 * Stylesheet pin: the salt.css the theme serves is the pinned contract's
 * styles/salt.css, byte for byte.
 *
 * The contract makes this normative (salt-contract README, Stylesheets):
 * both platforms serve the bundle verbatim, as a stylesheet of its own, and
 * its conformance runner compares the served file with the package's byte for
 * byte, so CSS that went through Tailwind, PostCSS or a minifier can never
 * pass. bin/build-assets.mjs copies it into assets/dist; this holds the copy
 * (through the manifest, the path salt_enqueue_assets() serves) to the
 * installed package, which SaltContractPinTest holds to the committed tarball.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContractStylesheetTest extends SaltTestCase {

	private const ROOT = __DIR__ . '/../..';

	private const BUNDLE = self::ROOT . '/node_modules/@lightlysaltedhq/salt-contract/styles/salt.css';

	/**
	 * The manifest's path for salt.css, inside assets/dist.
	 *
	 * @return string
	 */
	private static function served(): string {
		$manifest = json_decode( (string) file_get_contents( self::ROOT . '/assets/dist/manifest.json' ), true );
		if ( ! is_array( $manifest ) || ! is_string( $manifest['salt.css'] ?? null ) ) {
			self::fail( 'assets/dist/manifest.json has no salt.css. Run npm run build and commit assets/dist.' );
		}

		return $manifest['salt.css'];
	}

	public function test_the_served_stylesheet_is_the_pinned_bundle_byte_for_byte(): void {
		$this->assertFileExists( self::BUNDLE, 'The contract is not installed. Run npm ci; this gate fails rather than skips.' );

		$served = self::ROOT . '/assets/dist/' . self::served();
		$this->assertFileExists( $served, 'The manifest names ' . self::served() . ', which is not in assets/dist. Run npm run build.' );

		$want = (string) file_get_contents( self::BUNDLE );
		$have = (string) file_get_contents( $served );
		$this->assertGreaterThan( 0, strlen( $want ), 'styles/salt.css is empty, so the comparison proves nothing.' );
		$this->assertSame(
			hash( 'sha256', $want ),
			hash( 'sha256', $have ),
			sprintf(
				"assets/dist/%s is not the pinned styles/salt.css (%d bytes served, %d pinned; first difference at byte %d).\n  It must be copied verbatim, never processed. Run npm run build and commit assets/dist.\n",
				self::served(),
				strlen( $have ),
				strlen( $want ),
				strspn( $want ^ $have, "\0" )
			)
		);
	}

	/**
	 * The contract finds its stylesheet in a page by the file name salt.css
	 * (normalise.mjs pageOf), so the content hash names the directory.
	 */
	public function test_the_served_file_is_named_salt_css(): void {
		$this->assertMatchesRegularExpression( '#^salt\.[0-9a-f]{10}/salt\.css$#', self::served() );
	}
}
