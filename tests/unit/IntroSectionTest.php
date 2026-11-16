<?php
/**
 * Intro / Lead section unit tests (spec §10 #2).
 *
 * Covers the COMPL02 states (heading + content empty renders nothing —
 * an eyebrow alone is not a section), WYSIWYG sanitisation through
 * salt_sanitize_html(), alignment handling, H1 claiming (A11Y01) and the
 * FLEET07/DATA02 layout contract shape.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class IntroSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Seed one intro FC row and render the section.
	 *
	 * @param array<string,mixed> $fields     Row fields.
	 * @param bool                $is_primary Loop context flag.
	 * @return string
	 */
	private function render_intro( array $fields = array(), bool $is_primary = false ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'intro',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'intro',
			array(
				'index'      => 0,
				'is_primary' => $is_primary,
			)
		);
		return (string) ob_get_clean();
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_state_renders_nothing(): void {
		$this->assertSame( '', $this->render_intro() );
	}

	public function test_eyebrow_alone_renders_nothing(): void {
		$this->assertSame( '', $this->render_intro( array( 'eyebrow' => 'About us' ) ) );
	}

	public function test_content_only_renders_without_claiming_the_h1(): void {
		$html = $this->render_intro(
			array( 'content' => '<p>Lead paragraph.</p>' ),
			true
		);

		$this->assertStringContainsString( 'Lead paragraph.', $html );
		$this->assertSame( 0, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less intro leaves the H1 claim for the page fallback (A11Y01).' );
	}

	public function test_full_intro_renders_eyebrow_heading_and_content(): void {
		$html = $this->render_intro(
			array(
				'eyebrow' => 'About us',
				'heading' => 'Who we are',
				'content' => '<p>We are a small studio.</p>',
			)
		);

		$this->assertStringContainsString( 'About us', $html );
		$this->assertStringContainsString( 'Who we are', $html );
		$this->assertStringContainsString( 'We are a small studio.', $html );
		$this->assertStringContainsString( 'data-section="intro"', $html );
		$this->assertStringContainsString( 'max-w-prose', $html, 'Narrow reading measure.' );
		$this->assertStringNotContainsString( '<script', $html );
	}

	// -----------------------------------------------------------------
	// Sanitisation (§16a).
	// -----------------------------------------------------------------

	public function test_wysiwyg_content_is_constrained_by_the_allow_list(): void {
		$html = $this->render_intro(
			array(
				'heading' => 'Safe',
				'content' => '<p style="color:red">Text</p><script>alert(1)</script>',
			)
		);

		$this->assertStringContainsString( 'Text', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'style="color:red"', $html );
	}

	// -----------------------------------------------------------------
	// Alignment.
	// -----------------------------------------------------------------

	public function test_center_align_centres_the_measure_and_text(): void {
		$html = $this->render_intro(
			array(
				'heading' => 'Centred',
				'align'   => 'center',
			)
		);

		$this->assertStringContainsString( 'mx-auto text-center', $html );
	}

	public function test_left_align_is_the_default(): void {
		$html = $this->render_intro( array( 'heading' => 'Left' ) );

		$this->assertStringNotContainsString( 'text-center', $html );
	}

	public function test_unknown_align_falls_back_to_left_and_logs(): void {
		$html = $this->render_intro(
			array(
				'heading' => 'Odd',
				'align'   => 'justified',
			)
		);

		$this->assertStringNotContainsString( 'text-center', $html );
		$this->assertStringContainsString( 'unknown align "justified"', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Headings (A11Y01).
	// -----------------------------------------------------------------

	public function test_primary_intro_heading_claims_the_h1(): void {
		$html = $this->render_intro( array( 'heading' => 'Who we are' ), true );

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertTrue( salt_h1_claimed() );
	}

	// -----------------------------------------------------------------
	// Layout contract (FLEET07 / DATA02).
	// -----------------------------------------------------------------

	public function test_layout_file_returns_the_frozen_contract_shape(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/intro.php';

		$this->assertSame( 'intro', $layout['name'] );
		$this->assertSame( 'layout_salt_intro', $layout['key'] );

		$names = array_values( array_filter( array_column( $layout['sub_fields'], 'name' ) ) );

		// Content fields first…
		$this->assertSame( array( 'eyebrow', 'heading', 'content', 'align' ), array_slice( $names, 0, 4 ) );
		// …then the §11 wrapper fields last.
		$this->assertSame( 'section_divider', end( $names ) );

		$by_name = array_column( $layout['sub_fields'], null, 'name' );
		$this->assertSame( 'wysiwyg', $by_name['content']['type'] );
		$this->assertSame( array( 'left', 'center' ), array_keys( $by_name['align']['choices'] ) );

		foreach ( array_filter( array_column( $layout['sub_fields'], 'key' ) ) as $key ) {
			$this->assertStringStartsWith( 'field_salt_intro_', (string) $key, 'DATA02 key convention.' );
		}
	}

	public function test_zero_state_queues_no_schema_nodes(): void {
		$this->render_intro();

		$this->assertSame( array(), salt_schema_queue() );
	}

	// -----------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one intro FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields  Row fields.
	 * @param array<string,mixed> $context Loop context.
	 * @return array<string,mixed>|null
	 */
	private function resolve_intro( array $fields, array $context = array(
		'index'      => 0,
		'is_primary' => false,
	) ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'intro',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'intro' );
		$this->assertSame( 'salt_resolve_section_intro', $resolver );

		return $resolver( $context );
	}

	public function test_resolver_returns_null_for_the_zero_state(): void {
		$this->assertNull( $this->resolve_intro( array() ) );
		$this->assertNull( $this->resolve_intro( array( 'eyebrow' => 'Only an eyebrow' ) ) );
		$this->assertNull(
			$this->resolve_intro(
				array(
					'heading' => '  ',
					'content' => "\n",
				)
			)
		);
	}

	public function test_resolver_props_are_local_snake_case_with_shared_names(): void {
		$resolved = $this->resolve_intro(
			array(
				'eyebrow' => 'Hello',
				'heading' => 'Welcome',
				'content' => '<p>Lead <strong>copy</strong>.</p>',
				'align'   => 'center',
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'content', 'align' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'Hello', $resolved['props']['eyebrow'] );
		$this->assertSame( 'Welcome', $resolved['props']['heading'] );
		$this->assertStringContainsString( '<strong>copy</strong>', $resolved['props']['content'] );
		$this->assertSame( 'center', $resolved['props']['align'] );
	}

	public function test_resolver_sanitises_content_nulls_blanks_and_defaults_align(): void {
		$resolved = $this->resolve_intro(
			array(
				'content' => '<p class="evil">Text</p><script>alert(1)</script>',
				'align'   => 'diagonal',
			)
		);

		$this->assertNull( $resolved['props']['eyebrow'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertStringNotContainsString( '<script', $resolved['props']['content'] );
		$this->assertStringNotContainsString( 'class="evil"', $resolved['props']['content'] );
		$this->assertSame( 'left', $resolved['props']['align'] );
		$this->assertStringContainsString( 'unknown align "diagonal"', implode( ' ', $this->logs() ) );

		$resolved = $this->resolve_intro( array( 'heading' => 'Heading only' ) );
		$this->assertNull( $resolved['props']['content'] );
		$this->assertTrue( $resolved['section']['has_heading'] );
	}

	public function test_resolver_owns_the_wrapper_inputs(): void {
		$resolved = $this->resolve_intro(
			array(
				'content'         => '<p>Body only.</p>',
				'section_spacing' => 'lg',
			),
			array(
				'index'      => 2,
				'is_primary' => true,
			)
		);

		$this->assertSame( 'intro', $resolved['section']['name'] );
		$this->assertSame( 'lg', $resolved['section']['spacing'] );
		$this->assertTrue( $resolved['section']['is_primary'] );
		$this->assertFalse( $resolved['section']['has_heading'], 'Heading-less section must not claim the H1 (A11Y01).' );
	}

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/intro.php',
			false,
			array(
				'props' => array(
					'eyebrow' => 'Eyebrow from props',
					'heading' => 'From props',
					'content' => '<p>Already sanitised.</p>',
					'align'   => 'center',
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
		$this->assertStringContainsString( 'Eyebrow from props', $html );
		$this->assertStringContainsString( 'x-intro max-w-prose mx-auto text-center', $html );
		$this->assertStringContainsString( '<div class="x-prose text-lg text-ink"><p>Already sanitised.</p></div>', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );
	}
}
