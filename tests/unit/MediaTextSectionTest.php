<?php
/**
 * Media + Text slice tests (spec §10 #6; COMPL02, A11Y01, DATA02).
 *
 * Covers the FC layout shape, the 0/1/N row states, malformed-row skipping,
 * the single-mode mediaSide vs alternating auto-flip rule, the heading
 * outline rule (row 0 at $level, later rows stepped to h2 when row 0 took
 * the H1), the x-image 'half' sizes context, and the CTA button path.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';

final class MediaTextSectionTest extends SaltTestCase {

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
			'srcset' => 'https://example.test/uploads/media-' . $id . '-600.jpg 600w, https://example.test/uploads/media-' . $id . '.jpg 1200w',
			'alt'    => 'Fixture image ' . $id,
		);
	}

	// -----------------------------------------------------------------
	// FC layout shape (§17 recipe, DATA02/FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_file_shape(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/media-text.php';

		$this->assertSame( 'media-text', $layout['name'] );
		$this->assertSame( 'layout_salt_media_text', $layout['key'] );

		$keys = array_column( $layout['sub_fields'], 'key' );

		// Content fields first.
		$this->assertSame( 'field_salt_media_text_mode', $keys[0] );
		$this->assertSame( 'field_salt_media_text_rows', $keys[1] );

		// Repeater row sub-fields (DATA02 key convention).
		$rows = $layout['sub_fields'][1];
		$this->assertSame( 'repeater', $rows['type'] );
		$row_fields = array();
		foreach ( $rows['sub_fields'] as $field ) {
			$row_fields[ $field['name'] ] = $field;
		}
		$this->assertSame( array( 'image', 'heading', 'content', 'mediaSide', 'cta' ), array_keys( $row_fields ) );
		foreach ( $row_fields as $name => $field ) {
			// DATA02: the key carries the field name in snake_case (mediaSide => media_side).
			$this->assertSame( 'field_salt_media_text_row_' . strtolower( (string) preg_replace( '/[A-Z]/', '_$0', $name ) ), $field['key'], 'DATA02 key convention.' );
		}
		$this->assertSame( 'id', $row_fields['image']['return_format'] );
		$this->assertSame( array( 'left', 'right' ), array_keys( $row_fields['mediaSide']['choices'] ) );
		$this->assertSame( 'link', $row_fields['cta']['type'] );
		$this->assertSame( array( 'single', 'alternating' ), array_keys( $layout['sub_fields'][0]['choices'] ) );

		// Wrapper fields LAST (§11 Advanced tab).
		$this->assertContains( 'field_salt_media_text_advanced_tab', $keys );
		$this->assertSame( 'field_salt_media_text_divider', end( $keys ) );
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
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
				),
			)
		);

		$this->assertSame( '', $html );
		$this->assertStringContainsString( 'skipped', implode( ' ', $this->logs() ) );
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

		$this->assertSame( 1, substr_count( $html, 'x-media-text-row ' ) + substr_count( $html, 'x-media-text-row"' ) );
		$this->assertStringContainsString( 'Kept row', $html );
		$this->assertStringContainsString( 'malformed', implode( ' ', $this->logs() ) );
	}

	public function test_single_row_renders_static_grid(): void {
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

		$this->assertSame( 1, substr_count( $html, 'md:grid-cols-2' ) );
		$this->assertStringContainsString( 'media-31.jpg', $html );
		$this->assertStringNotContainsString( 'data-salt-', $html, 'Media + Text ships no JS trigger in any state.' );
	}

	public function test_text_only_row_renders_full_width_without_grid(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'heading' => 'No image here',
						'content' => '<p>Text only.</p>',
					),
				),
			)
		);

		$this->assertStringNotContainsString( 'md:grid-cols-2', $html, 'Text-only rows never render an empty media column.' );
		$this->assertStringNotContainsString( 'x-media-text-media', $html );
		$this->assertStringContainsString( 'No image here', $html );
	}

	// -----------------------------------------------------------------
	// Media side: single vs alternating (the MODE RULE).
	// -----------------------------------------------------------------

	public function test_single_mode_respects_per_row_media_side(): void {
		$this->seed_attachment( 31 );
		$this->seed_attachment( 32 );

		$html = $this->render_media_text(
			array(
				'mode' => 'single',
				'rows' => array(
					array(
						'image'     => 31,
						'heading'   => 'Left row',
						'mediaSide' => 'left',
					),
					array(
						'image'     => 32,
						'heading'   => 'Right row',
						'mediaSide' => 'right',
					),
				),
			)
		);

		$this->assertSame( 1, substr_count( $html, 'md:order-2' ), 'Only the mediaSide=right row flips.' );
		$this->assertLessThan(
			strpos( $html, 'md:order-2' ),
			strpos( $html, 'media-31.jpg' ),
			'The left row renders before the flipped right row.'
		);
	}

	public function test_alternating_mode_flips_automatically_and_ignores_media_side(): void {
		$this->seed_attachment( 31 );
		$this->seed_attachment( 32 );
		$this->seed_attachment( 33 );

		// All rows say 'right' — alternating must IGNORE that and flip
		// even/odd (row 0 left, row 1 right, row 2 left).
		$html = $this->render_media_text(
			array(
				'mode' => 'alternating',
				'rows' => array(
					array(
						'image'     => 31,
						'heading'   => 'Row zero',
						'mediaSide' => 'right',
					),
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

		$this->assertSame( 3, substr_count( $html, 'md:grid-cols-2' ) );
		$this->assertSame( 1, substr_count( $html, 'md:order-2' ), 'Exactly the odd row flips — mediaSide is ignored in alternating mode.' );
		$this->assertLessThan(
			strpos( $html, 'md:order-2' ),
			strpos( $html, 'media-31.jpg' ),
			'Row 0 (media left) precedes the flipped row 1.'
		);
		$this->assertGreaterThan(
			strpos( $html, 'md:order-2' ),
			strpos( $html, 'media-33.jpg' ),
			'Row 2 (media left again) follows the flipped row 1.'
		);
	}

	public function test_invalid_mode_falls_back_to_single_and_logs(): void {
		$html = $this->render_media_text(
			array(
				'mode' => 'zigzag',
				'rows' => array(
					array(
						'heading'   => 'Row',
						'mediaSide' => 'right',
					),
				),
			)
		);

		$this->assertStringContainsString( 'Row', $html );
		$this->assertStringContainsString( 'invalid mode', implode( ' ', $this->logs() ) );
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

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ), 'Row 0 claims the H1 on a primary section.' );
		$this->assertSame( 2, preg_match_all( '/<h2[\s>]/', $html ), 'Later rows step to h2 — never more H1s, never a skip.' );
		$this->assertLessThan( strpos( $html, '<h2' ), strpos( $html, '<h1' ), 'H1 precedes the h2s.' );
	}

	public function test_non_primary_section_renders_all_row_headings_at_h2(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array( 'heading' => 'First' ),
					array( 'heading' => 'Second' ),
				),
			),
			false
		);

		$this->assertSame( 0, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertSame( 2, preg_match_all( '/<h2[\s>]/', $html ), 'Non-primary: $level is 2 for row 0 and stays 2 for later rows.' );
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

		$this->assertSame( 0, preg_match_all( '/<h1[\s>]/', $html ), 'has_heading keys off ROW 0 — a later heading must not take the H1.' );
		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ) );
		$this->assertFalse( salt_h1_claimed(), 'The page-level fallback H1 must still be able to fire (A11Y01).' );
	}

	// -----------------------------------------------------------------
	// Content, image and CTA rendering.
	// -----------------------------------------------------------------

	public function test_image_renders_through_x_image_with_half_sizes_context(): void {
		$this->seed_attachment( 31 );

		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'image'   => 31,
						'heading' => 'With image',
					),
				),
			)
		);

		$this->assertStringContainsString( 'sizes="(min-width: 768px) 50vw, 100vw"', $html, "sizes_context 'half'." );
		$this->assertStringContainsString( 'alt="Fixture image 31"', $html, 'Stored attachment alt wins when the alt arg is empty.' );
		$this->assertStringContainsString( 'class="x-image w-full rounded-lg"', $html );
	}

	public function test_broken_image_renders_comment_not_broken_img(): void {
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

		$this->assertStringNotContainsString( 'x-image missing attachment', $html, 'Broken attachments are zeroed up front (COMPL02) — no comment placeholder inside the row.' );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'Broken image row', $html, 'The row still renders its text.' );
		$this->assertStringContainsString( 'unresolvable', implode( ' ', $this->logs() ), 'The zeroed attachment is logged.' );
	}

	public function test_wysiwyg_content_is_sanitized(): void {
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

		$this->assertStringContainsString( '<p>Fine.</p>', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_cta_renders_as_x_button(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'heading' => 'With CTA',
						'cta'     => array(
							'url'    => 'https://example.test/contact/',
							'title'  => 'Get in touch',
							'target' => '',
						),
					),
				),
			)
		);

		$this->assertStringContainsString( 'x-button-link-arrow', $html );
		$this->assertStringContainsString( 'href="https://example.test/contact/"', $html );
		$this->assertStringContainsString( 'Get in touch', $html );
	}

	public function test_malformed_cta_is_skipped_and_logged_row_still_renders(): void {
		$html = $this->render_media_text(
			array(
				'rows' => array(
					array(
						'heading' => 'CTA-less row',
						'cta'     => array(
							'url'   => '',
							'title' => 'Dead link',
						),
					),
				),
			)
		);

		$this->assertStringContainsString( 'CTA-less row', $html );
		$this->assertStringNotContainsString( 'x-button', $html );
		$this->assertStringContainsString( 'malformed CTA', implode( ' ', $this->logs() ) );
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
						'heading'   => ' Our story ',
						'content'   => '<p>Copy <strong>here</strong>.</p><script>x()</script>',
						'mediaSide' => 'right',
						'cta'       => array(
							'url'    => '/about',
							'title'  => 'Read more',
							'target' => '_blank',
						),
					),
					array( 'content' => '<p>Text only.</p>' ),
				),
			)
		);

		$this->assertSame( array( 'rows' ), array_keys( $resolved['props'] ) );
		$first = $resolved['props']['rows'][0];
		$this->assertSame( array( 'image_id', 'heading', 'content', 'mediaSide', 'cta' ), array_keys( $first ) );
		$this->assertSame( 5, $first['image_id'] );
		$this->assertSame( 'Our story', $first['heading'] );
		$this->assertStringContainsString( '<strong>here</strong>', $first['content'] );
		$this->assertStringNotContainsString( '<script', $first['content'] );
		$this->assertSame( 'right', $first['mediaSide'] );
		$this->assertSame(
			array(
				'url'    => '/about',
				'title'  => 'Read more',
				'target' => '_blank',
			),
			$first['cta']
		);

		$second = $resolved['props']['rows'][1];
		$this->assertSame( 0, $second['image_id'] );
		$this->assertNull( $second['heading'] );
		$this->assertNull( $second['cta'] );
		$this->assertSame( 'left', $second['mediaSide'] );
		$this->assertTrue( $resolved['section']['has_heading'], 'The FIRST row\'s heading is the section heading.' );
	}

	public function test_resolver_applies_the_mode_rule_to_the_media_side(): void {
		$rows = array();
		foreach ( array( 'A', 'B', 'C' ) as $heading ) {
			$rows[] = array(
				'heading'   => $heading,
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
				'rows'    => array( array( 'heading' => 'Only' ) ),
				'spacing' => 'lg',
			),
			array(
				'index'      => 4,
				'is_primary' => true,
			)
		);

		$this->assertSame( 'media-text', $resolved['section']['name'] );
		$this->assertSame( 'lg', $resolved['section']['spacing'] );
		$this->assertTrue( $resolved['section']['is_primary'] );
		$this->assertTrue( $resolved['section']['has_heading'] );
	}

	public function test_view_draws_from_props_alone(): void {
		$this->seed_attachment( 5 );

		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/media-text.php',
			false,
			array(
				'props' => array(
					'rows' => array(
						array(
							'image_id'  => 5,
							'heading'   => 'First from props',
							'content'   => '<p>Already sanitised.</p>',
							'mediaSide' => 'right',
							'cta'       => array(
								'url'    => '/more',
								'title'  => 'More',
								'target' => '',
							),
						),
						array(
							'image_id'  => 0,
							'heading'   => 'Second from props',
							'content'   => null,
							'mediaSide' => 'left',
							'cta'       => null,
						),
					),
				),
				'plan'  => array(
					'index'         => 0,
					'headingLevel'  => 1,
					'priorityMedia' => false,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ), 'Row 0 draws at the section level.' );
		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ), 'Later rows step below an H1.' );
		$this->assertStringContainsString( 'x-media-text-media md:order-2', $html );
		$this->assertStringContainsString( '<div class="x-prose max-w-prose text-ink"><p>Already sanitised.</p></div>', $html );
		$this->assertStringContainsString( 'href="/more"', $html );
		$this->assertSame( 2, preg_match_all( '/class="x-media-text-row[" ]/', $html ) );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );
	}
}
