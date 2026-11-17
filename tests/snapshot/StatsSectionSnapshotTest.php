<?php
/**
 * Stats section snapshots (FLEET01) — the canonical stats row + single-stat
 * static variant.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class StatsSectionSnapshotTest extends SaltSnapshotTestCase {

	/**
	 * Render the stats section for one seeded FC row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_stats( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'stats',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'stats',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_stats_row(): void {
		$this->assertMatchesHtmlSnapshot(
			'stats-row',
			$this->render_stats(
				array(
					'heading' => 'The numbers',
					'columns' => '3',
					'items'   => array(
						array(
							'value'  => '45',
							'suffix' => '%',
							'label'  => 'More conversions',
						),
						array(
							'value'  => '12',
							'suffix' => 'k',
							'label'  => 'Happy users',
						),
						array(
							'value'  => '99.9',
							'suffix' => '%',
							'label'  => 'Uptime',
						),
					),
				)
			)
		);
	}

	public function test_stats_single_static(): void {
		$this->assertMatchesHtmlSnapshot(
			'stats-single',
			$this->render_stats(
				array(
					'columns' => '2',
					'items'   => array(
						array(
							'value'  => '10',
							'suffix' => '+',
							'label'  => 'Years in business',
						),
					),
				)
			)
		);
	}
}
