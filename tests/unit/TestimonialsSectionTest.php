<?php
/**
 * Testimonial slice tests (spec §10 #8, Appendix A.2; SEO02, COMPL02,
 * FAIL02, SEC09).
 *
 * Covers the CPT display-only posture, the generated field group, the FC
 * layout shape, the section's 0/1/N states, the no-buttons no-JS slider
 * base, the photo (never a logo) on the quote card, and the schema: no
 * Review node, since salt-contract 1.0.0-rc.7 dropped the testimonial's
 * service, and no AggregateRating.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/testimonial.php';
require_once __DIR__ . '/../shims/media.php';

require_once dirname( __DIR__, 2 ) . '/inc/cpt/testimonial.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/collections.php';

final class TestimonialsSectionTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		$GLOBALS['salt_test_acf_groups'] = array();
		$GLOBALS['salt_test_cpts']       = array();
		$GLOBALS['salt_test_post_types'] = array();
		$GLOBALS['salt_test_permalinks'] = array();
		$GLOBALS['salt_test_schema']     = array();
		salt_test_attachments_reset();
	}

	/**
	 * Read a captured CPT registration from whichever slice shim's
	 * register_post_type won the function_exists race.
	 *
	 * @param string $post_type Post type key.
	 * @return array<string,mixed>|null
	 */
	private function captured_cpt( string $post_type ): ?array {
		return $GLOBALS['salt_test_cpts'][ $post_type ]
			?? $GLOBALS['salt_test_post_types'][ $post_type ]
			?? null;
	}

	// -----------------------------------------------------------------
	// Fixtures.
	// -----------------------------------------------------------------

	/**
	 * Seed one published service + three testimonials (date DESC order:
	 * 101 Alice [rating 5], 102 Bob, 103 Cara [rating 4]).
	 */
	private function seed_standard_set(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 201,
					'post_type'  => 'service',
					'post_title' => 'Web design',
				),
				array(
					'ID'         => 101,
					'post_type'  => 'testimonial',
					'post_title' => 'Alice Example',
					'post_date'  => '2026-01-03 00:00:00',
				),
				array(
					'ID'         => 102,
					'post_type'  => 'testimonial',
					'post_title' => 'Bob Sample',
					'post_date'  => '2026-01-02 00:00:00',
				),
				array(
					'ID'         => 103,
					'post_type'  => 'testimonial',
					'post_title' => 'Cara Test',
					'post_date'  => '2026-01-01 00:00:00',
				),
			)
		);

		$GLOBALS['salt_test_fields']['101'] = array(
			'quote'   => 'Salt transformed our site.',
			'role'    => 'Founder',
			'company' => 'Acme Ltd',
			'rating'  => 5,
		);
		$GLOBALS['salt_test_fields']['102'] = array(
			'quote' => 'Fast, friendly, flawless.',
		);
		$GLOBALS['salt_test_fields']['103'] = array(
			'quote'  => 'Would recommend to anyone.',
			'rating' => 4,
		);

		$GLOBALS['salt_test_permalinks'][201]     = 'https://example.test/services/web-design/';
		$GLOBALS['salt_test_schema']['permalink'] = 'https://example.test/services/web-design/';
	}

	/**
	 * Seed the FC row and render the testimonials section.
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return string Rendered HTML.
	 */
	private function render_testimonials( array $fields = array() ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'testimonials',
					'fields' => $fields,
				),
			)
		);
		if ( have_rows( 'sections' ) ) {
			the_row();
		}

		ob_start();
		salt_render_section(
			'testimonials',
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * Assemble the graph and return only the Review nodes.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function schema_reviews(): array {
		$graph = salt_schema_graph(
			array(
				'home_url' => 'https://example.test',
				'page_url' => 'https://example.test/p',
			)
		)['@graph'];

		return array_values(
			array_filter(
				$graph,
				static fn( array $node ): bool => 'Review' === ( $node['@type'] ?? '' )
			)
		);
	}

	// -----------------------------------------------------------------
	// CPT posture (SEC09).
	// -----------------------------------------------------------------

	public function test_cpt_registers_with_display_only_posture(): void {
		salt_register_cpt_testimonial();

		$cpt = $this->captured_cpt( 'testimonial' );
		$this->assertNotNull( $cpt );

		$this->assertFalse( $cpt['public'] );
		$this->assertTrue( $cpt['show_ui'] );
		$this->assertFalse( $cpt['publicly_queryable'] );
		$this->assertTrue( $cpt['exclude_from_search'] );
		$this->assertFalse( $cpt['show_in_rest'] );
		$this->assertFalse( $cpt['has_archive'] );
		$this->assertSame( array( 'title', 'thumbnail' ), $cpt['supports'] );
	}

	public function test_cpt_registration_skipped_when_module_disabled(): void {
		$GLOBALS['salt_test_fields']['option']['module_testimonial'] = false;

		salt_register_cpt_testimonial();

		$this->assertNull( $this->captured_cpt( 'testimonial' ) );
	}

	// -----------------------------------------------------------------
	// Field group (Appendix A.2, DATA02).
	// -----------------------------------------------------------------

	public function test_field_group_is_the_contracts_testimonial(): void {
		$group = salt_test_collection_group( 'testimonials' );

		$this->assertSame( 'group_salt_collection_testimonials', $group['key'] );
		$this->assertSame( 'testimonial', $group['location'][0][0]['value'] );

		$fields = array_column( $group['fields'], null, 'name' );
		$this->assertSame( array( 'quote', 'role', 'company', 'rating' ), array_keys( $fields ), 'No logo and no service: the contract dropped both.' );
		$this->assertSame(
			array(
				'name'  => 'title',
				'photo' => 'featured-image',
			),
			$group['salt']['native']
		);
		foreach ( $fields as $name => $field ) {
			$this->assertSame( 'field_salt_collection_testimonials_' . $name, $field['key'], 'DATA02 key convention.' );
		}

		$this->assertSame( 'textarea', $fields['quote']['type'] );
		$this->assertSame( 'number', $fields['rating']['type'] );
		$this->assertSame( 1, $fields['rating']['min'] );
		$this->assertSame( 5, $fields['rating']['max'] );
		$this->assertSame( 0.5, $fields['rating']['step'], 'Half stars (the contract\'s step).' );
	}

	public function test_field_group_skipped_when_module_disabled(): void {
		$GLOBALS['salt_test_fields']['option']['module_testimonial'] = false;

		$this->assertNull( salt_test_collection_group( 'testimonials' ) );
	}

	// -----------------------------------------------------------------
	// FC layout shape (§17 recipe, FLEET07).
	// -----------------------------------------------------------------

	public function test_layout_file_shape(): void {
		$layout = include dirname( __DIR__, 2 ) . '/inc/fields/layouts/testimonials.php';

		$this->assertSame( 'testimonials', $layout['name'] );
		$this->assertSame( 'layout_salt_testimonials', $layout['key'] );

		$keys  = array_column( $layout['sub_fields'], 'key' );
		$names = array_column( $layout['sub_fields'], 'name' );

		// Content fields first.
		$this->assertSame( 'field_salt_testimonials_layout', $keys[0] );
		$this->assertContains( 'field_salt_testimonials_heading', $keys );
		$this->assertContains( 'field_salt_testimonials_eyebrow', $keys );

		// Shared query controls embedded (no taxonomy filter for testimonial).
		$this->assertContains( 'field_salt_query_testimonial_source', $keys );
		$this->assertContains( 'field_salt_query_testimonial_count', $keys );
		$this->assertNotContains( 'taxonomy_terms', $names );

		// The contract's section settings group LAST, keyed in this layout's scope.
		$this->assertSame( 'field_salt_testimonials_settings', end( $keys ) );
		$settings = end( $layout['sub_fields'] );
		$this->assertSame( 'group', $settings['type'] );
		$this->assertSame(
			array( 'spacing', 'tone', 'toneDark', 'width', 'backgroundImage', 'anchorId', 'firstPageOnly', 'divider' ),
			array_column( $settings['sub_fields'], 'name' )
		);
		$this->assertSame( 'field_salt_testimonials_settings_spacing', $settings['sub_fields'][0]['key'] );
		$this->assertLessThan(
			array_search( 'field_salt_testimonials_settings', $keys, true ),
			array_search( 'field_salt_query_testimonial_source', $keys, true ),
			'Query controls come before the settings group.'
		);
	}

	// -----------------------------------------------------------------
	// Section states (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_items_renders_nothing(): void {
		$this->assertSame( '', $this->render_testimonials() );
	}

	public function test_module_disabled_renders_nothing(): void {
		$this->seed_standard_set();
		$GLOBALS['salt_test_fields']['option']['module_testimonial'] = false;

		$this->assertSame( '', $this->render_testimonials() );
	}

	public function test_all_rows_broken_renders_nothing_and_logs(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 110,
					'post_type'  => 'testimonial',
					'post_title' => 'No Quote',
				),
			)
		);
		$GLOBALS['salt_test_fields']['110'] = array( 'quote' => '   ' );

		$this->assertSame( '', $this->render_testimonials() );
		$this->assertStringContainsString( 'skipped', implode( ' ', $this->logs() ) );
	}

	public function test_broken_rows_are_skipped_but_valid_rows_render(): void {
		$this->seed_standard_set();
		$GLOBALS['salt_test_fields']['102'] = array( 'quote' => '' );

		$html = $this->render_testimonials();

		$this->assertSame( 2, substr_count( $html, 'x-card-testimonial ' ) );
		$this->assertStringContainsString( 'skipped', implode( ' ', $this->logs() ) );
	}

	public function test_single_item_renders_static_card_without_slider(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 101,
					'post_type'  => 'testimonial',
					'post_title' => 'Alice Example',
				),
			)
		);
		$GLOBALS['salt_test_fields']['101'] = array( 'quote' => 'Just the one.' );

		$html = $this->render_testimonials( array( 'layout' => 'slider' ) );

		$this->assertStringContainsString( 'x-testimonials-single', $html );
		$this->assertStringContainsString( 'x-card-testimonial', $html );
		$this->assertStringContainsString( '<blockquote', $html );
		$this->assertStringNotContainsString( 'data-salt-testimonials', $html, '1 item: no JS trigger attribute (FAIL02).' );
		$this->assertStringNotContainsString( 'snap-x', $html );
		$this->assertStringNotContainsString( '<button', $html );
	}

	public function test_slider_base_is_a_snap_track_with_no_buttons(): void {
		$this->seed_standard_set();

		$html = $this->render_testimonials( array( 'layout' => 'slider' ) );

		$this->assertStringContainsString( 'data-salt-testimonials', $html );
		$this->assertStringContainsString( 'snap-x', $html );
		$this->assertStringContainsString( 'overflow-x-auto', $html );
		$this->assertStringContainsString( 'snap-start', $html );
		$this->assertSame( 3, substr_count( $html, 'x-testimonials-item' ) );
		$this->assertStringNotContainsString( '<button', $html, 'FAIL02: buttons are JS-injected only — no dead buttons no-JS.' );
	}

	public function test_grid_layout_renders_grid_not_track(): void {
		$this->seed_standard_set();

		$html = $this->render_testimonials(
			array(
				'layout'  => 'grid',
				'heading' => 'What clients say',
			)
		);

		$this->assertStringContainsString( 'x-testimonials-grid', $html );
		$this->assertStringNotContainsString( 'data-salt-testimonials', $html );
		$this->assertStringContainsString( '<h1', $html, 'Primary section heading claims the H1.' );
		$this->assertSame( 3, substr_count( $html, '<figure' ) );
	}

	public function test_the_photo_renders_through_x_image_and_a_stored_logo_does_not(): void {
		$this->seed_standard_set();
		$GLOBALS['salt_test_posts'][1]->salt_thumbnail_id = 56;
		$GLOBALS['salt_test_fields']['101']['logo']       = 55;
		foreach ( array(
			55 => 'acme-logo.png',
			56 => 'alice.jpg',
		) as $id => $file ) {
			$GLOBALS['salt_test_attachments'][ $id ] = array(
				'src'    => 'https://example.test/uploads/' . $file,
				'width'  => 200,
				'height' => 200,
				'srcset' => '',
				'alt'    => '',
			);
		}

		$html = $this->render_testimonials();

		$this->assertStringContainsString( 'class="x-image h-10 w-auto"', $html );
		$this->assertStringContainsString( 'alice.jpg', $html, 'The quote card draws the photo (the featured image).' );
		$this->assertStringNotContainsString( 'acme-logo.png', $html, 'Never a logo: the contract dropped it.' );
	}

	public function test_star_rating_is_one_labelled_group_of_decorative_icons(): void {
		$this->seed_standard_set();

		$html = $this->render_testimonials();

		$this->assertStringContainsString( 'aria-label="Rated 5 out of 5 stars"', $html );
		$this->assertStringContainsString( 'aria-label="Rated 4 out of 5 stars"', $html );
		$this->assertSame( 22, substr_count( $html, 'aria-hidden="true"' ), 'Two rated cards, each a decorative stars span holding 5 empty and 5 filled star icons.' );
	}

	/**
	 * Stored rating, its fill and its label (rc.7 testimonials.json rating,
	 * step 0.5; markup/card.json rating).
	 *
	 * @return array<string,array{float|int,string,string}>
	 */
	public static function rating_provider(): array {
		return array(
			'4.5 draws four and a half stars' => array( 4.5, '90%', 'Rated 4.5 out of 5 stars' ),
			'4 draws four stars'              => array( 4, '80%', 'Rated 4 out of 5 stars' ),
			'1 draws one star'                => array( 1, '20%', 'Rated 1 out of 5 stars' ),
			'5 draws five stars'              => array( 5, '100%', 'Rated 5 out of 5 stars' ),
		);
	}

	#[DataProvider( 'rating_provider' )]
	public function test_a_rating_fills_its_share_of_five_stars( float|int $stored, string $fill, string $label ): void {
		$this->seed_standard_set();
		$GLOBALS['salt_test_fields']['101']['rating'] = $stored;
		unset( $GLOBALS['salt_test_fields']['103']['rating'] );

		$html = $this->render_testimonials();

		$this->assertStringContainsString( 'style="--salt-rating-fill: ' . $fill . '"', $html );
		$this->assertSame( 1, substr_count( $html, 'style="--salt-rating-fill' ), 'One rated card.' );
		// A half value is written "4.5" in the label (the founder's ruling on PR #26).
		$this->assertStringContainsString( 'aria-label="' . $label . '"', $html );
		$stars = substr( $html, (int) strpos( $html, 'x-card-testimonial-stars ' ) );
		$stars = substr( $stars, 0, (int) strpos( $stars, '</blockquote>' ) );
		$fill  = substr( $stars, (int) strpos( $stars, 'x-card-testimonial-stars-fill' ) );
		$this->assertSame( 10, substr_count( $stars, '<svg' ), 'Five empty stars and five filled ones.' );
		$this->assertSame( 5, substr_count( $fill, '<svg' ), 'The five filled stars are in the clipped fill span.' );
	}

	// -----------------------------------------------------------------
	// Schema (SEO02).
	// -----------------------------------------------------------------

	public function test_no_review_node_is_queued_even_for_a_stored_service(): void {
		$this->seed_standard_set();
		$GLOBALS['salt_test_fields']['101']['service'] = array( 201 );

		$this->render_testimonials();

		$this->assertSame( array(), $this->schema_reviews(), 'The contract dropped the testimonial\'s service, so nothing says what a Review reviews (SEO02: never org-attached).' );
	}

	public function test_no_aggregate_rating_is_emitted(): void {
		$this->seed_standard_set();
		$this->render_testimonials();

		$graph = salt_schema_graph(
			array(
				'home_url' => 'https://example.test',
				'page_url' => 'https://example.test/p',
			)
		);

		$this->assertStringNotContainsString( 'aggregateRating', (string) wp_json_encode( $graph ), 'v1 emits no AggregateRating (deferred — see section docblock).' );
	}

	public function test_rating_above_five_is_clamped_in_markup(): void {
		$this->seed_standard_set();
		$GLOBALS['salt_test_fields']['101']['rating'] = 7;

		$html = $this->render_testimonials();

		$this->assertStringContainsString( 'aria-label="Rated 5 out of 5 stars"', $html );
		$this->assertStringContainsString( 'clamped', implode( ' ', $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// Resolver and view (logic/view split, ID-3587).
	// -----------------------------------------------------------------

	/**
	 * Seed one testimonials FC row and run its resolver (no rendering).
	 *
	 * @param array<string,mixed> $fields Row sub-field fixtures.
	 * @return array<string,mixed>|null
	 */
	private function resolve_testimonials( array $fields = array() ): ?array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'testimonials',
					'fields' => $fields,
				),
			)
		);
		the_row();

		$resolver = salt_section_resolver( 'testimonials' );
		$this->assertSame( 'salt_resolve_section_testimonials', $resolver );

		return $resolver(
			array(
				'index'      => 0,
				'is_primary' => true,
			)
		);
	}

	public function test_resolver_returns_null_for_zero_items_broken_rows_and_a_disabled_module(): void {
		$this->assertNull( $this->resolve_testimonials( array( 'heading' => 'Kind words' ) ) );

		$this->seed_posts(
			array(
				array(
					'ID'         => 101,
					'post_type'  => 'testimonial',
					'post_title' => 'No Quote',
				),
			)
		);
		$this->assertNull( $this->resolve_testimonials( array( 'heading' => 'Kind words' ) ) );
		$this->assertStringContainsString( 'testimonial 101', implode( ' ', $this->logs() ) );

		$this->seed_standard_set();
		$GLOBALS['salt_test_fields']['option']['module_testimonial'] = false;
		$this->assertNull( $this->resolve_testimonials( array( 'heading' => 'Kind words' ) ) );
	}

	public function test_resolver_returns_data_for_the_cards_not_rendered_components(): void {
		$this->seed_standard_set();
		$GLOBALS['salt_test_posts'][1]->salt_thumbnail_id = 56;
		$GLOBALS['salt_test_fields']['103']['rating']     = 9;

		$resolved = $this->resolve_testimonials(
			array(
				'eyebrow' => 'Praise',
				'layout'  => 'carousel',
			)
		);

		$this->assertSame( array( 'eyebrow', 'heading', 'layout', 'items' ), array_keys( $resolved['props'] ) );
		$this->assertSame( 'Praise', $resolved['props']['eyebrow'] );
		$this->assertNull( $resolved['props']['heading'] );
		$this->assertSame( 'grid', $resolved['props']['layout'], 'Unknown layout falls back to grid.' );
		$this->assertFalse( $resolved['section']['has_heading'] );

		$items = $resolved['props']['items'];
		$this->assertCount( 3, $items );
		$this->assertSame( array( 'author', 'quote', 'role', 'company', 'rating', 'photo_id' ), array_keys( $items[0] ) );
		$this->assertSame( 'Alice Example', $items[0]['author'] );
		$this->assertSame( 'Salt transformed our site.', $items[0]['quote'] );
		$this->assertSame( 'Acme Ltd', $items[0]['company'] );
		$this->assertSame( 56, $items[0]['photo_id'], 'The photo is the featured image.' );
		$this->assertSame( 0, $items[1]['photo_id'] );
		$this->assertSame( 5.0, $items[2]['rating'], 'The resolver clamps ratings.' );
		$this->assertStringContainsString( 'rating 9 on testimonial 103', implode( ' ', $this->logs() ) );

		$this->assertStringNotContainsString( '<', (string) wp_json_encode( $items ), 'No pre-rendered component HTML in props (rule 3).' );
	}

	public function test_resolver_queues_no_schema(): void {
		$this->seed_standard_set();

		$this->resolve_testimonials();

		$this->assertSame( array(), salt_schema_queue() );
	}

	/**
	 * Draw the view from props.
	 *
	 * @param array<string,mixed> $props Props.
	 * @return string
	 */
	private function draw_testimonials_view( array $props ): string {
		ob_start();
		load_template(
			dirname( __DIR__, 2 ) . '/template-parts/sections/testimonials.php',
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

	public function test_view_draws_the_cards_from_props_alone(): void {
		$GLOBALS['salt_test_attachments'][55] = array(
			'src'    => 'https://example.test/uploads/prop-photo.jpg',
			'width'  => 200,
			'height' => 80,
			'srcset' => '',
		);
		$item                                 = array(
			'author'   => 'Prop Author',
			'quote'    => 'Drawn from props.',
			'role'     => 'CEO',
			'company'  => 'Prop Co',
			'rating'   => 4,
			'photo_id' => 55,
		);
		$props                                = array(
			'eyebrow' => 'Praise',
			'heading' => 'From props',
			'layout'  => 'slider',
			'items'   => array(
				$item,
				array_merge(
					$item,
					array(
						'author'   => 'Second',
						'photo_id' => 0,
					)
				),
			),
		);

		$html = $this->draw_testimonials_view( $props );

		$this->assertSame( 1, preg_match_all( '/<h2[\s>]/', $html ), 'Heading at the plan level.' );
		$this->assertStringContainsString( 'data-salt-testimonials', $html );
		$this->assertSame( 2, substr_count( $html, 'x-testimonials-item' ) );
		$this->assertStringContainsString( 'prop-photo.jpg', $html, 'The view renders the image component from photo_id.' );
		$this->assertStringContainsString( 'Drawn from props.', $html );
		$this->assertStringNotContainsString( 'data-section=', $html, 'The wrapper is the entry\'s, not the view\'s.' );

		$props['layout'] = 'grid';
		$grid            = $this->draw_testimonials_view( $props );
		$this->assertStringContainsString( 'x-testimonials-grid', $grid );
		$this->assertStringNotContainsString( 'data-salt-testimonials', $grid );

		$props['items'] = array( $item );
		$single         = $this->draw_testimonials_view( $props );
		$this->assertStringContainsString( 'x-testimonials-single', $single );
		$this->assertStringNotContainsString( 'x-testimonials-track', $single );
	}
}
