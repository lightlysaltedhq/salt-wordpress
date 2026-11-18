<?php
/**
 * Principle-7 query resolver tests (spec §10a).
 *
 * Covers resolution order (manual → controls → defaults), the DATA05
 * broken-relationship warning, DATA04 legacy services_overview parity,
 * and the COMPL02 zero-result contract (array(), never null/false).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../shims/acf.php';

final class ResolverTest extends SaltTestCase {

	/**
	 * A mixed fixture set: published services with distinct menu_order,
	 * dates, titles and terms, one draft service, plus a couple of posts
	 * so cross-CPT leakage would be caught.
	 */
	private function seed_services(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 1,
					'post_type'  => 'service',
					'post_title' => 'Branding',
					'post_date'  => '2026-01-03 00:00:00',
					'menu_order' => 20,
					'salt_terms' => array( 7 ),
				),
				array(
					'ID'         => 2,
					'post_type'  => 'service',
					'post_title' => 'Web design',
					'post_date'  => '2026-01-01 00:00:00',
					'menu_order' => 10,
					'salt_terms' => array( 9 ),
				),
				array(
					'ID'         => 3,
					'post_type'  => 'service',
					'post_title' => 'SEO',
					'post_date'  => '2026-01-02 00:00:00',
					'menu_order' => 30,
					'salt_terms' => array( 7, 9 ),
				),
				array(
					'ID'          => 4,
					'post_type'   => 'service',
					'post_status' => 'draft',
					'post_title'  => 'Unpublished',
					'menu_order'  => 1,
				),
				array(
					'ID'         => 50,
					'post_type'  => 'post',
					'post_title' => 'A blog post',
					'post_date'  => '2026-02-01 00:00:00',
					'salt_terms' => array( 7 ),
				),
			)
		);
	}

	/**
	 * Extract IDs from a resolved post list.
	 *
	 * @param WP_Post[] $posts Posts.
	 * @return int[]
	 */
	private function ids( array $posts ): array {
		return array_map( static fn( WP_Post $p ): int => (int) $p->ID, $posts );
	}

	// -----------------------------------------------------------------
	// 1. Manual wins; hand-picked order preserved; count slices.
	// -----------------------------------------------------------------

	public function test_manual_wins_over_taxonomy_and_defaults_in_handpicked_order(): void {
		$this->seed_services();

		$controls = array(
			'source'         => 'manual',
			'manual'         => array( 3, 1 ),
			'taxonomy_terms' => array( 9 ),
			'orderby'        => 'title',
			'order'          => 'DESC',
		);

		$result = salt_resolve_query( $controls, 'service' );

		// Hand-picked order, not menu_order/title/taxonomy filtering.
		$this->assertSame( array( 3, 1 ), $this->ids( $result ) );
	}

	public function test_manual_count_slices_the_handpicked_list(): void {
		$this->seed_services();

		$controls = array(
			'source' => 'manual',
			'manual' => array( 2, 3, 1 ),
			'count'  => 2,
		);

		$this->assertSame( array( 2, 3 ), $this->ids( salt_resolve_query( $controls, 'service' ) ) );
	}

	// -----------------------------------------------------------------
	// 2. Broken manual ID → skipped + DATA05 warning naming the ID.
	// -----------------------------------------------------------------

	public function test_broken_manual_id_is_skipped_and_logged(): void {
		$this->seed_services();

		$controls = array(
			'source' => 'manual',
			'manual' => array( 3, 999, 4 ), // 999 missing, 4 is a draft.
		);

		$result = salt_resolve_query( $controls, 'service' );

		$this->assertSame( array( 3 ), $this->ids( $result ) );

		$log_blob = implode( "\n", $this->logs() );
		$this->assertStringContainsString( '999', $log_blob );
		$this->assertStringContainsString( '4', $log_blob );
		$this->assertStringContainsString( 'service', $log_blob );
	}

	// -----------------------------------------------------------------
	// 3. source=manual with empty manual falls through.
	// -----------------------------------------------------------------

	public function test_manual_source_with_empty_manual_falls_through_to_taxonomy(): void {
		$this->seed_services();

		$controls = array(
			'source'         => 'manual',
			'manual'         => array(),
			'taxonomy_terms' => array( 9 ),
		);

		// Term 9: services 2 and 3, menu_order ASC (default) => 2, 3.
		$this->assertSame( array( 2, 3 ), $this->ids( salt_resolve_query( $controls, 'service' ) ) );
	}

	public function test_manual_source_with_empty_manual_and_no_terms_falls_through_to_defaults(): void {
		$this->seed_services();

		$controls = array(
			'source' => 'manual',
			'manual' => array(),
		);

		// Pure service defaults: menu_order ASC, all published.
		$this->assertSame( array( 2, 1, 3 ), $this->ids( salt_resolve_query( $controls, 'service' ) ) );
	}

	// -----------------------------------------------------------------
	// 4. Taxonomy filtering + orderby/count fallback behaviour.
	// -----------------------------------------------------------------

	public function test_taxonomy_filtering_respects_terms_with_default_ordering(): void {
		$this->seed_services();

		$controls = array(
			'source'         => 'taxonomy',
			'taxonomy_terms' => array( 7 ),
		);

		// Term 7: services 1 and 3; default menu_order ASC => 1, 3.
		$this->assertSame( array( 1, 3 ), $this->ids( salt_resolve_query( $controls, 'service' ) ) );
	}

	public function test_taxonomy_filtering_honours_control_orderby_and_count(): void {
		$this->seed_services();

		$controls = array(
			'source'         => 'taxonomy',
			'taxonomy_terms' => array( 7 ),
			'orderby'        => 'title',
			'order'          => 'DESC',
			'count'          => 1,
		);

		// Titles among term-7 services: SEO (3) > Branding (1) descending.
		$this->assertSame( array( 3 ), $this->ids( salt_resolve_query( $controls, 'service' ) ) );
	}

	public function test_empty_string_controls_fall_back_to_defaults(): void {
		$this->seed_services();

		$controls = array(
			'source'  => 'default',
			'orderby' => '',
			'order'   => '',
			'count'   => 0,
		);

		$this->assertSame( array( 2, 1, 3 ), $this->ids( salt_resolve_query( $controls, 'service' ) ) );
	}

	// -----------------------------------------------------------------
	// 5. Per-CPT defaults.
	// -----------------------------------------------------------------

	public function test_service_default_matches_legacy_services_overview(): void {
		$this->seed_services();

		// DATA04 parity: the legacy services_overview hardcoded query was
		// menu_order ASC, ALL published services. Empty controls must
		// reproduce it exactly.
		$this->assertSame( array( 2, 1, 3 ), $this->ids( salt_resolve_query( array(), 'service' ) ) );

		$defaults = salt_query_default_args( 'service' );
		$this->assertSame(
			array(
				'orderby' => 'menu_order',
				'order'   => 'ASC',
				'count'   => -1,
			),
			$defaults
		);
	}

	public function test_post_default_returns_three_newest(): void {
		$this->seed_posts(
			array(
				array(
					'ID'        => 10,
					'post_date' => '2026-03-01 00:00:00',
				),
				array(
					'ID'        => 11,
					'post_date' => '2026-05-01 00:00:00',
				),
				array(
					'ID'        => 12,
					'post_date' => '2026-04-01 00:00:00',
				),
				array(
					'ID'        => 13,
					'post_date' => '2026-01-01 00:00:00',
				),
				array(
					'ID'        => 14,
					'post_date' => '2026-02-01 00:00:00',
				),
			)
		);

		$this->assertSame( array( 11, 12, 10 ), $this->ids( salt_resolve_query( array(), 'post' ) ) );
	}

	public function test_unknown_cpt_defaults_are_logged(): void {
		$defaults = salt_query_default_args( 'mystery_cpt' );

		$this->assertSame(
			array(
				'orderby' => 'date',
				'order'   => 'DESC',
				'count'   => 6,
			),
			$defaults
		);
		$this->assertStringContainsString( 'mystery_cpt', implode( "\n", $this->logs() ) );
	}

	// -----------------------------------------------------------------
	// 6. Zero results → array() exactly (COMPL02).
	// -----------------------------------------------------------------

	public function test_zero_results_returns_exactly_empty_array(): void {
		$this->seed_posts( array() );

		$this->assertSame( array(), salt_resolve_query( array(), 'work' ) );
		$this->assertSame(
			array(),
			salt_resolve_query(
				array(
					'source' => 'manual',
					'manual' => array( 123 ),
				),
				'work'
			)
		);
		$this->assertSame(
			array(),
			salt_resolve_query(
				array(
					'source'         => 'taxonomy',
					'taxonomy_terms' => array( 5 ),
				),
				'work'
			)
		);
	}

	// -----------------------------------------------------------------
	// Taxonomy map (final contract).
	// -----------------------------------------------------------------

	public function test_taxonomy_map_is_the_frozen_contract(): void {
		$this->assertSame( 'service_category', salt_query_taxonomy( 'service' ) );
		$this->assertNull( salt_query_taxonomy( 'team_member' ), 'Team has no topics (SC-027).' );
		$this->assertSame( 'work_type', salt_query_taxonomy( 'work' ) );
		$this->assertSame( 'faq_category', salt_query_taxonomy( 'faq' ) );
		$this->assertSame( 'category', salt_query_taxonomy( 'post' ) );
		$this->assertSame( 'location_area', salt_query_taxonomy( 'location' ) );
		$this->assertNull( salt_query_taxonomy( 'testimonial' ) );
	}

	// -----------------------------------------------------------------
	// The contract's collection query (salt_query_controls_from_query()).
	// -----------------------------------------------------------------

	/**
	 * Seed one row holding a contract `query` group and read it.
	 *
	 * @param array<string,mixed>|null $query The stored group.
	 * @return array<string,mixed>
	 */
	private function controls_from( ?array $query ): array {
		salt_test_seed_fc(
			array(
				array(
					'layout' => 'faq',
					'fields' => null === $query ? array() : array( 'query' => $query ),
				),
			)
		);
		the_row();
		return salt_query_controls_from_query();
	}

	public function test_query_automatic_is_the_sources_defaults(): void {
		$expected = array(
			'source'         => 'default',
			'taxonomy_terms' => array(),
			'orderby'        => '',
			'order'          => '',
			'count'          => 0,
		);
		$this->assertSame( $expected, $this->controls_from( null ), 'A row with no query group reads as automatic.' );
		$this->assertSame(
			$expected,
			$this->controls_from(
				array(
					'mode'       => 'automatic',
					'categories' => array( 9 ),
					'order'      => 'default',
					'count'      => 0,
				)
			),
			'Categories are read only in by-category mode.'
		);
	}

	public function test_query_by_category_filters_by_its_terms_with_order_and_count(): void {
		$this->assertSame(
			array(
				'source'         => 'taxonomy',
				'taxonomy_terms' => array( 7, 9 ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'count'          => 4,
			),
			$this->controls_from(
				array(
					'mode'       => 'by-category',
					'categories' => array( '7', 9 ),
					'order'      => 'newest',
					'count'      => '4',
				)
			)
		);
	}

	public function test_query_manual_keeps_the_picked_order_and_ignores_order_and_count(): void {
		$this->assertSame(
			array(
				'source' => 'manual',
				'manual' => array( 3, 1 ),
			),
			$this->controls_from(
				array(
					'mode'  => 'manual',
					'items' => array( 3, '1' ),
					'order' => 'title',
					'count' => 1,
				)
			)
		);
	}

	public function test_query_title_order_caps_count_and_unknown_values_fall_back(): void {
		$controls = $this->controls_from(
			array(
				'mode'  => 'sideways',
				'order' => 'title',
				'count' => 99,
			)
		);
		$this->assertSame( 'default', $controls['source'] );
		$this->assertSame( array( 'title', 'ASC' ), array( $controls['orderby'], $controls['order'] ) );
		$this->assertSame( 24, $controls['count'], 'The contract caps count at 24.' );
		$this->assertStringContainsString( 'unknown mode "sideways"', implode( ' ', $this->logs() ) );

		$controls = $this->controls_from( array( 'order' => 'random' ) );
		$this->assertSame( array( '', '' ), array( $controls['orderby'], $controls['order'] ) );
		$this->assertStringContainsString( 'unknown order "random"', implode( ' ', $this->logs() ) );
	}

	public function test_query_controls_feed_the_resolver(): void {
		$this->seed_services();
		$posts = salt_resolve_query(
			$this->controls_from(
				array(
					'mode'  => 'manual',
					'items' => array( 2, 1 ),
				)
			),
			'service'
		);
		$this->assertSame( array( 2, 1 ), array_map( static fn( WP_Post $p ): int => (int) $p->ID, $posts ) );
	}
}
