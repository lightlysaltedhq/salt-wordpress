<?php
/**
 * Process / Steps slice unit tests: the process-step component contract
 * (Appendix B), layout shape (DATA02), section 0/1/N states (COMPL02),
 * <ol> semantics with position-derived numbers, malformed rows skipped +
 * logged, and the OPT-IN HowTo schema (default OFF — AEO not SEO, §15).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class ProcessSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Render ONE process section row.
	 *
	 * @param array<string,mixed> $fields  Row sub-field fixtures.
	 * @param array<string,mixed> $context Loop context.
	 * @return string
	 */
	private function render_process_section( array $fields = array(), array $context = array(
		'index'      => 0,
		'is_primary' => true,
	) ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'process',
					'fields' => $fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section( 'process', $context );
		return (string) ob_get_clean();
	}

	/**
	 * The current schema @graph for the fixture page.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function graph(): array {
		return salt_schema_graph(
			array(
				'home_url' => 'https://example.test',
				'page_url' => 'https://example.test/p',
			)
		)['@graph'];
	}

	/**
	 * Three valid step fixtures.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function three_steps(): array {
		return array(
			array(
				'title' => 'Discover',
				'text'  => 'We learn about your goals.',
				'icon'  => 'check',
			),
			array(
				'title' => 'Design',
				'text'  => 'We shape the solution.',
			),
			array(
				'title' => 'Deliver',
				'text'  => 'We ship and iterate.',
			),
		);
	}

	// -----------------------------------------------------------------
	// The process-step component (Appendix B).
	// -----------------------------------------------------------------

	public function test_process_step_component_renders_number_title_and_text(): void {
		$html = salt_get_component(
			'process-step',
			array(
				'number' => '2',
				'title'  => 'Design',
				'text'   => 'We shape the solution.',
				'level'  => 3,
			)
		);

		$this->assertStringContainsString( '<span class="salt-process__number" aria-hidden="true">2</span>', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html, 'Visible number is decorative — the <ol> carries semantics (A11Y01).' );
		$this->assertSame( 1, preg_match_all( '/<h3\b/', $html ), 'Title renders at the passed level.' );
		$this->assertStringContainsString( 'Design', $html );
		$this->assertStringContainsString( 'We shape the solution.', $html );
	}

	public function test_process_step_component_escapes_values(): void {
		$html = salt_get_component(
			'process-step',
			array(
				'number' => '1',
				'title'  => '<script>1</script>',
				'text'   => '<em>text</em>',
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<em>', $html );
	}

	public function test_process_step_component_omits_empty_text_and_icon(): void {
		$html = salt_get_component(
			'process-step',
			array(
				'number' => '1',
				'title'  => 'Discover',
			)
		);

		$this->assertSame( '<li class="salt-process__step"><span class="salt-process__number" aria-hidden="true">1</span><h3>Discover</h3></li>', $html );
	}

	public function test_process_step_component_with_blank_title_renders_nothing(): void {
		$this->assertSame(
			'',
			trim(
				salt_get_component(
					'process-step',
					array(
						'number' => '1',
						'title'  => '   ',
					)
				)
			)
		);
	}

	// -----------------------------------------------------------------
	// Layout shape (DATA02 / FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_is_generated_from_the_contract(): void {
		$layout = salt_test_layout( 'process' );

		$this->assertSame( 'layout_salt_process', $layout['key'] );
		$this->assertSame(
			array( 'eyebrow', 'heading', 'intro', 'layout', 'steps', 'structuredData', '', 'settings' ),
			array_column( $layout['sub_fields'], 'name' )
		);

		$fields = array_column( $layout['sub_fields'], null, 'name' );
		$this->assertSame( 'repeater', $fields['steps']['type'] );
		$this->assertSame( array( 'icon', 'image', 'title', 'text', 'cta' ), array_column( $fields['steps']['sub_fields'], 'name' ) );
		foreach ( $fields['steps']['sub_fields'] as $sub ) {
			$this->assertStringStartsWith( 'field_salt_process_steps_', (string) $sub['key'] );
		}
		$this->assertSame( 'true_false', $fields['structuredData']['type'] );
		$this->assertSame( 0, $fields['structuredData']['default_value'], 'HowTo schema is OPT-IN — default OFF (§15).' );
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_steps_renders_nothing(): void {
		// markup/process.json zeroState: eyebrow, heading, intro and steps all empty.
		$this->assertSame( '', $this->render_process_section( array( 'steps' => array() ) ) );
		$this->assertSame( '', $this->render_process_section( array( 'steps' => 'not-an-array' ) ) );
	}

	public function test_single_step_is_a_valid_static_layout(): void {
		$html = $this->render_process_section(
			array(
				'heading' => 'How we work',
				'steps'   => array(
					array(
						'title' => 'Discover',
						'text'  => 'We learn about your goals.',
					),
				),
			)
		);

		$this->assertStringContainsString( '<ol', $html );
		$this->assertSame( 1, preg_match_all( '/<li\b/', $html ) );
		$this->assertStringContainsString( '<h1', $html, 'Primary section with heading claims the H1.' );
		$this->assertStringNotContainsString( '<script', $html, 'No JS in the process section.' );
	}

	public function test_n_steps_render_as_an_ordered_list_with_position_numbers(): void {
		$html = $this->render_process_section(
			array(
				'heading' => 'How we work',
				'steps'   => $this->three_steps(),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<ol\b/', $html ), 'Ordered-list semantics.' );
		$this->assertSame( 3, preg_match_all( '/<li\b/', $html ) );
		$this->assertSame( 3, preg_match_all( '/salt-process__number/', $html ) );
		// Numbers derive from position, in order.
		$this->assertMatchesRegularExpression( '/>1<\/span>.*>2<\/span>.*>3<\/span>/s', $html );
		// Step titles step to level + 1 (section heading is h1 here).
		$this->assertSame( 3, preg_match_all( '/<h2\b/', $html ) );
	}

	public function test_timeline_is_the_default_and_cards_switches_the_grid(): void {
		$timeline = $this->render_process_section(
			array(
				'steps'  => $this->three_steps(),
				'layout' => 'unknown-value',
			)
		);
		$this->assertStringContainsString( '<ol class="salt-grid salt-process" role="list" data-layout="timeline">', $timeline );

		$cards = $this->render_process_section(
			array(
				'steps'  => $this->three_steps(),
				'layout' => 'cards',
			)
		);
		$this->assertStringContainsString( 'data-layout="cards"', $cards );
		$this->assertStringNotContainsString( 'data-layout="timeline"', $cards );
	}

	public function test_malformed_steps_are_skipped_and_logged(): void {
		$html = $this->render_process_section(
			array(
				'heading' => 'How we work',
				'steps'   => array(
					array( 'title' => 'Kept' ),
					array(
						'title' => '   ',
						'text'  => 'Blank title',
					),
					'not-a-row',
					array( 'text' => 'Missing title' ),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<li\b/', $html ), 'Only the valid step renders.' );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'process section: step 1 has no title', $logs );
		$this->assertStringContainsString( 'process section: step 2 is not a row array', $logs );
		$this->assertStringContainsString( 'process section: step 3 has no title', $logs );
	}

	public function test_all_steps_malformed_renders_nothing(): void {
		$html = $this->render_process_section(
			array( 'steps' => array( array( 'text' => 'no title' ) ) )
		);

		$this->assertSame( '', $html, 'Never a blank band (COMPL02).' );
		$this->assertNotSame( array(), $this->logs() );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$html = $this->render_process_section(
			array( 'steps' => array( array( 'title' => 'Discover' ) ) )
		);

		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less primary section must not consume the H1 claim (A11Y01).' );
	}

	// -----------------------------------------------------------------
	// HowTo schema — OPT-IN, default OFF (§15).
	// -----------------------------------------------------------------

	public function test_no_schema_node_is_queued_by_default(): void {
		salt_schema_reset();
		$baseline = count( $this->graph() );

		$this->render_process_section(
			array(
				'heading' => 'How we work',
				'steps'   => $this->three_steps(),
			)
		);

		$this->assertCount( $baseline, $this->graph(), 'structuredData defaults OFF — no HowTo node.' );
	}

	public function test_toggled_on_queues_one_howto_node_with_positioned_steps(): void {
		salt_schema_reset();

		$this->render_process_section(
			array(
				'heading'        => 'How we work',
				'structuredData' => 1,
				'steps'          => $this->three_steps(),
			)
		);

		$howto = null;
		foreach ( $this->graph() as $node ) {
			if ( 'HowTo' === ( $node['@type'] ?? '' ) ) {
				$howto = $node;
			}
		}

		$this->assertNotNull( $howto, 'Exactly one HowTo node is queued when toggled on.' );
		$this->assertSame( 'How we work', $howto['name'] );
		$this->assertCount( 3, $howto['step'] );
		$this->assertSame(
			array( 1, 2, 3 ),
			array_map( static fn( array $step ): int => (int) $step['position'], $howto['step'] )
		);
		$this->assertSame( 'HowToStep', $howto['step'][0]['@type'] );
		$this->assertSame( 'Discover', $howto['step'][0]['name'] );
		$this->assertSame( 'We learn about your goals.', $howto['step'][0]['text'] );
	}

	public function test_howto_step_text_falls_back_to_the_title_and_skipped_rows_are_excluded(): void {
		salt_schema_reset();

		$this->render_process_section(
			array(
				'heading'        => 'How we work',
				'structuredData' => 1,
				'steps'          => array(
					array( 'title' => 'Only title' ),
					array( 'text' => 'broken — no title' ),
				),
			)
		);

		$howto = null;
		foreach ( $this->graph() as $node ) {
			if ( 'HowTo' === ( $node['@type'] ?? '' ) ) {
				$howto = $node;
			}
		}

		$this->assertNotNull( $howto );
		$this->assertCount( 1, $howto['step'], 'Skipped rows never reach the schema node.' );
		$this->assertSame( 'Only title', $howto['step'][0]['text'], 'Empty text falls back to the step title.' );
	}

	// -----------------------------------------------------------------
	// Logic/view split (ID-3587): resolver and view.
	// -----------------------------------------------------------------

	/**
	 * Seed one process FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return array<string,mixed>|null
	 */
	private function resolve_process( array $fields ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'process',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'process' );
		$this->assertSame( 'salt_resolve_section_process', $resolver );

		return $resolver(
			array(
				'index'      => 1,
				'is_primary' => true,
			)
		);
	}

	public function test_resolver_returns_null_and_queues_nothing_without_valid_steps(): void {
		$this->assertNull(
			$this->resolve_process(
				array(
					'steps'          => array( 'not a row', array( 'title' => '  ' ) ),
					'structuredData' => true,
				)
			)
		);
		$this->assertSame( array(), salt_schema_queue(), 'Schema is queued only on a path that returns props.' );

		// A heading with no steps draws the heading alone, and queues no HowTo.
		$resolved = $this->resolve_process(
			array(
				'heading'        => 'How it works',
				'steps'          => array( array( 'title' => '  ' ) ),
				'structuredData' => true,
			)
		);
		$this->assertSame( array(), $resolved['props']['steps'] );
		$this->assertSame( array(), salt_schema_queue(), 'No steps: no HowTo.' );
	}

	public function test_resolver_props_use_the_contract_names(): void {
		$resolved = $this->resolve_process(
			array(
				'eyebrow'        => 'Steps',
				'heading'        => ' How it works ',
				'layout'         => 'cards',
				'steps'          => array(
					array(
						'title' => ' Plan ',
						'text'  => ' We talk. ',
						'icon'  => ' check ',
					),
					array( 'title' => 'Build' ),
				),
				'structuredData' => true,
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'intro', 'layout', 'steps', 'priorityStep', 'arrow', 'structuredData' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'Steps', $resolved['props']['eyebrow'] );
		$this->assertSame( ' How it works ', $resolved['props']['heading'], 'Optional text is never trimmed.' );
		$this->assertSame( 'cards', $resolved['props']['layout'] );
		$this->assertSame(
			array(
				array(
					'title' => 'Plan',
					'text'  => 'We talk.',
					'icon'  => 'check',
					'image' => null,
					'cta'   => null,
				),
				array(
					'title' => 'Build',
					'text'  => null,
					'icon'  => null,
					'image' => null,
					'cta'   => null,
				),
			),
			$resolved['props']['steps']
		);
		$this->assertTrue( $resolved['props']['structuredData'] );
		$this->assertCount( 1, salt_schema_queue(), 'The HowTo node is queued by the resolver.' );

		$this->assertSame( 'process', $resolved['section']['name'] );
		$this->assertTrue( $resolved['section']['is_primary'] );
		$this->assertTrue( $resolved['section']['has_heading'] );
	}

	public function test_resolver_defaults_layout_and_schema_off(): void {
		$resolved = $this->resolve_process(
			array(
				'layout' => 'zigzag',
				'steps'  => array( array( 'title' => 'Only' ) ),
			)
		);

		$this->assertNull( $resolved['props']['eyebrow'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertSame( 'timeline', $resolved['props']['layout'] );
		$this->assertFalse( $resolved['props']['structuredData'] );
		$this->assertFalse( $resolved['section']['has_heading'] );
		$this->assertSame( array(), salt_schema_queue() );
	}

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/process.php',
			false,
			array(
				'props' => array(
					'eyebrow'        => null,
					'heading'        => 'From props',
					'intro'          => null,
					'layout'         => 'cards',
					'steps'          => array(
						array(
							'title' => 'First',
							'text'  => 'One',
							'icon'  => null,
							'image' => null,
							'cta'   => array(
								'label'  => 'Book',
								'href'   => '/book/',
								'style'  => 'secondary',
								'newTab' => false,
							),
						),
						array(
							'title' => 'Second',
							'text'  => null,
							'icon'  => null,
							'image' => null,
							'cta'   => null,
						),
					),
					'priorityStep'   => null,
					'arrow'          => null,
					'structuredData' => true,
				),
				'plan'  => array(
					'index'         => 0,
					'anchor'        => 'steps',
					'headingLevel'  => 1,
					'headingId'     => 'steps__heading',
					'bodyBaseLevel' => 1,
					'priorityMedia' => false,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertSame( 2, preg_match_all( '/<h2[\s>]/', $html ), 'Step titles one level below the heading.' );
		$this->assertStringContainsString( '<h1 id="steps__heading">From props</h1>', $html );
		$this->assertStringContainsString( 'data-layout="cards"', $html );
		$this->assertSame( 2, substr_count( $html, '<li class="salt-process__step">' ) );
		$this->assertStringContainsString( '<a class="salt-button" href="/book/" data-style="secondary" data-track-control="cta">Book</a>', $html );
		$this->assertStringNotContainsString( 'salt-section', $html, 'The band is the entry\'s, not the view\'s.' );
		$this->assertSame( array(), salt_schema_queue(), 'The view never queues schema.' );
	}
}
