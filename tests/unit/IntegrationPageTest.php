<?php
/**
 * Cross-section integration (BUILD-BRIEF §6 integration pass, Gate 2):
 * a full composed page with multiple schema-emitting sections renders with
 * a correct heading outline (A11Y01), assembles ONE valid @graph with ONE
 * FAQPage (SEO03), and drives per-section asset enqueue from the FC
 * pre-scan.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class IntegrationPageTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( function_exists( 'salt_schema_reset' ) ) {
			salt_schema_reset();
		}
		if ( function_exists( 'salt_asset_manifest' ) ) {
			salt_asset_manifest( true );
		}
	}

	/**
	 * A representative composed page: hero + two FAQ sections + rich text.
	 *
	 * @return string Rendered HTML.
	 */
	private function render_composed_page(): string {
		$this->seed_posts(
			array(
				array(
					'ID'         => 101,
					'post_type'  => 'faq',
					'post_title' => 'How much does it cost?',
				),
				array(
					'ID'         => 102,
					'post_type'  => 'faq',
					'post_title' => 'How do I get support?',
				),
			)
		);
		$GLOBALS['salt_test_fields']['101']['answer'] = '<p>It depends.</p>';
		$GLOBALS['salt_test_fields']['102']['answer'] = '<p>Email us.</p>';

		salt_test_seed_fc(
			array(
				array(
					'layout' => 'hero',
					'fields' => array(
						'variant'    => 'minimal',
						'eyebrow'    => 'Welcome',
						'heading'    => 'Integration fixture page',
						'subheading' => 'Multiple schema sections, one graph.',
					),
				),
				array(
					'layout' => 'faq',
					'fields' => array(
						'heading'       => 'Pricing FAQs',
						'schema_toggle' => true,
						'items'         => array(),
					),
				),
				array(
					'layout' => 'faq',
					'fields' => array(
						'heading'       => 'Support FAQs',
						'schema_toggle' => true,
						'items'         => array(),
					),
				),
				array(
					'layout' => 'rich_text',
					'fields' => array(
						'heading' => 'About us',
						'content' => '<p>Copy.</p>',
					),
				),
			)
		);

		ob_start();
		get_template_part( 'template-parts/flexible-content' );
		return (string) ob_get_clean();
	}

	public function test_composed_page_outline_one_h1_no_skips(): void {
		$html = $this->render_composed_page();

		$this->assertSame( 1, preg_match_all( '/<h1[\s>]/', $html ), 'Exactly one H1 across four sections.' );
		preg_match_all( '/<h([1-6])[\s>]/', $html, $m );
		$levels = array_map( 'intval', $m[1] );
		$this->assertSame( 1, $levels[0], 'Document starts at H1.' );
		foreach ( range( 1, count( $levels ) - 1 ) as $i ) {
			$this->assertLessThanOrEqual( 1, $levels[ $i ] - max( array_slice( $levels, 0, $i ) ), 'No heading level skips.' );
		}
	}

	public function test_schema_graph_is_single_valid_graph_with_one_faqpage(): void {
		$this->render_composed_page();

		$graph = salt_schema_graph(
			array(
				'home_url'   => 'https://example.test',
				'page_url'   => 'https://example.test/fixture',
				'page_title' => 'Integration fixture page',
				'site_name'  => 'Fixture Co',
				'org'        => array( 'name' => 'Fixture Co' ),
			)
		);

		$json = wp_json_encode( $graph );
		$this->assertIsString( $json );
		$decoded = json_decode( (string) $json, true );
		$this->assertIsArray( $decoded, 'Graph round-trips as valid JSON (TEST02).' );
		$this->assertSame( 'https://schema.org', $decoded['@context'] );
		$this->assertIsArray( $decoded['@graph'] );

		$faq_nodes = array_values( array_filter( $decoded['@graph'], static fn( array $n ): bool => 'FAQPage' === ( $n['@type'] ?? '' ) ) );
		$this->assertCount( 1, $faq_nodes, 'Two FAQ sections merge into exactly ONE FAQPage node (SEO03).' );

		$ids = array_map( static fn( array $n ) => $n['@id'] ?? null, $decoded['@graph'] );
		$ids = array_filter( $ids );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ), 'All @ids unique (SEO01 dedupe).' );
	}

	public function test_section_asset_enqueue_follows_the_page_scan(): void {
		// Page containing a gallery (has a JS module) and rich text (none).
		$GLOBALS['salt_test_fields']['current']['sections'] = array(
			array( 'acf_fc_layout' => 'gallery' ),
			array( 'acf_fc_layout' => 'rich_text' ),
		);

		$this->assertSame( array( 'gallery', 'rich-text' ), salt_page_section_slugs() );

		salt_enqueue_section_assets();
		$scripts = $GLOBALS['salt_test_enqueued']['scripts'];

		$this->assertArrayHasKey( 'salt-section-gallery', $scripts, 'Gallery module enqueued when the section is on the page.' );
		$this->assertNull( $scripts['salt-section-gallery']['ver'], 'No ?ver= — hash is the cache-buster (ARCH03).' );
		$this->assertArrayNotHasKey( 'salt-section-rich-text', $scripts, 'No module for sections that ship none.' );
		$this->assertCount( 1, $scripts, 'Nothing loads for sections not on the page.' );
	}

	public function test_page_without_sections_enqueues_no_section_assets(): void {
		salt_enqueue_section_assets();
		$this->assertSame( array(), $GLOBALS['salt_test_enqueued']['scripts'] );
	}
}
