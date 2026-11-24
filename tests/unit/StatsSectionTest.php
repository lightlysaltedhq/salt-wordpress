<?php
/**
 * Stats / Metrics slice unit tests: layout shape (DATA02), section 0/1/N
 * states (COMPL02) in the contract's markup (markup/stats.json, stat.json),
 * malformed items skipped + logged, and the no-schema invariant.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class StatsSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Render ONE stats section row.
	 *
	 * @param array<string,mixed> $fields  Row sub-field fixtures.
	 * @param array<string,mixed> $context Loop context.
	 * @return string
	 */
	private function render_stats_section( array $fields = array(), array $context = array(
		'index'      => 0,
		'is_primary' => true,
	) ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'stats',
					'fields' => $fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section( 'stats', $context );
		return (string) ob_get_clean();
	}

	// -----------------------------------------------------------------
	// Layout shape (DATA02 / FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_is_generated_from_the_contract(): void {
		$layout = salt_test_layout( 'stats' );

		$this->assertSame( 'layout_salt_stats', $layout['key'] );
		$this->assertSame( array( 'heading', 'intro', 'columns', 'items', '', 'settings' ), array_column( $layout['sub_fields'], 'name' ) );

		$items = array_column( $layout['sub_fields'], null, 'name' )['items'];
		$this->assertSame( 'repeater', $items['type'] );
		foreach ( $items['sub_fields'] as $sub ) {
			$this->assertStringStartsWith( 'field_salt_stats_items_', (string) $sub['key'] );
		}
		$this->assertSame( array( 'icon', 'value', 'suffix', 'label' ), array_column( $items['sub_fields'], 'name' ) );
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_state_renders_nothing(): void {
		$this->assertSame( '', $this->render_stats_section( array( 'items' => array() ) ) );
		$this->assertSame( '', $this->render_stats_section( array( 'items' => 'not-an-array' ) ) );
		$this->assertSame(
			'',
			$this->render_stats_section(
				array(
					'heading' => ' ',
					'intro'   => '  ',
					'items'   => array(),
				)
			),
			'Heading, intro and items all empty (markup zeroState).'
		);
	}

	public function test_heading_without_items_renders_the_band_without_a_list(): void {
		$html = $this->render_stats_section( array( 'heading' => 'Numbers' ) );

		$this->assertStringStartsWith( '<section class="salt-section" id="stats" aria-labelledby="stats__heading"', $html );
		$this->assertStringContainsString( '<div class="salt-block" data-block="stats"><h1 id="stats__heading">Numbers</h1></div>', $html );
		$this->assertStringNotContainsString( 'salt-grid', $html, 'The list is drawn only when an item has a value.' );
	}

	public function test_intro_alone_is_drawn_in_a_div_band(): void {
		$html = $this->render_stats_section( array( 'intro' => 'Five years & counting.' ) );

		$this->assertStringStartsWith( '<div class="salt-section" id="stats"', $html, 'No heading: a div band (section#labelled-by).' );
		$this->assertStringNotContainsString( 'aria-labelledby', $html );
		$this->assertStringContainsString( '<div class="salt-block" data-block="stats"><p class="salt-intro">Five years &amp; counting.</p></div>', $html, 'The intro is drawn, escaped, as p.salt-intro.' );
	}

	public function test_single_item_is_a_valid_static_layout(): void {
		$html = $this->render_stats_section(
			array(
				'heading' => 'The numbers',
				'columns' => '3',
				'items'   => array(
					array(
						'value'  => '45',
						'suffix' => '%',
						'label'  => 'More conversions',
					),
				),
			)
		);

		$this->assertStringContainsString( '<ul class="salt-grid" role="list" data-columns="3"><li class="salt-stat"><p class="salt-stat__value">45<span class="salt-stat__suffix">%</span></p><p class="salt-stat__label">More conversions</p></li></ul>', $html, 'The value is text in p.salt-stat__value: no number span (stat.json).' );
		$this->assertStringNotContainsString( 'x-stat', $html );
		$this->assertStringContainsString( '<h1 id="stats__heading">The numbers</h1>', $html, 'Primary section with heading claims the H1.' );
		$this->assertSame( 1, preg_match_all( '/<h[1-6]/', $html ), 'A stat is never a heading (A11Y01).' );
		$this->assertStringNotContainsString( '<script', $html, 'No JS — the count-up animation is out.' );
	}

	public function test_n_items_render_in_the_selected_columns(): void {
		$html = $this->render_stats_section(
			array(
				'columns' => '4',
				'items'   => array(
					array(
						'icon'   => 'check',
						'value'  => '45',
						'suffix' => '%',
						'label'  => 'Conversions',
					),
					array(
						'value'  => '12',
						'suffix' => 'k',
						'label'  => 'Users',
					),
					array(
						'value'  => '99.9',
						'suffix' => '%',
						'label'  => 'Uptime',
					),
					array(
						'value' => '3',
						'label' => 'Offices',
					),
				),
			)
		);

		$this->assertSame( 4, preg_match_all( '/<li class="salt-stat">/', $html ) );
		$this->assertStringContainsString( '<ul class="salt-grid" role="list" data-columns="4">', $html );
		$this->assertSame( 1, preg_match_all( '/<svg\b/', $html ), 'An icon only on the stat that sets one.' );
		$this->assertStringContainsString( '<li class="salt-stat"><svg class="salt-icon" data-icon="check" data-size="lg"', $html, 'The icon, large, leads its stat.' );
		$this->assertStringContainsString( '<li class="salt-stat"><p class="salt-stat__value">3</p><p class="salt-stat__label">Offices</p></li>', $html, 'No suffix: no suffix span.' );
	}

	public function test_a_stat_without_a_label_draws_no_label(): void {
		$html = $this->render_stats_section( array( 'items' => array( array( 'value' => '7' ) ) ) );

		$this->assertStringContainsString( '<li class="salt-stat"><p class="salt-stat__value">7</p></li>', $html );
	}

	public function test_malformed_items_are_skipped_and_logged(): void {
		$html = $this->render_stats_section(
			array(
				'heading' => 'Numbers',
				'items'   => array(
					array(
						'value' => '45',
						'label' => 'Kept',
					),
					array(
						'value' => '  ',
						'label' => 'Blank number',
					),
					'not-a-row',
					array( 'label' => 'Missing number' ),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<li class="salt-stat">/', $html ), 'Only the valid item renders.' );
		$this->assertStringContainsString( '<p class="salt-stat__label">Kept</p>', $html );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'stats section: item 1 has no value', $logs );
		$this->assertStringContainsString( 'stats section: item 2 is not a row array', $logs );
		$this->assertStringContainsString( 'stats section: item 3 has no value', $logs );
	}

	public function test_all_items_malformed_and_nothing_else_renders_nothing(): void {
		$html = $this->render_stats_section(
			array( 'items' => array( array( 'label' => 'no number' ) ) )
		);

		$this->assertSame( '', $html, 'Never a blank band (COMPL02).' );
		$this->assertStringContainsString( 'stats section: item 0 has no value', implode( ' ', $this->logs() ) );
	}

	public function test_all_items_malformed_under_a_heading_renders_the_band_without_a_list(): void {
		$html = $this->render_stats_section(
			array(
				'heading' => 'Numbers',
				'items'   => array( array( 'label' => 'no number' ) ),
			)
		);

		$this->assertStringContainsString( '<h1 id="stats__heading">Numbers</h1></div>', $html );
		$this->assertStringNotContainsString( '<ul', $html, 'No blank stat and no empty list (COMPL02).' );
		$this->assertStringContainsString( 'stats section: item 0 has no value', implode( ' ', $this->logs() ) );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$html = $this->render_stats_section(
			array( 'items' => array( array( 'value' => '45' ) ) ),
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);

		$this->assertStringStartsWith( '<div class="salt-section" id="stats"', $html, 'No heading: a div band, not a section.' );
		$this->assertStringNotContainsString( 'aria-labelledby', $html );
		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less primary section must not consume the H1 claim (A11Y01).' );
	}

	// -----------------------------------------------------------------
	// No schema for this section (§10 #7).
	// -----------------------------------------------------------------

	public function test_no_schema_nodes_are_queued(): void {
		salt_schema_reset();

		$graph_args = array(
			'home_url' => 'https://example.test',
			'page_url' => 'https://example.test/p',
		);
		$baseline   = count( salt_schema_graph( $graph_args )['@graph'] );

		$this->render_stats_section(
			array(
				'heading' => 'Numbers',
				'items'   => array( array( 'value' => '45' ) ),
			)
		);

		$this->assertCount( $baseline, salt_schema_graph( $graph_args )['@graph'], 'Stats section queues no schema nodes.' );
	}

	// -----------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one stats FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields  Row fields.
	 * @param array<string,mixed> $context Loop context.
	 * @return array<string,mixed>|null
	 */
	private function resolve_stats( array $fields, array $context = array(
		'index'      => 0,
		'is_primary' => false,
	) ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'stats',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'stats' );
		$this->assertSame( 'salt_resolve_section_stats', $resolver );

		return $resolver( $context );
	}

	public function test_resolver_returns_null_only_in_the_zero_state(): void {
		$this->assertNull( $this->resolve_stats( array() ) );
		$this->assertNull( $this->resolve_stats( array( 'items' => array( array( 'value' => ' ' ), 'not-a-row' ) ) ) );

		$heading_only = $this->resolve_stats( array( 'heading' => 'Numbers' ) );
		$this->assertSame( array(), $heading_only['props']['items'], 'A heading alone is drawn: items may be empty.' );
		$this->assertTrue( $heading_only['section']['has_heading'] );
		$this->assertSame( 'Lead in', $this->resolve_stats( array( 'intro' => 'Lead in' ) )['props']['intro'] );
	}

	public function test_resolver_props_use_the_contract_names(): void {
		$resolved = $this->resolve_stats(
			array(
				'heading' => 'The numbers',
				'intro'   => 'Since 2020.',
				'columns' => '4',
				'items'   => array(
					array(
						'icon'   => ' star ',
						'value'  => ' 45 ',
						'suffix' => '%',
						'label'  => 'Conversions',
					),
					array( 'value' => '3' ),
				),
			)
		);

		$this->assertSame( array( 'heading', 'intro', 'columns', 'items' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'The numbers', $resolved['props']['heading'] );
		$this->assertSame( 'Since 2020.', $resolved['props']['intro'] );
		$this->assertSame( '4', $resolved['props']['columns'] );
		$this->assertSame(
			array(
				array(
					'icon'   => 'star',
					'value'  => '45',
					'suffix' => '%',
					'label'  => 'Conversions',
				),
				array(
					'icon'   => null,
					'value'  => '3',
					'suffix' => null,
					'label'  => null,
				),
			),
			$resolved['props']['items']
		);
	}

	public function test_resolver_defaults_an_unknown_column_count_and_owns_the_wrapper(): void {
		$resolved = $this->resolve_stats(
			array(
				'columns'  => '9',
				'settings' => array(
					'spacing' => 'lg',
				),
				'items'    => array( array( 'value' => '1' ) ),
			),
			array(
				'index'      => 3,
				'is_primary' => true,
			)
		);

		$this->assertSame( '3', $resolved['props']['columns'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertNull( $resolved['props']['intro'] );
		$this->assertSame( 'stats', $resolved['section']['name'] );
		$this->assertTrue( $resolved['section']['contract'], 'Drawn in the contract\'s wrapper (M4).' );
		$this->assertSame( 'lg', $resolved['section']['spacing'] );
		$this->assertFalse( $resolved['section']['has_heading'] );
	}

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/stats.php',
			false,
			array(
				'props' => array(
					'heading' => 'From props',
					'intro'   => 'Intro from props',
					'columns' => '2',
					'items'   => array(
						array(
							'icon'   => null,
							'value'  => '77',
							'suffix' => '%',
							'label'  => 'Drawn',
						),
					),
				),
				'plan'  => array(
					'index'         => 0,
					'anchor'        => 'numbers',
					'headingLevel'  => 2,
					'headingId'     => 'numbers__heading',
					'bodyBaseLevel' => 2,
					'priorityMedia' => false,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame(
			'<div class="salt-block" data-block="stats"><h2 id="numbers__heading">From props</h2><p class="salt-intro">Intro from props</p>'
			. '<ul class="salt-grid" role="list" data-columns="2"><li class="salt-stat"><p class="salt-stat__value">77<span class="salt-stat__suffix">%</span></p><p class="salt-stat__label">Drawn</p></li></ul></div>',
			$html,
			'The block only: the band is the entry\'s, not the view\'s.'
		);
	}
}
