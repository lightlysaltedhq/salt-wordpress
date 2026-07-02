<?php
/**
 * x-button snapshots (FLEET01).
 *
 * Every CTA, hero and card action across the fleet renders through this
 * markup — any structural change fails CI and forces the semver decision.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class ButtonSnapshotTest extends SaltSnapshotTestCase {

	public function test_button_primary_md(): void {
		$this->assertMatchesHtmlSnapshot(
			'button-primary-md',
			salt_get_component(
				'button',
				array(
					'label' => 'Get started',
					'url'   => 'https://example.com/contact',
				)
			)
		);
	}

	public function test_button_link_arrow_sm_blank(): void {
		$this->assertMatchesHtmlSnapshot(
			'button-link-arrow-sm-blank',
			salt_get_component(
				'button',
				array(
					'label'  => 'Read the case study',
					'url'    => 'https://example.com/case-study',
					'style'  => 'link-arrow',
					'size'   => 'sm',
					'target' => '_blank',
				)
			)
		);
	}
}
