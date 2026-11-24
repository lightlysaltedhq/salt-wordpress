<?php
/**
 * The page plan (EP-72 M4, ID-3975): what the page decides for each band
 * before it draws. Ids settle in the contract's two steps, editors' anchors
 * first (section#anchors); a band's index and primacy count only the bands
 * drawn before it; the one priority image goes to the first band that has
 * an image (section#priority-media, SC-026). A post's bands are planned
 * the same way, its prose taking no id and no place in a minted id's
 * number, and its featured image holding the
 * priority image (section#sections-in-a-post).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/media.php';
require_once __DIR__ . '/../shims/blog.php';
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

	public function test_a_row_that_is_never_a_band_takes_no_place(): void {
		$ids = salt_section_plan_ids(
			array(
				array(
					'section' => 'rich-text',
					'anchor'  => '',
					'band'    => false,
				),
				array(
					'section' => 'rich-text',
					'anchor'  => '',
				),
			)
		);

		$this->assertSame( array( '', 'rich-text' ), $ids, 'A post\'s prose takes no id and does not move the next one\'s number.' );
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
		$this->assertStringNotContainsString( 'salt-sr-only', $html, 'No fallback heading: a band claimed the h1.' );
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
	// A row that draws nothing never takes an editor's anchor.
	// -----------------------------------------------------------------

	/**
	 * A minimal hero row.
	 *
	 * @param string $heading Heading ('' for none, which draws nothing).
	 * @param string $anchor  anchorId.
	 * @return array{layout:string,fields:array<string,mixed>}
	 */
	private static function hero( string $heading, string $anchor ): array {
		return array(
			'layout' => 'hero',
			'fields' => array(
				'variant'  => 'minimal',
				'heading'  => $heading,
				'settings' => array( 'anchorId' => $anchor ),
			),
		);
	}

	public function test_an_anchored_row_that_draws_nothing_leaves_its_anchor_to_the_next(): void {
		$html = $this->render_page(
			array(
				self::rich_text( '', '', 'foo' ),
				self::rich_text( 'Drawn', '<p>Words.</p>', 'foo' ),
				self::hero( 'Hero', 'foo' ),
			)
		);

		$this->assertMatchesRegularExpression( '/id="foo"[^>]*>.*Drawn.*id="foo-2"[^>]*>.*Hero/s', $html );
		$this->assertStringNotContainsString( 'foo-3', $html );
		$this->assertStringNotContainsString( 'salt-plan-', $html, 'No placeholder reaches the page.' );
	}

	public function test_an_empty_hero_and_a_missing_layout_take_no_anchor(): void {
		$this->assertStringContainsString( 'id="about"', $this->render_page( array( self::hero( '', 'about' ), self::hero( 'About', 'about' ) ) ) );

		salt_render_state_reset();
		$html = $this->render_page(
			array(
				array(
					'layout' => 'no-such-layout',
					'fields' => array( 'settings' => array( 'anchorId' => 'prices' ) ),
				),
				self::hero( 'Prices', 'prices' ),
			)
		);
		$this->assertStringContainsString( 'id="prices"', $html );
		$this->assertStringNotContainsString( 'prices-2', $html );
	}

	public function test_a_row_hidden_past_page_one_takes_no_anchor(): void {
		$GLOBALS['salt_test_query_vars']['paged']      = 2;
		$hidden                                        = self::rich_text( 'First page only', '', 'foo' );
		$hidden['fields']['settings']['firstPageOnly'] = true;

		$html = $this->render_page( array( $hidden, self::rich_text( 'Every page', '', 'foo' ) ) );

		$this->assertStringNotContainsString( 'First page only', $html );
		$this->assertStringContainsString( 'id="foo" aria-labelledby="foo__heading"', $html, 'A hidden row is a row that draws nothing.' );
	}

	public function test_a_minted_id_is_the_same_on_every_page(): void {
		$hidden                                        = self::rich_text( 'First page only', '' );
		$hidden['fields']['settings']['firstPageOnly'] = true;
		$rows = array( $hidden, self::rich_text( 'Every page', '' ), self::rich_text( 'Anchored', '', 'rich-text' ) );

		$html = $this->render_page( $rows );
		$this->assertMatchesRegularExpression( '/id="rich-text-2"[^>]*>.*First page only.*id="rich-text-3"[^>]*>.*Every page.*id="rich-text"[^>]*>.*Anchored/s', $html );

		salt_render_state_reset();
		$GLOBALS['salt_test_query_vars']['paged'] = 2;
		$html                                     = $this->render_page( $rows );
		$this->assertStringNotContainsString( 'First page only', $html );
		$this->assertMatchesRegularExpression( '/id="rich-text-3"[^>]*>.*Every page.*id="rich-text"[^>]*>.*Anchored/s', $html, 'The hidden row still holds its minted id, so the next one does not move on page 2.' );
		$this->assertStringNotContainsString( 'id="rich-text-2"', $html );
	}

	public function test_a_minted_id_holds_every_editors_anchor_drawn_or_not(): void {
		$html = $this->render_page(
			array(
				self::rich_text( '', '', 'rich-text' ),
				self::rich_text( 'Unanchored', '' ),
			)
		);
		$this->assertStringContainsString( 'id="rich-text-2" aria-labelledby="rich-text-2__heading"', $html, 'The minted id keeps clear of the empty row\'s anchor, so it does not move when that row fills.' );

		salt_render_state_reset();
		$html = $this->render_page(
			array(
				self::rich_text( 'Filled', '', 'rich-text' ),
				self::rich_text( 'Unanchored', '' ),
			)
		);
		$this->assertMatchesRegularExpression( '/id="rich-text"[^>]*>.*Filled.*id="rich-text-2"[^>]*>.*Unanchored/s', $html );
	}

	public function test_an_anchor_of_zero_is_registered(): void {
		$html = $this->render_page( array( self::rich_text( 'Zero', '', '0' ) ) );

		$this->assertStringContainsString( 'id="0" aria-labelledby="0__heading"', $html );
		$this->assertSame( '0-2', salt_anchor_id( '0' ), 'A later id never takes it.' );
	}

	public function test_an_override_of_salt_render_section_that_drops_the_id_keeps_the_plan(): void {
		$script = (string) tempnam( sys_get_temp_dir(), 'salt-override-' );
		file_put_contents(
			$script,
			<<<'PHP'
<?php
// A child theme's override, defined before the parent loads (ARCH07): it
// rebuilds the loop context and drops `id`.
function salt_render_section( string $layout, array $context = array() ): void {
	$located = locate_template( 'template-parts/sections/' . $layout . '.php' );
	if ( '' !== $located ) {
		salt_render_resolved_section( $layout, $located, array( 'index' => (int) ( $context['index'] ?? 0 ), 'is_primary' => ! empty( $context['is_primary'] ) ) );
	}
}
require $argv[1] . '/tests/bootstrap.php';
require_once $argv[1] . '/tests/shims/acf.php';
salt_test_seed_fc( json_decode( $argv[2], true ) );
get_template_part( 'template-parts/flexible-content' );
PHP
		);
		$rows = array(
			self::rich_text( 'One', '' ),
			self::rich_text( 'Two', '', 'rich-text' ),
			self::rich_text( 'Three', '' ),
		);
		// A fresh PHP process: a pluggable function can be overridden only before the theme loads.
		$html = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) ) . ' ' . escapeshellarg( (string) json_encode( $rows ) ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- Test only: the override must be defined before the theme loads.
		unlink( $script );

		$this->assertMatchesRegularExpression( '/id="rich-text-2"[^>]*>.*One.*id="rich-text"[^>]*>.*Two.*id="rich-text-3"[^>]*>.*Three/s', $html, $html );
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

	// -----------------------------------------------------------------
	// A post's bands (section#sections-in-a-post): planned as a page's.
	// -----------------------------------------------------------------

	/**
	 * Draw post 500's layout over these rows.
	 *
	 * @param array<int,array{layout:string,fields:array<string,mixed>}> $rows      Stored rows.
	 * @param int                                                        $thumbnail The featured image (0 for none).
	 * @param array<int,array<string,mixed>>                             $posts     Other posts to seed.
	 * @return string
	 */
	private function render_post( array $rows, int $thumbnail = 0, array $posts = array() ): string {
		$posts[] = array(
			'ID'                => 500,
			'post_type'         => 'post',
			'post_title'        => 'A post',
			'salt_thumbnail_id' => $thumbnail,
		);
		$this->seed_posts( $posts );
		salt_test_seed_fc( $rows );
		ob_start();
		salt_the_post_layout( 500 );
		return (string) ob_get_clean();
	}

	public function test_a_posts_band_counts_its_prose_and_not_a_band_that_draws_nothing(): void {
		$GLOBALS['salt_test_fields']['1']['answer'] = '<p>A day or two.</p>';

		$html = $this->render_post(
			array(
				self::rich_text( '', '<p>Prose first.</p>' ),
				self::rich_text( '', '', 'skipped' ),
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
			),
			0,
			array(
				array(
					'ID'         => 1,
					'post_type'  => 'faq',
					'post_title' => 'How long does planting take?',
				),
			)
		);

		$this->assertStringContainsString( 'name="faq-1"', $html, 'The post/band-faq numbering: the prose is index 0, the FAQ band 1.' );
		$this->assertStringNotContainsString( '<h1', $html, 'The post\'s title holds the h1.' );
	}

	public function test_a_posts_ids_settle_as_a_pages_do(): void {
		$html = $this->render_post(
			array(
				self::rich_text( '', '<p>Prose.</p>' ),
				self::rich_text( 'First band', '' ),
				self::rich_text( 'Anchored band', '', 'rich-text' ),
			)
		);

		$this->assertStringStartsWith( '<div class="salt-rich-text">', $html, 'Prose draws no wrapper and so no id.' );
		$this->assertMatchesRegularExpression( '/id="rich-text-2"[^>]*>.*First band.*id="rich-text"[^>]*>.*Anchored band/s', $html, 'The editor\'s anchor holds rich-text, so the first band takes the next free number.' );
	}

	public function test_a_posts_prose_takes_no_place_in_a_minted_id(): void {
		$html = $this->render_post(
			array(
				self::rich_text( '', '<p>Prose.</p>' ),
				self::rich_text( 'First band', '' ),
				self::rich_text( 'Second band', '' ),
			)
		);

		$this->assertMatchesRegularExpression( '/^<div class="salt-rich-text">.*id="rich-text"[^>]*>.*First band.*id="rich-text-2"[^>]*>.*Second band/s', $html, 'The founder\'s ruling: prose does not count, so the first band is #rich-text.' );
		$this->assertStringNotContainsString( 'id="rich-text-3"', $html );
	}

	public function test_a_posts_featured_image_holds_the_priority_image(): void {
		$hero = $this->hero_with_image();
		$html = $this->render_post( array( $hero ), 41 );

		$this->assertStringNotContainsString( 'fetchpriority="high"', $html, 'The featured image above the layout holds it.' );
		$this->assertStringContainsString( 'loading="lazy"', $html );

		salt_render_state_reset();
		$html = $this->render_post( array( $hero ) );
		$this->assertSame( 1, substr_count( $html, 'fetchpriority="high"' ), 'With no featured image, the first band with an image takes it.' );
	}

	public function test_a_posts_empty_anchored_band_takes_no_anchor(): void {
		$html = $this->render_post(
			array(
				self::rich_text( '', '', 'foo' ),
				self::rich_text( 'Band', '', 'foo' ),
			)
		);

		$this->assertStringContainsString( 'id="foo" aria-labelledby="foo__heading"', $html );
	}
}
