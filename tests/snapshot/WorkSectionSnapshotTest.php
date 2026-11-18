<?php
/**
 * Portfolio/Work section snapshots (FLEET01) — one per meaningful variant:
 * full three-column grid (heading, eyebrow, thumbnails, view-all), the
 * static 1-item state, and a two-column grid without header chrome.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';
require_once __DIR__ . '/../shims/work.php';

final class WorkSectionSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		$GLOBALS['salt_test_permalinks']    = array();
		$GLOBALS['salt_test_archive_links'] = array( 'work' => 'https://example.test/work/' );
		salt_test_attachments_reset();
	}

	/**
	 * Seed the fixture rows/posts and render the work section.
	 *
	 * @param array<string,mixed> $fields     Row fields.
	 * @param bool                $is_primary Loop context flag.
	 * @return string
	 */
	private function render_work( array $fields, bool $is_primary = true ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'work',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'work',
			array(
				'index'      => 0,
				'is_primary' => $is_primary,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_work_grid_three_columns_full(): void {
		$this->seed_posts(
			array(
				array(
					'ID'                => 1,
					'post_type'         => 'work',
					'post_title'        => 'Alpha rebrand',
					'post_excerpt'      => 'A full rebrand across print and digital.',
					'post_date'         => '2026-01-03 00:00:00',
					'salt_thumbnail_id' => 51,
				),
				array(
					'ID'           => 2,
					'post_type'    => 'work',
					'post_title'   => 'Beta ecommerce build',
					'post_excerpt' => 'Headless storefront with a design system.',
					'post_date'    => '2026-01-02 00:00:00',
				),
				array(
					'ID'         => 3,
					'post_type'  => 'work',
					'post_title' => 'Gamma campaign',
					'post_date'  => '2026-01-01 00:00:00',
				),
			)
		);
		$GLOBALS['salt_test_permalinks']      = array(
			1 => 'https://example.test/work/alpha-rebrand/',
			2 => 'https://example.test/work/beta-ecommerce/',
			3 => 'https://example.test/work/gamma-campaign/',
		);
		$GLOBALS['salt_test_fields']['1']     = array( 'client' => 'Alpha Ltd' );
		$GLOBALS['salt_test_fields']['2']     = array( 'client' => 'Beta & Co' );
		$GLOBALS['salt_test_attachments'][51] = array(
			'src'    => 'https://example.test/uploads/alpha-card.jpg',
			'width'  => 800,
			'height' => 600,
			'srcset' => 'https://example.test/uploads/alpha-card-400.jpg 400w, https://example.test/uploads/alpha-card.jpg 800w',
			'alt'    => 'Alpha brand collateral',
		);

		$this->assertMatchesHtmlSnapshot(
			'work-grid-three-full',
			$this->render_work(
				array(
					'eyebrow'  => 'Portfolio',
					'heading'  => 'Our work',
					'columns'  => 3,
					'view_all' => 1,
				)
			)
		);
	}

	public function test_work_single_item_static(): void {
		$this->seed_posts(
			array(
				array(
					'ID'           => 7,
					'post_type'    => 'work',
					'post_title'   => 'Delta annual report',
					'post_excerpt' => 'Design and build of the interactive annual report.',
				),
			)
		);
		$GLOBALS['salt_test_permalinks'][7] = 'https://example.test/work/delta-annual-report/';
		$GLOBALS['salt_test_fields']['7']   = array( 'client' => 'Delta Trust' );

		$this->assertMatchesHtmlSnapshot(
			'work-single-item',
			$this->render_work(
				array(
					'heading'  => 'Featured work',
					'columns'  => 3,
					'view_all' => 0,
				)
			)
		);
	}

	public function test_work_two_columns_no_header(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 1,
					'post_type'  => 'work',
					'post_title' => 'Alpha rebrand',
					'post_date'  => '2026-01-02 00:00:00',
				),
				array(
					'ID'         => 2,
					'post_type'  => 'work',
					'post_title' => 'Beta ecommerce build',
					'post_date'  => '2026-01-01 00:00:00',
				),
			)
		);
		$GLOBALS['salt_test_permalinks'] = array(
			1 => 'https://example.test/work/alpha-rebrand/',
			2 => 'https://example.test/work/beta-ecommerce/',
		);

		$this->assertMatchesHtmlSnapshot(
			'work-grid-two-no-header',
			$this->render_work(
				array(
					'columns'  => 2,
					'view_all' => 0,
				),
				false
			)
		);
	}
}
