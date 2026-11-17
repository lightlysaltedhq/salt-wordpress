<?php
/**
 * Call to Action slice unit tests: layout shape (DATA02), section 0/1/N
 * states (COMPL02), malformed/overflow buttons skipped + logged, the
 * brand-tint background default, escaping, and the no-schema invariant
 * (§10 #10 has no schema assignment; newsletter variant deferred).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class CallToActionSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Render ONE call-to-action section row.
	 *
	 * @param array<string,mixed> $fields  Row sub-field fixtures.
	 * @param array<string,mixed> $context Loop context.
	 * @return string
	 */
	private function render_cta_section( array $fields = array(), array $context = array(
		'index'      => 0,
		'is_primary' => true,
	) ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'call-to-action',
					'fields' => $fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section( 'call-to-action', $context );
		return (string) ob_get_clean();
	}

	// -----------------------------------------------------------------
	// Layout shape (DATA02 / FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_file_shape_and_key_conventions(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/call-to-action.php';

		$this->assertSame( 'call-to-action', $layout['name'] );
		$this->assertSame( 'layout_salt_call_to_action', $layout['key'] );

		$names = array_map(
			static fn( array $field ): string => (string) ( $field['name'] ?? '' ),
			$layout['sub_fields']
		);

		// Content fields first…
		$this->assertSame( array( 'heading', 'body', 'buttons' ), array_slice( array_values( array_filter( $names ) ), 0, 3 ) );
		// …then the shared §11 wrapper controls last.
		$this->assertContains( 'spacing', $names );
		$this->assertGreaterThan( array_search( 'buttons', $names, true ), array_search( 'spacing', $names, true ) );

		$buttons    = null;
		$background = null;
		foreach ( $layout['sub_fields'] as $field ) {
			if ( 'buttons' === ( $field['name'] ?? '' ) ) {
				$buttons = $field;
			}
			if ( 'tone' === ( $field['name'] ?? '' ) ) {
				$background = $field;
			}
		}

		$this->assertNotNull( $buttons );
		$this->assertSame( 'repeater', $buttons['type'] );
		$this->assertSame( 2, $buttons['max'], 'Buttons repeater caps at 2.' );
		foreach ( $buttons['sub_fields'] as $sub ) {
			$this->assertStringStartsWith( 'field_salt_call_to_action_buttons_', (string) $sub['key'] );
		}
		$this->assertSame(
			array( 'label', 'url', 'style' ),
			array_map( static fn( array $sub ): string => (string) $sub['name'], $buttons['sub_fields'] )
		);

		// The CTA band defaults to the brand tint in the admin UI too.
		$this->assertNotNull( $background );
		$this->assertSame( 'brand-tint', $background['default_value'] );

		// Newsletter variant DEFERRED: no email/embed field may ship (PRIV01).
		$this->assertNotContains( 'variant', $names );
		$this->assertNotContains( 'newsletter_embed', $names );
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_empty_row_renders_nothing(): void {
		$this->assertSame( '', $this->render_cta_section() );
		$this->assertSame( '', $this->render_cta_section( array( 'heading' => '   ' ) ) );
		$this->assertSame(
			'',
			$this->render_cta_section( array( 'buttons' => array( array( 'label' => 'No URL' ) ) ) ),
			'A row whose only content is malformed buttons renders nothing (COMPL02).'
		);
	}

	public function test_single_button_is_a_valid_static_layout(): void {
		$html = $this->render_cta_section(
			array(
				'heading' => 'Ready to start?',
				'body'    => 'Tell us about your project.',
				'buttons' => array(
					array(
						'label' => 'Get in touch',
						'url'   => 'https://example.test/contact/',
						'style' => 'primary',
					),
				),
			)
		);

		$this->assertStringContainsString( 'x-cta', $html );
		$this->assertSame( 1, preg_match_all( '/<a\b/', $html ) );
		$this->assertStringContainsString( 'href="https://example.test/contact/"', $html );
		$this->assertStringContainsString( '<h1', $html, 'Primary section with heading claims the H1.' );
		$this->assertStringNotContainsString( '<script', $html, 'No JS in this section — ever.' );
		$this->assertStringNotContainsString( '<input', $html, 'Newsletter variant is deferred: no email input may ship (PRIV01).' );
	}

	public function test_two_buttons_render_with_styles(): void {
		$html = $this->render_cta_section(
			array(
				'heading' => 'Ready?',
				'buttons' => array(
					array(
						'label' => 'Primary',
						'url'   => 'https://example.test/a/',
						'style' => 'primary',
					),
					array(
						'label' => 'Secondary',
						'url'   => 'https://example.test/b/',
						'style' => 'secondary',
					),
				),
			)
		);

		$this->assertSame( 2, preg_match_all( '/<a\b/', $html ) );
		$this->assertStringContainsString( 'x-button-primary', $html );
		$this->assertStringContainsString( 'x-button-secondary', $html );
	}

	public function test_malformed_and_overflow_buttons_are_skipped_and_logged(): void {
		$html = $this->render_cta_section(
			array(
				'heading' => 'Ready?',
				'buttons' => array(
					array(
						'label' => 'Kept',
						'url'   => 'https://example.test/1/',
					),
					'not-a-row',
					array( 'label' => 'No URL' ),
					array(
						'label' => 'Also kept',
						'url'   => 'https://example.test/2/',
						'style' => 'wobbly',
					),
					array(
						'label' => 'Third — dropped',
						'url'   => 'https://example.test/3/',
					),
				),
			)
		);

		$this->assertSame( 2, preg_match_all( '/<a\b/', $html ), 'Max 2 valid buttons render.' );
		$this->assertStringNotContainsString( 'Third', $html );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'call-to-action section: button 1 is not a row array', $logs );
		$this->assertStringContainsString( 'call-to-action section: button 2 is missing a label or URL', $logs );
		$this->assertStringContainsString( 'unknown style "wobbly"', $logs );
		$this->assertStringContainsString( 'only the first 2 render', $logs );
	}

	// -----------------------------------------------------------------
	// Background default (assignment: brand-tint).
	// -----------------------------------------------------------------

	public function test_background_defaults_to_brand_tint(): void {
		$html = $this->render_cta_section(
			array(
				'heading' => 'Ready?',
			)
		);

		$this->assertStringContainsString( 'bg-brand-tint', $html );
	}

	public function test_editor_chosen_background_wins(): void {
		$html = $this->render_cta_section(
			array(
				'heading' => 'Ready?',
				'tone'    => 'surface-inverse',
			)
		);

		$this->assertStringNotContainsString( 'bg-brand-tint', $html );
		$this->assertStringContainsString( 'bg-surface-inverse text-ink-inverse', $html, 'The contract tone surface-inverse is x-section\'s inverse band.' );
	}

	// -----------------------------------------------------------------
	// Escaping + heading claim.
	// -----------------------------------------------------------------

	public function test_values_are_escaped_on_output(): void {
		$html = $this->render_cta_section(
			array(
				'heading' => 'Safe <script>alert(1)</script>',
				'body'    => 'Copy <em>markup</em>',
				'buttons' => array(
					array(
						'label' => 'Go <b>now</b>',
						'url'   => 'javascript:alert(2)',
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<em>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$html = $this->render_cta_section(
			array(
				'buttons' => array(
					array(
						'label' => 'Go',
						'url'   => 'https://example.test/',
					),
				),
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
	// No schema for this section (§10 #10).
	// -----------------------------------------------------------------

	public function test_no_schema_nodes_are_queued(): void {
		salt_schema_reset();

		$graph_args = array(
			'home_url' => 'https://example.test',
			'page_url' => 'https://example.test/p',
		);
		$baseline   = count( salt_schema_graph( $graph_args )['@graph'] );

		$this->render_cta_section(
			array(
				'heading' => 'Ready?',
				'buttons' => array(
					array(
						'label' => 'Go',
						'url'   => 'https://example.test/',
					),
				),
			)
		);

		$this->assertCount( $baseline, salt_schema_graph( $graph_args )['@graph'], 'CTA section queues no schema nodes.' );
	}

	// -----------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one call-to-action FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields  Row fields.
	 * @param array<string,mixed> $context Loop context.
	 * @return array<string,mixed>|null
	 */
	private function resolve_cta( array $fields, array $context = array(
		'index'      => 0,
		'is_primary' => false,
	) ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'call-to-action',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'call-to-action' );
		$this->assertSame( 'salt_resolve_section_call_to_action', $resolver );

		return $resolver( $context );
	}

	public function test_resolver_returns_null_for_the_zero_state(): void {
		$this->assertNull(
			$this->resolve_cta(
				array(
					'heading' => '  ',
					'body'    => "\n",
					'buttons' => array(
						array(
							'label' => 'No url',
							'url'   => '',
						),
					),
				)
			)
		);
	}

	public function test_resolver_props_use_the_contract_names(): void {
		$resolved = $this->resolve_cta(
			array(
				'heading' => ' Ready? ',
				'body'    => 'Talk to us.',
				'buttons' => array(
					array(
						'label' => 'Go',
						'url'   => '/go',
						'style' => 'secondary',
					),
					array(
						'label' => 'Odd',
						'url'   => '/odd',
						'style' => 'bogus',
					),
				),
			)
		);

		$this->assertSame( array( 'heading', 'body_text', 'buttons' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'Ready?', $resolved['props']['heading'] );
		$this->assertSame( 'Talk to us.', $resolved['props']['body_text'] );
		$this->assertSame(
			array(
				array(
					'label' => 'Go',
					'href'  => '/go',
					'style' => 'secondary',
				),
				array(
					'label' => 'Odd',
					'href'  => '/odd',
					'style' => 'primary',
				),
			),
			$resolved['props']['buttons']
		);
	}

	public function test_resolver_nulls_blank_text_and_keeps_buttons_only_sections(): void {
		$resolved = $this->resolve_cta(
			array(
				'buttons' => array(
					array(
						'label' => 'Go',
						'url'   => '/go',
					),
				),
			)
		);

		$this->assertNull( $resolved['props']['heading'] );
		$this->assertNull( $resolved['props']['body_text'] );
		$this->assertFalse( $resolved['section']['has_heading'] );
	}

	public function test_resolver_owns_the_wrapper_inputs_and_the_brand_tint_default(): void {
		$resolved = $this->resolve_cta( array( 'heading' => 'Hi' ) );
		$this->assertSame( 'call-to-action', $resolved['section']['name'] );
		$this->assertSame( 'brand-tint', $resolved['section']['background'] );
		$this->assertTrue( $resolved['section']['has_heading'] );

		$resolved = $this->resolve_cta(
			array(
				'heading' => 'Hi',
				'tone'    => 'surface-alt',
			)
		);
		$this->assertSame( 'surface-alt', $resolved['section']['background'] );
	}

	public function test_resolver_drops_buttons_beyond_two(): void {
		$buttons = array();
		foreach ( array( 'a', 'b', 'c' ) as $key ) {
			$buttons[] = array(
				'label' => $key,
				'url'   => '/' . $key,
			);
		}
		$resolved = $this->resolve_cta( array( 'buttons' => $buttons ) );

		$this->assertCount( 2, $resolved['props']['buttons'] );
	}

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/call-to-action.php',
			false,
			array(
				'props' => array(
					'heading'   => 'From props',
					'body_text' => 'Copy <b>x</b>',
					'buttons'   => array(
						array(
							'label' => 'Press',
							'href'  => '/pressed',
							'style' => 'ghost',
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
		$this->assertStringContainsString( 'From props', $html );
		$this->assertStringContainsString( 'Copy &lt;b&gt;x&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'href="/pressed"', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );
	}
}
