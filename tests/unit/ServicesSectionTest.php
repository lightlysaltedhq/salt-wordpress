<?php
/**
 * Services slice tests: CPT + fields registration, layout shape, the
 * section's 0/1/N states (COMPL02), broken-row skipping, Service schema
 * nodes and the §10b module gate.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/service.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/service.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/cpt-service.php';

final class ServicesSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		$GLOBALS['salt_test_cpts']       = array();
		$GLOBALS['salt_test_taxonomies'] = array();
		$GLOBALS['salt_test_acf_groups'] = array();
		$GLOBALS['salt_test_permalinks'] = array();
		unset( $GLOBALS['salt_test_current_post'] );
	}

	/**
	 * Seed three published services (menu_order ASC fixture set).
	 *
	 * @return void
	 */
	private function seed_services(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 5,
					'post_type'  => 'service',
					'post_title' => 'Web Design',
					'menu_order' => 1,
				),
				array(
					'ID'         => 6,
					'post_type'  => 'service',
					'post_title' => 'Branding',
					'menu_order' => 2,
				),
				array(
					'ID'         => 7,
					'post_type'  => 'service',
					'post_title' => 'SEO',
					'menu_order' => 3,
				),
			)
		);
		$GLOBALS['salt_test_fields']['5'] = array(
			'summary' => 'Fast, accessible sites.',
			'icon'    => 'star',
		);
		$GLOBALS['salt_test_fields']['6'] = array(
			'summary' => 'Identities that stick.',
			'icon'    => 'check',
		);
		$GLOBALS['salt_test_fields']['7'] = array( 'summary' => 'Be found.' );
	}

	/**
	 * Render the services section for one seeded FC row.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string
	 */
	private function render_services( array $fields = array() ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'services',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'services',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------------

	public function test_zero_items_renders_nothing(): void {
		$this->seed_posts( array() );

		$html = $this->render_services( array( 'heading' => 'Our services' ) );

		$this->assertSame( '', $html, '0-state must render NOTHING — never a blank band (COMPL02).' );
		$this->assertSame( array(), salt_schema_queue(), 'No Service nodes for an unrendered section.' );
	}

	public function test_one_item_renders_a_static_card_with_no_js_hooks(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 5,
					'post_type'  => 'service',
					'post_title' => 'Web Design',
					'menu_order' => 1,
				),
			)
		);
		$GLOBALS['salt_test_fields']['5'] = array( 'summary' => 'Fast, accessible sites.' );

		$html = $this->render_services( array( 'heading' => 'Our services' ) );

		$this->assertSame( 1, substr_count( $html, 'x-card-service' ), 'Exactly one static card.' );
		$this->assertStringContainsString( 'data-section="services"', $html );
		$this->assertStringContainsString( 'Web Design', $html );
		$this->assertStringNotContainsString( '<script', $html, 'No JS in this section — ever (FAIL02).' );
	}

	public function test_n_items_render_the_grid_with_column_classes(): void {
		$this->seed_services();

		$html = $this->render_services(
			array(
				'heading' => 'Our services',
				'layout'  => 'grid',
				'columns' => '4',
			)
		);

		$this->assertSame( 3, substr_count( $html, '<article' ) );
		$this->assertStringContainsString( 'x-services-grid', $html );
		$this->assertStringContainsString( 'lg:grid-cols-4', $html );
	}

	public function test_unknown_layout_and_columns_degrade_to_grid_defaults(): void {
		$this->seed_services();

		$html = $this->render_services(
			array(
				'heading' => 'Our services',
				'layout'  => 'carousel',
				'columns' => '7',
			)
		);

		$this->assertStringContainsString( 'x-services-grid', $html );
		$this->assertStringContainsString( 'lg:grid-cols-3', $html );
	}

	public function test_list_and_featured_variants_render_their_wrappers(): void {
		$this->seed_services();

		$list = $this->render_services(
			array(
				'heading' => 'Our services',
				'layout'  => 'list',
			)
		);
		$this->assertStringContainsString( 'x-services-list', $list );

		salt_render_state_reset();
		salt_schema_reset();

		$featured = $this->render_services(
			array(
				'heading' => 'Our services',
				'layout'  => 'featured',
			)
		);
		$this->assertStringContainsString( 'x-services-featured', $featured );
		$this->assertStringContainsString( 'x-card-service-featured', $featured );
		$this->assertSame( 1, substr_count( $featured, 'x-card-service-featured' ), 'Only the first item leads.' );
	}

	public function test_broken_rows_are_skipped_and_logged(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 5,
					'post_type'  => 'service',
					'post_title' => 'Web Design',
					'menu_order' => 1,
				),
				array(
					'ID'         => 9,
					'post_type'  => 'service',
					'post_title' => '',
					'menu_order' => 2,
				),
			)
		);

		$html = $this->render_services( array( 'heading' => 'Our services' ) );

		$this->assertSame( 1, substr_count( $html, 'x-card-service' ), 'Malformed service skipped.' );
		$this->assertStringContainsString( 'service 9', implode( ' ', $this->logs() ) );
	}

	public function test_all_broken_rows_skip_the_section_entirely(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 9,
					'post_type'  => 'service',
					'post_title' => '',
					'menu_order' => 1,
				),
			)
		);

		$this->assertSame( '', $this->render_services( array( 'heading' => 'Our services' ) ) );
	}

	// -----------------------------------------------------------------------
	// Headings (A11Y01) + teaser link (§6).
	// -----------------------------------------------------------------------

	public function test_primary_section_heading_is_h1_and_card_titles_step_to_h2(): void {
		$this->seed_services();

		$html = $this->render_services(
			array(
				'heading' => 'Our services',
				'eyebrow' => 'What we do',
			)
		);

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ), 'Primary section claims the H1.' );
		$this->assertSame( 3, preg_match_all( '/<h2[\s>]/', $html ), 'Card titles render at level + 1.' );
		$this->assertStringContainsString( 'x-eyebrow', $html );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$this->seed_services();

		$html = $this->render_services( array() );

		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less section must leave the H1 claim free (A11Y01).' );
	}

	public function test_view_all_links_to_the_service_archive(): void {
		$this->seed_services();

		$html = $this->render_services(
			array(
				'heading'  => 'Our services',
				'view_all' => 1,
			)
		);

		$this->assertStringContainsString( 'View all services', $html );
		$this->assertStringContainsString( 'https://example.test/services/', $html );
		$this->assertStringNotContainsString( 'page/2', $html, 'CPT sections never paginate (§6 teaser rule).' );
	}

	public function test_no_view_all_link_by_default(): void {
		$this->seed_services();

		$this->assertStringNotContainsString(
			'View all services',
			$this->render_services( array( 'heading' => 'Our services' ) )
		);
	}

	// -----------------------------------------------------------------------
	// Schema (§15).
	// -----------------------------------------------------------------------

	public function test_service_nodes_are_queued_and_assembled_into_the_graph(): void {
		$this->seed_services();

		$this->render_services( array( 'heading' => 'Our services' ) );

		$graph = salt_schema_graph(
			array(
				'home_url' => 'https://example.test',
				'page_url' => 'https://example.test/p',
			)
		)['@graph'];

		$services = array_values( array_filter( $graph, static fn( array $node ): bool => 'Service' === $node['@type'] ) );

		$this->assertCount( 3, $services );
		$this->assertSame( 'Web Design', $services[0]['name'] );
		$this->assertSame( 'https://example.test/services/service-5/', $services[0]['url'] );
		$this->assertSame( 'Fast, accessible sites.', $services[0]['description'] );
		$this->assertSame( 'https://example.test/services/service-5/#service', $services[0]['@id'], 'Stable permalink-derived @id so Service nodes dedupe and merge with Review.itemReviewed (SEO01).' );
	}

	public function test_schema_omits_empty_description(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 5,
					'post_type'  => 'service',
					'post_title' => 'Web Design',
					'menu_order' => 1,
				),
			)
		);

		$this->render_services( array( 'heading' => 'Our services' ) );

		$queue = salt_schema_queue();
		$this->assertCount( 1, $queue );
		$this->assertArrayNotHasKey( 'description', $queue[0] );
	}

	// -----------------------------------------------------------------------
	// Module gate (§10b).
	// -----------------------------------------------------------------------

	public function test_module_off_renders_nothing(): void {
		$GLOBALS['salt_test_fields']['option']['module_service'] = false;
		$this->seed_services();

		$this->assertSame( '', $this->render_services( array( 'heading' => 'Our services' ) ) );
		$this->assertSame( array(), salt_schema_queue(), 'Module off queues no schema.' );
	}

	public function test_module_off_skips_cpt_and_field_registration(): void {
		$GLOBALS['salt_test_fields']['option']['module_service'] = false;

		salt_register_cpt_service();
		salt_register_service_fields();

		$this->assertArrayNotHasKey( 'service', $GLOBALS['salt_test_cpts'] );
		$this->assertArrayNotHasKey( 'service_category', $GLOBALS['salt_test_taxonomies'] );
		$this->assertSame( array(), $GLOBALS['salt_test_acf_groups'] );
	}

	public function test_cpt_registers_with_appendix_a1_flags(): void {
		salt_register_cpt_service();

		$this->assertArrayHasKey( 'service', $GLOBALS['salt_test_cpts'] );
		$cpt = $GLOBALS['salt_test_cpts']['service'];

		$this->assertTrue( $cpt['public'] );
		$this->assertTrue( $cpt['has_archive'] );
		$this->assertSame( 'services', $cpt['rewrite']['slug'] );
		$this->assertSame(
			array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
			$cpt['supports']
		);

		$this->assertArrayHasKey( 'service_category', $GLOBALS['salt_test_taxonomies'] );
		$tax = $GLOBALS['salt_test_taxonomies']['service_category'];
		$this->assertSame( array( 'service' ), $tax['object_type'] );
		$this->assertTrue( $tax['args']['hierarchical'] );
	}

	public function test_field_group_follows_appendix_a1_and_data02(): void {
		salt_register_service_fields();

		$this->assertCount( 1, $GLOBALS['salt_test_acf_groups'] );
		$group = $GLOBALS['salt_test_acf_groups'][0];

		$this->assertSame( 'group_salt_service', $group['key'] );
		$this->assertSame(
			array( 'icon', 'summary', 'headline', 'benefits', 'price_from', 'related_services', 'cta' ),
			array_column( $group['fields'], 'name' )
		);
		foreach ( $group['fields'] as $field ) {
			$this->assertStringStartsWith( 'field_salt_service_', $field['key'] );
		}
		$this->assertSame( 'service', $group['location'][0][0]['value'] );
	}

	public function test_layout_definition_shape_and_field_order(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/services.php';

		$this->assertSame( 'services', $layout['name'] );
		$this->assertSame( 'layout_salt_services', $layout['key'] );

		$names = array_values( array_filter( array_column( $layout['sub_fields'], 'name' ) ) );

		// Content fields first…
		$this->assertSame( array( 'layout', 'columns', 'eyebrow', 'heading', 'view_all' ), array_slice( $names, 0, 5 ) );
		// …then the shared query controls (§10a)…
		$this->assertContains( 'source', $names );
		$this->assertContains( 'taxonomy_terms', $names );
		// …then the shared §11 wrapper tab LAST.
		$this->assertSame( 'divider', end( $names ) );

		foreach ( $layout['sub_fields'] as $field ) {
			if ( isset( $field['key'] ) ) {
				$this->assertStringStartsWith( 'field_salt_', (string) $field['key'] );
			}
		}
	}

	// -----------------------------------------------------------------------
	// Archive-loop content card (§6).
	// -----------------------------------------------------------------------

	public function test_archive_content_card_delegates_to_the_component(): void {
		$GLOBALS['salt_test_current_post'] = 7;
		$GLOBALS['salt_test_title']        = 'SEO';
		$GLOBALS['salt_test_fields']['7']  = array(
			'summary' => 'Be found.',
			'icon'    => 'star',
		);

		ob_start();
		get_template_part( 'template-parts/content/card', 'service' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'x-card-service', $html );
		$this->assertStringContainsString( 'SEO', $html );
		$this->assertStringContainsString( 'Be found.', $html );
		$this->assertStringContainsString( 'https://example.test/services/service-7/', $html );
		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ), 'Archive card titles are h2 under the archive H1.' );
	}

	// -----------------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------------

	/**
	 * Seed one services FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return array<string,mixed>|null
	 */
	private function resolve_services( array $fields = array() ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'services',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'services' );
		$this->assertSame( 'salt_resolve_section_services', $resolver );

		return $resolver(
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
	}

	public function test_resolver_returns_null_for_zero_items_and_a_disabled_module(): void {
		$this->seed_posts( array() );
		$this->assertNull( $this->resolve_services( array( 'heading' => 'Our services' ) ) );
		$this->assertSame( array(), salt_schema_queue() );

		$this->seed_services();
		$GLOBALS['salt_test_fields']['option']['module_service'] = false;
		$this->assertNull( $this->resolve_services( array( 'heading' => 'Our services' ) ) );
	}

	public function test_resolver_props_items_and_fallbacks(): void {
		$this->seed_services();

		$resolved = $this->resolve_services(
			array(
				'heading' => 'Our services',
				'layout'  => 'carousel',
				'columns' => '9',
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'layout', 'columns', 'items', 'view_all_url' ), array_keys( $resolved['props'] ) );
		$this->assertNull( $resolved['props']['eyebrow'] );
		$this->assertSame( 'Our services', $resolved['props']['heading'] );
		$this->assertSame( 'grid', $resolved['props']['layout'], 'Unknown layout falls back to grid.' );
		$this->assertSame( '3', $resolved['props']['columns'], 'Unknown columns fall back to 3.' );
		$this->assertNull( $resolved['props']['view_all_url'] );

		$this->assertCount( 3, $resolved['props']['items'] );
		$this->assertSame( array( 'title', 'url', 'summary', 'icon' ), array_keys( $resolved['props']['items'][0] ) );
		$this->assertSame( 'Web Design', $resolved['props']['items'][0]['title'] );
		$this->assertSame( 'star', $resolved['props']['items'][0]['icon'] );
		$this->assertSame( '', $resolved['props']['items'][2]['icon'], 'A missing icon is an empty string.' );

		$this->assertSame( 'services', $resolved['section']['name'] );
		$this->assertTrue( $resolved['section']['has_heading'] );
	}

	public function test_resolver_skips_broken_rows_and_queues_the_schema_itself(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 5,
					'post_type'  => 'service',
					'post_title' => 'Web Design',
					'menu_order' => 1,
				),
				array(
					'ID'         => 6,
					'post_type'  => 'service',
					'post_title' => '   ',
					'menu_order' => 2,
				),
			)
		);

		$resolved = $this->resolve_services();

		$this->assertCount( 1, $resolved['props']['items'] );
		$this->assertStringContainsString( 'service 6 has an empty title', implode( ' ', $this->logs() ) );
		$this->assertCount( 1, salt_schema_queue(), 'Schema is logic: queued without the view ever running.' );
		$this->assertFalse( $resolved['section']['has_heading'], 'Heading-less section must not claim the H1 (A11Y01).' );
	}

	public function test_resolver_view_all_url_only_when_on_and_the_archive_exists(): void {
		$this->seed_services();

		$on = $this->resolve_services( array( 'view_all' => 1 ) );
		$this->assertIsString( $on['props']['view_all_url'] );
		$this->assertStringContainsString( '/services', $on['props']['view_all_url'] );

		$off = $this->resolve_services( array( 'view_all' => 0 ) );
		$this->assertNull( $off['props']['view_all_url'] );
	}

	public function test_view_draws_from_props_alone(): void {
		$item  = array(
			'title'   => 'Prop Service',
			'url'     => 'https://example.test/services/prop/',
			'summary' => 'From props.',
			'icon'    => '',
		);
		$props = array(
			'eyebrow'      => 'What we do',
			'heading'      => 'From props',
			'layout'       => 'featured',
			'columns'      => '2',
			'items'        => array( $item, array_merge( $item, array( 'title' => 'Second' ) ) ),
			'view_all_url' => 'https://example.test/services/',
		);

		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/services.php',
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
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ), 'Heading at the plan level.' );
		$this->assertStringContainsString( 'Prop Service', $html );
		$this->assertStringContainsString( 'Second', $html );
		$this->assertStringContainsString( 'x-services-featured', $html );
		$this->assertStringContainsString( 'sm:grid-cols-2', $html );
		$this->assertStringContainsString( 'What we do', $html );
		$this->assertStringContainsString( 'From props.', $html );
		$this->assertStringContainsString( 'https://example.test/services/', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );

		$props['view_all_url'] = null;
		$props['eyebrow']      = null;
		$props['heading']      = null;
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/services.php',
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
		$bare = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'View all services', $bare );
		$this->assertStringNotContainsString( '<h2', $bare );
	}
}
