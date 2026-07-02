<?php
/**
 * x-cta snapshots (FLEET01).
 *
 * The CTA block composes x-button and receives its heading level from the
 * section wrapper — structural changes fail CI and force the semver decision.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class CtaSnapshotTest extends SaltSnapshotTestCase {

	public function test_cta_full(): void {
		$this->assertMatchesHtmlSnapshot(
			'cta-full',
			salt_get_component(
				'cta',
				array(
					'heading' => 'Ready to work together?',
					'text'    => 'Tell us about your project and we will get back within one working day.',
					'buttons' => array(
						array(
							'label' => 'Start a project',
							'url'   => 'https://example.com/contact',
						),
						array(
							'label' => 'See our work',
							'url'   => 'https://example.com/work',
							'style' => 'secondary',
						),
					),
					'level'   => 2,
				)
			)
		);
	}

	public function test_cta_left_minimal_buttons_only(): void {
		$this->assertMatchesHtmlSnapshot(
			'cta-left-minimal',
			salt_get_component(
				'cta',
				array(
					'buttons' => array(
						array(
							'label' => 'Browse services',
							'url'   => 'https://example.com/services',
							'style' => 'ghost',
						),
					),
					'align'   => 'left',
				)
			)
		);
	}
}
