<?php
/**
 * Blog Teaser section snapshots (FLEET01) — one per meaningful variant:
 * full three-column grid (heading, eyebrow, thumbnail, dates, view-all),
 * the static 1-item state, and a two-column grid without header chrome.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';
require_once __DIR__ . '/../shims/blog.php';

final class BlogTeaserSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		$GLOBALS['salt_test_permalinks'] = array();
		salt_test_attachments_reset();
	}

	/**
	 * Seed the fixture rows/posts and render the blog teaser section.
	 *
	 * @param array<string,mixed> $fields     Row fields.
	 * @param bool                $is_primary Loop context flag.
	 * @return string
	 */
	private function render_blog_teaser( array $fields, bool $is_primary = true ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'blog_teaser',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'blog-teaser',
			array(
				'index'      => 0,
				'is_primary' => $is_primary,
			)
		);
		return (string) ob_get_clean();
	}

	public function test_blog_teaser_three_columns_full(): void {
		$this->seed_posts(
			array(
				array(
					'ID'                => 1,
					'post_type'         => 'post',
					'post_status'       => 'publish',
					'post_title'        => 'Launching the new starter theme',
					'post_date'         => '2026-01-03 09:00:00',
					'post_excerpt'      => 'What changed and why it matters.',
					'salt_thumbnail_id' => 51,
				),
				array(
					'ID'           => 2,
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => 'Accessibility checklist for editors',
					'post_date'    => '2026-01-02 09:00:00',
					'post_excerpt' => 'Ten checks before you hit publish.',
				),
				array(
					'ID'          => 3,
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_title'  => 'A privacy-first analytics setup',
					'post_date'   => '2026-01-01 09:00:00',
				),
			)
		);
		$GLOBALS['salt_test_permalinks']                = array(
			1  => 'https://example.test/blog/launching-the-new-starter-theme/',
			2  => 'https://example.test/blog/accessibility-checklist/',
			3  => 'https://example.test/blog/privacy-first-analytics/',
			12 => 'https://example.test/blog/',
		);
		$GLOBALS['salt_test_options']['page_for_posts'] = 12;
		$GLOBALS['salt_test_attachments'][51]           = array(
			'src'    => 'https://example.test/uploads/launch-card.jpg',
			'width'  => 800,
			'height' => 450,
			'srcset' => 'https://example.test/uploads/launch-card-400.jpg 400w, https://example.test/uploads/launch-card.jpg 800w',
			'alt'    => 'The new starter theme homepage',
		);

		$this->assertMatchesHtmlSnapshot(
			'blog-teaser-three-full',
			$this->render_blog_teaser(
				array(
					'eyebrow'  => 'News',
					'heading'  => 'From the blog',
					'columns'  => 3,
					'view_all' => 1,
				)
			)
		);
	}

	public function test_blog_teaser_single_item_static(): void {
		$this->seed_posts(
			array(
				array(
					'ID'           => 7,
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => 'Hello world',
					'post_date'    => '2026-03-05 09:30:00',
					'post_excerpt' => 'Our very first post.',
				),
			)
		);
		$GLOBALS['salt_test_permalinks'][7] = 'https://example.test/blog/hello-world/';

		$this->assertMatchesHtmlSnapshot(
			'blog-teaser-single-item',
			$this->render_blog_teaser(
				array(
					'heading'  => 'Latest post',
					'columns'  => 3,
					'view_all' => 0,
				)
			)
		);
	}

	public function test_blog_teaser_two_columns_no_header(): void {
		$this->seed_posts(
			array(
				array(
					'ID'          => 1,
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_title'  => 'Alpha post',
					'post_date'   => '2026-01-02 00:00:00',
				),
				array(
					'ID'          => 2,
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_title'  => 'Beta post',
					'post_date'   => '2026-01-01 00:00:00',
				),
			)
		);
		$GLOBALS['salt_test_permalinks'] = array(
			1 => 'https://example.test/blog/alpha-post/',
			2 => 'https://example.test/blog/beta-post/',
		);

		$this->assertMatchesHtmlSnapshot(
			'blog-teaser-two-no-header',
			$this->render_blog_teaser(
				array(
					'columns'  => 2,
					'view_all' => 0,
				),
				false
			)
		);
	}
}
