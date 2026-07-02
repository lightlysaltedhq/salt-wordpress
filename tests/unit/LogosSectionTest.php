<?php
/**
 * Logo / Trust Strip slice unit tests: layout shape (DATA02), section
 * 0/1/N states (COMPL02), broken-row skipping + logging (DATA05), alt
 * precedence (name feeds alt only when the attachment alt is empty),
 * greyscale toggle and logo links.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';

final class LogosSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();
	}

	/**
	 * Seed one resolvable attachment fixture.
	 *
	 * @param int    $id  Attachment ID.
	 * @param string $alt Stored attachment alt.
	 * @return void
	 */
	private function seed_attachment( int $id, string $alt = '' ): void {
		$GLOBALS['salt_test_attachments'][ $id ] = array(
			'src'    => 'https://example.test/logo-' . $id . '.png',
			'width'  => 240,
			'height' => 80,
			'srcset' => '',
			'alt'    => $alt,
		);
	}

	/**
	 * Render ONE logos section row.
	 *
	 * @param array<string,mixed> $fields  Row sub-field fixtures.
	 * @param array<string,mixed> $context Loop context.
	 * @return string
	 */
	private function render_logos_section( array $fields = array(), array $context = array(
		'index'      => 0,
		'is_primary' => true,
	) ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'logos',
					'fields' => $fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section( 'logos', $context );
		return (string) ob_get_clean();
	}

	// -----------------------------------------------------------------
	// Layout shape (DATA02).
	// -----------------------------------------------------------------

	public function test_layout_shape_content_first_then_wrapper(): void {
		$layout = require dirname( __DIR__, 2 ) . '/inc/fields/layouts/logos.php';

		$this->assertSame( 'logos', $layout['name'] );
		$this->assertSame( 'layout_salt_logos', $layout['key'] );

		$names = array_column( $layout['sub_fields'], 'name' );
		$this->assertSame( array( 'heading', 'logos', 'greyscale' ), array_slice( $names, 0, 3 ) );
		$this->assertContains( 'section_spacing', $names, 'Wrapper Advanced tab embedded last (§11).' );
		$this->assertGreaterThan(
			array_search( 'greyscale', $names, true ),
			array_search( 'section_spacing', $names, true ),
			'Content fields precede the wrapper fields.'
		);

		$fields = array_column( $layout['sub_fields'], null, 'name' );
		$this->assertSame( 'field_salt_logos_greyscale', $fields['greyscale']['key'] );
		$this->assertSame( 1, $fields['greyscale']['default_value'] );
		$this->assertSame( 'repeater', $fields['logos']['type'] );

		$sub = array_column( $fields['logos']['sub_fields'], null, 'name' );
		$this->assertSame( 'id', $sub['image']['return_format'] );
		$this->assertSame( 'url', $sub['url']['type'] );
		$this->assertSame( 'text', $sub['name']['type'] );
	}

	// -----------------------------------------------------------------
	// States (COMPL02) + broken rows (DATA05).
	// -----------------------------------------------------------------

	public function test_zero_logos_renders_nothing(): void {
		$this->assertSame( '', $this->render_logos_section( array( 'logos' => array() ) ) );
		$this->assertSame( '', $this->render_logos_section( array() ) );
	}

	public function test_broken_rows_are_skipped_and_logged(): void {
		$this->seed_attachment( 7 );

		$html = $this->render_logos_section(
			array(
				'logos' => array(
					array( 'image' => 7 ),
					array(
						'url'  => 'https://example.test/no-image',
						'name' => 'No image',
					),
					'not-a-row',
					array( 'image' => 999 ), // Unresolvable attachment.
				),
			)
		);

		$this->assertSame( 1, substr_count( $html, '<li' ), 'Only the resolvable logo renders.' );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'no image', $logs );
		$this->assertStringContainsString( 'malformed logo row', $logs );
		$this->assertStringContainsString( '999', $logs );
	}

	public function test_all_rows_broken_renders_nothing(): void {
		$html = $this->render_logos_section(
			array(
				'logos' => array(
					array( 'image' => 999 ),
					array( 'name' => 'Missing' ),
				),
			)
		);

		$this->assertSame( '', $html, '0 renderable logos: no blank band (COMPL02).' );
	}

	public function test_single_logo_renders_static_strip(): void {
		$this->seed_attachment( 7 );

		$html = $this->render_logos_section(
			array( 'logos' => array( array( 'image' => 7 ) ) )
		);

		$this->assertSame( 1, substr_count( $html, '<li' ) );
		$this->assertStringContainsString( 'x-image', $html );
		$this->assertStringNotContainsString( '<script', $html, 'No JS in the logo strip, ever.' );
	}

	// -----------------------------------------------------------------
	// Links, greyscale, alt precedence, heading.
	// -----------------------------------------------------------------

	public function test_logo_with_url_is_wrapped_in_a_link(): void {
		$this->seed_attachment( 7 );
		$this->seed_attachment( 8 );

		$html = $this->render_logos_section(
			array(
				'logos' => array(
					array(
						'image' => 7,
						'url'   => 'https://press.example.org/feature',
						'name'  => 'The Press',
					),
					array( 'image' => 8 ),
				),
			)
		);

		$this->assertStringContainsString( 'href="https://press.example.org/feature"', $html );
		$this->assertStringContainsString( 'aria-label="The Press"', $html );
		$this->assertSame( 1, substr_count( $html, '<a ' ), 'Only the linked logo gets an anchor.' );
	}

	public function test_greyscale_toggle_controls_the_grayscale_utility(): void {
		$this->seed_attachment( 7 );

		$on = $this->render_logos_section(
			array(
				'logos'     => array( array( 'image' => 7 ) ),
				'greyscale' => 1,
			)
		);
		$this->assertStringContainsString( 'grayscale', $on );
		$this->assertStringContainsString( 'hover:grayscale-0', $on );

		$off = $this->render_logos_section(
			array(
				'logos'     => array( array( 'image' => 7 ) ),
				'greyscale' => 0,
			)
		);
		$this->assertStringNotContainsString( 'grayscale', $off );
	}

	public function test_name_feeds_alt_only_when_attachment_alt_is_empty(): void {
		$this->seed_attachment( 7, '' );
		$this->seed_attachment( 8, 'Stored alt wins' );

		$html = $this->render_logos_section(
			array(
				'logos' => array(
					array(
						'image' => 7,
						'name'  => 'Acme Co',
					),
					array(
						'image' => 8,
						'name'  => 'Ignored name',
					),
				),
			)
		);

		$this->assertStringContainsString( 'alt="Acme Co"', $html );
		$this->assertStringContainsString( 'alt="Stored alt wins"', $html );
		$this->assertStringNotContainsString( 'Ignored name', $html );
	}

	public function test_headingless_strip_never_claims_the_h1(): void {
		$this->seed_attachment( 7 );

		$html = $this->render_logos_section(
			array( 'logos' => array( array( 'image' => 7 ) ) )
		);

		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less section leaves the H1 claim free (A11Y01).' );
	}

	public function test_heading_renders_at_the_derived_level(): void {
		$this->seed_attachment( 7 );

		$html = $this->render_logos_section(
			array(
				'heading' => 'As featured in',
				'logos'   => array( array( 'image' => 7 ) ),
			)
		);

		$this->assertStringContainsString( '<h1', $html );
		$this->assertStringContainsString( 'As featured in', $html );
	}
}
