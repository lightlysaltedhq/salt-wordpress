<?php
/**
 * Share links snapshots (FLEET01), in the contract's markup
 * (salt-contract markup/share-links.json): every service with the heading
 * and the copy control, a subset without it, and nothing at all.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class ShareLinksSnapshotTest extends SaltSnapshotTestCase {

	/**
	 * The share links' labels, as salt_contract_labels( 'post' ) gives them.
	 *
	 * @return array<string,mixed>
	 */
	private function labels(): array {
		return salt_view_label_defaults( 'post' );
	}

	public function test_share_links_default_suite(): void {
		$services = array();
		foreach ( salt_share_links( 'https://example.test/fixture-post/', 'Fixture post title' ) as $link ) {
			$services[] = array(
				'service' => $link['key'],
				'href'    => $link['url'],
			);
		}
		$this->assertMatchesHtmlSnapshot(
			'share-links-default',
			salt_get_component(
				'share-links',
				array(
					'heading'  => 'Share',
					'services' => $services,
					'copy'     => true,
					'labels'   => $this->labels(),
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
					'heading'  => null,
					'services' => array(
						array(
							'service' => 'linkedin',
							'href'    => 'https://www.linkedin.com/sharing/share-offsite/?url=https%3A%2F%2Fexample.test%2F',
						),
						array(
							'service' => 'email',
							'href'    => 'mailto:?subject=Title&body=https%3A%2F%2Fexample.test%2F',
						),
					),
					'copy'     => false,
					'labels'   => $this->labels(),
				)
			)
		);
	}

	public function test_no_services_and_no_copy_renders_nothing(): void {
		$this->assertSame(
			'',
			salt_get_component(
				'share-links',
				array(
					'services' => array(),
					'copy'     => false,
					'labels'   => $this->labels(),
				)
			)
		);
	}
}
