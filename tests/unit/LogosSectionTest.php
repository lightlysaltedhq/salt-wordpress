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

	public function test_layout_is_generated_from_the_contract(): void {
		$layout = salt_test_layout( 'logos' );

		$this->assertSame( 'layout_salt_logos', $layout['key'] );
		$this->assertSame( array( 'heading', 'intro', 'columns', 'greyscale', 'items', 'settings' ), array_column( $layout['sub_fields'], 'name' ) );

		$fields = array_column( $layout['sub_fields'], null, 'name' );
		$this->assertSame( 'field_salt_logos_greyscale', $fields['greyscale']['key'] );
		$this->assertSame( 1, $fields['greyscale']['default_value'] );
		$this->assertSame( 'repeater', $fields['items']['type'] );

		$sub = array_column( $fields['items']['sub_fields'], null, 'name' );
		$this->assertSame( 'id', $sub['image']['return_format'] );
		$this->assertSame( 'text', $sub['url']['type'], 'An address in a text field (salt.format url).' );
		$this->assertSame( 'text', $sub['name']['type'] );
	}

	// -----------------------------------------------------------------
	// States (COMPL02) + broken rows (DATA05).
	// -----------------------------------------------------------------

	public function test_zero_logos_renders_nothing(): void {
		$this->assertSame( '', $this->render_logos_section( array( 'items' => array() ) ) );
		$this->assertSame( '', $this->render_logos_section( array() ) );
	}

	public function test_broken_rows_are_skipped_and_logged(): void {
		$this->seed_attachment( 7 );

		$html = $this->render_logos_section(
			array(
				'items' => array(
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
				'items' => array(
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
			array( 'items' => array( array( 'image' => 7 ) ) )
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
				'items' => array(
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
				'items'     => array( array( 'image' => 7 ) ),
				'greyscale' => 1,
			)
		);
		$this->assertStringContainsString( 'grayscale', $on );
		$this->assertStringContainsString( 'hover:grayscale-0', $on );

		$off = $this->render_logos_section(
			array(
				'items'     => array( array( 'image' => 7 ) ),
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
				'items' => array(
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
			array( 'items' => array( array( 'image' => 7 ) ) )
		);

		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less section leaves the H1 claim free (A11Y01).' );
	}

	public function test_heading_renders_at_the_derived_level(): void {
		$this->seed_attachment( 7 );

		$html = $this->render_logos_section(
			array(
				'heading' => 'As featured in',
				'items'   => array( array( 'image' => 7 ) ),
			)
		);

		$this->assertStringContainsString( '<h1', $html );
		$this->assertStringContainsString( 'As featured in', $html );
	}

	// -----------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one logos FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields  Row fields.
	 * @param array<string,mixed> $context Loop context.
	 * @return array<string,mixed>|null
	 */
	private function resolve_logos( array $fields, array $context = array(
		'index'      => 0,
		'is_primary' => false,
	) ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'logos',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'logos' );
		$this->assertSame( 'salt_resolve_section_logos', $resolver );

		return $resolver( $context );
	}

	public function test_resolver_returns_null_without_resolvable_logos(): void {
		$this->assertNull( $this->resolve_logos( array( 'heading' => 'As featured in' ) ) );
		$this->assertNull( $this->resolve_logos( array( 'items' => array( array( 'image' => 99 ), 'not-a-row' ) ) ) );
	}

	public function test_resolver_props_use_the_contract_names(): void {
		$this->seed_attachment( 7 );
		$this->seed_attachment( 8, 'Stored alt' );

		$resolved = $this->resolve_logos(
			array(
				'heading'   => 'As featured in',
				'greyscale' => 1,
				'items'     => array(
					array(
						'image' => 7,
						'name'  => ' Acme ',
						'url'   => ' https://acme.test/ ',
					),
					array(
						'image' => array( 'ID' => 8 ),
						'name'  => 'Beta',
						'url'   => '',
					),
				),
			)
		);

		$this->assertSame( array( 'heading', 'greyscale', 'items' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'As featured in', $resolved['props']['heading'] );
		$this->assertTrue( $resolved['props']['greyscale'] );
		$this->assertSame(
			array(
				array(
					'image_id'   => 7,
					'alt'        => 'Acme',
					'href'       => 'https://acme.test/',
					'link_label' => 'Acme',
				),
				array(
					'image_id'   => 8,
					'alt'        => '',
					'href'       => '',
					'link_label' => '',
				),
			),
			$resolved['props']['items']
		);
		$this->assertFalse( $resolved['section']['is_primary'] );
		$this->assertTrue( $resolved['section']['has_heading'] );
	}

	public function test_resolver_leaves_a_nameless_link_unlinked_and_logs_it(): void {
		// Pre-split behaviour kept: with a stored alt but no name, alt stays
		// '' (it is only ever filled from the name), so the link is dropped.
		$this->seed_attachment( 8, 'Stored alt' );

		$resolved = $this->resolve_logos(
			array(
				'items' => array(
					array(
						'image' => 8,
						'url'   => 'https://acme.test/',
					),
				),
			)
		);

		$this->assertSame( '', $resolved['props']['items'][0]['href'] );
		$this->assertSame( '', $resolved['props']['items'][0]['link_label'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertStringContainsString( 'linked logo has no name or alt', implode( ' ', $this->logs() ) );
	}

	public function test_unlinked_logo_log_follows_every_row_skip_log(): void {
		$this->seed_attachment( 8, 'Stored alt' );

		$this->resolve_logos(
			array(
				'items' => array(
					array(
						'image' => 8,
						'url'   => 'https://acme.test/',
					),
					array( 'name' => 'No image' ),
				),
			)
		);

		$logs = $this->logs();
		$this->assertCount( 2, $logs );
		$this->assertStringContainsString( 'logo row has no image', $logs[0] );
		$this->assertStringContainsString( 'linked logo has no name or alt', $logs[1] );
	}

	public function test_view_draws_from_props_alone(): void {
		$this->seed_attachment( 7 );

		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/logos.php',
			false,
			array(
				'props' => array(
					'heading'   => 'From props',
					'greyscale' => true,
					'items'     => array(
						array(
							'image_id'   => 7,
							'alt'        => 'Drawn logo',
							'href'       => 'https://drawn.test/',
							'link_label' => 'Drawn label',
						),
						array(
							'image_id'   => 7,
							'alt'        => '',
							'href'       => '',
							'link_label' => '',
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
		$this->assertStringContainsString( '<a href="https://drawn.test/" aria-label="Drawn label">', $html );
		$this->assertSame( 1, substr_count( $html, '<a ' ), 'Only the logo with an href is linked.' );
		$this->assertSame( 2, substr_count( $html, 'x-logos-item' ) );
		$this->assertStringContainsString( 'grayscale', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );
	}
}
