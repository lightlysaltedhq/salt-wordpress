<?php
/**
 * Rendered-markup snapshot gate (FLEET01) — infrastructure placeholder.
 *
 * From Phase 1 onward this suite renders every shared component and
 * section part with fixture args and byte-compares against committed
 * .snap files, so an unintended markup change surfaces as a reviewable
 * CI failure (the machine trigger for the semver major/minor decision).
 * This placeholder keeps the suite non-empty so the CI gate exists and
 * runs from the first commit.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SnapshotGateTest extends TestCase {

	public function test_snapshot_directory_scaffold_exists(): void {
		$this->assertDirectoryExists( dirname( __DIR__, 2 ) . '/template-parts' );
	}
}
