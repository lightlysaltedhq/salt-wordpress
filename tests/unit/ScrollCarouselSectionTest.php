<?php
/**
 * Scroll Carousel slice unit tests: layout shape (DATA02), section 0/1/N
 * states (COMPL02 — 1 card is static with NO JS trigger attribute, FAIL02),
 * generic vs testimonial card markup, malformed rows skipped + logged,
 * escaping, x-image composition, and the no-schema invariant.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';

final class ScrollCarouselSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		$GLOBALS['salt_test_attachments'] = array();
	}

	/**
	 * Render ONE scroll-carousel section row.
	 *
	 * @param array<string,mixed> $fields  Row sub-field fixtures.
	 * @param array<string,mixed> $context Loop context.
	 * @return string
	 */
	private function render_carousel_section( array $fields = array(), array $context = array(
		'index'      => 0,
		'is_primary' => true,
	) ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'scroll_carousel',
					'fields' => $fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section( 'scroll-carousel', $context );
		return (string) ob_get_clean();
	}

	/**
	 * Three valid generic card fixtures.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function three_cards(): array {
		return array(
			array(
				'title' => 'First card',
				'text'  => 'First text.',
			),
			array(
				'title' => 'Second card',
				'text'  => 'Second text.',
			),
			array(
				'title' => 'Third card',
				'text'  => 'Third text.',
			),
		);
	}

	// -----------------------------------------------------------------
	// Layout shape (DATA02 / FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_file_shape_and_key_conventions(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/scroll-carousel.php';

		$this->assertSame( 'scroll_carousel', $layout['name'] );
		$this->assertSame( 'layout_salt_scroll_carousel', $layout['key'] );

		$names = array_map(
			static fn( array $field ): string => (string) ( $field['name'] ?? '' ),
			$layout['sub_fields']
		);

		// Content fields first, wrapper controls last.
		$this->assertSame(
			array( 'heading', 'eyebrow', 'card_type', 'cards' ),
			array_slice( array_values( array_filter( $names ) ), 0, 4 )
		);
		$this->assertGreaterThan( array_search( 'cards', $names, true ), array_search( 'section_spacing', $names, true ) );

		$cards = null;
		foreach ( $layout['sub_fields'] as $field ) {
			if ( 'cards' === ( $field['name'] ?? '' ) ) {
				$cards = $field;
			}
		}

		$this->assertNotNull( $cards );
		$this->assertSame( 'repeater', $cards['type'] );
		$this->assertSame(
			array( 'image', 'title', 'text', 'role' ),
			array_map( static fn( array $sub ): string => (string) $sub['name'], $cards['sub_fields'] )
		);
		foreach ( $cards['sub_fields'] as $sub ) {
			$this->assertStringStartsWith( 'field_salt_scroll_carousel_cards_', (string) $sub['key'] );
		}
	}

	// -----------------------------------------------------------------
	// States (COMPL02 / FAIL02).
	// -----------------------------------------------------------------

	public function test_zero_cards_renders_nothing(): void {
		$this->assertSame( '', $this->render_carousel_section( array( 'heading' => 'Highlights' ) ) );
		$this->assertSame( '', $this->render_carousel_section( array( 'cards' => array() ) ) );
		$this->assertSame( '', $this->render_carousel_section( array( 'cards' => 'not-an-array' ) ) );
	}

	public function test_single_card_is_static_with_no_js_trigger(): void {
		$html = $this->render_carousel_section(
			array(
				'heading' => 'Highlights',
				'cards'   => array(
					array(
						'title' => 'Only card',
						'text'  => 'Some text.',
					),
				),
			)
		);

		$this->assertStringContainsString( 'x-scroll-carousel-single', $html );
		$this->assertStringContainsString( 'Only card', $html );
		$this->assertStringNotContainsString( 'data-salt-carousel', $html, '1 card => no JS trigger (COMPL02/FAIL02).' );
		$this->assertStringNotContainsString( 'x-scroll-carousel-track', $html );
		$this->assertStringNotContainsString( '<button', $html, 'No dead buttons in the no-JS base.' );
	}

	public function test_n_cards_render_a_functional_scroll_snap_track(): void {
		$html = $this->render_carousel_section(
			array(
				'heading' => 'Highlights',
				'cards'   => $this->three_cards(),
			)
		);

		$this->assertStringContainsString( 'data-salt-carousel', $html );
		$this->assertStringContainsString( 'snap-x', $html, 'No-JS base is a CSS scroll-snap track (FAIL02).' );
		$this->assertStringContainsString( 'overflow-x-auto', $html );
		$this->assertStringContainsString( 'tabindex="0"', $html, 'Track is keyboard-focusable.' );
		$this->assertStringContainsString( 'aria-label="Highlights"', $html );
		$this->assertStringContainsString( 'data-salt-prev-label=', $html );
		$this->assertStringContainsString( 'data-salt-next-label=', $html );
		$this->assertSame( 3, preg_match_all( '/x-scroll-carousel-card/', $html ) );
		$this->assertStringNotContainsString( '<button', $html, 'Prev/next arrive only via the JS enhancement.' );
	}

	// -----------------------------------------------------------------
	// Card types.
	// -----------------------------------------------------------------

	public function test_generic_cards_are_divs_with_stepped_headings(): void {
		$html = $this->render_carousel_section(
			array(
				'heading' => 'Highlights',
				'cards'   => $this->three_cards(),
			)
		);

		$this->assertStringNotContainsString( '<blockquote', $html );
		$this->assertStringNotContainsString( '<figure', $html );
		// Section heading is h1 (primary); card titles step to h2.
		$this->assertSame( 3, preg_match_all( '/<h2\b/', $html ) );
	}

	public function test_testimonial_cards_are_blockquote_styled(): void {
		$html = $this->render_carousel_section(
			array(
				'card_type' => 'testimonial',
				'cards'     => array(
					array(
						'title' => 'Jane Doe',
						'text'  => 'Salt transformed our site.',
						'role'  => 'Founder, Acme',
					),
					array(
						'title' => 'John Smith',
						'text'  => 'Brilliant work.',
					),
				),
			)
		);

		$this->assertSame( 2, preg_match_all( '/<figure\b/', $html ) );
		$this->assertSame( 2, preg_match_all( '/<blockquote\b/', $html ) );
		$this->assertStringContainsString( 'Salt transformed our site.', $html );
		$this->assertStringContainsString( 'Jane Doe', $html );
		$this->assertStringContainsString( 'Founder, Acme', $html );
		$this->assertSame( 0, preg_match_all( '/<h[2-6]\b/', $html ), 'Testimonial attribution is never a heading.' );
	}

	public function test_unknown_card_type_falls_back_to_generic(): void {
		$html = $this->render_carousel_section(
			array(
				'card_type' => 'weird',
				'cards'     => $this->three_cards(),
			)
		);

		$this->assertStringNotContainsString( '<blockquote', $html );
		$this->assertSame( 3, preg_match_all( '/x-scroll-carousel-card/', $html ) );
	}

	public function test_card_image_renders_through_x_image(): void {
		$GLOBALS['salt_test_attachments'][7] = array(
			'src'    => 'https://example.test/wp-content/uploads/avatar.jpg',
			'width'  => 400,
			'height' => 400,
			'srcset' => '',
			'alt'    => 'Portrait of Jane',
		);

		$html = $this->render_carousel_section(
			array(
				'cards' => array(
					array(
						'image' => 7,
						'title' => 'With image',
						'text'  => 'Text.',
					),
					array(
						'title' => 'Without image',
						'text'  => 'Text.',
					),
				),
			)
		);

		$this->assertStringContainsString( 'x-image', $html );
		$this->assertStringContainsString( 'avatar.jpg', $html );
		$this->assertSame( 1, preg_match_all( '/<img\b/', $html ), 'Only the card with an image renders one.' );
	}

	// -----------------------------------------------------------------
	// Broken rows + escaping.
	// -----------------------------------------------------------------

	public function test_malformed_cards_are_skipped_and_logged(): void {
		$html = $this->render_carousel_section(
			array(
				'cards' => array(
					array(
						'title' => 'Kept',
						'text'  => 'Valid.',
					),
					array(
						'title' => 'Kept too',
						'text'  => '',
					),
					'not-a-row',
					array(
						'title' => '  ',
						'text'  => '',
					),
				),
			)
		);

		$this->assertSame( 2, preg_match_all( '/x-scroll-carousel-card/', $html ), 'Only valid cards render.' );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'scroll-carousel section: card 2 is not a row array', $logs );
		$this->assertStringContainsString( 'scroll-carousel section: card 3 has no title or text', $logs );
	}

	public function test_all_cards_malformed_renders_nothing(): void {
		$html = $this->render_carousel_section(
			array( 'cards' => array( array( 'role' => 'no title, no text' ) ) )
		);

		$this->assertSame( '', $html, 'Never a blank band (COMPL02).' );
		$this->assertNotSame( array(), $this->logs() );
	}

	public function test_card_values_are_escaped(): void {
		$html = $this->render_carousel_section(
			array(
				'card_type' => 'testimonial',
				'cards'     => array(
					array(
						'title' => '<script>1</script>',
						'text'  => '<em>quote</em>',
						'role'  => '<b>role</b>',
					),
					array(
						'title' => 'Second',
						'text'  => 'Quote.',
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<em>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$html = $this->render_carousel_section(
			array( 'cards' => $this->three_cards() )
		);

		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less primary section must not consume the H1 claim (A11Y01).' );
	}

	// -----------------------------------------------------------------
	// No schema for this section (§10 #18).
	// -----------------------------------------------------------------

	public function test_no_schema_nodes_are_queued(): void {
		salt_schema_reset();

		$graph_args = array(
			'home_url' => 'https://example.test',
			'page_url' => 'https://example.test/p',
		);
		$baseline   = count( salt_schema_graph( $graph_args )['@graph'] );

		$this->render_carousel_section(
			array(
				'heading' => 'Highlights',
				'cards'   => $this->three_cards(),
			)
		);

		$this->assertCount( $baseline, salt_schema_graph( $graph_args )['@graph'], 'Scroll carousel queues no schema nodes.' );
	}

	// -----------------------------------------------------------------
	// Logic/view split (ID-3587): resolver and view.
	// -----------------------------------------------------------------

	/**
	 * Seed one scroll_carousel FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return array<string,mixed>|null
	 */
	private function resolve_carousel( array $fields ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'scroll_carousel',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'scroll-carousel' );
		$this->assertSame( 'salt_resolve_section_scroll_carousel', $resolver );

		return $resolver(
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
	}

	public function test_resolver_returns_null_without_a_valid_card(): void {
		$this->assertNull(
			$this->resolve_carousel(
				array(
					'heading' => 'Carousel',
					'cards'   => array(
						'not a row',
						array(
							'title' => ' ',
							'text'  => '',
						),
					),
				)
			)
		);
		$this->assertCount( 2, $this->logs(), 'Each broken row is logged.' );
	}

	public function test_resolver_props_keep_wordpress_local_names(): void {
		$resolved = $this->resolve_carousel(
			array(
				'eyebrow'   => 'Eyebrow',
				'heading'   => ' Voices ',
				'card_type' => 'testimonial',
				'cards'     => array(
					array(
						'title' => ' Ada ',
						'text'  => ' Great work. ',
						'role'  => ' CTO ',
						'image' => array( 'ID' => 77 ),
					),
					array(
						'text'  => 'Only text',
						'image' => 'not numeric',
					),
				),
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'card_type', 'cards' ), array_keys( $resolved['props'] ) );
		$this->assertSame( ' Voices ', $resolved['props']['heading'], 'Optional text is never trimmed.' );
		$this->assertSame( 'testimonial', $resolved['props']['card_type'] );
		$this->assertSame(
			array(
				array(
					'image_id' => 77,
					'title'    => 'Ada',
					'text'     => 'Great work.',
					'role'     => 'CTO',
				),
				array(
					'image_id' => 0,
					'title'    => '',
					'text'     => 'Only text',
					'role'     => '',
				),
			),
			$resolved['props']['cards'],
			'image_id is passed through unvalidated, as before the split (x-image decides).'
		);
		$this->assertSame( 'scroll-carousel', $resolved['section']['name'] );
		$this->assertTrue( $resolved['section']['has_heading'] );
	}

	public function test_resolver_defaults_card_type_and_nulls_blank_text(): void {
		$resolved = $this->resolve_carousel(
			array(
				'heading'   => '  ',
				'card_type' => 'bogus',
				'cards'     => array( array( 'title' => 'One' ) ),
			)
		);

		$this->assertNull( $resolved['props']['eyebrow'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertSame( 'generic', $resolved['props']['card_type'] );
		$this->assertFalse( $resolved['section']['has_heading'] );
	}

	/**
	 * Draw the scroll-carousel view from props alone.
	 *
	 * @param array<string,mixed> $props View props.
	 * @return string
	 */
	private function draw_carousel_view( array $props ): string {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/scroll-carousel.php',
			false,
			array(
				'props' => $props,
				'plan'  => array(
					'index'         => 0,
					'headingLevel'  => 2,
					'priorityMedia' => false,
				),
			)
		);
		return (string) ob_get_clean();
	}

	public function test_view_draws_from_props_alone(): void {
		$html = $this->draw_carousel_view(
			array(
				'eyebrow'   => null,
				'heading'   => null,
				'card_type' => 'generic',
				'cards'     => array(
					array(
						'image_id' => 0,
						'title'    => 'First',
						'text'     => 'One',
						'role'     => '',
					),
					array(
						'image_id' => 0,
						'title'    => 'Second',
						'text'     => '',
						'role'     => '',
					),
				),
			)
		);

		$this->assertStringContainsString( 'data-salt-carousel="scroll-carousel"', $html );
		$this->assertStringContainsString( 'aria-label="Carousel"', $html, 'No heading: the track gets the fallback name.' );
		$this->assertSame( 2, preg_match_all( '/<h3[\s>]/', $html ), 'Card titles one level below the plan heading level.' );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );
	}

	public function test_view_draws_one_card_without_the_track(): void {
		$html = $this->draw_carousel_view(
			array(
				'eyebrow'   => null,
				'heading'   => 'Voices',
				'card_type' => 'testimonial',
				'cards'     => array(
					array(
						'image_id' => 0,
						'title'    => 'Ada',
						'text'     => 'Great.',
						'role'     => 'CTO',
					),
				),
			)
		);

		$this->assertStringContainsString( '<div class="x-scroll-carousel-single max-w-2xl"><figure', $html );
		$this->assertStringNotContainsString( 'data-salt-carousel', $html );
		$this->assertStringContainsString( 'x-scroll-carousel-role', $html );
	}
}
