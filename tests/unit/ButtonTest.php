<?php
/**
 * x-button behavioural logic: target validation, rel hardening, URL
 * neutralisation, style fallback (FAIL01, SEC08).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

final class ButtonTest extends SaltTestCase {

	/**
	 * Render x-button with fixture args merged over a valid baseline.
	 *
	 * @param array<string,mixed> $overrides Arg overrides.
	 * @return string
	 */
	private function render_button( array $overrides = array() ): string {
		return salt_get_component(
			'button',
			array_merge(
				array(
					'label' => 'Fixture label',
					'url'   => 'https://example.com/path',
				),
				$overrides
			)
		);
	}

	public function test_blank_target_gets_noopener_noreferrer(): void {
		$html = $this->render_button( array( 'target' => '_blank' ) );

		$this->assertStringContainsString( 'target="_blank"', $html );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
	}

	public function test_empty_target_emits_no_target_or_rel(): void {
		$html = $this->render_button();

		$this->assertStringNotContainsString( 'target=', $html );
		$this->assertStringNotContainsString( 'rel=', $html );
	}

	public function test_invalid_target_is_dropped_and_logged(): void {
		$html = $this->render_button( array( 'target' => '_self' ) );

		$this->assertStringNotContainsString( 'target=', $html );
		$this->assertStringNotContainsString( 'rel=', $html );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( '_self', $this->logs()[0] );
	}

	public function test_javascript_url_is_neutralised_by_esc_url(): void {
		$html = $this->render_button( array( 'url' => 'javascript:alert(1)' ) );

		$this->assertStringContainsString( 'href=""', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_unknown_style_falls_back_to_primary_and_logs(): void {
		$html = $this->render_button( array( 'style' => 'danger' ) );

		$this->assertStringContainsString( 'x-button-primary', $html );
		$this->assertStringContainsString( 'bg-brand text-on-brand', $html );
		$this->assertNotEmpty( $this->logs() );
		$this->assertStringContainsString( 'danger', $this->logs()[0] );
	}

	public function test_link_arrow_appends_aria_hidden_glyph(): void {
		$html = $this->render_button( array( 'style' => 'link-arrow' ) );

		$this->assertStringContainsString( '<span aria-hidden="true">&#8594;</span>', $html );
	}

	public function test_label_is_escaped(): void {
		$html = $this->render_button( array( 'label' => 'A <b>bold</b> & "quoted" label' ) );

		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( 'A &lt;b&gt;bold&lt;/b&gt; &amp;', $html );
	}
}
