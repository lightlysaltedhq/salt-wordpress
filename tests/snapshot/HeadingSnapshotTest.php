<?php
/**
 * x-heading snapshots (FLEET01).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class HeadingSnapshotTest extends SaltSnapshotTestCase {

	public function test_heading_h2_default_size(): void {
		$this->assertMatchesHtmlSnapshot(
			'heading-h2-default',
			salt_get_component(
				'heading',
				array(
					'text'  => 'Fixture heading',
					'level' => 2,
				)
			)
		);
	}

	public function test_heading_h3_size_override(): void {
		$this->assertMatchesHtmlSnapshot(
			'heading-h3-size-xl',
			salt_get_component(
				'heading',
				array(
					'text'    => 'Visually large, semantically third',
					'level'   => 3,
					'size'    => 'xl',
					'classes' => 'mb-4',
				)
			)
		);
	}
}
