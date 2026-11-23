<?php
/**
 * Media + Text slice tests (salt-contract markup/media-text.json, rc.8;
 * spec §10 #6; COMPL02, A11Y01, DATA02).
 *
 * Covers the FC layout shape, the 0/1/N row states, malformed-row skipping,
 * the single-mode mediaSide vs alternating auto-flip rule (data-media-side),
 * the heading outline rule (row 0 at the plan's level with the band's id,
 * later rows at body base + 1, row text ranked under its row heading), the
 * half and content image slots and the priority image, and the contract's
 * buttons.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';

final class MediaTextSectionTest extends SaltTestCase {

	/** The half slot's sizes in a default-width band (contract/image-sizes.json). */
	private const HALF_SIZES = '(min-width: 75rem) 29rem, (min-width: 64rem) 29.22rem, (min-width: 48.01rem) calc((100vw - 4rem) / 2), calc(100vw - 2rem)';

	/** The content slot's sizes in a default-width band (contract/image-sizes.json). */
	private const CONTENT_SIZES = '(min-width: 75rem) 60rem, (min-width: 64rem) 60.44rem, calc(100vw - 2rem)';

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();
	}

	/**
	 * Seed the FC row and render the media-text section.
	 *
	 * @param array<string,mixed> $fields     Row sub-field fixtures.
	 * @param bool                $is_primary Loop context flag.
	 * @return string Rendered HTML.
	 */
	private function render_media_text( array $fields = array(), bool $is_primary = true ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'media-text',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'media-text',
			array(
				'index'      => 0,
				'is_primary' => $is_primary,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * Seed one resolvable attachment fixture.
	 *
	 * @param int $id Attachment ID.
	 * @return void
	 */
	private function seed_attachment( int $id ): void {
		$GLOBALS['salt_test_attachments'][ $id ] = array(
			'src'    => 'https://example.test/uploads/media-' . $id . '.jpg',
			'width'  => 1200,
			'height' => 800,
			'alt'    => 'Fixture image ' . $id,
		);
	}

	/**
	 * The data-media-side of each drawn row, in order ('' for a row without one).
	 *
	 * @param string $html Rendered HTML.
	 * @return array<int,string>
	 */
	private function media_sides( string $html ): array {
		preg_match_all( '/<div class="salt-media-text__row"(?: data-media-side="([a-z]+)")?>/', $html, $matches );
		return $matches[1];
	}

	// -----------------------------------------------------------------
	// FC layout shape (§17 recipe, DATA02/FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_is_generated_from_the_contract(): void {
		$layout = salt_test_layout( 'media-text' );

		$this->assertSame( 'layout_salt_media_text', $layout['key'] );
		$this->assertSame( array( 'mode', 'rows', '', 'settings' ), array_column( $layout['sub_fields'], 'name' ) );

		$rows = $layout['sub_fields'][1];
		$this->assertSame( 'repeater', $rows['type'] );
		$row_fields = array_column( $rows['sub_fields'], null, 'name' );
		$this->assertSame( array( 'image', 'heading', 'content', 'mediaSide', 'buttons' ), array_keys( $row_fields ) );
		$this->assertSame( 'field_salt_media_text_rows_media_side', $row_fields['mediaSide']['key'], 'DATA02: the name in snake_case.' );
		$this->assertSame( 'id', $row_fields['image']['return_format'] );
		$this->assertSame( array( 'left', 'right' ), array_keys( $row_fields['mediaSide']['choices'] ) );
		$this->assertSame( 2, $row_fields['buttons']['max'] );
		$this->assertSame( array( 'single', 'alternating' ), array_keys( $layout['sub_fields'][0]['choices'] ) );
	}

	// -----------------------------------------------------------------
	// States (COMPL02, markup zeroState).
	// -----------------------------------------------------------------

	public function test_zero_rows_renders_nothing(): void {
		$this->assertSame( '', $this->render_media_text() );
		$this->assertSame( '', $this->render_media_text( array( 'rows' => array() ) ) );
	}

	public function test_all_rows_empty_renders_nothing_and_logs(): void {
		$html = $this->render_media_text(
			array(
				'mode' => 'single',
				'rows' => array(
					array(
						'image'   => 0,
						'heading' => '   ',
						'content' => '',
					),
					array( 'content' => '<p>&nbsp;</p>' ),
				),
			)
		);

		$this->assertSame( '', $html, 'Text without words does not make a row usable.' );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'section media-text: row 0 has no image, heading, text or button — skipped.', $logs );
		$this->assertStringContainsString( 'section media-text: row 1 has no image, heading, text or button — skipped.', $logs );
	}

	public function test_malformed_rows_skipped_but_valid_rows_render(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					'not-a-row',
					array(
						'heading' => 'Kept row',
						'content' => '<p>Copy.</p>',
					),
				),
			)
		);

		$this->assertSame( 1, substr_count( $html, '<div class="salt-media-text__row"' ) );
		$this->assertStringContainsString( 'Kept row', $html );
		$this->assertStringContainsString( 'section media-text: row 0 is malformed — skipped.', implode( ' ', $this->logs() ) );
	}

	public function test_single_row_draws_the_block_media_then_body(): void {
		$this->seed_attachment( 31 );

		$html = $this->render_media_text(
			array(
				'mode' => 'single',
				'rows' => array(
					array(
						'image'   => 31,
						'heading' => 'One row',
						'content' => '<p>Body.</p>',
					),
				),
			)
		);

		$this->assertMatchesRegularExpression(
			'#<div class="salt-section__content"><div class="salt-block" data-block="media-text"><div class="salt-media-text"><div class="salt-media-text__row" data-media-side="left"><div class="salt-media-text__media"><img [^>]*></div><div class="salt-media-text__body"><h1 id="media-text__heading">One row</h1><div class="salt-rich-text"><p>Body.</p></div></div></div></div></div></div></section>$#',
			$html
		);
		$this->assertStringNotContainsString( 'data-section', $html, 'The x-section band is gone.' );
		$this->assertStringNotContainsString( 'data-salt-', $html, 'Media + Text ships no JS trigger in any state.' );
	}

	public function test_text_only_row_draws_the_body_alone_without_a_side(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'heading'   => 'No image here',
						'content'   => '<p>Text only.</p>',
						'mediaSide' => 'right',
					),
				),
			)
		);

		$this->assertStringNotContainsString( 'salt-media-text__media', $html, 'Text-only rows never render an empty media column.' );
		$this->assertSame( array( '' ), $this->media_sides( $html ), 'A row without an image has no side.' );
		$this->assertStringContainsString( '<div class="salt-media-text__body"><h1 id="media-text__heading">No image here</h1>', $html );
	}

	public function test_a_row_with_only_a_button_is_drawn(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array( 'buttons' => array( salt_test_button( 'Get in touch', 'https://example.test/contact/' ) ) ),
				),
			),
			false
		);

		$this->assertStringStartsWith( '<div class="salt-section" id="media-text"', $html, 'No heading: the band is a div, not a named section.' );
		$this->assertStringContainsString( '<div class="salt-media-text__row"><div class="salt-media-text__body"><div class="salt-media-text__actions"><a class="salt-button" href="/contact/"', $html );
		$this->assertSame( array(), $this->logs() );
	}

	// -----------------------------------------------------------------
	// Media side: single vs alternating (the MODE RULE).
	// -----------------------------------------------------------------

	public function test_single_mode_respects_per_row_media_side(): void {
		$this->seed_attachment( 31 );
		$this->seed_attachment( 32 );
		$this->seed_attachment( 33 );

		$html = $this->render_media_text(
			array(
				'mode' => 'single',
				'rows' => array(
					array(
						'image'     => 31,
						'heading'   => 'Right row',
						'mediaSide' => 'right',
					),
					array(
						'image'     => 32,
						'heading'   => 'Left row',
						'mediaSide' => 'left',
					),
					array(
						'image'   => 33,
						'heading' => 'Unset row',
					),
				),
			)
		);

		$this->assertSame( array( 'right', 'left', 'left' ), $this->media_sides( $html ), 'Each row\'s own side; left when unset.' );
		$this->assertLessThan( strpos( $html, 'salt-media-text__body' ), strpos( $html, 'media-31.jpg' ), 'Media then body in the DOM, whatever the side.' );
		$this->assertStringNotContainsString( 'order-', $html, 'The side is data-media-side, not an order class.' );
	}

	public function test_alternating_mode_flips_by_rendered_row_and_ignores_media_side(): void {
		$this->seed_attachment( 31 );
		$this->seed_attachment( 32 );
		$this->seed_attachment( 33 );

		// Every row says 'right' and a skipped row sits between them:
		// alternating must ignore the stored side and count rendered rows.
		$html = $this->render_media_text(
			array(
				'mode' => 'alternating',
				'rows' => array(
					array(
						'image'     => 31,
						'heading'   => 'Row zero',
						'mediaSide' => 'right',
					),
					array( 'heading' => '' ),
					array(
						'image'     => 32,
						'heading'   => 'Row one',
						'mediaSide' => 'right',
					),
					array(
						'image'     => 33,
						'heading'   => 'Row two',
						'mediaSide' => 'right',
					),
				),
			)
		);

		$this->assertSame( array( 'left', 'right', 'left' ), $this->media_sides( $html ) );
	}

	public function test_invalid_mode_falls_back_to_single_and_logs(): void {
		$this->seed_attachment( 31 );

		$html = $this->render_media_text(
			array(
				'mode' => 'zigzag',
				'rows' => array(
					array(
						'image'     => 31,
						'heading'   => 'Row',
						'mediaSide' => 'right',
					),
				),
			)
		);

		$this->assertSame( array( 'right' ), $this->media_sides( $html ), 'Single mode: the row\'s own side.' );
		$this->assertStringContainsString( 'section media-text: invalid mode "zigzag" — using single.', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Heading outline (the HEADING OUTLINE RULE; A11Y01).
	// -----------------------------------------------------------------

	public function test_primary_section_row_zero_h1_later_rows_h2(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'heading' => 'Section heading',
						'content' => '<p>One.</p>',
					),
					array(
						'heading' => 'Second row',
						'content' => '<p>Two.</p>',
					),
					array(
						'heading' => 'Third row',
						'content' => '<p>Three.</p>',
					),
				),
			),
			true
		);

		$this->assertStringStartsWith( '<section class="salt-section" id="media-text" aria-labelledby="media-text__heading"', $html );
		$this->assertStringContainsString( '<h1 id="media-text__heading">Section heading</h1>', $html );
		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ), 'Row 0 claims the H1 on a primary section.' );
		$this->assertStringContainsString( '<h2>Second row</h2>', $html );
		$this->assertStringContainsString( '<h2>Third row</h2>', $html, 'Later rows at body base + 1: never more H1s, never a skip.' );
		$this->assertSame( 1, preg_match_all( '/<h[1-6] id=/', $html ), 'Only the first row\'s heading carries an id.' );
	}

	public function test_non_primary_section_ranks_later_row_headings_under_the_first(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array( 'heading' => 'First' ),
					array( 'heading' => 'Second' ),
				),
			),
			false
		);

		$this->assertStringContainsString( '<h2 id="media-text__heading">First</h2>', $html );
		$this->assertStringContainsString( '<h3>Second</h3>', $html, 'Ruled: Next\'s ranking, the first row names the section.' );
		$this->assertSame( 0, preg_match_all( '/<h1[\s>]/', $html ) );
	}

	public function test_row_text_ranks_its_headings_under_the_row_heading(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'heading' => 'First',
						'content' => '<h3>Under first</h3>',
					),
					array(
						'heading' => 'Second',
						'content' => '<h3>Under second</h3>',
					),
					array( 'content' => '<h3>Under the band</h3>' ),
				),
			),
			false
		);

		$this->assertStringContainsString( '<div class="salt-rich-text"><h3>Under first</h3></div>', $html );
		$this->assertStringContainsString( '<h3>Second</h3><div class="salt-rich-text"><h4>Under second</h4></div>', $html, 'Under the row\'s h3, not the band\'s h2.' );
		$this->assertStringContainsString( '<div class="salt-rich-text"><h3>Under the band</h3></div>', $html, 'A headingless row ranks under the body base.' );
	}

	public function test_headingless_first_row_never_claims_the_h1(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array( 'content' => '<p>No heading on the lead row.</p>' ),
					array( 'heading' => 'Later heading' ),
				),
			),
			true
		);

		$this->assertStringStartsWith( '<div class="salt-section" id="media-text" data-tone=', $html, 'No first-row heading: a div band with no aria-labelledby.' );
		$this->assertSame( 0, preg_match_all( '/<h1[\s>]/', $html ), 'has_heading keys off ROW 0: a later heading must not take the H1.' );
		$this->assertStringContainsString( '<h2>Later heading</h2>', $html );
		$this->assertStringNotContainsString( '__heading', $html );
		$this->assertFalse( salt_h1_claimed(), 'The page-level fallback H1 must still be able to fire (A11Y01).' );
	}

	// -----------------------------------------------------------------
	// Images (markup/media.json; contract/image-sizes.json).
	// -----------------------------------------------------------------

	public function test_image_beside_words_takes_the_half_slot(): void {
		$this->seed_attachment( 31 );

		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'image'   => 31,
						'heading' => 'With image',
					),
				),
			),
			false
		);

		$this->assertStringContainsString(
			'<div class="salt-media-text__media"><img src="https://example.test/uploads/media-31.jpg" srcset="https://example.test/uploads/media-31.jpg 1200w" sizes="' . self::HALF_SIZES . '" width="1200" height="800" alt="Fixture image 31" class="salt-media-text__image"',
			$html
		);
	}

	public function test_image_alone_takes_the_content_slot_and_no_side(): void {
		$this->seed_attachment( 31 );

		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'image'     => 31,
						'mediaSide' => 'right',
					),
				),
			),
			false
		);

		$this->assertStringContainsString( 'sizes="' . self::CONTENT_SIZES . '"', $html );
		$this->assertSame( array( '' ), $this->media_sides( $html ), 'An image without words has no side.' );
		$this->assertStringNotContainsString( 'salt-media-text__body', $html );
	}

	public function test_only_the_first_row_image_may_be_the_priority_image(): void {
		$this->seed_attachment( 31 );
		$this->seed_attachment( 32 );

		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'image'   => 31,
						'heading' => 'First',
					),
					array(
						'image'   => 32,
						'heading' => 'Second',
					),
				),
			)
		);

		$this->assertSame( 1, substr_count( $html, 'fetchpriority="high"' ) );
		$this->assertMatchesRegularExpression( '#<img src="[^"]*media-31\.jpg"[^>]* fetchpriority="high" decoding="async">#', $html );
		$this->assertMatchesRegularExpression( '#<img src="[^"]*media-32\.jpg"[^>]* loading="lazy" decoding="async">#', $html );
	}

	public function test_the_first_row_with_an_image_takes_the_priority_image(): void {
		$this->seed_attachment( 32 );
		$this->seed_attachment( 33 );

		$html = $this->render_media_text(
			array(
				'rows' => array(
					array( 'heading' => 'Words first' ),
					array(
						'image'   => 32,
						'heading' => 'Image second',
					),
					array(
						'image'   => 33,
						'heading' => 'Image third',
					),
				),
			)
		);

		// The band's principal image is its first row's that has one (review #9).
		$this->assertSame( 1, substr_count( $html, 'fetchpriority="high"' ) );
		$this->assertMatchesRegularExpression( '#<img src="[^"]*media-32\.jpg"[^>]* fetchpriority="high" decoding="async">#', $html );
		$this->assertMatchesRegularExpression( '#<img src="[^"]*media-33\.jpg"[^>]* loading="lazy" decoding="async">#', $html );
		$this->assertTrue( salt_render_state()['priority_media'], 'The page\'s priority image is taken.' );
	}

	public function test_an_image_with_no_dimensions_is_dropped_and_logged(): void {
		$this->seed_attachment( 34 );
		$GLOBALS['salt_test_attachments'][34]['width']  = 0;
		$GLOBALS['salt_test_attachments'][34]['height'] = 0;

		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'image'   => 34,
						'heading' => 'Words stay',
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'Words stay', $html );
		$this->assertContains( 'section media-text: row 0 image 34 has no width or height — not rendered.', $this->logs() );
	}

	public function test_unresolvable_image_is_absent_and_the_row_keeps_its_words(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'image'   => 999,
						'heading' => 'Broken image row',
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<!--', $html, 'No comment placeholder inside the row.' );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'salt-media-text__media', $html );
		$this->assertSame( array( '' ), $this->media_sides( $html ) );
		$this->assertStringContainsString( 'Broken image row', $html, 'The row still renders its text.' );
		$this->assertStringContainsString( 'attachment 999 could not be resolved', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Text and buttons.
	// -----------------------------------------------------------------

	public function test_wysiwyg_content_is_sanitised(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'heading' => 'Safe',
						'content' => '<p>Fine.</p><script>alert(1)</script>',
					),
				),
			)
		);

		$this->assertStringContainsString( '<div class="salt-rich-text"><p>Fine.</p>', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_buttons_draw_as_contract_buttons_with_the_site_arrow(): void {
		add_filter( 'salt_contract_arrow', static fn(): string => '›' );

		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'heading' => 'With buttons',
						'buttons' => array(
							salt_test_button( 'Get in touch', 'https://example.test/contact/', '' ),
							salt_test_button( 'Our work', 'https://example.test/work/', 'primary', true ),
						),
					),
				),
			)
		);

		$this->assertStringContainsString(
			'<div class="salt-media-text__actions">'
			. '<a class="salt-button" href="/contact/" data-style="secondary" data-track-control="cta"><span class="salt-button__label">Get in touch<span class="salt-arrow" aria-hidden="true">&#x2060;›</span></span></a>'
			. '<a class="salt-button" href="/work/" data-style="primary" data-track-control="cta" target="_blank" rel="noopener noreferrer"><span class="salt-button__label">Our work<span class="salt-arrow" aria-hidden="true">&#x2060;›</span></span></a>'
			. '</div>',
			$html,
			'Unstyled buttons default to secondary.'
		);
		$this->assertStringNotContainsString( 'x-button', $html );
	}

	public function test_malformed_cta_is_skipped_and_logged_row_still_renders(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'heading' => 'CTA-less row',
						'buttons' => array( salt_test_button( 'Dead link', '' ) ),
					),
				),
			)
		);

		$this->assertStringContainsString( 'CTA-less row', $html );
		$this->assertStringNotContainsString( 'salt-button', $html );
		$this->assertStringNotContainsString( 'salt-media-text__actions', $html );
		$this->assertStringContainsString( 'section media-text: row 0: button 0 has no label or no address', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Schema: this section queues no nodes.
	// -----------------------------------------------------------------

	public function test_no_schema_nodes_are_queued(): void {
		$this->render_media_text(
			array(
				'rows' => array(
					array( 'heading' => 'Row' ),
				),
			)
		);

		$this->assertSame( array(), salt_schema_queue() );
	}

	// -----------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one media-text FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields  Row fields.
	 * @param array<string,mixed> $context Loop context.
	 * @return array<string,mixed>|null
	 */
	private function resolve_media_text( array $fields, array $context = array(
		'index'      => 0,
		'is_primary' => false,
	) ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'media-text',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'media-text' );
		$this->assertSame( 'salt_resolve_section_media_text', $resolver );

		return $resolver( $context );
	}

	public function test_resolver_returns_null_without_usable_rows(): void {
		$this->assertNull( $this->resolve_media_text( array() ) );
		$this->assertNull(
			$this->resolve_media_text(
				array(
					'rows' => array(
						array( 'image' => 99 ),
						'not-a-row',
					),
				)
			)
		);
	}

	public function test_resolver_props_use_the_contract_names(): void {
		$this->seed_attachment( 5 );

		$resolved = $this->resolve_media_text(
			array(
				'mode' => 'single',
				'rows' => array(
					array(
						'image'     => 5,
						'heading'   => 'Our story',
						'content'   => '<p>Copy <strong>here</strong>.</p><script>x()</script>',
						'mediaSide' => 'right',
						'buttons'   => array( salt_test_button( 'Read more', '/about', 'primary', true ) ),
					),
					array(
						'content'   => '<p>Text only.</p>',
						'mediaSide' => 'right',
					),
				),
			)
		);

		$this->assertSame( array( 'rows', 'arrow' ), array_keys( $resolved['props'] ) );
		$this->assertSame( '→', $resolved['props']['arrow'] );

		$first = $resolved['props']['rows'][0];
		$this->assertSame( array( 'image', 'heading', 'content', 'mediaSide', 'buttons' ), array_keys( $first ) );
		$this->assertSame( 'https://example.test/uploads/media-5.jpg', $first['image']['src'] );
		$this->assertSame( self::HALF_SIZES, $first['image']['sizes'] );
		$this->assertSame( 'Our story', $first['heading'] );
		$this->assertStringContainsString( '<strong>here</strong>', $first['content'] );
		$this->assertStringNotContainsString( '<script', $first['content'] );
		$this->assertSame( 'right', $first['mediaSide'] );
		$this->assertSame(
			array(
				array(
					'label'  => 'Read more',
					'href'   => '/about',
					'style'  => 'primary',
					'newTab' => true,
				),
			),
			$first['buttons']
		);

		$second = $resolved['props']['rows'][1];
		$this->assertNull( $second['image'] );
		$this->assertNull( $second['heading'] );
		$this->assertSame( array(), $second['buttons'] );
		$this->assertNull( $second['mediaSide'], 'No image: no side, whatever was stored.' );
		$this->assertTrue( $resolved['section']['has_heading'], 'The FIRST row\'s heading is the section heading.' );
		$this->assertSame( array( 'priorityMedia' => true ), $resolved['plan'], 'The first row draws an image.' );
	}

	public function test_resolver_asks_for_the_priority_image_when_any_row_draws_one(): void {
		$this->seed_attachment( 5 );

		$with = $this->resolve_media_text(
			array(
				'rows' => array(
					array( 'heading' => 'No image' ),
					array(
						'image'   => 5,
						'heading' => 'Image',
					),
				),
			)
		);
		$this->assertSame( array( 'priorityMedia' => true ), $with['plan'], 'A later row\'s image is the band\'s principal image.' );

		$without = $this->resolve_media_text( array( 'rows' => array( array( 'heading' => 'No image' ) ) ) );
		$this->assertSame( array( 'priorityMedia' => false ), $without['plan'] );
	}

	public function test_resolver_applies_the_mode_rule_to_the_media_side(): void {
		$rows = array();
		foreach ( array( 11, 12, 13 ) as $id ) {
			$this->seed_attachment( $id );
			$rows[] = array(
				'image'     => $id,
				'heading'   => 'Row ' . $id,
				'mediaSide' => 'right',
			);
		}

		$alternating = $this->resolve_media_text(
			array(
				'mode' => 'alternating',
				'rows' => $rows,
			)
		);
		$this->assertSame(
			array( 'left', 'right', 'left' ),
			array_column( $alternating['props']['rows'], 'mediaSide' ),
			'Alternating ignores mediaSide and flips per rendered row.'
		);
		$this->assertTrue( $alternating['section']['has_heading'] );

		$single = $this->resolve_media_text(
			array(
				'mode' => 'single',
				'rows' => $rows,
			)
		);
		$this->assertSame( array( 'right', 'right', 'right' ), array_column( $single['props']['rows'], 'mediaSide' ) );
	}

	public function test_resolver_owns_the_wrapper_inputs(): void {
		$resolved = $this->resolve_media_text(
			array(
				'rows'     => array( array( 'heading' => 'Only' ) ),
				'settings' => array( 'spacing' => 'lg' ),
			),
			array(
				'index'      => 4,
				'is_primary' => true,
			)
		);

		$this->assertSame( 'media-text', $resolved['section']['name'] );
		$this->assertTrue( $resolved['section']['contract'], 'Drawn in the contract\'s wrapper (M4).' );
		$this->assertSame( 'lg', $resolved['section']['spacing'] );
		$this->assertTrue( $resolved['section']['is_primary'] );
		$this->assertTrue( $resolved['section']['has_heading'] );
	}

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/media-text.php',
			false,
			array(
				'props' => array(
					'rows'  => array(
						array(
							'image'     => array(
								'src'        => 'https://example.test/props.jpg',
								'srcset'     => null,
								'sizes'      => '50vw',
								'width'      => 1200,
								'height'     => 800,
								'alt'        => 'From props',
								'caption'    => null,
								'focalPoint' => null,
							),
							'heading'   => 'First from props',
							'content'   => '<p>Already sanitised.</p>',
							'mediaSide' => 'right',
							'buttons'   => array(
								array(
									'label'  => 'More',
									'href'   => '/more',
									'style'  => 'secondary',
									'newTab' => false,
								),
							),
						),
						array(
							'image'     => null,
							'heading'   => 'Second from props',
							'content'   => null,
							'mediaSide' => null,
							'buttons'   => array(),
						),
					),
					'arrow' => null,
				),
				'plan'  => array(
					'index'         => 0,
					'anchor'        => 'story',
					'headingLevel'  => 1,
					'headingId'     => 'story__heading',
					'bodyBaseLevel' => 1,
					'priorityMedia' => true,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame(
			'<div class="salt-block" data-block="media-text"><div class="salt-media-text">'
			. '<div class="salt-media-text__row" data-media-side="right"><div class="salt-media-text__media"><img src="https://example.test/props.jpg" sizes="50vw" width="1200" height="800" alt="From props" class="salt-media-text__image" fetchpriority="high" decoding="async"></div>'
			. '<div class="salt-media-text__body"><h1 id="story__heading">First from props</h1><div class="salt-rich-text"><p>Already sanitised.</p></div>'
			. '<div class="salt-media-text__actions"><a class="salt-button" href="/more" data-style="secondary" data-track-control="cta">More</a></div></div></div>'
			. '<div class="salt-media-text__row"><div class="salt-media-text__body"><h2>Second from props</h2></div></div>'
			. '</div></div>',
			$html,
			'The block only: the band is the entry\'s, not the view\'s.'
		);
	}

	public function test_row_content_keeps_only_headings_three_and_four(): void {
		$resolved = $this->resolve_media_text(
			array(
				'rows' => array(
					array(
						'heading' => 'Row',
						'content' => '<h2>Two</h2><h3>Three</h3><h6>Six</h6>',
					),
				),
			)
		);
		$content  = $resolved['props']['rows'][0]['content'];

		$this->assertStringContainsString( '<h3>Three</h3>', $content );
		$this->assertStringNotContainsString( '<h2', $content );
		$this->assertStringNotContainsString( '<h6', $content );
	}
}
