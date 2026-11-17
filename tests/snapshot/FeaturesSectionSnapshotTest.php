<?php
/**
 * Features section snapshots (FLEET01) — 3-column grid with links (the
 * canonical variant) + single-item static.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class FeaturesSectionSnapshotTest extends SaltSnapshotTestCase {

	/**
	 * Render the features section for one seeded FC row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_features( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'features',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'features',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_features_three_columns_with_links(): void {
		$this->assertMatchesHtmlSnapshot(
			'features-3col-links',
			$this->render_features(
				array(
					'eyebrow' => 'Benefits',
					'heading' => 'Why choose us',
					'columns' => '3',
					'items'   => array(
						array(
							'icon'  => 'check',
							'title' => 'Fast by default',
							'text'  => 'Lean pages, quick loads.',
							'url'   => array(
								'url'   => 'https://example.test/fast/',
								'title' => 'Fast by default',
							),
						),
						array(
							'icon'  => 'star',
							'title' => 'Accessible',
							'text'  => 'WCAG-first components.',
							'url'   => array(
								'url'   => 'https://example.test/accessible/',
								'title' => 'Accessible',
							),
						),
						array(
							'icon'  => 'mail',
							'title' => 'Low carbon',
							'text'  => 'Every kilobyte counted.',
							'url'   => array(
								'url'   => 'https://example.test/low-carbon/',
								'title' => 'Low carbon',
							),
						),
					),
				)
			)
		);
	}

	public function test_features_single_item_static(): void {
		$this->assertMatchesHtmlSnapshot(
			'features-single',
			$this->render_features(
				array(
					'heading' => 'One benefit',
					'columns' => '3',
					'items'   => array(
						array(
							'icon'  => 'check',
							'title' => 'Fast by default',
							'text'  => 'Lean pages, quick loads.',
						),
					),
				)
			)
		);
	}
}
