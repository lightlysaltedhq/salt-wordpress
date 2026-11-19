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

	public function test_layout_is_generated_from_the_contract(): void {
		$layout = salt_test_layout( 'call-to-action' );

		$this->assertSame( 'layout_salt_call_to_action', $layout['key'] );
		$this->assertSame( array( 'heading', 'body', 'buttons', '', 'settings' ), array_column( $layout['sub_fields'], 'name' ) );

		$by_name = array_column( $layout['sub_fields'], null, 'name' );
		$this->assertSame( 'wysiwyg', $by_name['body']['type'], 'The contract body is rich text.' );
		$this->assertSame( 2, $by_name['buttons']['max'], 'Buttons list caps at 2.' );
		$this->assertSame( array( 'link', 'style' ), array_column( $by_name['buttons']['sub_fields'], 'name' ) );

		// The CTA band defaults to the brand tint in the admin UI too.
		$tone = array_column( $by_name['settings']['sub_fields'], null, 'name' )['tone'];
		$this->assertSame( 'brand-tint', $tone['default_value'] );
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_empty_row_renders_nothing(): void {
		$this->assertSame( '', $this->render_cta_section() );
		$this->assertSame( '', $this->render_cta_section( array( 'heading' => '   ' ) ) );
		$this->assertSame(
			'',
			$this->render_cta_section( array( 'buttons' => array( salt_test_button( 'No URL', '' ) ) ) ),
			'A row whose only content is malformed buttons renders nothing (COMPL02).'
		);
	}

	public function test_single_button_is_a_valid_static_layout(): void {
		$html = $this->render_cta_section(
			array(
				'heading' => 'Ready to start?',
				'body'    => 'Tell us about your project.',
				'buttons' => array(
					salt_test_button( 'Get in touch', 'https://example.test/contact/', 'primary' ),
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
					salt_test_button( 'Primary', 'https://example.test/a/', 'primary' ),
					salt_test_button( 'Secondary', 'https://example.test/b/', 'secondary' ),
				),
			)
		);

		$this->assertSame( 2, preg_match_all( '/<a\b/', $html ) );
		$this->assertStringContainsString( 'x-button-primary', $html );
		$this->assertStringContainsString( 'x-button-secondary', $html );
	}

	public function test_malformed_buttons_are_skipped_but_usable_overflow_buttons_render(): void {
		$html = $this->render_cta_section(
			array(
				'heading' => 'Ready?',
				'buttons' => array(
					salt_test_button( 'Kept', 'https://example.test/1/' ),
					'not-a-row',
					salt_test_button( 'No URL', '' ),
					salt_test_button( 'Also kept', 'https://example.test/2/', 'wobbly' ),
					salt_test_button( 'Third', 'https://example.test/3/' ),
				),
			)
		);

		$this->assertSame( 3, preg_match_all( '/<a\b/', $html ) );
		$this->assertStringContainsString( 'Third', $html );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'call-to-action section: button 1 has no label or no address', $logs );
		$this->assertStringContainsString( 'call-to-action section: button 2 has no label or no address', $logs );
		$this->assertStringContainsString( 'unknown style "wobbly"', $logs );
		$this->assertStringContainsString( 'rendered in full', $logs );
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
				'heading'  => 'Ready?',
				'settings' => array( 'tone' => 'surface-inverse' ),
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
				'body'    => '<p>Copy <em>markup</em><script>alert(3)</script></p>',
				'buttons' => array(
					salt_test_button( 'Go <b>now</b>', 'javascript:alert(2)' ),
				),
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '<em>markup</em>', $html, 'The body is rich text: allowed markup is kept.' );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$html = $this->render_cta_section(
			array(
				'buttons' => array(
					salt_test_button( 'Go', 'https://example.test/' ),
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
					salt_test_button( 'Go', 'https://example.test/' ),
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
						salt_test_button( 'No url', '' ),
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
					salt_test_button( 'Go', '/go', 'secondary' ),
					salt_test_button( 'Odd', '/odd', 'bogus' ),
				),
			)
		);

		$this->assertSame( array( 'heading', 'body', 'buttons' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'Ready?', $resolved['props']['heading'] );
		$this->assertSame( 'Talk to us.', $resolved['props']['body'] );
		$this->assertSame(
			array(
				array(
					'label'  => 'Go',
					'href'   => '/go',
					'style'  => 'secondary',
					'newTab' => false,
				),
				array(
					'label'  => 'Odd',
					'href'   => '/odd',
					'style'  => 'primary',
					'newTab' => false,
				),
			),
			$resolved['props']['buttons']
		);
	}

	public function test_resolver_nulls_blank_text_and_keeps_buttons_only_sections(): void {
		$resolved = $this->resolve_cta(
			array(
				'buttons' => array(
					salt_test_button( 'Go', '/go' ),
				),
			)
		);

		$this->assertNull( $resolved['props']['heading'] );
		$this->assertNull( $resolved['props']['body'] );
		$this->assertFalse( $resolved['section']['has_heading'] );
	}

	public function test_resolver_owns_the_wrapper_inputs_and_the_brand_tint_default(): void {
		$resolved = $this->resolve_cta( array( 'heading' => 'Hi' ) );
		$this->assertSame( 'call-to-action', $resolved['section']['name'] );
		$this->assertSame( 'brand-tint', $resolved['section']['background'] );
		$this->assertTrue( $resolved['section']['has_heading'] );

		$resolved = $this->resolve_cta(
			array(
				'heading'  => 'Hi',
				'settings' => array( 'tone' => 'surface-alt' ),
			)
		);
		$this->assertSame( 'surface-alt', $resolved['section']['background'] );
	}

	public function test_resolver_keeps_buttons_beyond_two(): void {
		$buttons = array();
		foreach ( array( 'a', 'b', 'c' ) as $key ) {
			$buttons[] = salt_test_button( $key, '/' . $key );
		}
		$resolved = $this->resolve_cta( array( 'buttons' => $buttons ) );

		$this->assertCount( 3, $resolved['props']['buttons'] );
	}

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/call-to-action.php',
			false,
			array(
				'props' => array(
					'heading' => 'From props',
					'body'    => '<p>Copy <b>x</b></p>',
					'buttons' => array(
						array(
							'label'  => 'Press',
							'href'   => '/pressed',
							'style'  => 'ghost',
							'newTab' => true,
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
		$this->assertStringContainsString( '<p>Copy <b>x</b></p>', $html, 'body arrives sanitised; the view prints it.' );
		$this->assertStringContainsString( 'target="_blank"', $html );
		$this->assertStringContainsString( 'href="/pressed"', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );
	}
	public function test_body_keeps_only_headings_three_and_four(): void {
		$resolved = $this->resolve_cta(
			array(
				'heading' => 'Ready?',
				'body'    => '<h2>Two</h2><h3>Three</h3><h4>Four</h4><h5>Five</h5>',
			)
		);
		$body     = $resolved['props']['body'];

		$this->assertStringContainsString( '<h3>Three</h3><h4>Four</h4>', $body );
		$this->assertStringNotContainsString( '<h2', $body );
		$this->assertStringNotContainsString( '<h5', $body );
		$this->assertStringContainsString( 'Two', $body, 'Stripped to its text.' );
	}
}
