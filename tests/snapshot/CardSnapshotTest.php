<?php
/**
 * x-card snapshots (FLEET01).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class CardSnapshotTest extends SaltSnapshotTestCase {

	public function test_card_with_url_and_media(): void {
		$this->assertMatchesHtmlSnapshot(
			'card-url-media',
			salt_get_component(
				'card',
				array(
					'title' => 'Fixture card title',
					'url'   => 'https://example.test/fixture-post/',
					'media' => '<img src="https://example.test/wp-content/uploads/fixture.jpg" alt="">',
					'meta'  => '<time datetime="2026-01-01">1 January 2026</time>',
					'body'  => 'Fixture excerpt copy for the linked card.',
				)
			)
		);
	}

	public function test_card_minimal(): void {
		$this->assertMatchesHtmlSnapshot(
			'card-minimal',
			salt_get_component( 'card', array( 'title' => 'Minimal fixture card' ) )
		);
	}
}
