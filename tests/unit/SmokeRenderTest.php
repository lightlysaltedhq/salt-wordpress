<?php
/**
 * Gate-1 smoke test: a composed page renders with a correct heading
 * outline and zero third-party loads (BUILD-BRIEF §5 Gate 1; A11Y01,
 * PRIV01 invariant).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class SmokeRenderTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Render the section loop template with the current fixtures.
	 *
	 * @return string
	 */
	private function render_flexible_content(): string {
		ob_start();
		get_template_part( 'template-parts/flexible-content' );
		return (string) ob_get_clean();
	}

	public function test_composed_page_has_exactly_one_h1_and_stepped_headings(): void {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'rich-text',
					'fields' => array(
						'heading' => 'Welcome to the smoke page',
						'body'    => '<p>Intro copy with <strong>bold</strong>.</p>',
					),
				),
				array(
					'layout' => 'rich-text',
					'fields' => array(
						'heading'  => 'Second section',
						'body'     => '<p>More copy.</p>',
						'settings' => array(
							'spacing' => 'lg',
						),
					),
				),
			)
		);

		$html = $this->render_flexible_content();

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ), 'Exactly one H1 (A11Y01).' );
		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ), 'Second section heading steps to h2.' );
		$this->assertStringContainsString( 'Welcome to the smoke page', $html );
		$this->assertLessThan(
			strpos( $html, '<h2' ),
			strpos( $html, '<h1' ),
			'H1 precedes h2 — outline has no skips.'
		);
	}

	public function test_page_with_headingless_sections_still_emits_an_h1(): void {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'rich-text',
					'fields' => array( 'body' => '<p>No heading here.</p>' ),
				),
			)
		);
		$GLOBALS['salt_test_title'] = 'Fallback title';

		$html = $this->render_flexible_content();

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ), 'Zero-H1 pages are impossible (A11Y01).' );
		$this->assertStringContainsString( 'visually-hidden', $html );
		$this->assertStringContainsString( 'Fallback title', $html );
	}

	public function test_empty_page_renders_title_h1_only(): void {
		$html = $this->render_flexible_content();

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ) );
	}

	public function test_wysiwyg_content_is_constrained_by_the_allow_list(): void {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'rich-text',
					'fields' => array(
						'heading' => 'Safe content',
						'body'    => '<p style="color:red" class="evil">Text</p><script>alert(1)</script><h2>Smuggled</h2>',
					),
				),
			)
		);

		$html = $this->render_flexible_content();

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'style="color:red"', $html );
		$this->assertStringNotContainsString( 'class="evil"', $html );
		$this->assertStringNotContainsString( '<h2>Smuggled</h2>', $html, 'h2 is forbidden in prose (§16a).' );
	}

	public function test_unknown_layout_degrades_to_fallback_not_blank_fatal(): void {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'retired_section',
					'fields' => array(),
				),
			)
		);

		$html = $this->render_flexible_content();

		$this->assertStringContainsString( 'salt: section unavailable', $html );
		$this->assertStringContainsString( 'retired-section', implode( ' ', $this->logs() ) );
	}

	public function test_layout_name_removed_in_6_takes_the_fallback(): void {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'rich_text',
					'fields' => array( 'content' => '<p>Saved before 6.0.0.</p>' ),
				),
				array(
					'layout' => 'blog_teaser',
					'fields' => array(),
				),
			)
		);

		$html = $this->render_flexible_content();
		$logs = implode( ' ', $this->logs() );

		$this->assertStringContainsString( 'salt: section unavailable', $html, 'A legacy rich_text row is not resolved as an empty rich-text section.' );
		$this->assertStringContainsString( 'section layout "rich_text" has no template', $logs );
		$this->assertStringNotContainsString( '"blog_teaser" has no template', $logs, 'Layouts still registered with underscores keep mapping to their template.' );
	}

	public function test_no_third_party_origin_anywhere_in_rendered_output(): void {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'rich-text',
					'fields' => array(
						'heading' => 'Privacy check',
						'body'    => '<p><a href="https://example.test/ok">internal</a></p>',
					),
				),
			)
		);

		$html = $this->render_flexible_content();

		preg_match_all( '#https?://([a-z0-9.-]+)#i', $html, $matches );
		$foreign = array_filter(
			$matches[1],
			static fn( string $host ): bool => 'example.test' !== $host
		);

		$this->assertSame( array(), array_values( $foreign ), 'PRIV01: no third-party origin may appear while salt_consent() is a fail-closed stub.' );
	}
}
