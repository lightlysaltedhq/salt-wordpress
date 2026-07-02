<?php
/**
 * Stats / Metrics slice unit tests: x-stat component contract (Appendix B —
 * a <p> pair, never a heading), layout shape (DATA02), section 0/1/N states
 * (COMPL02), malformed items skipped + logged, and the no-schema invariant.
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
	// x-stat component (Appendix B).
	// -----------------------------------------------------------------

	public function test_stat_component_renders_a_p_pair_not_a_heading(): void {
		$html = salt_get_component(
			'stat',
			array(
				'number' => '45',
				'suffix' => '%',
				'label'  => 'More conversions',
			)
		);

		$this->assertStringContainsString( '<span class="x-stat-number">45</span>', $html );
		$this->assertStringContainsString( '<span class="x-stat-suffix">%</span>', $html );
		$this->assertStringContainsString( 'More conversions', $html );
		$this->assertSame( 2, preg_match_all( '/<p\b/', $html ), 'Number + label are a <p> pair.' );
		$this->assertSame( 0, preg_match_all( '/<h[1-6]/', $html ), 'A stat is NEVER a heading (A11Y01).' );
	}

	public function test_stat_component_omits_empty_suffix_and_label(): void {
		$html = salt_get_component( 'stat', array( 'number' => '12' ) );

		$this->assertStringNotContainsString( 'x-stat-suffix', $html );
		$this->assertStringNotContainsString( 'x-stat-label', $html );
	}

	public function test_stat_component_escapes_values(): void {
		$html = salt_get_component(
			'stat',
			array(
				'number' => '<script>1</script>',
				'label'  => '<em>label</em>',
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<em>', $html );
	}

	public function test_stat_component_with_blank_number_renders_nothing(): void {
		$this->assertSame( '', trim( salt_get_component( 'stat', array( 'number' => '   ' ) ) ) );
	}

	// -----------------------------------------------------------------
	// Layout shape (DATA02 / FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_file_shape_and_key_conventions(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/stats.php';

		$this->assertSame( 'stats', $layout['name'] );
		$this->assertSame( 'layout_salt_stats', $layout['key'] );

		$names = array_map(
			static fn( array $field ): string => (string) ( $field['name'] ?? '' ),
			$layout['sub_fields']
		);

		// Content fields first, wrapper controls last.
		$this->assertSame( array( 'heading', 'columns', 'items' ), array_slice( array_values( array_filter( $names ) ), 0, 3 ) );
		$this->assertGreaterThan( array_search( 'items', $names, true ), array_search( 'section_spacing', $names, true ) );

		$items = null;
		foreach ( $layout['sub_fields'] as $field ) {
			if ( 'items' === ( $field['name'] ?? '' ) ) {
				$items = $field;
			}
		}
		$this->assertNotNull( $items );
		$this->assertSame( 'repeater', $items['type'] );
		foreach ( $items['sub_fields'] as $sub ) {
			$this->assertStringStartsWith( 'field_salt_stats_items_', (string) $sub['key'] );
		}
		$this->assertSame(
			array( 'number', 'suffix', 'label' ),
			array_map( static fn( array $sub ): string => (string) $sub['name'], $items['sub_fields'] )
		);
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_items_renders_nothing(): void {
		$this->assertSame( '', $this->render_stats_section( array( 'heading' => 'Numbers' ) ) );
		$this->assertSame( '', $this->render_stats_section( array( 'items' => array() ) ) );
		$this->assertSame( '', $this->render_stats_section( array( 'items' => 'not-an-array' ) ) );
	}

	public function test_single_item_is_a_valid_static_layout(): void {
		$html = $this->render_stats_section(
			array(
				'heading' => 'The numbers',
				'columns' => '3',
				'items'   => array(
					array(
						'number' => '45',
						'suffix' => '%',
						'label'  => 'More conversions',
					),
				),
			)
		);

		$this->assertStringContainsString( 'x-stats-grid', $html );
		$this->assertSame( 1, preg_match_all( '/x-stat-value/', $html ) );
		$this->assertStringContainsString( '<h1', $html, 'Primary section with heading claims the H1.' );
		$this->assertStringNotContainsString( '<script', $html, 'No JS — the count-up animation is out.' );
	}

	public function test_n_items_render_in_the_selected_columns(): void {
		$html = $this->render_stats_section(
			array(
				'columns' => '4',
				'items'   => array(
					array(
						'number' => '45',
						'suffix' => '%',
						'label'  => 'Conversions',
					),
					array(
						'number' => '12',
						'suffix' => 'k',
						'label'  => 'Users',
					),
					array(
						'number' => '99.9',
						'suffix' => '%',
						'label'  => 'Uptime',
					),
					array(
						'number' => '3',
						'label'  => 'Offices',
					),
				),
			)
		);

		$this->assertSame( 4, preg_match_all( '/x-stat-value/', $html ) );
		$this->assertStringContainsString( 'lg:grid-cols-4', $html );
	}

	public function test_malformed_items_are_skipped_and_logged(): void {
		$html = $this->render_stats_section(
			array(
				'heading' => 'Numbers',
				'items'   => array(
					array(
						'number' => '45',
						'label'  => 'Kept',
					),
					array(
						'number' => '  ',
						'label'  => 'Blank number',
					),
					'not-a-row',
					array( 'label' => 'Missing number' ),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/x-stat-value/', $html ), 'Only the valid item renders.' );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'stats section: item 1 has no number', $logs );
		$this->assertStringContainsString( 'stats section: item 2 is not a row array', $logs );
		$this->assertStringContainsString( 'stats section: item 3 has no number', $logs );
	}

	public function test_all_items_malformed_renders_nothing(): void {
		$html = $this->render_stats_section(
			array( 'items' => array( array( 'label' => 'no number' ) ) )
		);

		$this->assertSame( '', $html, 'Never a blank band (COMPL02).' );
		$this->assertNotSame( array(), $this->logs() );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$html = $this->render_stats_section(
			array( 'items' => array( array( 'number' => '45' ) ) ),
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);

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
				'items'   => array( array( 'number' => '45' ) ),
			)
		);

		$this->assertCount( $baseline, salt_schema_graph( $graph_args )['@graph'], 'Stats section queues no schema nodes.' );
	}
}
