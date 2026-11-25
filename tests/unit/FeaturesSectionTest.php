<?php
/**
 * Feature / Benefits Grid slice unit tests: layout shape (DATA02), section
 * 0/1/N states (COMPL02), malformed items skipped + logged, escaping, and
 * the no-schema invariant (§10 #5 has no schema assignment).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class FeaturesSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
	}

	/**
	 * Render ONE features section row.
	 *
	 * @param array<string,mixed> $fields  Row sub-field fixtures.
	 * @param array<string,mixed> $context Loop context.
	 * @return string
	 */
	private function render_features_section( array $fields = array(), array $context = array(
		'index'      => 0,
		'is_primary' => true,
	) ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'features',
					'fields' => $fields,
				),
			)
		);
		$this->assertTrue( have_rows( 'sections' ) );
		the_row();

		ob_start();
		salt_render_section( 'features', $context );
		return (string) ob_get_clean();
	}

	// -----------------------------------------------------------------
	// Layout shape (DATA02 / FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_is_generated_from_the_contract(): void {
		$layout = salt_test_layout( 'features' );

		$this->assertSame( 'layout_salt_features', $layout['key'] );
		$this->assertSame( array( 'eyebrow', 'heading', 'intro', 'columns', 'items', '', 'settings' ), array_column( $layout['sub_fields'], 'name' ) );

		$items = array_column( $layout['sub_fields'], null, 'name' )['items'];
		$this->assertSame( 'repeater', $items['type'] );
		$fields = array_column( $items['sub_fields'], null, 'name' );
		$this->assertSame( array( 'icon', 'title', 'text', 'url' ), array_keys( $fields ) );
		$this->assertSame( 'select', $fields['icon']['type'], 'The icon is the site\'s icon select.' );
		$this->assertContains( 'check', array_keys( $fields['icon']['choices'] ) );
		$this->assertSame( 'wysiwyg', $fields['text']['type'], 'The text is rich text.' );
		$this->assertSame( 'text', $fields['url']['type'], 'The url is a plain address.' );
	}

	// -----------------------------------------------------------------
	// States (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_state_renders_nothing(): void {
		$this->assertSame( '', $this->render_features_section( array( 'items' => array() ) ) );
		$this->assertSame( '', $this->render_features_section( array( 'items' => 'not-an-array' ) ) );
		$this->assertSame(
			'',
			$this->render_features_section(
				array(
					'eyebrow' => '  ',
					'heading' => ' ',
					'intro'   => '',
					'items'   => array(),
				)
			),
			'Eyebrow, heading, intro and items all empty (markup zeroState).'
		);
	}

	public function test_heading_without_items_renders_the_band_without_a_list(): void {
		$html = $this->render_features_section( array( 'heading' => 'Why us' ) );

		$this->assertStringStartsWith( '<section class="salt-section" id="features" aria-labelledby="features__heading"', $html );
		$this->assertStringContainsString( '<div class="salt-block" data-block="features"><h1 id="features__heading">Why us</h1></div>', $html );
		$this->assertStringNotContainsString( 'salt-grid', $html, 'The list is drawn only when an item has a title.' );
	}

	public function test_intro_alone_is_drawn_in_a_div_band(): void {
		$html = $this->render_features_section( array( 'intro' => 'What we do & why.' ) );

		$this->assertStringStartsWith( '<div class="salt-section" id="features"', $html, 'No heading: a div band (section#labelled-by).' );
		$this->assertStringNotContainsString( 'aria-labelledby', $html );
		$this->assertStringContainsString( '<div class="salt-block" data-block="features"><p class="salt-intro">What we do &amp; why.</p></div>', $html, 'The intro is drawn, escaped, as p.salt-intro.' );
	}

	public function test_single_item_is_a_valid_static_layout(): void {
		$html = $this->render_features_section(
			array(
				'heading' => 'Why us',
				'columns' => '3',
				'items'   => array(
					array(
						'icon'  => 'check',
						'title' => 'Fast',
						'text'  => 'Pages load quickly.',
					),
				),
			),
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);

		$this->assertStringContainsString( '<ul class="salt-grid" role="list" data-columns="3">', $html );
		$this->assertSame( 1, preg_match_all( '/<li><div class="salt-card">/', $html ) );
		$this->assertStringContainsString( '<h1 id="features__heading">Why us</h1>', $html, 'Primary section with heading claims the H1.' );
		$this->assertStringContainsString( '<h2>Fast</h2>', $html, 'Card titles are h2 under an h1.' );
		$this->assertStringContainsString( '<div class="salt-rich-text">Pages load quickly.</div>', $html );
		$this->assertStringNotContainsString( '<script', $html, 'No JS in this section — ever.' );
	}

	public function test_n_items_render_with_icons_links_and_columns(): void {
		$html = $this->render_features_section(
			array(
				'eyebrow' => 'Benefits',
				'heading' => 'Why us',
				'columns' => '4',
				'items'   => array(
					array(
						'icon'  => 'check',
						'title' => 'Fast',
						'text'  => 'Quick.',
						'url'   => 'https://example.test/fast/',
					),
					array(
						'title' => 'Accessible',
						'text'  => 'For everyone.',
					),
					array(
						'icon'  => 'star',
						'title' => 'Low carbon',
					),
				),
			),
			array(
				'index'      => 1,
				'is_primary' => false,
			)
		);

		$this->assertSame( 3, preg_match_all( '/<li><div class="salt-card">/', $html ) );
		$this->assertStringContainsString( '<ul class="salt-grid" role="list" data-columns="4">', $html );
		$this->assertStringContainsString( '<p class="salt-eyebrow">Benefits</p><h2 id="features__heading">Why us</h2>', $html );
		$this->assertStringContainsString( '<h3><a class="salt-card__link" href="/fast/">Fast<span class="salt-arrow" aria-hidden="true">&#x2060;→</span></a></h3>', $html, 'A linked title under an h2 is an h3 with the site\'s arrow.' );
		$this->assertStringContainsString( '<h3>Accessible</h3>', $html, 'No url: a plain title.' );
		$this->assertSame( 1, preg_match_all( '/<a\b/', $html ) );
		$this->assertSame( 2, preg_match_all( '/<svg class="salt-icon" data-icon="(check|star)" data-size="lg"/', $html ), 'An icon per item that sets one, large.' );
		$this->assertStringContainsString( '<div class="salt-card"><svg class="salt-icon" data-icon="check"', $html, 'The icon leads the card.' );
		$this->assertStringContainsString( '<li><div class="salt-card"><h3>Accessible</h3>', $html, 'No icon set: none drawn.' );
		$this->assertStringContainsString( '<h3>Low carbon</h3></div></li>', $html, 'No words: no rich-text body.' );
	}

	public function test_a_linked_title_draws_no_arrow_when_the_site_has_none(): void {
		add_filter( 'salt_contract_arrow', static fn() => null );

		$html = $this->render_features_section(
			array(
				'items' => array(
					array(
						'title' => 'Fast',
						'url'   => '/fast',
					),
				),
			)
		);

		$this->assertStringContainsString( '<h2><a class="salt-card__link" href="/fast">Fast</a></h2>', $html );
		$this->assertStringNotContainsString( 'salt-arrow', $html );
	}

	public function test_malformed_items_are_skipped_and_logged(): void {
		$html = $this->render_features_section(
			array(
				'heading' => 'Why us',
				'items'   => array(
					array( 'title' => 'Kept' ),
					array(
						'title' => '   ',
						'text'  => 'No title.',
					),
					'not-a-row',
					array( 'text' => 'Missing title entirely.' ),
				),
			)
		);

		$this->assertSame( 1, preg_match_all( '/<li\b/', $html ), 'Only the valid item renders.' );
		$this->assertStringContainsString( '<h2>Kept</h2>', $html );
		$logs = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'features section: item 1 has no title', $logs );
		$this->assertStringContainsString( 'features section: item 2 is not a row array', $logs );
		$this->assertStringContainsString( 'features section: item 3 has no title', $logs );
	}

	public function test_all_items_malformed_and_nothing_else_renders_nothing(): void {
		$html = $this->render_features_section(
			array( 'items' => array( array( 'text' => 'no title' ) ) )
		);

		$this->assertSame( '', $html, 'Never a blank band (COMPL02).' );
		$this->assertStringContainsString( 'features section: item 0 has no title', implode( ' ', $this->logs() ) );
	}

	public function test_all_items_malformed_under_a_heading_renders_the_band_without_a_list(): void {
		$html = $this->render_features_section(
			array(
				'heading' => 'Why us',
				'items'   => array( array( 'text' => 'no title' ) ),
			)
		);

		$this->assertStringContainsString( '<h1 id="features__heading">Why us</h1></div>', $html );
		$this->assertStringNotContainsString( '<ul', $html, 'No blank card and no empty list (COMPL02).' );
		$this->assertStringContainsString( 'features section: item 0 has no title', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Escaping + heading claim.
	// -----------------------------------------------------------------

	public function test_values_are_escaped_on_output(): void {
		$html = $this->render_features_section(
			array(
				'heading' => 'Safe <script>alert(1)</script>',
				'items'   => array(
					array(
						'title' => 'Title <em>markup</em>',
						'text'  => '<p>Text <script>alert(2)</script></p>',
						'url'   => 'javascript:alert(3)',
					),
				),
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<em>', $html, 'The title is plain text.' );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_headingless_section_does_not_claim_the_h1(): void {
		$html = $this->render_features_section(
			array(
				'items' => array( array( 'title' => 'Fast' ) ),
			),
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);

		$this->assertStringStartsWith( '<div class="salt-section" id="features"', $html, 'No heading: a div band, not a section.' );
		$this->assertStringNotContainsString( 'aria-labelledby', $html );
		$this->assertStringNotContainsString( '<h1', $html );
		$this->assertStringContainsString( '<h2>Fast</h2>', $html, 'With no band heading the card titles are h2.' );
		$this->assertFalse( salt_h1_claimed(), 'Heading-less primary section must not consume the H1 claim (A11Y01).' );
	}

	// -----------------------------------------------------------------
	// No schema for this section (§10 #5).
	// -----------------------------------------------------------------

	public function test_no_schema_nodes_are_queued(): void {
		salt_schema_reset();

		$graph_args = array(
			'home_url' => 'https://example.test',
			'page_url' => 'https://example.test/p',
		);
		$baseline   = count( salt_schema_graph( $graph_args )['@graph'] );

		$this->render_features_section(
			array(
				'heading' => 'Why us',
				'items'   => array( array( 'title' => 'Fast' ) ),
			)
		);

		$this->assertCount( $baseline, salt_schema_graph( $graph_args )['@graph'], 'Features section queues no schema nodes.' );
	}

	// -----------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one features FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields  Row fields.
	 * @param array<string,mixed> $context Loop context.
	 * @return array<string,mixed>|null
	 */
	private function resolve_features( array $fields, array $context = array(
		'index'      => 0,
		'is_primary' => false,
	) ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'features',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'features' );
		$this->assertSame( 'salt_resolve_section_features', $resolver );

		return $resolver( $context );
	}

	public function test_resolver_returns_null_only_in_the_zero_state(): void {
		$this->assertNull( $this->resolve_features( array() ) );
		$this->assertNull(
			$this->resolve_features(
				array(
					'items' => array(
						array( 'text' => 'No title' ),
						'not-a-row',
					),
				)
			)
		);

		$heading_only = $this->resolve_features( array( 'heading' => 'Why us' ) );
		$this->assertSame( array(), $heading_only['props']['items'], 'A heading alone is drawn: items may be empty.' );
		$this->assertTrue( $heading_only['section']['has_heading'] );
		$this->assertSame( 'Lead in', $this->resolve_features( array( 'intro' => 'Lead in' ) )['props']['intro'] );
		$this->assertSame( 'Kicker', $this->resolve_features( array( 'eyebrow' => 'Kicker' ) )['props']['eyebrow'] );
	}

	public function test_resolver_props_use_the_contract_names(): void {
		$resolved = $this->resolve_features(
			array(
				'eyebrow' => 'Benefits',
				'heading' => 'Why us',
				'intro'   => 'Three reasons.',
				'columns' => '4',
				'items'   => array(
					array(
						'icon'  => 'star',
						'title' => ' Fast ',
						'text'  => '<p>Really <strong>fast</strong>.</p>',
						'url'   => '/fast',
					),
					array( 'title' => 'Bare' ),
				),
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'intro', 'columns', 'items', 'arrow' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'Benefits', $resolved['props']['eyebrow'] );
		$this->assertSame( 'Why us', $resolved['props']['heading'] );
		$this->assertSame( 'Three reasons.', $resolved['props']['intro'] );
		$this->assertSame( '→', $resolved['props']['arrow'], 'The site\'s arrow (salt_contract_arrow()).' );
		$this->assertSame( '4', $resolved['props']['columns'] );
		$this->assertSame(
			array(
				array(
					'icon'  => 'star',
					'title' => 'Fast',
					'text'  => '<p>Really <strong>fast</strong>.</p>',
					'href'  => '/fast',
				),
				array(
					'icon'  => null,
					'title' => 'Bare',
					'text'  => null,
					'href'  => null,
				),
			),
			$resolved['props']['items']
		);
	}

	public function test_resolver_nulls_blank_text_defaults_columns_and_owns_the_wrapper(): void {
		$resolved = $this->resolve_features(
			array(
				'eyebrow'  => '  ',
				'intro'    => ' ',
				'columns'  => '7',
				'settings' => array( 'spacing' => 'lg' ),
				'items'    => array( array( 'title' => 'One' ) ),
			)
		);

		$this->assertNull( $resolved['props']['eyebrow'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertNull( $resolved['props']['intro'] );
		$this->assertSame( '3', $resolved['props']['columns'] );
		$this->assertSame( 'features', $resolved['section']['name'] );
		$this->assertSame( 'lg', $resolved['section']['spacing'] );
		$this->assertFalse( $resolved['section']['has_heading'] );
	}

	public function test_view_draws_from_props_alone(): void {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/features.php',
			false,
			array(
				'props' => array(
					'eyebrow' => 'Eyebrow from props',
					'heading' => 'From props',
					'intro'   => 'Intro from props',
					'columns' => '2',
					'items'   => array(
						array(
							'icon'  => null,
							'title' => 'Card title',
							'text'  => '<p>Card copy</p>',
							'href'  => '/card',
						),
					),
					'arrow'   => '>',
				),
				'plan'  => array(
					'index'         => 0,
					'anchor'        => 'why',
					'headingLevel'  => 2,
					'headingId'     => 'why__heading',
					'bodyBaseLevel' => 2,
					'priorityMedia' => false,
				),
			)
		);
		$html = (string) ob_get_clean();

		$this->assertSame(
			'<div class="salt-block" data-block="features"><p class="salt-eyebrow">Eyebrow from props</p><h2 id="why__heading">From props</h2><p class="salt-intro">Intro from props</p>'
			. '<ul class="salt-grid" role="list" data-columns="2"><li><div class="salt-card"><h3><a class="salt-card__link" href="/card">Card title<span class="salt-arrow" aria-hidden="true">&#x2060;&gt;</span></a></h3><div class="salt-rich-text"><p>Card copy</p></div></div></li></ul></div>',
			$html,
			'The block only: the band is the entry\'s, not the view\'s; card titles one below the body base; the arrow from props, escaped.'
		);
	}
	public function test_item_text_keeps_no_heading_under_the_card_title(): void {
		$resolved = $this->resolve_features(
			array(
				'items' => array(
					array(
						'title' => 'Fast',
						'text'  => '<h2>Card</h2><h3>Sub</h3><blockquote><p>Q</p></blockquote>',
					),
				),
			)
		);
		$text     = $resolved['props']['items'][0]['text'];

		$this->assertStringNotContainsString( '<h2', $text, 'A features item allows no heading (salt.allowed): an h2 would break the outline under the h3 card title (A11Y01).' );
		$this->assertStringNotContainsString( '<h3', $text );
		$this->assertStringContainsString( 'Card', $text, 'Stripped to its text.' );
		$this->assertStringContainsString( '<blockquote>', $text );
	}
}
