<?php
/**
 * Rich Text section unit tests (spec §10 #3) — the logic/view split
 * exemplar (ID-3587): the resolver's 0-state, props and wrapper inputs,
 * and a view that draws from props alone.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/acf.php';

final class RichTextSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Seed one rich-text FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields  Row fields.
	 * @param array<string,mixed> $context Loop context.
	 * @return array<string,mixed>|null
	 */
	private function resolve_rich_text( array $fields, array $context = array(
		'index'      => 0,
		'is_primary' => false,
	) ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'rich-text',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'rich-text' );
		$this->assertSame( 'salt_resolve_section_rich_text', $resolver );

		return $resolver( $context );
	}

	/**
	 * Seed one rich-text FC row and render it through the loop entry.
	 *
	 * @param array<string,mixed> $fields Row fields.
	 * @return string
	 */
	private function render_rich_text( array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'rich-text',
					'fields' => $fields,
				),
			)
		);
		the_row();

		ob_start();
		salt_render_section(
			'rich-text',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	// -----------------------------------------------------------------
	// Resolver.
	// -----------------------------------------------------------------

	/**
	 * Rows with nothing to draw: the resolver returns null (COMPL02).
	 *
	 * @return array<string,array{0:array<string,mixed>}>
	 */
	public static function zero_state_rows(): array {
		return array(
			'no fields'       => array( array() ),
			'whitespace only' => array(
				array(
					'heading' => '   ',
					'body'    => "\n",
				),
			),
			'wrapper fields'  => array( array( 'tone' => 'surface-alt' ) ),
			'empty paragraph' => array( array( 'body' => '<p>&nbsp;</p>' ) ),
		);
	}

	/**
	 * @param array<string,mixed> $fields Row fields.
	 */
	#[DataProvider( 'zero_state_rows' )]
	public function test_resolver_returns_null_for_the_zero_state( array $fields ): void {
		$this->assertNull( $this->resolve_rich_text( $fields ) );
		$this->assertSame( '', $this->render_rich_text( $fields ), 'Null renders nothing — no wrapper band.' );
	}

	public function test_resolver_props_use_the_contract_names(): void {
		$resolved = $this->resolve_rich_text(
			array(
				'heading' => 'About us',
				'body'    => '<p>Copy with <strong>bold</strong>.</p>',
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'body', 'alignment' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'About us', $resolved['props']['heading'] );
		$this->assertSame( 'left', $resolved['props']['alignment'] );
		$this->assertStringContainsString( '<strong>bold</strong>', $resolved['props']['body'] );
	}

	public function test_resolver_sanitises_the_body(): void {
		$resolved = $this->resolve_rich_text(
			array(
				'body' => '<p class="evil">Text</p><script>alert(1)</script><h1>Smuggled</h1>',
			)
		);

		$this->assertNull( $resolved['props']['heading'] );
		$this->assertIsString( $resolved['props']['body'] );
		$this->assertStringNotContainsString( '<script', $resolved['props']['body'] );
		$this->assertStringNotContainsString( 'class="evil"', $resolved['props']['body'] );
		$this->assertStringNotContainsString( '<h1>', $resolved['props']['body'], 'h1 is forbidden in prose (§16a).' );
	}

	public function test_resolver_heading_only_has_a_null_body(): void {
		$resolved = $this->resolve_rich_text( array( 'heading' => 'Just a heading' ) );

		$this->assertNull( $resolved['props']['body'] );
		$this->assertTrue( $resolved['section']['has_heading'] );
	}

	public function test_resolver_owns_the_wrapper_inputs(): void {
		$resolved = $this->resolve_rich_text(
			array(
				'body'     => '<p>Body only.</p>',
				'settings' => array(
					'spacing'  => 'lg',
					'tone'     => 'surface-alt',
					'anchorId' => 'About',
				),
			),
			array(
				'index'      => 2,
				'is_primary' => true,
			)
		);

		$this->assertSame( 'rich-text', $resolved['section']['name'] );
		$this->assertTrue( $resolved['section']['contract'], 'Drawn in the contract\'s wrapper (M4).' );
		$this->assertSame( 'lg', $resolved['section']['spacing'] );
		$this->assertSame( 'surface-alt', $resolved['section']['tone'] );
		$this->assertSame( 'About', $resolved['section']['anchor'], 'Raw anchor: the wrapper settles it (EDIT02).' );
		$this->assertTrue( $resolved['section']['is_primary'] );
		$this->assertFalse( $resolved['section']['has_heading'], 'Heading-less section must not claim the H1 (A11Y01).' );
	}

	// -----------------------------------------------------------------
	// Rendered through the entry.
	// -----------------------------------------------------------------

	public function test_primary_section_heading_claims_the_h1_inside_the_wrapper(): void {
		$html = $this->render_rich_text(
			array(
				'heading' => 'Welcome',
				'body'    => '<p>Hello.</p>',
			)
		);

		$this->assertStringStartsWith( '<section class="salt-section" id="rich-text" aria-labelledby="rich-text__heading"', $html );
		$this->assertStringContainsString( '<h1 id="rich-text__heading">Welcome</h1>', $html );
		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertStringContainsString( '<div class="salt-rich-text"><p>Hello.</p></div>', $html );
	}

	// -----------------------------------------------------------------
	// View.
	// -----------------------------------------------------------------

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/rich-text.php',
			false,
			array(
				'props' => array(
					'eyebrow'   => null,
					'heading'   => 'From props',
					'body'      => '<p>Already sanitised.</p>',
					'alignment' => 'centre',
				),
				'plan'  => array(
					'index'         => 0,
					'anchor'        => 'about',
					'headingLevel'  => 2,
					'headingId'     => 'about__heading',
					'bodyBaseLevel' => 2,
					'priorityMedia' => false,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame( '<div class="salt-block" data-block="rich-text" data-align="centre"><h2 id="about__heading">From props</h2><div class="salt-rich-text"><p>Already sanitised.</p></div></div>', $html, 'The block only: the band is the entry\'s, not the view\'s.' );
	}
	public function test_resolver_keeps_headings_two_to_six(): void {
		$resolved = $this->resolve_rich_text( array( 'body' => '<h2>Two</h2><h5>Five</h5><h6>Six</h6>' ) );

		$this->assertSame( '<h2>Two</h2><h5>Five</h5><h6>Six</h6>', $resolved['props']['body'], 'The rich-text body alone allows heading-2 to heading-6 (rc.7, ID-3950).' );
	}
}
