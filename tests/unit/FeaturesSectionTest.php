<?php
/**
 * Feature / Benefits Grid slice unit tests: layout shape (DATA02), section
 * 0/1/N states (COMPL02), malformed items skipped + logged, escaping, and
 * the no-schema invariant (§10 #5 has no schema assignment).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class FeaturesSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Render ONE features section row.
	 *
	 * @param array<string,mixed> $fields  Row sub-field fixtures.
	 * @param array<string,mixed> $context Loop context.
	 * @return string
	 */
	private function render_features_section( array $fields = array(), array $context = array(
		'index'      => 0,
		'is_primary' => true,
	) ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'features',
					'fields' => $fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section( 'features', $context );
		return (string) ob_get_clean();
	}

	// -----------------------------------------------------------------
	// Layout shape (DATA02 / FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_file_shape_and_key_conventions(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/features.php';

		$this->assertSame( 'features', $layout['name'] );
		$this->assertSame( 'layout_salt_features', $layout['key'] );

		$names = array_map(
			static fn( array $field ): string => (string) ( $field['name'] ?? '' ),
			$layout['sub_fields']
		);

		// Content fields first…
		$this->assertSame( array( 'eyebrow', 'heading', 'columns', 'items' ), array_slice( array_values( array_filter( $names ) ), 0, 4 ) );
		// …then the shared §11 wrapper controls last.
		$this->assertContains( 'section_spacing', $names );
		$this->assertGreaterThan( array_search( 'items', $names, true ), array_search( 'section_spacing', $names, true ) );

		// Repeater sub-field keys follow field_salt_features_items_{name}.
		$items = null;
		foreach ( $layout['sub_fields'] as $field ) {
			if ( 'items' === ( $field['name'] ?? '' ) ) {
				$items = $field;
			}
		}
		$this->assertNotNull( $items );
		$this->assertSame( 'repeater', $items['type'] );
		foreach ( $items['sub_fields'] as $sub ) {
			$this->assertStringStartsWith( 'field_salt_features_items_', (string) $sub['key'] );
		}
		$this->assertSame(
			array( 'icon', 'title', 'text', 'link' ),
			array_map( static fn( array $sub ): string => (string) $sub['name'], $items['sub_fields'] )
		);
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_items_renders_nothing(): void {
		$this->assertSame( '', $this->render_features_section( array( 'heading' => 'Why us' ) ) );
		$this->assertSame( '', $this->render_features_section( array( 'items' => array() ) ) );
		$this->assertSame( '', $this->render_features_section( array( 'items' => 'not-an-array' ) ) );
	}

	public function test_single_item_is_a_valid_static_layout(): void {
		$html = $this->render_features_section(
			array(
				'heading' => 'Why us',
				'columns' => '3',
				'items'   => array(
					array(
						'icon'  => 'check',
						'title' => 'Fast',
						'text'  => 'Pages load quickly.',
					),
				),
			),
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);

		$this->assertStringContainsString( 'x-features-grid', $html );
		$this->assertSame( 1, preg_match_all( '/<article\b/', $html ) );
		$this->assertStringContainsString( 'Fast', $html );
		$this->assertStringContainsString( '<h1', $html, 'Primary section with heading claims the H1.' );
		$this->assertStringContainsString( '<h2', $html, 'Card title steps to level + 1.' );
		$this->assertStringNotContainsString( '<script', $html, 'No JS in this section — ever.' );
	}

	public function test_n_items_render_with_links_and_column_class(): void {
		$html = $this->render_features_section(
			array(
				'eyebrow' => 'Benefits',
				'heading' => 'Why us',
				'columns' => '4',
				'items'   => array(
					array(
						'icon'  => 'check',
						'title' => 'Fast',
						'text'  => 'Quick.',
						'link'  => array(
							'url'   => 'https://example.test/fast/',
							'title' => 'Fast',
						),
					),
					array(
						'title' => 'Accessible',
						'text'  => 'For everyone.',
					),
					array(
						'icon'  => 'star',
						'title' => 'Low carbon',
					),
				),
			)
		);

		$this->assertSame( 3, preg_match_all( '/<article\b/', $html ) );
		$this->assertStringContainsString( 'lg:grid-cols-4', $html );
		$this->assertStringContainsString( 'href="https://example.test/fast/"', $html );
		$this->assertStringContainsString( 'x-eyebrow', $html );
		$this->assertStringContainsString( 'x-icon', $html );
	}

	public function test_malformed_items_are_skipped_and_logged(): void {
		$html = $this->render_features_section(
			array(
				'heading' => 'Why us',
				'items'   => array(
					array( 'title' => 'Kept' ),
					array(
						'title' => '   ',
						'text'  => 'No title.',
					),
					'not-a-row',
					array( 'text' => 'Missing title entirely.' ),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<article\b/', $html ), 'Only the valid item renders.' );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'features section: item 1 has no title', $logs );
		$this->assertStringContainsString( 'features section: item 2 is not a row array', $logs );
		$this->assertStringContainsString( 'features section: item 3 has no title', $logs );
	}

	public function test_all_items_malformed_renders_nothing(): void {
		$html = $this->render_features_section(
			array(
				'heading' => 'Why us',
				'items'   => array( array( 'text' => 'no title' ) ),
			)
		);

		$this->assertSame( '', $html, 'Never a blank band (COMPL02).' );
		$this->assertNotSame( array(), $this->logs() );
	}

	// -----------------------------------------------------------------
	// Escaping + heading claim.
	// -----------------------------------------------------------------

	public function test_values_are_escaped_on_output(): void {
		$html = $this->render_features_section(
			array(
				'heading' => 'Safe <script>alert(1)</script>',
				'items'   => array(
					array(
						'title' => 'Title <em>markup</em>',
						'text'  => 'Text <script>alert(2)</script>',
						'link'  => array( 'url' => 'javascript:alert(3)' ),
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<em>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$html = $this->render_features_section(
			array(
				'items' => array( array( 'title' => 'Fast' ) ),
			),
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);

		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less primary section must not consume the H1 claim (A11Y01).' );
	}

	// -----------------------------------------------------------------
	// No schema for this section (§10 #5).
	// -----------------------------------------------------------------

	public function test_no_schema_nodes_are_queued(): void {
		salt_schema_reset();

		$graph_args = array(
			'home_url' => 'https://example.test',
			'page_url' => 'https://example.test/p',
		);
		$baseline   = count( salt_schema_graph( $graph_args )['@graph'] );

		$this->render_features_section(
			array(
				'heading' => 'Why us',
				'items'   => array( array( 'title' => 'Fast' ) ),
			)
		);

		$this->assertCount( $baseline, salt_schema_graph( $graph_args )['@graph'], 'Features section queues no schema nodes.' );
	}
}
