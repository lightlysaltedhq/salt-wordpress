<?php
/**
 * Work CPT + Portfolio/Work section unit tests (spec §10 #15, Appendix A.4).
 *
 * Covers the COMPL02 states (0 renders nothing / 1 static / N grid),
 * malformed-row skipping, module gating (§10b), the teaser-rule "view all"
 * link (§6), CreativeWork schema queueing (§15) and the DATA02 field/CPT
 * registration contracts.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';
require_once __DIR__ . '/../shims/work.php';

require_once dirname( __DIR__, 2 ) . '/inc/cpt/work.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/cpt-work.php';

final class WorkSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		$GLOBALS['salt_test_post_types']    = array();
		$GLOBALS['salt_test_taxonomies']    = array();
		$GLOBALS['salt_test_acf_groups']    = array();
		$GLOBALS['salt_test_permalinks']    = array();
		$GLOBALS['salt_test_archive_links'] = array();
		salt_test_attachments_reset();
	}

	/**
	 * Seed one work FC row and render the section.
	 *
	 * @param array<string,mixed> $fields Row fields.
	 * @param bool                $is_primary Loop context flag.
	 * @return string
	 */
	private function render_work( array $fields = array(), bool $is_primary = true ): string {
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

	/**
	 * Seed a published work post fixture.
	 *
	 * @param array<int,array<string,mixed>> $posts Post prop arrays.
	 * @return void
	 */
	private function seed_work_posts( array $posts ): void {
		$defaults = array(
			'post_type'   => 'work',
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
		$this->assertSame( '', $this->render_work( array( 'heading' => 'Our work' ) ) );
	}

	public function test_module_disabled_renders_nothing(): void {
		add_filter(
			'salt_module_enabled',
			static fn( bool $enabled, string $slug ): bool => 'work' === $slug ? false : $enabled,
			20,
			2
		);
		$this->seed_work_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);

		$this->assertSame( '', $this->render_work( array( 'heading' => 'Our work' ) ) );
	}

	public function test_one_item_renders_a_static_card_with_no_js(): void {
		$this->seed_work_posts(
			array(
				array(
					'ID'         => 7,
					'post_title' => 'Alpha rebrand',
				),
			)
		);
		$GLOBALS['salt_test_permalinks'][7] = 'https://example.test/work/alpha-rebrand/';
		$GLOBALS['salt_test_fields']['7']   = array(
			'client'  => 'Alpha Ltd',
			'summary' => 'A full rebrand.',
		);

		$html = $this->render_work(
			array(
				'heading'  => 'Our work',
				'view_all' => 0,
			)
		);

		$this->assertSame( 1, substr_count( $html, '<article' ), 'Exactly one card.' );
		$this->assertStringContainsString( 'Alpha rebrand', $html );
		$this->assertStringContainsString( 'Alpha Ltd', $html );
		$this->assertStringContainsString( 'A full rebrand.', $html );
		$this->assertStringContainsString( 'https://example.test/work/alpha-rebrand/', $html );
		$this->assertStringNotContainsString( '<script', $html, 'No slider/JS init on 1 item.' );
		$this->assertStringNotContainsString( 'sm:grid-cols-2', $html, 'Single item is a static single column.' );
	}

	public function test_n_items_render_a_grid_with_the_chosen_columns(): void {
		$this->seed_work_posts(
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

		$html = $this->render_work(
			array(
				'heading'  => 'Our work',
				'columns'  => 2,
				'view_all' => 0,
			)
		);

		$this->assertSame( 3, substr_count( $html, '<article' ) );
		$this->assertStringContainsString( 'sm:grid-cols-2', $html );
		$this->assertStringNotContainsString( 'lg:grid-cols-3', $html, 'Two-column variant never emits the 3-col class.' );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_primary_section_heading_is_h1_and_card_titles_step_down(): void {
		$this->seed_work_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);

		$html = $this->render_work(
			array(
				'heading'  => 'Our work',
				'view_all' => 0,
			),
			true
		);

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ), 'Primary section claims the H1 (A11Y01).' );
		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ), 'Card titles render at level + 1.' );
	}

	public function test_headingless_section_does_not_claim_h1(): void {
		$this->seed_work_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);

		$html = $this->render_work( array( 'view_all' => 0 ), true );

		$this->assertSame( 0, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less section leaves the H1 claim for the page fallback.' );
	}

	public function test_malformed_rows_are_skipped_and_logged(): void {
		$this->seed_work_posts(
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

		$html = $this->render_work(
			array(
				'heading'  => 'Our work',
				'view_all' => 0,
			)
		);

		$this->assertSame( 1, substr_count( $html, '<article' ), 'Titleless item is skipped, not a broken card.' );
		$this->assertStringContainsString( 'work post 2 has no title', implode( ' ', $this->logs() ) );
	}

	public function test_all_items_malformed_renders_nothing(): void {
		$this->seed_work_posts(
			array(
				array(
					'ID'         => 2,
					'post_title' => '',
				),
			)
		);

		$this->assertSame( '', $this->render_work( array( 'heading' => 'Our work' ) ) );
		$this->assertNotSame( array(), $this->logs() );
	}

	public function test_invalid_columns_value_falls_back_to_three_and_logs(): void {
		$this->seed_work_posts(
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

		$html = $this->render_work(
			array(
				'heading'  => 'Our work',
				'columns'  => 5,
				'view_all' => 0,
			)
		);

		$this->assertStringContainsString( 'lg:grid-cols-3', $html );
		$this->assertStringContainsString( 'invalid columns "5"', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Teaser rule (§6).
	// -----------------------------------------------------------------

	public function test_view_all_links_to_the_work_archive(): void {
		$this->seed_work_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);
		$GLOBALS['salt_test_archive_links']['work'] = 'https://example.test/work/';

		$html = $this->render_work(
			array(
				'heading'  => 'Our work',
				'view_all' => 1,
			)
		);

		$this->assertStringContainsString( 'https://example.test/work/', $html );
		$this->assertStringContainsString( 'View all work', $html );
	}

	public function test_view_all_off_renders_no_archive_link(): void {
		$this->seed_work_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);
		$GLOBALS['salt_test_archive_links']['work'] = 'https://example.test/work/';

		$html = $this->render_work(
			array(
				'heading'  => 'Our work',
				'view_all' => 0,
			)
		);

		$this->assertStringNotContainsString( 'View all work', $html );
	}

	public function test_view_all_skipped_when_archive_link_unavailable(): void {
		$this->seed_work_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);
		$GLOBALS['salt_test_archive_links']['work'] = false;

		$html = $this->render_work(
			array(
				'heading'  => 'Our work',
				'view_all' => 1,
			)
		);

		$this->assertStringNotContainsString( 'View all work', $html );
	}

	// -----------------------------------------------------------------
	// Schema (§15).
	// -----------------------------------------------------------------

	public function test_creativework_nodes_are_queued_per_item(): void {
		$this->seed_work_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
					'post_date'  => '2026-01-02 00:00:00',
				),
				array(
					'ID'         => 2,
					'post_title' => 'Beta',
					'post_date'  => '2026-01-01 00:00:00',
				),
			)
		);
		$GLOBALS['salt_test_permalinks']  = array(
			1 => 'https://example.test/work/alpha/',
			2 => 'https://example.test/work/beta/',
		);
		$GLOBALS['salt_test_fields']['1'] = array( 'client' => 'Alpha Ltd' );

		$this->render_work(
			array(
				'heading'  => 'Our work',
				'view_all' => 0,
			)
		);

		$graph = salt_schema_graph(
			array(
				'home_url' => 'https://example.test',
				'page_url' => 'https://example.test/p',
			)
		)['@graph'];

		$nodes = array_values( array_filter( $graph, static fn( array $n ): bool => 'CreativeWork' === $n['@type'] ) );

		$this->assertCount( 2, $nodes );
		$this->assertSame( 'Alpha', $nodes[0]['name'] );
		$this->assertSame( 'https://example.test/work/alpha/', $nodes[0]['url'] );
		$this->assertSame(
			array(
				'@type' => 'Organization',
				'name'  => 'Alpha Ltd',
			),
			$nodes[0]['about'],
			'about carries the client when set.'
		);
		$this->assertSame( 'Beta', $nodes[1]['name'] );
		$this->assertArrayNotHasKey( 'about', $nodes[1], 'No about when the client is empty.' );
	}

	public function test_zero_state_queues_no_schema_nodes(): void {
		$this->render_work( array( 'heading' => 'Our work' ) );

		$this->assertSame( array(), salt_schema_queue() );
	}

	// -----------------------------------------------------------------
	// CPT registration (Appendix A.4, DATA01).
	// -----------------------------------------------------------------

	public function test_cpt_work_registers_public_with_archive_and_taxonomies(): void {
		salt_register_cpt_work();

		$this->assertArrayHasKey( 'work', $GLOBALS['salt_test_post_types'] );
		$cpt = $GLOBALS['salt_test_post_types']['work'];
		$this->assertTrue( $cpt['public'] );
		$this->assertTrue( $cpt['has_archive'] );
		$this->assertSame( 'work', $cpt['rewrite']['slug'] );
		$this->assertSame( array( 'title', 'editor', 'thumbnail', 'excerpt' ), $cpt['supports'] );

		foreach ( array( 'work_type', 'industry' ) as $taxonomy ) {
			$this->assertArrayHasKey( $taxonomy, $GLOBALS['salt_test_taxonomies'] );
			$this->assertSame( array( 'work' ), $GLOBALS['salt_test_taxonomies'][ $taxonomy ]['object_type'] );
		}
	}

	public function test_cpt_work_registers_nothing_when_module_disabled(): void {
		add_filter(
			'salt_module_enabled',
			static fn( bool $enabled, string $slug ): bool => 'work' === $slug ? false : $enabled,
			20,
			2
		);

		salt_register_cpt_work();

		$this->assertSame( array(), $GLOBALS['salt_test_post_types'] );
		$this->assertSame( array(), $GLOBALS['salt_test_taxonomies'] );
	}

	// -----------------------------------------------------------------
	// Fields-as-code (Appendix A.4, DATA02).
	// -----------------------------------------------------------------

	public function test_work_field_group_registers_the_appendix_a4_fields(): void {
		salt_register_work_fields();

		$this->assertCount( 1, $GLOBALS['salt_test_acf_groups'] );
		$group = $GLOBALS['salt_test_acf_groups'][0];
		$this->assertSame( 'group_salt_work', $group['key'] );

		$by_name = array_column( $group['fields'], null, 'name' );
		$this->assertSame(
			array( 'client', 'summary', 'gallery', 'challenge', 'solution', 'results', 'services', 'live_url' ),
			array_keys( $by_name )
		);
		$this->assertSame( 'gallery', $by_name['gallery']['type'] );
		$this->assertSame( 'id', $by_name['gallery']['return_format'] );
		$this->assertSame( 'repeater', $by_name['results']['type'] );
		$this->assertSame(
			array( 'stat', 'label' ),
			array_column( $by_name['results']['sub_fields'], 'name' )
		);
		$this->assertSame( array( 'service' ), $by_name['services']['post_type'] );
		$this->assertSame( 'url', $by_name['live_url']['type'] );
		$this->assertStringStartsWith( 'field_salt_work_', $by_name['client']['key'] );

		$this->assertSame(
			array(),
			array_filter( $this->logs(), static fn( string $l ): bool => str_contains( $l, 'DATA02' ) ),
			'No key-convention violations logged.'
		);
	}

	public function test_work_field_group_skipped_when_module_disabled(): void {
		add_filter(
			'salt_module_enabled',
			static fn( bool $enabled, string $slug ): bool => 'work' === $slug ? false : $enabled,
			20,
			2
		);

		salt_register_work_fields();

		$this->assertSame( array(), $GLOBALS['salt_test_acf_groups'] );
	}

	// -----------------------------------------------------------------
	// Layout contract (FLEET07 / DATA02).
	// -----------------------------------------------------------------

	public function test_layout_file_returns_the_frozen_contract_shape(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/work.php';

		$this->assertSame( 'work', $layout['name'] );
		$this->assertSame( 'layout_salt_work', $layout['key'] );

		$names = array_values(
			array_filter( array_column( $layout['sub_fields'], 'name' ) )
		);

		// Content fields first…
		$this->assertSame( array( 'eyebrow', 'heading', 'columns', 'view_all' ), array_slice( $names, 0, 4 ) );
		// …then the shared query controls…
		$this->assertContains( 'source', $names );
		$this->assertContains( 'taxonomy_terms', $names );
		$this->assertContains( 'count', $names );
		// …then the contract's section settings group last.
		$this->assertSame( 'settings', end( $names ) );

		$this->assertLessThan(
			array_search( 'source', $names, true ),
			array_search( 'view_all', $names, true ),
			'Query controls come after the content fields.'
		);
		$this->assertLessThan(
			array_search( 'settings', $names, true ),
			array_search( 'count', $names, true ),
			'The settings group comes after the query controls.'
		);

		$columns = array_column( $layout['sub_fields'], null, 'name' )['columns'];
		$this->assertSame( array( 2, 3 ), array_keys( $columns['choices'] ) );

		$keys = array_filter( array_column( $layout['sub_fields'], 'key' ) );
		foreach ( $keys as $key ) {
			$this->assertStringStartsWith( 'field_salt_', (string) $key, 'DATA02 key convention.' );
		}
	}

	// -----------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one work FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row fields.
	 * @return array<string,mixed>|null
	 */
	private function resolve_work( array $fields = array() ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'work',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'work' );
		$this->assertSame( 'salt_resolve_section_work', $resolver );

		return $resolver(
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
	}

	public function test_resolver_returns_null_for_zero_items_malformed_items_and_a_disabled_module(): void {
		$this->assertNull( $this->resolve_work( array( 'heading' => 'Our work' ) ) );

		$this->seed_work_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => '  ',
				),
			)
		);
		$this->assertNull( $this->resolve_work( array( 'heading' => 'Our work' ) ) );
		$this->assertSame( array(), salt_schema_queue() );

		$this->seed_work_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);
		add_filter(
			'salt_module_enabled',
			static fn( bool $enabled, string $slug ): bool => 'work' === $slug ? false : $enabled,
			20,
			2
		);
		$this->assertNull( $this->resolve_work( array( 'heading' => 'Our work' ) ) );
	}

	public function test_resolver_props_items_and_fallbacks(): void {
		$this->seed_work_posts(
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
		$GLOBALS['salt_test_fields']['1'] = array(
			'client'  => 'Acme',
			'summary' => 'A summary.',
		);

		$resolved = $this->resolve_work(
			array(
				'eyebrow' => 'Portfolio',
				'columns' => 9,
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'columns', 'items', 'view_all_url' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'Portfolio', $resolved['props']['eyebrow'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertSame( 3, $resolved['props']['columns'] );
		$this->assertNull( $resolved['props']['view_all_url'] );
		$this->assertStringContainsString( 'invalid columns "9"', implode( ' ', $this->logs() ) );

		$this->assertCount( 2, $resolved['props']['items'] );
		$by_title = array_column( $resolved['props']['items'], null, 'title' );
		$this->assertSame( array( 'title', 'url', 'client', 'summary', 'image_id' ), array_keys( $by_title['Alpha'] ) );
		$this->assertSame( 'Acme', $by_title['Alpha']['client'] );
		$this->assertSame( 'A summary.', $by_title['Alpha']['summary'] );
		$this->assertSame( 0, $by_title['Beta']['image_id'] );
		$this->assertFalse( $resolved['section']['has_heading'] );
		$this->assertSame( 'work', $resolved['section']['name'] );
	}

	public function test_resolver_queues_the_schema_itself_and_resolves_view_all(): void {
		$this->seed_work_posts(
			array(
				array(
					'ID'         => 1,
					'post_title' => 'Alpha',
				),
			)
		);
		$GLOBALS['salt_test_archive_links']['work'] = 'https://example.test/work/';

		$on = $this->resolve_work( array( 'view_all' => 1 ) );
		$this->assertSame( 'https://example.test/work/', $on['props']['view_all_url'] );
		$this->assertCount( 1, salt_schema_queue(), 'Schema is logic: queued without the view ever running.' );

		$GLOBALS['salt_test_archive_links']['work'] = false;
		$this->assertNull( $this->resolve_work( array( 'view_all' => 1 ) )['props']['view_all_url'] );
		$this->assertNull( $this->resolve_work( array( 'view_all' => 0 ) )['props']['view_all_url'] );
	}

	/**
	 * Draw the view from props.
	 *
	 * @param array<string,mixed> $props Props.
	 * @return string
	 */
	private function draw_work_view( array $props ): string {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/work.php',
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
			'title'    => 'Prop Project',
			'url'      => 'https://example.test/work/prop/',
			'client'   => 'Prop Client',
			'summary'  => 'Drawn from props.',
			'image_id' => 0,
		);
		$props = array(
			'eyebrow'      => 'Portfolio',
			'heading'      => 'From props',
			'columns'      => 2,
			'items'        => array( $item, array_merge( $item, array( 'title' => 'Second' ) ) ),
			'view_all_url' => 'https://example.test/work/',
		);

		$html = $this->draw_work_view( $props );

		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ), 'Section heading at the plan level.' );
		$this->assertSame( 2, preg_match_all( '/<h3[\s>]/', $html ), 'Card titles one level below.' );
		$this->assertStringContainsString( 'x-work-header', $html );
		$this->assertStringContainsString( 'grid list-none gap-8 p-0 sm:grid-cols-2"', $html );
		$this->assertStringNotContainsString( 'lg:grid-cols-3', $html );
		$this->assertStringContainsString( 'Prop Project', $html );
		$this->assertStringContainsString( 'View all work', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );

		$props['items']        = array( $item );
		$props['eyebrow']      = null;
		$props['heading']      = null;
		$props['view_all_url'] = null;
		$bare                  = $this->draw_work_view( $props );
		$this->assertStringNotContainsString( 'x-work-header', $bare );
		$this->assertStringNotContainsString( 'sm:grid-cols-2', $bare, 'One item draws a single column.' );
		$this->assertStringNotContainsString( 'View all work', $bare );
	}
}
