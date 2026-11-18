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
			array_search( 'settings', $names, true ),
			array_search( 'source', $names, true ),
			'Query controls precede the shared settings group.'
		);
		$this->assertSame( 'settings', end( $names ), 'The contract\'s section settings group last.' );
	}

	// -----------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one blog_teaser FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row fields.
	 * @return array<string,mixed>|null
	 */
	private function resolve_blog_teaser( array $fields = array() ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'blog_teaser',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'blog-teaser' );
		$this->assertSame( 'salt_resolve_section_blog_teaser', $resolver );

		return $resolver(
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
	}

	public function test_resolver_returns_null_for_zero_and_malformed_items(): void {
		$this->assertNull( $this->resolve_blog_teaser( array( 'heading' => 'From the blog' ) ) );

		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => '  ',
				),
			)
		);
		$this->assertNull( $this->resolve_blog_teaser( array( 'heading' => 'From the blog' ) ) );
		$this->assertStringContainsString( 'post 1 has no title', implode( ' ', $this->logs() ) );
	}

	public function test_resolver_props_items_and_fallbacks(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'           => 1,
					'post_title'   => 'Alpha',
					'post_date'    => '2026-03-05 09:30:00',
					'post_excerpt' => 'First post.',
				),
				array(
					'ID'         => 2,
					'post_title' => 'Undated',
					'post_date'  => '',
				),
			)
		);

		$resolved = $this->resolve_blog_teaser(
			array(
				'eyebrow' => 'News',
				'columns' => 5,
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'columns', 'items', 'view_all_url' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'News', $resolved['props']['eyebrow'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertSame( 3, $resolved['props']['columns'] );
		$this->assertNull( $resolved['props']['view_all_url'] );
		$this->assertStringContainsString( 'invalid columns "5"', implode( ' ', $this->logs() ) );
		$this->assertFalse( $resolved['section']['has_heading'] );
		$this->assertSame( 'blog-teaser', $resolved['section']['name'] );

		$by_title = array_column( $resolved['props']['items'], null, 'title' );
		$this->assertSame( array( 'title', 'url', 'date', 'datetime', 'excerpt', 'image_id' ), array_keys( $by_title['Alpha'] ) );
		$this->assertSame( '2026-03-05T09:30:00+00:00', $by_title['Alpha']['datetime'] );
		$this->assertSame( 'First post.', $by_title['Alpha']['excerpt'] );
		$this->assertSame( 0, $by_title['Alpha']['image_id'] );
		$this->assertSame( '', $by_title['Undated']['date'], 'An unresolvable date is an empty string.' );
		$this->assertSame( '', $by_title['Undated']['datetime'] );
	}

	public function test_resolver_view_all_url_is_the_posts_page_else_home_and_null_when_off(): void {
		$this->seed_blog_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);

		$home = $this->resolve_blog_teaser( array( 'view_all' => 1 ) );
		$this->assertSame( 'https://example.test/', $home['props']['view_all_url'] );

		$GLOBALS['salt_test_options']['page_for_posts'] = 12;
		$GLOBALS['salt_test_permalinks'][12]            = 'https://example.test/blog/';
		$page = $this->resolve_blog_teaser( array( 'view_all' => 1 ) );
		$this->assertSame( 'https://example.test/blog/', $page['props']['view_all_url'] );

		$this->assertNull( $this->resolve_blog_teaser( array( 'view_all' => 0 ) )['props']['view_all_url'] );
		$this->assertSame( array(), salt_schema_queue(), 'Blog teaser queues no schema.' );
	}

	/**
	 * Draw the view from props.
	 *
	 * @param array<string,mixed> $props Props.
	 * @return string
	 */
	private function draw_blog_teaser_view( array $props ): string {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/blog-teaser.php',
			false,
			array(
				'props' => $props,
				'plan'  => array(
					'index'         => 0,
					'headingLevel'  => 2,
					'priorityMedia' => false,
				),
			)
		);
		return (string) ob_get_clean();
	}

	public function test_view_draws_from_props_alone(): void {
		$item  = array(
			'title'    => 'Prop Post',
			'url'      => 'https://example.test/prop-post/',
			'date'     => '5 March 2026',
			'datetime' => '2026-03-05T09:30:00+00:00',
			'excerpt'  => 'Drawn from props.',
			'image_id' => 0,
		);
		$props = array(
			'eyebrow'      => 'News',
			'heading'      => 'From props',
			'columns'      => 2,
			'items'        => array( $item, array_merge( $item, array( 'title' => 'Second' ) ) ),
			'view_all_url' => 'https://example.test/blog/',
		);

		$html = $this->draw_blog_teaser_view( $props );

		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ), 'Section heading at the plan level.' );
		$this->assertSame( 2, preg_match_all( '/<h3[\s>]/', $html ), 'Card titles one level below.' );
		$this->assertStringContainsString( 'x-blog-teaser-header', $html );
		$this->assertStringContainsString( 'grid list-none gap-8 p-0 sm:grid-cols-2"', $html );
		$this->assertStringContainsString( 'datetime="2026-03-05T09:30:00+00:00"', $html );
		$this->assertStringContainsString( 'Drawn from props.', $html );
		$this->assertStringContainsString( 'https://example.test/blog/', $html );
		$this->assertStringContainsString( 'View all posts', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );

		$props['items']        = array( $item );
		$props['eyebrow']      = null;
		$props['heading']      = null;
		$props['view_all_url'] = null;
		$bare                  = $this->draw_blog_teaser_view( $props );
		$this->assertStringNotContainsString( 'x-blog-teaser-header', $bare );
		$this->assertStringNotContainsString( 'sm:grid-cols-2', $bare, 'One item draws a single column.' );
		$this->assertStringNotContainsString( 'View all posts', $bare );
	}
}
