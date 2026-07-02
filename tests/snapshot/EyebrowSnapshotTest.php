<?php
/**
 * x-eyebrow snapshots (FLEET01).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class EyebrowSnapshotTest extends SaltSnapshotTestCase {

	public function test_eyebrow(): void {
		$this->assertMatchesHtmlSnapshot(
			'eyebrow',
			salt_get_component( 'eyebrow', array( 'text' => 'Our services' ) )
		);
	}
}
