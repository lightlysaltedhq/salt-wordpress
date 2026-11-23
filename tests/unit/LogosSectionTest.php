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
		$this->assertSame( array( 'heading', 'intro', 'columns', 'greyscale', 'items', '', 'settings' ), array_column( $layout['sub_fields'], 'name' ) );

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
		$this->assertStringContainsString( 'class="salt-logos__image" loading="lazy"', $html );
		$this->assertStringContainsString( '<span class="salt-logos__frame"><img ', $html );
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
		$this->assertStringContainsString( 'data-greyscale="true"', $on );

		$off = $this->render_logos_section(
			array(
				'items'     => array( array( 'image' => 7 ) ),
				'greyscale' => 0,
			)
		);
		$this->assertStringContainsString( 'data-greyscale="false"', $off );

		$unsaved = $this->render_logos_section( array( 'items' => array( array( 'image' => 7 ) ) ) );
		$this->assertStringContainsString( 'data-greyscale="true"', $unsaved, 'The field defaults on.' );
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
		$this->assertNull( $this->resolve_logos( array( 'items' => array( array( 'image' => 99 ), 'not-a-row' ) ) ) );
		// markup/logos.json zeroState: heading, intro and logos all empty; a heading alone draws.
		$this->assertSame( array(), $this->resolve_logos( array( 'heading' => 'As featured in' ) )['props']['items'] );
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

		$this->assertSame( array( 'heading', 'intro', 'columns', 'greyscale', 'items' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'As featured in', $resolved['props']['heading'] );
		$this->assertNull( $resolved['props']['intro'] );
		$this->assertSame( '4', $resolved['props']['columns'], 'The contract\'s default.' );
		$this->assertTrue( $resolved['props']['greyscale'] );
		$items = $resolved['props']['items'];
		$this->assertSame( array( 'image', 'name', 'href' ), array_keys( $items[0] ) );
		$this->assertSame( 'Acme', $items[0]['name'] );
		$this->assertSame( 'https://acme.test/', $items[0]['href'] );
		$this->assertSame( 'Acme', $items[0]['image']['alt'], 'No stored alt: the name.' );
		$this->assertSame( '8rem', $items[0]['image']['sizes'], 'The thumb slot.' );
		$this->assertSame( 'Beta', $items[1]['name'] );
		$this->assertNull( $items[1]['href'] );
		$this->assertSame( 'Stored alt', $items[1]['image']['alt'], 'The stored alt wins over the name.' );
		$this->assertFalse( $resolved['section']['is_primary'] );
		$this->assertTrue( $resolved['section']['has_heading'] );
	}

	public function test_resolver_leaves_a_nameless_link_unlinked_and_logs_it(): void {
		// The link's accessible name is the name (markup/logos.json): without one the logo is unlinked.
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

		$this->assertNull( $resolved['props']['items'][0]['href'] );
		$this->assertNull( $resolved['props']['items'][0]['name'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertStringContainsString( 'linked logo has no name', implode( ' ', $this->logs() ) );
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
		$this->assertStringContainsString( 'linked logo has no name', $logs[1] );
	}

	public function test_view_draws_from_props_alone(): void {
		$image = array(
			'src'    => 'https://u.test/logo.png',
			'srcset' => null,
			'sizes'  => '8rem',
			'width'  => 200,
			'height' => 100,
			'alt'    => 'Drawn logo',
		);

		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/logos.php',
			false,
			array(
				'props' => array(
					'heading'   => 'From props',
					'intro'     => 'An intro.',
					'columns'   => '3',
					'greyscale' => false,
					'items'     => array(
						array(
							'image' => $image,
							'name'  => 'Drawn label',
							'href'  => 'https://drawn.test/',
						),
						array(
							'image' => $image,
							'name'  => null,
							'href'  => null,
						),
					),
				),
				'plan'  => array(
					'index'         => 0,
					'anchor'        => 'press',
					'headingLevel'  => 2,
					'headingId'     => 'press__heading',
					'bodyBaseLevel' => 2,
					'priorityMedia' => true,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<h2 id="press__heading">From props</h2><p class="salt-intro">An intro.</p>', $html );
		$this->assertStringContainsString( '<ul class="salt-grid salt-logos" role="list" data-columns="3" data-greyscale="false">', $html );
		$this->assertStringContainsString( '<a class="salt-logos__frame" href="https://drawn.test/" aria-label="Drawn label">', $html );
		$this->assertSame( 1, substr_count( $html, '<span class="salt-logos__frame">' ), 'Unlinked: a span frame.' );
		$this->assertStringNotContainsString( 'fetchpriority', $html, 'Logos never take the priority image.' );
		$this->assertStringNotContainsString( 'salt-section', $html, 'The band is the entry\'s, not the view\'s.' );
	}
}
