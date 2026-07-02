<?php
/**
 * Blog Teaser section unit tests (spec §10 #17).
 *
 * Covers the COMPL02 states (0 renders nothing / 1 static / N grid),
 * malformed-row skipping, the default 3-newest resolution, the ISO 8601
 * <time datetime> attribute, the teaser-rule "view all" link to the posts
 * page (§6, never pagination) and the DATA02 layout-field contract.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';
require_once __DIR__ . '/../shims/blog.php';

final class BlogTeaserSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		$GLOBALS['salt_test_permalinks'] = array();
		$GLOBALS['salt_test_acf_groups'] = array();
		salt_test_attachments_reset();
	}

	/**
	 * Seed one blog_teaser FC row and render the section.
	 *
	 * @param array<string,mixed> $fields     Row fields.
	 * @param bool                $is_primary Loop context flag.
	 * @return string
	 */
	private function render_blog_teaser( array $fields = array(), bool $is_primary = true ): string {
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

	/**
	 * Seed published post fixtures.
	 *
	 * @param array<int,array<string,mixed>> $posts Post prop arrays.
	 * @return void
	 */
	private function seed_blog_posts( array $posts ): void {
		$defaults = array(
			'post_type'   => 'post',
			'post_status' => 'publish',
		);
		$this->seed_posts(
			array_map( static fn( array $p ): array => array_merge( $defaults, $p ), $posts )
		);
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_items_renders_nothing(): void {
		$this->assertSame( '', $this->render_blog_teaser( array( 'heading' => 'From the blog' ) ) );
	}

	public function test_one_item_renders_a_static_card_with_no_js(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'           => 7,
					'post_title'   => 'Hello world',
					'post_date'    => '2026-03-05 09:30:00',
					'post_excerpt' => 'A first post.',
				),
			)
		);
		$GLOBALS['salt_test_permalinks'][7] = 'https://example.test/blog/hello-world/';

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'view_all' => 0,
			)
		);

		$this->assertSame( 1, substr_count( $html, '<article' ), 'Exactly one card.' );
		$this->assertStringContainsString( 'Hello world', $html );
		$this->assertStringContainsString( 'A first post.', $html );
		$this->assertStringContainsString( 'https://example.test/blog/hello-world/', $html );
		$this->assertStringNotContainsString( '<script', $html, 'No slider/JS init on 1 item.' );
		$this->assertStringNotContainsString( 'sm:grid-cols-2', $html, 'Single item is a static single column.' );
	}

	public function test_n_items_render_a_grid_with_the_chosen_columns(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
					'post_date'  => '2026-01-03 00:00:00',
				),
				array(
					'ID'         => 2,
					'post_title' => 'Beta',
					'post_date'  => '2026-01-02 00:00:00',
				),
				array(
					'ID'         => 3,
					'post_title' => 'Gamma',
					'post_date'  => '2026-01-01 00:00:00',
				),
			)
		);

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'columns'  => 2,
				'view_all' => 0,
			)
		);

		$this->assertSame( 3, substr_count( $html, '<article' ) );
		$this->assertStringContainsString( 'sm:grid-cols-2', $html );
		$this->assertStringNotContainsString( 'lg:grid-cols-3', $html, 'Two-column variant never emits the 3-col class.' );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_default_resolution_is_the_three_newest_posts(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Oldest',
					'post_date'  => '2026-01-01 00:00:00',
				),
				array(
					'ID'         => 2,
					'post_title' => 'Older',
					'post_date'  => '2026-01-02 00:00:00',
				),
				array(
					'ID'         => 3,
					'post_title' => 'Newer',
					'post_date'  => '2026-01-03 00:00:00',
				),
				array(
					'ID'         => 4,
					'post_title' => 'Newest',
					'post_date'  => '2026-01-04 00:00:00',
				),
			)
		);

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'view_all' => 0,
			)
		);

		$this->assertSame( 3, substr_count( $html, '<article' ), 'Frozen post default: 3 newest.' );
		$this->assertStringContainsString( 'Newest', $html );
		$this->assertStringNotContainsString( 'Oldest', $html );
	}

	public function test_primary_section_heading_is_h1_and_card_titles_step_down(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'view_all' => 0,
			),
			true
		);

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ), 'Primary section claims the H1 (A11Y01).' );
		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ), 'Card titles render at level + 1.' );
	}

	public function test_headingless_section_does_not_claim_h1(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);

		$html = $this->render_blog_teaser( array( 'view_all' => 0 ), true );

		$this->assertSame( 0, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less section leaves the H1 claim for the page fallback.' );
	}

	public function test_malformed_rows_are_skipped_and_logged(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
				array(
					'ID'         => 2,
					'post_title' => '   ',
				),
			)
		);

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'view_all' => 0,
			)
		);

		$this->assertSame( 1, substr_count( $html, '<article' ), 'Titleless post is skipped, not a broken card.' );
		$this->assertStringContainsString( 'post 2 has no title', implode( ' ', $this->logs() ) );
	}

	public function test_all_items_malformed_renders_nothing(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 2,
					'post_title' => '',
				),
			)
		);

		$this->assertSame( '', $this->render_blog_teaser( array( 'heading' => 'From the blog' ) ) );
		$this->assertNotSame( array(), $this->logs() );
	}

	public function test_invalid_columns_value_falls_back_to_three_and_logs(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
				array(
					'ID'         => 2,
					'post_title' => 'Beta',
				),
			)
		);

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'columns'  => 5,
				'view_all' => 0,
			)
		);

		$this->assertStringContainsString( 'lg:grid-cols-3', $html );
		$this->assertStringContainsString( 'invalid columns "5"', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Date markup.
	// -----------------------------------------------------------------

	public function test_date_is_iso_8601_in_the_datetime_attribute(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Dated post',
					'post_date'  => '2026-03-05 09:30:00',
				),
			)
		);

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'view_all' => 0,
			)
		);

		$this->assertStringContainsString( '<time class="x-card-post-date" datetime="2026-03-05T09:30:00+00:00">', $html );
	}

	public function test_missing_date_renders_no_time_element(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Undated post',
					'post_date'  => '',
				),
			)
		);

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'view_all' => 0,
			)
		);

		$this->assertStringNotContainsString( '<time', $html, 'No <time> when the date cannot resolve.' );
		$this->assertStringContainsString( 'Undated post', $html );
	}

	// -----------------------------------------------------------------
	// Teaser rule (§6): view-all link, never pagination.
	// -----------------------------------------------------------------

	public function test_view_all_links_to_the_posts_page(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);
		$GLOBALS['salt_test_options']['page_for_posts'] = 12;
		$GLOBALS['salt_test_permalinks'][12]            = 'https://example.test/blog/';

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'view_all' => 1,
			)
		);

		$this->assertStringContainsString( 'https://example.test/blog/', $html );
		$this->assertStringContainsString( 'View all posts', $html );
	}

	public function test_view_all_falls_back_to_home_when_no_posts_page_is_set(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'view_all' => 1,
			)
		);

		$this->assertStringContainsString( 'href="https://example.test/"', $html );
		$this->assertStringContainsString( 'View all posts', $html );
	}

	public function test_view_all_off_renders_no_link(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'view_all' => 0,
			)
		);

		$this->assertStringNotContainsString( 'View all posts', $html );
	}

	public function test_teaser_never_renders_pagination_markup(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
					'post_date'  => '2026-01-04 00:00:00',
				),
				array(
					'ID'         => 2,
					'post_title' => 'Beta',
					'post_date'  => '2026-01-03 00:00:00',
				),
				array(
					'ID'         => 3,
					'post_title' => 'Gamma',
					'post_date'  => '2026-01-02 00:00:00',
				),
				array(
					'ID'         => 4,
					'post_title' => 'Delta',
					'post_date'  => '2026-01-01 00:00:00',
				),
			)
		);

		$html = $this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'view_all' => 1,
			)
		);

		$this->assertStringNotContainsString( 'page-numbers', $html );
		$this->assertStringNotContainsString( 'pagination', $html );
		$this->assertStringNotContainsString( 'rel="next"', $html );
		$this->assertStringNotContainsString( 'rel="prev"', $html );
	}

	// -----------------------------------------------------------------
	// Schema: none (§10 table '—').
	// -----------------------------------------------------------------

	public function test_no_schema_nodes_are_queued(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);

		salt_schema_reset();
		$this->render_blog_teaser(
			array(
				'heading'  => 'From the blog',
				'view_all' => 0,
			)
		);

		$graph = salt_schema_graph(
			array(
				'home_url' => 'https://example.test',
				'page_url' => 'https://example.test/p',
			)
		)['@graph'];

		$types = array_map( static fn( array $n ): string => (string) $n['@type'], $graph );
		$this->assertNotContains( 'BlogPosting', $types );
		$this->assertNotContains( 'Blog', $types );
	}

	// -----------------------------------------------------------------
	// Layout field contract (DATA02).
	// -----------------------------------------------------------------

	public function test_layout_field_keys_follow_the_data02_contract(): void {
		$layout = require dirname( __DIR__, 2 ) . '/inc/fields/layouts/blog-teaser.php';

		$this->assertSame( 'layout_salt_blog_teaser', $layout['key'] );
		$this->assertSame( 'blog_teaser', $layout['name'] );

		$names = array_column( $layout['sub_fields'], 'name' );
		foreach ( array( 'eyebrow', 'heading', 'columns', 'view_all', 'source', 'count' ) as $expected ) {
			$this->assertContains( $expected, $names );
		}

		// Content fields first (DATA02 keys), query controls before the wrapper.
		$this->assertSame( 'field_salt_blog_teaser_eyebrow', $layout['sub_fields'][0]['key'] );
		$this->assertLessThan(
			array_search( 'section_spacing', $names, true ),
			array_search( 'source', $names, true ),
			'Query controls precede the shared wrapper fields.'
		);
	}
}
