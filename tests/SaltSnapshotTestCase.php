<?php
/**
 * Snapshot test base (FLEET01).
 *
 * Renders shared markup with fixture args and byte-compares against a
 * committed .snap.html file. A changed snapshot fails CI — the machine
 * trigger that forces the human major/minor semver decision. Regenerate
 * deliberately with:
 *
 *   SALT_UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --testsuite snapshot
 *
 * and commit the .snap.html diff for review.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

abstract class SaltSnapshotTestCase extends SaltTestCase {

	/**
	 * Assert rendered HTML matches its committed snapshot.
	 *
	 * @param string $name Snapshot name (file: __snapshots__/{name}.snap.html).
	 * @param string $html Rendered HTML.
	 * @return void
	 */
	protected function assertMatchesHtmlSnapshot( string $name, string $html ): void {
		$dir  = __DIR__ . '/snapshot/__snapshots__';
		$file = $dir . '/' . $name . '.snap.html';

		$normalised = trim( $html ) . "\n";

		if ( false !== getenv( 'SALT_UPDATE_SNAPSHOTS' ) ) {
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0755, true );
			}
			file_put_contents( $file, $normalised );
			$this->assertFileExists( $file );
			return;
		}

		$this->assertFileExists(
			$file,
			sprintf(
				'No committed snapshot "%s". Generate it with SALT_UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --testsuite snapshot and commit the file.',
				$name
			)
		);

		$this->assertSame(
			file_get_contents( $file ),
			$normalised,
			sprintf(
				'Rendered markup for "%s" no longer matches its committed snapshot (FLEET01). If the change is INTENDED, decide the semver impact (markup structure change = MAJOR), regenerate with SALT_UPDATE_SNAPSHOTS=1, and commit the snapshot diff.',
				$name
			)
		);
	}
}
