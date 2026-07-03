<?php
/**
 * x-author-box snapshots (FLEET01) — full attribution and name-only.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class AuthorBoxSnapshotTest extends SaltSnapshotTestCase {

	public function test_author_box_full(): void {
		$this->assertMatchesHtmlSnapshot(
			'author-box-full',
			salt_get_component(
				'author-box',
				array(
					'name'  => 'Jane Fixture',
					'bio'   => 'Jane writes about design systems and performance.',
					'url'   => 'https://example.test/author/jane/',
					'role'  => 'Creative Director',
					'media' => '<img src="https://example.test/uploads/jane.jpg" alt="Jane Fixture">',
				)
			)
		);
	}

	public function test_author_box_name_only(): void {
		$this->assertMatchesHtmlSnapshot(
			'author-box-name-only',
			salt_get_component(
				'author-box',
				array( 'name' => 'Jane Fixture' )
			)
		);
	}
}
