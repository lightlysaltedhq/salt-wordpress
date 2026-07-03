<?php
/**
 * x-share-links snapshots (FLEET01) — default suite, custom subset, no copy.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class ShareLinksSnapshotTest extends SaltSnapshotTestCase {

	public function test_share_links_default_suite(): void {
		$this->assertMatchesHtmlSnapshot(
			'share-links-default',
			salt_get_component(
				'share-links',
				array(
					'url'     => 'https://example.test/fixture-post/',
					'title'   => 'Fixture post title',
					'heading' => 'Share this post',
				)
			)
		);
	}

	public function test_share_links_subset_no_copy(): void {
		$this->assertMatchesHtmlSnapshot(
			'share-links-subset-no-copy',
			salt_get_component(
				'share-links',
				array(
					'url'      => 'https://example.test/fixture-post/',
					'title'    => 'Fixture post title',
					'services' => array( 'x', 'linkedin', 'email' ),
					'copy'     => false,
				)
			)
		);
	}

	public function test_share_links_empty_url_renders_nothing(): void {
		$this->assertSame(
			'',
			salt_get_component(
				'share-links',
				array( 'url' => '' )
			)
		);
	}
}
