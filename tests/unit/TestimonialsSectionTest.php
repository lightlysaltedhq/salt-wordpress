<?php
/**
 * Testimonial slice tests (spec §10 #8, Appendix A.2; SEO02, COMPL02,
 * FAIL02, SEC09).
 *
 * Covers the CPT display-only posture, the field group, the FC layout
 * shape, the section's 0/1/N states, the no-buttons no-JS slider base,
 * and the Review schema path (service-scoped only, rating clamped 1–5,
 * no AggregateRating).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';
require_once __DIR__ . '/../shims/testimonial.php';
require_once __DIR__ . '/../shims/media.php';

require_once dirname( __DIR__, 2 ) . '/inc/cpt/testimonial.php';
require_once dirname( __DIR__, 2 ) . '/inc/fields/cpt-testimonial.php';

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
	 * 101 Alice [service 201, rating 5], 102 Bob, 103 Cara [rating 4]).
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
			'service' => array( 201 ),
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

	public function test_field_group_matches_appendix_a2(): void {
		salt_register_testimonial_fields();

		$groups = array_values(
			array_filter(
				$GLOBALS['salt_test_acf_groups'],
				static fn( array $g ): bool => 'group_salt_testimonial' === ( $g['key'] ?? '' )
			)
		);
		$this->assertCount( 1, $groups );

		$fields = array();
		foreach ( $groups[0]['fields'] as $field ) {
			$fields[ $field['name'] ] = $field;
		}

		$this->assertSame(
			array( 'quote', 'role', 'company', 'logo', 'rating', 'service' ),
			array_keys( $fields )
		);
		foreach ( $fields as $name => $field ) {
			$this->assertSame( 'field_salt_testimonial_' . $name, $field['key'], 'DATA02 key convention.' );
		}

		$this->assertSame( 'textarea', $fields['quote']['type'] );
		$this->assertSame( 'id', $fields['logo']['return_format'] );
		$this->assertSame( 'number', $fields['rating']['type'] );
		$this->assertSame( 1, $fields['rating']['min'] );
		$this->assertSame( 5, $fields['rating']['max'] );
		$this->assertSame( array( 'service' ), $fields['service']['post_type'] );
		$this->assertSame( 'id', $fields['service']['return_format'] );
	}

	public function test_field_group_skipped_when_module_disabled(): void {
		$GLOBALS['salt_test_fields']['option']['module_testimonial'] = false;

		salt_register_testimonial_fields();

		$this->assertSame( array(), $GLOBALS['salt_test_acf_groups'] );
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

		// Wrapper fields LAST.
		$this->assertSame( 'field_salt_testimonials_section_divider', end( $keys ) );
		$this->assertLessThan(
			array_search( 'field_salt_testimonials_advanced_tab', $keys, true ),
			array_search( 'field_salt_query_testimonial_source', $keys, true ),
			'Query controls come before the wrapper Advanced tab.'
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

	public function test_company_logo_renders_through_x_image(): void {
		$this->seed_standard_set();
		$GLOBALS['salt_test_fields']['101']['logo'] = 55;
		$GLOBALS['salt_test_attachments'][55]       = array(
			'src'    => 'https://example.test/uploads/acme-logo.png',
			'width'  => 200,
			'height' => 80,
			'srcset' => '',
			'alt'    => 'Acme Ltd logo',
		);

		$html = $this->render_testimonials();

		$this->assertStringContainsString( 'class="x-image h-10 w-auto"', $html );
		$this->assertStringContainsString( 'acme-logo.png', $html );
	}

	public function test_star_rating_is_one_labelled_group_of_decorative_icons(): void {
		$this->seed_standard_set();

		$html = $this->render_testimonials();

		$this->assertStringContainsString( 'aria-label="Rated 5 out of 5 stars"', $html );
		$this->assertStringContainsString( 'aria-label="Rated 4 out of 5 stars"', $html );
		$this->assertSame( 10, substr_count( $html, 'aria-hidden="true"' ), 'Two rated cards x 5 decorative stars (filled + empty).' );
	}

	// -----------------------------------------------------------------
	// Schema (SEO02).
	// -----------------------------------------------------------------

	public function test_review_node_only_for_testimonials_with_a_service(): void {
		$this->seed_standard_set();
		$this->render_testimonials();

		$reviews = $this->schema_reviews();

		$this->assertCount( 1, $reviews, 'Only the service-linked testimonial queues a Review (never org-attached).' );

		$review = $reviews[0];
		$this->assertSame(
			array(
				'@type' => 'Service',
				'name'  => 'Web design',
				'@id'   => 'https://example.test/services/web-design/#service',
			),
			$review['itemReviewed']
		);
		$this->assertSame(
			array(
				'@type' => 'Person',
				'name'  => 'Alice Example',
			),
			$review['author']
		);
		$this->assertSame( 'Salt transformed our site.', $review['reviewBody'] );
		$this->assertSame(
			array(
				'@type'       => 'Rating',
				'ratingValue' => 5,
				'bestRating'  => 5,
				'worstRating' => 1,
			),
			$review['reviewRating']
		);
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

	public function test_rating_above_five_is_clamped_in_schema_and_markup(): void {
		$this->seed_standard_set();
		$GLOBALS['salt_test_fields']['101']['rating'] = 7;

		$html = $this->render_testimonials();

		$this->assertSame( 5, $this->schema_reviews()[0]['reviewRating']['ratingValue'] );
		$this->assertStringContainsString( 'aria-label="Rated 5 out of 5 stars"', $html );
		$this->assertStringContainsString( 'clamped', implode( ' ', $this->logs() ) );
	}

	public function test_unset_rating_omits_review_rating_but_keeps_the_review(): void {
		$this->seed_standard_set();
		unset( $GLOBALS['salt_test_fields']['101']['rating'] );

		$this->render_testimonials();

		$reviews = $this->schema_reviews();
		$this->assertCount( 1, $reviews );
		$this->assertArrayNotHasKey( 'reviewRating', $reviews[0] );
	}

	public function test_broken_service_relationship_emits_no_review_and_logs(): void {
		$this->seed_standard_set();
		$GLOBALS['salt_test_fields']['101']['service'] = array( 999 );

		$html = $this->render_testimonials();

		$this->assertStringContainsString( 'x-card-testimonial', $html, 'The card still renders — only the schema is skipped.' );
		$this->assertSame( array(), $this->schema_reviews() );
		$this->assertStringContainsString( 'broken relationship', implode( ' ', $this->logs() ) );
	}

	public function test_unpublished_service_emits_no_review(): void {
		$this->seed_posts(
			array(
				array(
					'ID'          => 202,
					'post_type'   => 'service',
					'post_status' => 'draft',
					'post_title'  => 'Retired service',
				),
				array(
					'ID'         => 101,
					'post_type'  => 'testimonial',
					'post_title' => 'Alice Example',
				),
			)
		);
		$GLOBALS['salt_test_fields']['101'] = array(
			'quote'   => 'Quote for a retired service.',
			'service' => array( 202 ),
		);

		$this->render_testimonials();

		$this->assertSame( array(), $this->schema_reviews() );
	}
}
