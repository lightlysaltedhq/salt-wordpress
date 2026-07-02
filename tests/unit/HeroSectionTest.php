<?php
/**
 * Hero section unit tests (spec §10 #1).
 *
 * Covers the COMPL02 states (empty heading + image renders nothing), the
 * LCP image posture (§8: eager + fetchpriority, never lazy), variant
 * handling (minimal ignores + logs a set image; unknown variants degrade),
 * malformed CTA skipping, H1 claiming (A11Y01) and the FLEET07/DATA02
 * layout contract shape.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';

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
	 * Seed the standard hero attachment fixture.
	 *
	 * @return void
	 */
	private function seed_hero_image(): void {
		$GLOBALS['salt_test_attachments'][41] = array(
			'src'    => 'https://example.test/uploads/hero.jpg',
			'width'  => 2000,
			'height' => 1200,
			'srcset' => 'https://example.test/uploads/hero-1000.jpg 1000w, https://example.test/uploads/hero.jpg 2000w',
			'alt'    => 'Studio workspace',
		);
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_empty_heading_and_image_renders_nothing(): void {
		$this->assertSame( '', $this->render_hero() );
	}

	public function test_subheading_and_cta_alone_still_render_nothing(): void {
		$html = $this->render_hero(
			array(
				'subheading'  => 'A lonely strapline.',
				'cta_primary' => array(
					'title' => 'Go',
					'url'   => 'https://example.test/go/',
				),
			)
		);

		$this->assertSame( '', $html, 'No heading and no image is an empty band — skipped (COMPL02).' );
	}

	public function test_heading_only_renders_a_static_hero_with_no_js(): void {
		$html = $this->render_hero( array( 'heading' => 'Welcome' ) );

		$this->assertStringContainsString( 'Welcome', $html );
		$this->assertStringContainsString( 'data-section="hero"', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	// -----------------------------------------------------------------
	// LCP image posture (§8).
	// -----------------------------------------------------------------

	public function test_hero_image_is_eager_with_fetchpriority_high(): void {
		$this->seed_hero_image();

		$html = $this->render_hero(
			array(
				'heading' => 'Welcome',
				'image'   => 41,
			)
		);

		$this->assertStringContainsString( 'loading="eager" fetchpriority="high"', $html );
		$this->assertStringNotContainsString( 'loading="lazy"', $html, 'The LCP image is never lazy (§8).' );
		$this->assertStringContainsString( 'alt="Studio workspace"', $html, 'Stored attachment alt is used.' );
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

		$this->assertStringContainsString( 'x-hero-full-bleed', $html );
		$this->assertStringContainsString( 'unknown variant "diagonal"', implode( ' ', $this->logs() ) );
	}

	public function test_full_bleed_forces_the_full_container(): void {
		$this->seed_hero_image();

		$html = $this->render_hero(
			array(
				'variant' => 'full-bleed',
				'heading' => 'Welcome',
				'image'   => 41,
			)
		);

		$this->assertStringContainsString( '<div class="w-full">', $html, 'Wrapper container is forced to full.' );
		$this->assertStringContainsString( 'x-hero-full-bleed', $html );
	}

	public function test_split_variant_uses_a_two_column_grid(): void {
		$this->seed_hero_image();

		$html = $this->render_hero(
			array(
				'variant' => 'split',
				'heading' => 'Welcome',
				'image'   => 41,
			)
		);

		$this->assertStringContainsString( 'md:grid-cols-2', $html );
	}

	public function test_split_variant_without_an_image_degrades_to_a_text_block(): void {
		$html = $this->render_hero(
			array(
				'variant' => 'split',
				'heading' => 'Welcome',
			)
		);

		$this->assertStringNotContainsString( 'md:grid-cols-2', $html );
		$this->assertStringContainsString( 'Welcome', $html );
	}

	// -----------------------------------------------------------------
	// Headings (A11Y01).
	// -----------------------------------------------------------------

	public function test_primary_hero_heading_claims_the_h1(): void {
		$html = $this->render_hero( array( 'heading' => 'Welcome' ), true );

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertTrue( salt_h1_claimed() );
	}

	public function test_headingless_hero_with_image_does_not_claim_the_h1(): void {
		$this->seed_hero_image();

		$html = $this->render_hero( array( 'image' => 41 ), true );

		$this->assertSame( 0, preg_match_all( '/<h1[\s>]/', $html ) );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less hero leaves the H1 claim for the page fallback.' );
	}

	// -----------------------------------------------------------------
	// CTAs.
	// -----------------------------------------------------------------

	public function test_ctas_render_primary_and_secondary_styles(): void {
		$html = $this->render_hero(
			array(
				'heading'       => 'Welcome',
				'cta_primary'   => array(
					'title'  => 'Get started',
					'url'    => 'https://example.test/start/',
					'target' => '',
				),
				'cta_secondary' => array(
					'title' => 'Learn more',
					'url'   => 'https://example.test/about/',
				),
			)
		);

		$this->assertStringContainsString( 'x-button-primary', $html );
		$this->assertStringContainsString( 'Get started', $html );
		$this->assertStringContainsString( 'x-button-secondary', $html );
		$this->assertStringContainsString( 'Learn more', $html );
	}

	public function test_malformed_cta_is_skipped_and_logged(): void {
		$html = $this->render_hero(
			array(
				'heading'     => 'Welcome',
				'cta_primary' => array( 'title' => 'No destination' ),
			)
		);

		$this->assertStringNotContainsString( 'No destination', $html );
		$this->assertStringNotContainsString( 'x-hero-ctas', $html, 'No empty CTA row.' );
		$this->assertStringContainsString( 'cta_primary link is malformed', implode( ' ', $this->logs() ) );
	}

	public function test_blank_target_cta_opens_in_the_same_tab(): void {
		$html = $this->render_hero(
			array(
				'heading'     => 'Welcome',
				'cta_primary' => array(
					'title'  => 'External',
					'url'    => 'https://example.test/out/',
					'target' => '_blank',
				),
			)
		);

		$this->assertStringContainsString( 'target="_blank" rel="noopener noreferrer"', $html );
	}

	// -----------------------------------------------------------------
	// Layout contract (FLEET07 / DATA02).
	// -----------------------------------------------------------------

	public function test_layout_file_returns_the_frozen_contract_shape(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/hero.php';

		$this->assertSame( 'hero', $layout['name'] );
		$this->assertSame( 'layout_salt_hero', $layout['key'] );

		$names = array_values( array_filter( array_column( $layout['sub_fields'], 'name' ) ) );

		// Content fields first…
		$this->assertSame(
			array( 'variant', 'eyebrow', 'heading', 'subheading', 'image', 'cta_primary', 'cta_secondary' ),
			array_slice( $names, 0, 7 )
		);
		// …then the §11 wrapper fields last.
		$this->assertSame( 'section_divider', end( $names ) );

		$by_name = array_column( $layout['sub_fields'], null, 'name' );
		$this->assertSame( array( 'full-bleed', 'split', 'minimal' ), array_keys( $by_name['variant']['choices'] ) );
		$this->assertSame( 'id', $by_name['image']['return_format'], 'Image is an attachment ID.' );
		$this->assertSame( 'link', $by_name['cta_primary']['type'] );
		$this->assertSame( 'array', $by_name['cta_primary']['return_format'] );

		foreach ( array_filter( array_column( $layout['sub_fields'], 'key' ) ) as $key ) {
			$this->assertStringStartsWith( 'field_salt_hero_', (string) $key, 'DATA02 key convention.' );
		}
	}

	public function test_zero_state_queues_no_schema_nodes(): void {
		$this->render_hero();

		$this->assertSame( array(), salt_schema_queue() );
	}
}
