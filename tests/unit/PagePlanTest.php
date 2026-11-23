<?php
/**
 * The page plan (EP-72 M4, ID-3975): what the page decides for each band
 * before it draws. Ids settle in the contract's two steps, editors' anchors
 * first (section#anchors); a band's index and primacy count only the bands
 * drawn before it; the one priority image goes to the first band that has
 * an image (section#priority-media, SC-026).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';
require_once __DIR__ . '/../shims/faq.php';
require_once dirname( __DIR__, 2 ) . '/inc/cpt/faq.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/collections.php';

final class PagePlanTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();
	}

	/**
	 * Draw the page loop over these rows.
	 *
	 * @param array<int,array{layout:string,fields:array<string,mixed>}> $rows Stored rows.
	 * @return string
	 */
	private function render_page( array $rows ): string {
		salt_test_seed_fc( $rows );
		ob_start();
		get_template_part( 'template-parts/flexible-content' );
		return (string) ob_get_clean();
	}

	/**
	 * A rich-text row.
	 *
	 * @param string $heading Heading ('' for none).
	 * @param string $body    Body ('' for none).
	 * @param string $anchor  anchorId ('' for none).
	 * @return array{layout:string,fields:array<string,mixed>}
	 */
	private static function rich_text( string $heading, string $body, string $anchor = '' ): array {
		return array(
			'layout' => 'rich-text',
			'fields' => array(
				'heading'  => $heading,
				'body'     => $body,
				'settings' => array( 'anchorId' => $anchor ),
			),
		);
	}

	/**
	 * A split hero row drawing attachment 41.
	 *
	 * @return array{layout:string,fields:array<string,mixed>}
	 */
	private function hero_with_image(): array {
		$GLOBALS['salt_test_attachments'][41] = array(
			'src'    => 'https://example.test/uploads/hero.jpg',
			'width'  => 2000,
			'height' => 1200,
			'alt'    => 'Studio',
		);
		return array(
			'layout' => 'hero',
			'fields' => array(
				'variant' => 'split',
				'heading' => 'Hero',
				'image'   => 41,
			),
		);
	}

	// -----------------------------------------------------------------
	// Ids (section#anchors): the contract's worked examples.
	// -----------------------------------------------------------------

	public function test_anchored_rows_settle_before_any_id_is_minted(): void {
		$this->assertSame(
			array( 'faq', 'main-section', 'faq-2', 'faq-2-2', 'pricing' ),
			salt_section_plan_ids(
				array(
					array(
						'section' => 'faq',
						'anchor'  => 'faq',
					),
					array(
						'section' => 'faq',
						'anchor'  => 'main',
					),
					array(
						'section' => 'faq',
						'anchor'  => 'faq',
					),
					array(
						'section' => 'faq',
						'anchor'  => 'faq-2',
					),
					array(
						'section' => 'pricing',
						'anchor'  => '',
					),
				)
			)
		);
	}

	public function test_an_unanchored_row_above_never_takes_an_editors_anchor(): void {
		$this->assertSame(
			array( 'faq-2', 'faq' ),
			salt_section_plan_ids(
				array(
					array(
						'section' => 'faq',
						'anchor'  => '',
					),
					array(
						'section' => 'faq',
						'anchor'  => 'faq',
					),
				)
			)
		);
	}

	public function test_a_minted_id_counts_its_place_in_the_stored_layout(): void {
		$ids = salt_section_plan_ids(
			array(
				array(
					'section' => 'faq',
					'anchor'  => '',
					'on_page' => false,
				),
				array(
					'section' => 'faq',
					'anchor'  => '',
				),
			)
		);

		$this->assertSame( array( '', 'faq-2' ), $ids, 'The first faq counts though it is not drawn, so the second keeps faq-2.' );
	}

	// -----------------------------------------------------------------
	// A first row that draws nothing.
	// -----------------------------------------------------------------

	public function test_a_first_row_that_draws_nothing_leaves_the_next_band_first(): void {
		$GLOBALS['salt_test_posts'] = array();
		$this->seed_posts(
			array(
				array(
					'ID'         => 1,
					'post_type'  => 'faq',
					'post_title' => 'How long does planting take?',
				),
			)
		);
		$GLOBALS['salt_test_fields']['1']['answer'] = '<p>A day or two.</p>';

		$html = $this->render_page(
			array(
				self::rich_text( '', '' ),
				array(
					'layout' => 'faq',
					'fields' => array(
						'heading' => 'Questions',
						'query'   => array(
							'mode'  => 'manual',
							'items' => array( 1 ),
						),
					),
				),
			)
		);

		$this->assertStringContainsString( 'name="faq-0"', $html, 'The accordion is named by the band\'s place among drawn bands, not its stored row (1).' );
		$this->assertMatchesRegularExpression( '/<h1[\s>][^<]*Questions/', $html, 'The first drawn band claims the h1.' );
		$this->assertStringNotContainsString( 'visually-hidden', $html, 'No fallback heading: a band claimed the h1.' );
	}

	public function test_an_empty_first_row_still_counts_for_the_next_minted_id(): void {
		$html = $this->render_page(
			array(
				self::rich_text( '', '' ),
				self::rich_text( 'Approach', '<p>Words.</p>' ),
			)
		);

		$this->assertStringContainsString( 'id="rich-text-2" aria-labelledby="rich-text-2__heading"', $html );
		$this->assertStringContainsString( 'data-track="rich-text-1"', $html, 'data-track counts drawn bands only.' );
	}

	public function test_an_editors_anchor_keeps_its_name_below_an_unanchored_band(): void {
		$html = $this->render_page(
			array(
				self::rich_text( 'First', '' ),
				self::rich_text( 'Second', '', 'rich-text' ),
			)
		);

		$this->assertMatchesRegularExpression( '/id="rich-text-2"[^>]*>.*First.*id="rich-text"[^>]*>.*Second/s', $html );
	}

	// -----------------------------------------------------------------
	// The priority image (SC-026).
	// -----------------------------------------------------------------

	public function test_the_priority_image_goes_to_the_first_band_with_an_image(): void {
		$html = $this->render_page(
			array(
				self::rich_text( 'Welcome', '<p>Text first.</p>' ),
				$this->hero_with_image(),
				$this->hero_with_image(),
			)
		);

		$this->assertSame( 1, substr_count( $html, 'fetchpriority="high"' ), 'One priority image per page.' );
		$this->assertLessThan( strpos( $html, 'loading="lazy"' ), strpos( $html, 'fetchpriority="high"' ), 'The first image below the text band takes it; the later one is lazy.' );
	}
}
