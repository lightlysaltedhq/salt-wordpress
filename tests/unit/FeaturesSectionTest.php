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
		$this->assertContains( 'spacing', $names );
		$this->assertGreaterThan( array_search( 'items', $names, true ), array_search( 'spacing', $names, true ) );

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

	// -----------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one features FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields  Row fields.
	 * @param array<string,mixed> $context Loop context.
	 * @return array<string,mixed>|null
	 */
	private function resolve_features( array $fields, array $context = array(
		'index'      => 0,
		'is_primary' => false,
	) ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'features',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'features' );
		$this->assertSame( 'salt_resolve_section_features', $resolver );

		return $resolver( $context );
	}

	public function test_resolver_returns_null_without_valid_items(): void {
		$this->assertNull( $this->resolve_features( array( 'heading' => 'Why us' ) ) );
		$this->assertNull(
			$this->resolve_features(
				array(
					'items' => array(
						array( 'text' => 'No title' ),
						'not-a-row',
					),
				)
			)
		);
	}

	public function test_resolver_props_use_the_contract_names(): void {
		$resolved = $this->resolve_features(
			array(
				'eyebrow' => 'Benefits',
				'heading' => 'Why us',
				'columns' => '4',
				'items'   => array(
					array(
						'icon'  => 'star',
						'title' => ' Fast ',
						'text'  => ' Really fast. ',
						'link'  => array( 'url' => '/fast' ),
					),
					array( 'title' => 'Bare' ),
				),
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'columns', 'items' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'Benefits', $resolved['props']['eyebrow'] );
		$this->assertSame( 'Why us', $resolved['props']['heading'] );
		$this->assertSame( '4', $resolved['props']['columns'] );
		$this->assertSame(
			array(
				array(
					'icon'      => 'star',
					'title'     => 'Fast',
					'body_text' => 'Really fast.',
					'href'      => '/fast',
				),
				array(
					'icon'      => null,
					'title'     => 'Bare',
					'body_text' => null,
					'href'      => null,
				),
			),
			$resolved['props']['items']
		);
	}

	public function test_resolver_nulls_blank_text_defaults_columns_and_owns_the_wrapper(): void {
		$resolved = $this->resolve_features(
			array(
				'eyebrow' => '  ',
				'columns' => '7',
				'spacing' => 'lg',
				'items'   => array( array( 'title' => 'One' ) ),
			)
		);

		$this->assertNull( $resolved['props']['eyebrow'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertSame( '3', $resolved['props']['columns'] );
		$this->assertSame( 'features', $resolved['section']['name'] );
		$this->assertSame( 'lg', $resolved['section']['spacing'] );
		$this->assertFalse( $resolved['section']['has_heading'] );
	}

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/features.php',
			false,
			array(
				'props' => array(
					'eyebrow' => 'Eyebrow from props',
					'heading' => 'From props',
					'columns' => '2',
					'items'   => array(
						array(
							'icon'      => null,
							'title'     => 'Card title',
							'body_text' => 'Card copy',
							'href'      => '/card',
						),
					),
				),
				'plan'  => array(
					'index'         => 0,
					'headingLevel'  => 2,
					'priorityMedia' => false,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ) );
		$this->assertSame( 1, preg_match_all( '/<h3[\s>]/', $html ), 'Card titles step one level below the heading.' );
		$this->assertStringContainsString( 'Eyebrow from props', $html );
		$this->assertStringContainsString( 'Card copy', $html );
		$this->assertStringContainsString( 'href="/card"', $html );
		$this->assertStringContainsString( 'sm:grid-cols-2', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );
	}
}
