<?php
/**
 * Hero section unit tests (spec §10 #1; salt-contract markup/hero.json, rc.8).
 *
 * Covers the zero state (COMPL02, markup zeroState: eyebrow, heading,
 * subheading, image and buttons all empty), the contract's band and block,
 * the page's priority image (section#priority-media: fetchpriority high on
 * the first image, loading lazy on every other), the variants (stacked
 * draws itself; minimal ignores and logs a set image; unknown variants
 * degrade), malformed button skipping, H1 claiming (A11Y01) and the
 * FLEET07/DATA02 layout contract shape.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';
require_once __DIR__ . '/../shims/blog.php';

final class HeroSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();
	}

	/**
	 * Seed one hero FC row and render the section.
	 *
	 * @param array<string,mixed> $fields     Row fields.
	 * @param bool                $is_primary Loop context flag.
	 * @return string
	 */
	private function render_hero( array $fields = array(), bool $is_primary = true ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'hero',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'hero',
			array(
				'index'      => 0,
				'is_primary' => $is_primary,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * Seed the standard hero attachment fixture (resolves through
	 * salt_image_contract(): the upload at its own width, no sub-sizes).
	 *
	 * @return void
	 */
	private function seed_hero_image(): void {
		$GLOBALS['salt_test_attachments'][41] = array(
			'src'    => 'https://example.test/uploads/hero.jpg',
			'width'  => 2000,
			'height' => 1200,
			'alt'    => 'Studio workspace',
		);
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_all_fields_empty_renders_nothing(): void {
		$this->assertSame( '', $this->render_hero() );
	}

	/**
	 * Rows with no heading but something else to draw.
	 *
	 * @return array<string, array{0: array<string,mixed>, 1: string}>
	 */
	public static function headingless_rows(): array {
		return array(
			'subheading only' => array(
				array( 'subheading' => 'A lonely strapline.' ),
				'<p class="salt-hero__subheading">A lonely strapline.</p>',
			),
			'buttons only'    => array(
				array( 'buttons' => array( salt_test_button( 'Go', 'https://example.test/go/' ) ) ),
				'<div class="salt-hero__actions"><a class="salt-button" href="https://example.test/go/"',
			),
		);
	}

	/**
	 * Subheading or buttons alone now render (markup zeroState), as a div.
	 *
	 * @param array<string,mixed> $fields Row fields.
	 * @param string              $drawn  The part the band must draw.
	 */
	#[DataProvider( 'headingless_rows' )]
	public function test_a_headingless_hero_renders_a_div_band_that_does_not_claim_the_h1( array $fields, string $drawn ): void {
		$html = $this->render_hero( $fields );

		$this->assertStringStartsWith( '<div class="salt-section" id="hero" data-tone="surface"', $html, 'No heading: a div band, not a section, and no aria-labelledby (section#labelled-by).' );
		$this->assertStringEndsWith( '</div>', $html );
		$this->assertStringContainsString( $drawn, $html );
		$this->assertDoesNotMatchRegularExpression( '/<h[1-6][\s>]/', $html );
		$this->assertFalse( salt_h1_claimed(), 'A heading-less hero leaves the H1 for the page fallback (A11Y01).' );
	}

	public function test_heading_only_renders_the_contract_band_and_block_with_no_js(): void {
		$html = $this->render_hero( array( 'heading' => 'Welcome' ) );

		$this->assertSame(
			'<section class="salt-section" id="hero" aria-labelledby="hero__heading" data-tone="surface" data-tone-dark="surface" data-spacing="md" data-width="default" data-track="hero-1" style="--salt-section-space: var(--space-section-md)">'
			. '<div class="salt-section__content">'
			. '<div class="salt-block salt-hero" data-block="hero" data-variant="full-bleed">'
			. '<div class="salt-hero__text"><h1 id="hero__heading">Welcome</h1></div>'
			. '</div></div></section>',
			$html,
			'The id is minted from "hero"; no background, so no data-media and no img.'
		);
	}

	// -----------------------------------------------------------------
	// The priority image (section#priority-media, SC-026).
	// -----------------------------------------------------------------

	public function test_full_bleed_background_is_the_decorative_priority_image(): void {
		$this->seed_hero_image();

		$html = $this->render_hero(
			array(
				'heading'  => 'Welcome',
				'image'    => 41,
				'settings' => array( 'backgroundImage' => array( 'image' => 41 ) ),
			)
		);

		$this->assertStringContainsString( ' data-media=""', $html, 'The band says it draws a background.' );
		$this->assertStringContainsString(
			'><img src="https://example.test/uploads/hero.jpg" srcset="https://example.test/uploads/hero.jpg 2000w" sizes="100vw" width="2000" height="1200" alt="" class="salt-section__media" aria-hidden="true" style="object-fit: cover" fetchpriority="high" decoding="async"><div class="salt-section__scrim"',
			$html,
			'The wrapper draws the photograph behind the words, decorative and the priority image.'
		);
		$this->assertLessThan( strpos( $html, 'salt-section__content' ), strpos( $html, '<img' ), 'The background is the band\'s, before its content.' );
		$this->assertStringNotContainsString( 'loading=', $html, 'The priority image is never lazy, and eager is never written.' );
		$this->assertStringNotContainsString( 'Studio workspace', $html, 'A background is decorative: the attachment\'s alt is not used.' );
		$this->assertStringNotContainsString( 'salt-hero__media', $html, 'Full bleed draws no image of its own.' );
		$this->assertSame( 1, substr_count( $html, '<img' ) );
		$this->assertTrue( salt_render_state()['priority_media'], 'The page\'s priority image is taken.' );
	}

	public function test_the_priority_image_goes_to_the_first_hero_image_and_later_ones_are_lazy(): void {
		$this->seed_hero_image();
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'hero',
					'fields' => array(
						'variant' => 'split',
						'heading' => 'First',
						'image'   => 41,
					),
				),
				array(
					'layout' => 'hero',
					'fields' => array(
						'variant' => 'stacked',
						'heading' => 'Second',
						'image'   => 41,
					),
				),
			)
		);
		ob_start();
		get_template_part( 'template-parts/flexible-content' );
		$html = (string) ob_get_clean();

		$this->assertSame( 2, substr_count( $html, 'class="salt-hero__image"' ) );
		$this->assertSame( 1, substr_count( $html, 'fetchpriority="high"' ), 'One priority image per page.' );
		$this->assertSame( 1, substr_count( $html, 'loading="lazy"' ) );
		$this->assertStringNotContainsString( 'loading="eager"', $html );
		$this->assertMatchesRegularExpression( '/data-variant="split".*fetchpriority="high".*data-variant="stacked".*loading="lazy"/s', $html, 'The first image takes it; the later one is lazy.' );
	}

	// -----------------------------------------------------------------
	// Variants.
	// -----------------------------------------------------------------

	public function test_minimal_variant_ignores_a_set_image_and_logs(): void {
		$this->seed_hero_image();

		$html = $this->render_hero(
			array(
				'variant' => 'minimal',
				'heading' => 'Welcome',
				'image'   => 41,
			)
		);

		$this->assertStringContainsString( '<div class="salt-block salt-hero" data-block="hero" data-variant="minimal" data-align="left">', $html );
		$this->assertStringNotContainsString( '<img', $html, 'Minimal is text only.' );
		$this->assertStringContainsString( 'minimal variant ignores media — image 41', implode( ' ', $this->logs() ) );
	}

	public function test_minimal_variant_with_image_but_no_heading_renders_nothing(): void {
		$this->seed_hero_image();

		$html = $this->render_hero(
			array(
				'variant' => 'minimal',
				'image'   => 41,
			)
		);

		$this->assertSame( '', $html, 'Ignoring the image leaves nothing to render (COMPL02).' );
	}

	public function test_unknown_variant_falls_back_to_full_bleed_and_logs(): void {
		$html = $this->render_hero(
			array(
				'variant' => 'diagonal',
				'heading' => 'Welcome',
			)
		);

		$this->assertStringContainsString( 'data-variant="full-bleed"', $html );
		$this->assertStringNotContainsString( 'diagonal', $html );
		$this->assertStringContainsString( 'unknown variant "diagonal"', implode( ' ', $this->logs() ) );
	}

	public function test_split_variant_draws_its_image_after_the_text_on_its_media_side(): void {
		$this->seed_hero_image();

		$html = $this->render_hero(
			array(
				'variant'   => 'split',
				'heading'   => 'Welcome',
				'image'     => 41,
				'mediaSide' => 'right',
			)
		);

		$this->assertStringContainsString( '<div class="salt-block salt-hero" data-block="hero" data-variant="split" data-media-side="right">', $html );
		$this->assertStringContainsString(
			'<div class="salt-hero__text"><h1 id="hero__heading">Welcome</h1></div>'
			. '<div class="salt-hero__media"><img src="https://example.test/uploads/hero.jpg" srcset="https://example.test/uploads/hero.jpg 2000w" sizes="' . esc_attr( salt_image_slot_sizes( 'half', 'default' ) ) . '" width="2000" height="1200" alt="Studio workspace" class="salt-hero__image" fetchpriority="high" decoding="async"></div>',
			$html,
			'Text first, then the half-slot image with the attachment\'s alt.'
		);
		$this->assertStringNotContainsString( 'data-media=', $html, 'The block\'s image is not the band\'s background.' );
	}

	public function test_stacked_variant_draws_itself_with_the_content_image(): void {
		$this->seed_hero_image();

		$html = $this->render_hero(
			array(
				'variant'   => 'stacked',
				'heading'   => 'Welcome',
				'image'     => 41,
				'mediaSide' => 'right',
			)
		);

		$this->assertStringContainsString( '<div class="salt-block salt-hero" data-block="hero" data-variant="stacked"><div class="salt-hero__text">', $html, 'Stacked is drawn as itself, with no media side.' );
		$this->assertMatchesRegularExpression( '/<\/div><div class="salt-hero__media"><img [^>]*class="salt-hero__image"[^>]*><\/div><\/div><\/div><\/section>$/', $html, 'Text, then the image.' );
		$this->assertStringContainsString( 'sizes="' . esc_attr( salt_image_slot_sizes( 'content', 'default' ) ) . '"', $html, 'Stacked uses the content slot, not split\'s half.' );
	}

	public function test_full_bleed_ignores_the_split_image_field(): void {
		$this->seed_hero_image();

		$resolved = $this->resolve_hero(
			array(
				'variant' => 'full-bleed',
				'heading' => 'Welcome',
				'image'   => 41,
			)
		);

		$this->assertNull( $resolved['props']['image'], 'A full-bleed hero draws the background image, not image.' );
		$this->assertFalse( $resolved['plan']['priorityMedia'], 'No image of its own, so it does not ask for the priority image.' );
		$this->assertContains( 'section hero: full-bleed draws the background image, and none is set — image 41 not rendered.', $this->logs(), 'The dropped image is logged (review #6).' );
	}

	public function test_full_bleed_with_a_background_does_not_log_its_image_field(): void {
		$this->seed_hero_image();

		$this->resolve_hero(
			array(
				'variant'  => 'full-bleed',
				'heading'  => 'Welcome',
				'image'    => 41,
				'settings' => array( 'backgroundImage' => array( 'image' => 41 ) ),
			)
		);

		$this->assertSame( array(), $this->logs(), 'The photograph is drawn, as the background.' );
	}

	public function test_an_image_acf_returns_as_an_array_is_drawn(): void {
		$this->seed_hero_image();

		// Review #4: an image field set to return an array gives ['ID' => …].
		$resolved = $this->resolve_hero(
			array(
				'variant' => 'split',
				'heading' => 'Welcome',
				'image'   => array(
					'ID'  => 41,
					'url' => 'https://example.test/uploads/hero.jpg',
				),
			)
		);

		$this->assertSame( 'https://example.test/uploads/hero.jpg', $resolved['props']['image']['src'] ?? null );
	}

	public function test_an_image_with_no_dimensions_is_dropped_and_logged(): void {
		$this->seed_hero_image();
		$GLOBALS['salt_test_attachments'][41]['height'] = 0;

		$resolved = $this->resolve_hero(
			array(
				'variant' => 'stacked',
				'heading' => 'Welcome',
				'image'   => 41,
			)
		);

		$this->assertNull( $resolved['props']['image'] );
		$this->assertContains( 'section hero: image 41 has no width or height — not rendered.', $this->logs(), 'Review #5.' );
	}

	public function test_an_internal_button_links_to_its_document(): void {
		// Either get_permalink() shim may be loaded first: give both the address.
		$GLOBALS['salt_test_permalinks'][7]       = 'https://example.test/about-us/';
		$GLOBALS['salt_test_schema']['permalink'] = 'https://example.test/about-us/';

		$resolved = $this->resolve_hero(
			array(
				'heading' => 'Welcome',
				'buttons' => array(
					array(
						'link'  => array(
							'label'    => 'About us',
							'type'     => 'internal',
							'document' => 7,
						),
						'style' => 'ghost',
					),
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'label'  => 'About us',
					'href'   => 'https://example.test/about-us/',
					'style'  => 'ghost',
					'newTab' => false,
				),
			),
			$resolved['props']['buttons']
		);
	}

	public function test_split_variant_without_an_image_degrades_to_a_text_block(): void {
		$html = $this->render_hero(
			array(
				'variant'   => 'split',
				'heading'   => 'Welcome',
				'mediaSide' => 'right',
			)
		);

		$this->assertStringContainsString( '<div class="salt-block salt-hero" data-block="hero" data-variant="split"><div class="salt-hero__text">', $html, 'No image drawn: no data-media-side.' );
		$this->assertStringNotContainsString( 'salt-hero__media', $html );
		$this->assertStringContainsString( 'Welcome', $html );
	}

	// -----------------------------------------------------------------
	// Headings (A11Y01).
	// -----------------------------------------------------------------

	public function test_primary_hero_heading_claims_the_h1(): void {
		$html = $this->render_hero( array( 'heading' => 'Welcome' ), true );

		$this->assertStringContainsString( 'aria-labelledby="hero__heading"', $html );
		$this->assertStringContainsString( '<h1 id="hero__heading">Welcome</h1>', $html );
		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertTrue( salt_h1_claimed() );
	}

	public function test_headingless_hero_with_image_does_not_claim_the_h1(): void {
		$this->seed_hero_image();

		$html = $this->render_hero( array( 'settings' => array( 'backgroundImage' => array( 'image' => 41 ) ) ), true );

		$this->assertStringStartsWith( '<div class="salt-section" id="hero"', $html );
		$this->assertStringNotContainsString( 'aria-labelledby', $html );
		$this->assertSame( 0, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less hero leaves the H1 claim for the page fallback.' );
	}

	// -----------------------------------------------------------------
	// Buttons.
	// -----------------------------------------------------------------

	public function test_buttons_render_their_styles_with_the_sites_arrow(): void {
		$html = $this->render_hero(
			array(
				'heading' => 'Welcome',
				'buttons' => array(
					salt_test_button( 'Get started', 'https://example.test/start/' ),
					salt_test_button( 'Learn more', 'https://example.test/about/', 'secondary' ),
				),
			)
		);

		$this->assertStringContainsString(
			'<div class="salt-hero__actions">'
			. '<a class="salt-button" href="https://example.test/start/" data-style="primary" data-track-control="cta"><span class="salt-button__label">Get started<span class="salt-arrow" aria-hidden="true">&#x2060;→</span></span></a>'
			. '<a class="salt-button" href="https://example.test/about/" data-style="secondary" data-track-control="cta"><span class="salt-button__label">Learn more<span class="salt-arrow" aria-hidden="true">&#x2060;→</span></span></a>'
			. '</div>',
			$html
		);
	}

	public function test_malformed_button_is_skipped_and_logged(): void {
		$html = $this->render_hero(
			array(
				'heading' => 'Welcome',
				'buttons' => array( salt_test_button( 'No destination', '' ) ),
			)
		);

		$this->assertStringNotContainsString( 'No destination', $html );
		$this->assertStringNotContainsString( 'salt-hero__actions', $html, 'No empty actions row.' );
		$this->assertStringContainsString( 'section hero: button 0 has no label or no address', implode( ' ', $this->logs() ) );
	}

	public function test_new_tab_button_opens_a_new_tab(): void {
		$html = $this->render_hero(
			array(
				'heading' => 'Welcome',
				'buttons' => array( salt_test_button( 'External', 'https://example.test/out/', 'primary', true ) ),
			)
		);

		$this->assertStringContainsString( 'data-track-control="cta" target="_blank" rel="noopener noreferrer">', $html );
	}

	// -----------------------------------------------------------------
	// Layout contract (FLEET07 / DATA02).
	// -----------------------------------------------------------------

	public function test_layout_is_generated_from_the_contract(): void {
		$layout = salt_test_layout( 'hero' );

		$this->assertSame( 'layout_salt_hero', $layout['key'] );
		$this->assertSame(
			array( 'variant', 'eyebrow', 'heading', 'subheading', 'image', 'mediaSide', 'alignment', 'buttons', '', 'settings' ),
			array_column( $layout['sub_fields'], 'name' )
		);

		$by_name = array_column( $layout['sub_fields'], null, 'name' );
		$this->assertSame( array( 'full-bleed', 'split', 'stacked', 'minimal' ), array_keys( $by_name['variant']['choices'] ) );
		$this->assertSame( 'id', $by_name['image']['return_format'], 'Image is an attachment ID.' );
		$this->assertSame( 2, $by_name['buttons']['max'] );

		foreach ( array_filter( array_column( $layout['sub_fields'], 'key' ) ) as $key ) {
			$this->assertStringStartsWith( 'field_salt_hero_', (string) $key, 'DATA02 key convention.' );
		}
	}

	public function test_zero_state_queues_no_schema_nodes(): void {
		$this->render_hero();

		$this->assertSame( array(), salt_schema_queue() );
	}

	// -----------------------------------------------------------------
	// Resolver (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one hero FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row fields.
	 * @return array<string,mixed>|null
	 */
	private function resolve_hero( array $fields ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'hero',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'hero' );
		$this->assertSame( 'salt_resolve_section_hero', $resolver );

		return $resolver(
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
	}

	public function test_resolver_returns_null_for_the_zero_state(): void {
		$this->seed_hero_image();

		$this->assertNull(
			$this->resolve_hero(
				array(
					'eyebrow'    => ' ',
					'heading'    => '',
					'subheading' => '  ',
					'image'      => 41,
					'buttons'    => array( salt_test_button( 'No destination', '' ) ),
				)
			),
			'Blank text, a full-bleed hero\'s unused image field and no usable button are the zero state.'
		);
	}

	public function test_resolver_props_use_the_contract_names_and_null_blank_text(): void {
		$resolved = $this->resolve_hero(
			array(
				'variant'    => 'minimal',
				'eyebrow'    => '   ',
				'heading'    => 'Welcome',
				'subheading' => '',
				'alignment'  => 'centre',
			)
		);

		$this->assertSame(
			array( 'variant', 'eyebrow', 'heading', 'subheading', 'image', 'mediaSide', 'alignment', 'buttons', 'arrow' ),
			array_keys( $resolved['props'] )
		);
		$this->assertSame( 'minimal', $resolved['props']['variant'] );
		$this->assertNull( $resolved['props']['eyebrow'], 'Blank text is null, never an empty string.' );
		$this->assertSame( 'Welcome', $resolved['props']['heading'] );
		$this->assertNull( $resolved['props']['subheading'] );
		$this->assertNull( $resolved['props']['image'] );
		$this->assertSame( 'left', $resolved['props']['mediaSide'] );
		$this->assertSame( 'centre', $resolved['props']['alignment'] );
		$this->assertSame( array(), $resolved['props']['buttons'] );
		$this->assertSame( '→', $resolved['props']['arrow'], 'The site\'s arrow (salt_contract_arrow()).' );
		$this->assertSame( array( 'priorityMedia' => false ), $resolved['plan'], 'A hero with no image does not ask for the page\'s priority image (SC-026).' );
	}

	public function test_resolver_owns_the_wrapper_inputs(): void {
		$this->seed_hero_image();

		$resolved = $this->resolve_hero(
			array(
				'variant'  => 'full-bleed',
				'settings' => array(
					'width'           => 'wide',
					'backgroundImage' => array( 'image' => 41 ),
				),
			)
		);

		$this->assertSame( 'hero', $resolved['section']['name'] );
		$this->assertTrue( $resolved['section']['contract'], 'Drawn in the contract\'s wrapper (M4).' );
		$this->assertSame( 'wide', $resolved['section']['container'], 'The width is the editor\'s, not forced to full.' );
		$this->assertArrayNotHasKey( 'flush_top', $resolved['section'] );
		$this->assertSame( 'https://example.test/uploads/hero.jpg', $resolved['section']['background_image']['image']['src'], 'The photograph is the band\'s background.' );
		$this->assertTrue( $resolved['section']['is_primary'] );
		$this->assertFalse( $resolved['section']['has_heading'], 'Heading-less hero must not claim the H1.' );
		$this->assertNull( $resolved['props']['image'] );
		$this->assertSame( array( 'priorityMedia' => false ), $resolved['plan'], 'The background is the wrapper\'s priority image, not the block\'s.' );
	}

	public function test_resolver_asks_for_the_priority_image_only_for_an_image_it_draws(): void {
		$this->seed_hero_image();

		$resolved = $this->resolve_hero(
			array(
				'variant' => 'split',
				'heading' => 'Welcome',
				'image'   => 41,
			)
		);

		$this->assertSame( 'https://example.test/uploads/hero.jpg', $resolved['props']['image']['src'] );
		$this->assertSame( salt_image_slot_sizes( 'half', 'default' ), $resolved['props']['image']['sizes'] );
		$this->assertSame( array( 'priorityMedia' => true ), $resolved['plan'] );

		salt_render_state( 'set', 'priority_media', true );
		$later = $this->resolve_hero(
			array(
				'variant' => 'split',
				'heading' => 'Later',
				'image'   => 41,
			)
		);
		$this->assertSame( array( 'priorityMedia' => false ), $later['plan'], 'Once taken, a later hero does not ask.' );
	}

	public function test_resolver_drops_the_minimal_image_and_skips_malformed_buttons(): void {
		$this->seed_hero_image();

		$resolved = $this->resolve_hero(
			array(
				'variant' => 'minimal',
				'heading' => 'Welcome',
				'image'   => 41,
				'buttons' => array(
					salt_test_button( 'No destination', '' ),
					salt_test_button( 'About', 'https://example.test/about/', 'secondary', true ),
				),
			)
		);

		$this->assertNull( $resolved['props']['image'] );
		$this->assertSame(
			array(
				array(
					'label'  => 'About',
					'href'   => 'https://example.test/about/',
					'style'  => 'secondary',
					'newTab' => true,
				),
			),
			$resolved['props']['buttons']
		);

		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'minimal variant ignores media', $logs );
		$this->assertStringContainsString( 'section hero: button 0 has no label or no address', $logs );
	}

	public function test_view_draws_from_props_alone(): void {
		// No FC row at all: everything the view prints must come from props.
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/hero.php',
			false,
			array(
				'props' => array(
					'variant'    => 'split',
					'eyebrow'    => 'Eyebrow',
					'heading'    => 'From props',
					'subheading' => 'Drawn, not read.',
					'image'      => array(
						'src'        => 'https://example.test/p.jpg',
						'srcset'     => null,
						'sizes'      => '50vw',
						'width'      => 800,
						'height'     => 600,
						'alt'        => 'Alt from props',
						'caption'    => null,
						'focalPoint' => null,
					),
					'mediaSide'  => 'left',
					'alignment'  => 'left',
					'buttons'    => array(
						array(
							'label'  => 'Go',
							'href'   => 'https://example.test/go/',
							'style'  => 'ghost',
							'newTab' => false,
						),
					),
					'arrow'      => null,
				),
				'plan'  => array(
					'index'         => 0,
					'anchor'        => 'welcome',
					'headingLevel'  => 2,
					'headingId'     => 'welcome__heading',
					'bodyBaseLevel' => 2,
					'priorityMedia' => false,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame(
			'<div class="salt-block salt-hero" data-block="hero" data-variant="split" data-media-side="left">'
			. '<div class="salt-hero__text"><p class="salt-eyebrow">Eyebrow</p><h2 id="welcome__heading">From props</h2><p class="salt-hero__subheading">Drawn, not read.</p>'
			. '<div class="salt-hero__actions"><a class="salt-button" href="https://example.test/go/" data-style="ghost" data-track-control="cta">Go</a></div></div>'
			. '<div class="salt-hero__media"><img src="https://example.test/p.jpg" sizes="50vw" width="800" height="600" alt="Alt from props" class="salt-hero__image" loading="lazy" decoding="async"></div>'
			. '</div>',
			$html,
			'The block only, from props and plan: the band is the entry\'s, not the view\'s.'
		);
	}
}
