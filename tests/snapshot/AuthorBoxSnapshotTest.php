<?php
/**
 * Author box snapshots (FLEET01), in the contract's markup
 * (salt-contract markup/author-box.json): full attribution and name-only.
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
					'name'   => 'Jane Fixture',
					'bio'    => 'Jane writes about design systems and performance.',
					'href'   => '/team/jane-fixture/',
					'role'   => 'Creative Director',
					'photo'  => array(
						'src'    => 'https://example.test/uploads/jane-400.jpg',
						'srcset' => 'https://example.test/uploads/jane-256.jpg 256w, https://example.test/uploads/jane-400.jpg 400w',
						'sizes'  => '8rem',
						'width'  => 400,
						'height' => 400,
						'alt'    => 'Jane Fixture',
					),
					'labels' => salt_view_label_defaults( 'post' ),
				)
			)
		);
	}

	public function test_author_box_name_only(): void {
		$this->assertMatchesHtmlSnapshot(
			'author-box-name-only',
			salt_get_component(
				'author-box',
				array(
					'name'   => 'Jane Fixture',
					'labels' => salt_view_label_defaults( 'post' ),
				)
			)
		);
	}

	public function test_no_name_renders_nothing(): void {
		$this->assertSame( '', salt_get_component( 'author-box', array( 'name' => '  ' ) ) );
	}
}
