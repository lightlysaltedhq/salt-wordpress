<?php
/**
 * The collection-showcase and carousel sections (EP-72 M3 unit 8): their
 * zero states, the cards each source resolves to, the pending-ruling
 * seams (index page, labels, arrow), the carousel's three shapes, the
 * contract's image srcset, and the by-category save check.
 *
 * Markup is proved case by case against the contract's fixtures by
 * `npm run conformance:contract`; these hold the logic around it.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

foreach ( array( 'acf', 'media', 'blog', 'gallery', 'terms' ) as $salt_shim ) {
	require_once __DIR__ . '/../shims/' . $salt_shim . '.php';
}
unset( $salt_shim );
require_once dirname( __DIR__, 2 ) . '/inc/fields/collection-query.php';

final class CollectionSectionsTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();
		$GLOBALS['salt_test_post_terms'] = array();
		$GLOBALS['salt_test_permalinks'] = array();
		$_POST                           = array();
	}

	/**
	 * Render one row of a layout.
	 *
	 * @param string              $layout  collection-showcase|carousel.
	 * @param array<string,mixed> $fields  Row fields.
	 * @param array<string,mixed> $context Loop context.
	 * @return string
	 */
	private function render_row( string $layout, array $fields, array $context = array( 'index' => 2 ) ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => $layout,
					'fields' => $fields,
				),
			)
		);
		the_row();
		ob_start();
		salt_render_section( $layout, $context );
		return (string) ob_get_clean();
	}

	/**
	 * Seed testimonials: name => quote.
	 *
	 * @param array<string,string|null> $quotes Name => quote.
	 * @return void
	 */
	private function seed_testimonials( array $quotes ): void {
		$posts = array();
		$id    = 300;
		foreach ( $quotes as $name => $quote ) {
			++$id;
			$posts[]                                     = array(
				'ID'         => $id,
				'post_type'  => 'testimonial',
				'post_title' => $name,
				'menu_order' => $id,
			);
			$GLOBALS['salt_test_fields'][ (string) $id ] = array(
				'quote'   => $quote,
				'role'    => 'Owner',
				'company' => 'Example Ltd',
				'rating'  => 4.5,
			);
		}
		$this->seed_posts( $posts );
	}

	// -----------------------------------------------------------------
	// Zero states.
	// -----------------------------------------------------------------

	public function test_a_showcase_draws_nothing_without_an_installed_source_or_any_card(): void {
		$this->assertSame( '', $this->render_row( 'collection-showcase', array( 'source' => '' ) ) );
		$this->assertSame( '', $this->render_row( 'collection-showcase', array( 'source' => 'pricing' ) ) );
		$this->assertStringContainsString( 'source "pricing" is not installed', implode( ' ', $this->logs() ) );

		$GLOBALS['salt_test_options']['options_module_testimonial'] = false;
		add_filter( 'salt_module_enabled', static fn( bool $on, string $slug ): bool => 'testimonial' === $slug ? false : $on, 10, 2 );
		$this->seed_testimonials( array( 'Ann' => 'Lovely.' ) );
		$this->assertSame( '', $this->render_row( 'collection-showcase', array( 'source' => 'testimonials' ) ), 'A module switched off is not installed.' );
	}

	public function test_an_index_of_items_without_pages_draws_nothing(): void {
		$this->seed_testimonials( array( 'Ann' => 'Lovely.' ) );
		$html = $this->render_row(
			'collection-showcase',
			array(
				'source' => 'testimonials',
				'layout' => 'index',
			)
		);
		$this->assertSame( '', $html );
		$this->assertStringContainsString( 'the index layout needs items with pages of their own', implode( ' ', $this->logs() ) );
	}

	public function test_a_testimonial_with_no_quote_is_skipped_and_logged(): void {
		$this->seed_testimonials(
			array(
				'Ann' => 'Lovely.',
				'Bob' => '',
			)
		);
		$html = $this->render_row(
			'collection-showcase',
			array(
				'heading' => 'Kind words',
				'source'  => 'testimonials',
			)
		);
		$this->assertSame( 1, substr_count( $html, 'salt-showcase__quote' ) );
		$this->assertStringContainsString( 'testimonial 302 has no quote', implode( ' ', $this->logs() ) );
		$this->assertStringContainsString( 'aria-label="Rated 4.5 of 5"', $html, 'labels.rating, then "{n} of {N}" (markup/card.json).' );
		$this->assertStringContainsString( '--salt-rating-fill: 90%', $html );
		$this->assertStringContainsString( 'Owner, Example Ltd', $html, 'meta is role and company joined by ", " (the approved display).' );
	}

	// -----------------------------------------------------------------
	// The pending-ruling seams.
	// -----------------------------------------------------------------

	public function test_the_index_page_is_the_collections_own_or_none(): void {
		$GLOBALS['salt_test_archive_links'] = array( 'service' => 'https://example.test/services/' );
		$this->assertSame( 'https://example.test/services/', salt_collection_index_url( 'services' ) );
		$this->assertNull( salt_collection_index_url( 'team' ), 'Team has no archive.' );
		$this->assertNull( salt_collection_index_url( 'testimonials' ), 'Testimonials have no archive.' );

		$this->seed_posts(
			array(
				array(
					'ID'        => 77,
					'post_type' => 'page',
				),
			)
		);
		$this->assertNull( salt_collection_index_url( 'posts' ), 'No posts page and a static front page: no link.' );
		$GLOBALS['salt_test_options']['show_on_front'] = 'posts';
		$this->assertSame( home_url( '/' ), salt_collection_index_url( 'posts' ), 'The home page lists the posts.' );

		$GLOBALS['salt_test_options']['show_on_front']  = 'page';
		$GLOBALS['salt_test_options']['page_for_posts'] = 77;
		$GLOBALS['salt_test_permalinks'][77]            = 'https://example.test/journal/';
		$this->assertSame( 'https://example.test/journal/', salt_collection_index_url( 'posts' ) );

		add_filter( 'salt_collection_index_url', static fn( $url, string $source ) => 'services' === $source ? '/our-services/' : $url, 10, 2 );
		$this->assertSame( '/our-services/', salt_collection_index_url( 'services' ), 'One seam: the filter decides.' );
	}

	public function test_the_labels_and_arrow_are_one_seam_each(): void {
		$this->assertSame(
			array(
				'viewAll'  => 'View all',
				'slide'    => 'Slide',
				'rating'   => 'Rated',
				'results'  => 'Results',
				'social'   => 'Social profiles',
				'previous' => 'Previous',
				'next'     => 'Next',
			),
			salt_contract_labels(),
			'Ruled: translatable theme strings in the contract fixtures\' English.'
		);
		$this->assertSame( '→', salt_contract_arrow() );

		add_filter( 'salt_contract_labels', static fn( array $labels ): array => array_merge( $labels, array( 'viewAll' => 'See all' ) ) );
		add_filter( 'salt_contract_arrow', static fn() => null );
		$this->assertSame( 'See all', salt_contract_labels()['viewAll'] );
		$this->assertNull( salt_contract_arrow() );
	}

	public function test_view_all_needs_the_switch_and_an_index_page(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 201,
					'post_type'  => 'service',
					'post_title' => 'Design',
				),
			)
		);
		$GLOBALS['salt_test_archive_links'] = array( 'service' => '/services/' );
		$row                                = array(
			'heading' => 'Services',
			'source'  => 'services',
		);

		$this->assertStringContainsString( 'href="/services/" data-style="link" data-track-control="cta"><span class="salt-button__label">View all', $this->render_row( 'collection-showcase', $row ) );
		$this->assertStringNotContainsString( 'salt-showcase__view-all', $this->render_row( 'collection-showcase', $row + array( 'viewAll' => false ) ) );
		add_filter( 'salt_collection_index_url', static fn() => null );
		$this->assertStringNotContainsString( 'salt-showcase__view-all', $this->render_row( 'collection-showcase', $row ), 'No index page, no link.' );
	}

	public function test_drawn_links_are_root_relative_and_the_schema_keeps_absolute_urls(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 201,
					'post_type'  => 'service',
					'post_title' => 'Design',
					'menu_order' => 1,
				),
				array(
					'ID'         => 202,
					'post_type'  => 'service',
					'post_title' => 'Build',
					'menu_order' => 2,
				),
			)
		);
		// One page on the site itself (home_url() is https://example.test), one elsewhere.
		$GLOBALS['salt_test_permalinks']    = array(
			201 => 'https://example.test/services/design/?from=home#top',
			202 => 'https://partner.example/build/',
		);
		$GLOBALS['salt_test_archive_links'] = array( 'service' => 'https://example.test/services/' );
		salt_schema_queue( 'reset' );

		$grid = $this->render_row(
			'collection-showcase',
			array(
				'heading' => 'Services',
				'source'  => 'services',
			)
		);

		// Drawn: the site's own pages root-relative (salt_contract_url()), another host kept.
		$this->assertStringContainsString( 'class="salt-card__link" href="/services/design/?from=home#top"', $grid );
		$this->assertStringContainsString( 'class="salt-card__link" href="https://partner.example/build/"', $grid );
		$this->assertStringContainsString( 'class="salt-button" href="/services/" data-style="link"', $grid, 'The view-all link.' );
		$this->assertStringNotContainsString( 'href="https://example.test/', $grid );

		// The schema the resolver queued keeps the absolute addresses.
		$urls = array_map( static fn( array $node ): array => array( $node['@id'] ?? null, $node['url'] ?? null ), salt_schema_queue() );
		$this->assertSame(
			array(
				array( 'https://example.test/services/design/?from=home#top#service', 'https://example.test/services/design/?from=home#top' ),
				array( 'https://partner.example/build/#service', 'https://partner.example/build/' ),
			),
			$urls
		);

		$index = $this->render_row(
			'collection-showcase',
			array(
				'heading' => 'Services',
				'source'  => 'services',
				'layout'  => 'index',
			)
		);
		$this->assertStringContainsString( 'class="salt-showcase__link" href="/services/design/?from=home#top"', $index, 'The index layout\'s links.' );
		$this->assertStringContainsString( 'class="salt-showcase__link" href="https://partner.example/build/"', $index );
	}

	// -----------------------------------------------------------------
	// Cards and groups.
	// -----------------------------------------------------------------

	public function test_tags_never_draw_the_default_category(): void {
		$post = new WP_Post(
			array(
				'ID'        => 5,
				'post_type' => 'post',
			)
		);
		$GLOBALS['salt_test_options']['default_category'] = 1;
		$GLOBALS['salt_test_post_terms'][5]['category']   = array(
			new WP_Term(
				array(
					'term_id' => 1,
					'name'    => 'Uncategorised',
				)
			),
			new WP_Term(
				array(
					'term_id' => 4,
					'name'    => 'Wildlife',
				)
			),
		);
		$this->assertSame(
			array(
				array(
					'id'    => '4',
					'title' => 'Wildlife',
				),
			),
			salt_collection_tags( $post, 'category' )
		);
	}

	public function test_post_topics_show_the_primary_first_then_names_alphabetically(): void {
		$GLOBALS['salt_test_post_terms'][5]['category']                              = array_map(
			static fn( $id, $name ) => new WP_Term(
				array(
					'term_id' => $id,
					'name'    => $name,
				)
			),
			array( 2, 3, 4 ),
			array( 'Zinnias', 'Soil', 'Compost' )
		);
		$GLOBALS['salt_test_fields'][5]['field_salt_collection_posts_primary_topic'] = 3;
		$this->assertSame( array( 'Soil', 'Compost', 'Zinnias' ), array_column( salt_post_topics( 5 ), 'name' ) );
		$GLOBALS['salt_test_fields'][5]['field_salt_collection_posts_primary_topic'] = 9;
		$this->assertSame( array( 'Compost', 'Soil', 'Zinnias' ), array_column( salt_post_topics( 5 ), 'name' ) );
	}

	public function test_an_index_groups_loose_entries_first_then_each_topic(): void {
		$cards = array();
		foreach ( array(
			1 => array(),
			2 => array( 'A' ),
			3 => array( 'B', 'A' ),
		) as $id => $topics ) {
			$post = new WP_Post( array( 'ID' => $id ) );
			foreach ( $topics as $topic ) {
				$GLOBALS['salt_test_post_terms'][ $id ]['category'][] = new WP_Term(
					array(
						'term_id' => ord( $topic ),
						'name'    => $topic,
					)
				);
			}
			$cards[] = array(
				'title' => 'Entry ' . $id,
				'post'  => $post,
			);
		}

		$groups = salt_collection_index_groups( $cards, 'category' );
		$this->assertSame( array( null, 'A', 'B' ), array_column( $groups, 'title' ), 'Loose first, then topics in first-seen order (pending ruling).' );
		$this->assertSame( array( 'Entry 2', 'Entry 3' ), array_column( $groups[1]['cards'], 'title' ), 'An entry under two topics is in both (pending ruling).' );
	}

	public function test_phone_numbers_follow_the_contracts_tel_rule(): void {
		$this->assertSame( 'tel:01174960123', salt_contract_tel( '0117 496 0123' ) );
		$this->assertSame( 'tel:+441174960123', salt_contract_tel( '+44 (0)117 496 0123' ) );
		$this->assertSame( 'tel:+441174960123', salt_contract_tel( '+44 0117 496 0123' ) );
		$this->assertNull( salt_contract_tel( 'call reception' ) );
		$this->assertNull( salt_contract_tel( '0117 (496) 0123' ), 'Brackets only around the group before the national number.' );
		$this->assertNull( salt_contract_tel( '1234567890123456' ), 'At most 15 digits.' );
	}

	public function test_n_of_is_a_translatable_format_in_the_contracts_english(): void {
		$this->assertSame( 'Rated 4.5 of 5', salt_contract_n_of( 'Rated', 4.5, 5 ) );
		$this->assertSame( 'Slide 2 of 3', salt_contract_n_of( 'Slide', 2, 3 ) );
		$this->assertSame( 'services', salt_query_source_list( array( 'services' ) ) );
		$this->assertSame( 'team and posts', salt_query_source_list( array( 'team', 'posts' ) ) );
	}

	public function test_dates_are_the_locales_long_form(): void {
		$this->assertSame( '12 March 2026', salt_contract_date_text( '2026-03-12' ) );
	}

	// -----------------------------------------------------------------
	// Carousel.
	// -----------------------------------------------------------------

	/**
	 * Inline carousel cards.
	 *
	 * @param int $count How many.
	 * @return array<int,array<string,mixed>>
	 */
	private static function inline_cards( int $count ): array {
		$cards = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$cards[] = array(
				'title' => 'Card ' . $i,
				'text'  => 'Words ' . $i,
			);
		}
		return $cards;
	}

	public function test_a_carousel_is_a_track_only_when_named_and_plural(): void {
		$named = $this->render_row(
			'carousel',
			array(
				'heading' => 'Recent',
				'cards'   => self::inline_cards( 3 ),
			)
		);
		$this->assertStringContainsString( '<div class="salt-carousel" role="region" aria-roledescription="carousel" aria-label="Recent"><ul class="salt-carousel__track" role="list" tabindex="0">', $named );
		$this->assertStringContainsString( 'aria-label="Slide 3 of 3"', $named );
		$this->assertStringNotContainsString( 'salt-carousel__controls', $named, 'Controls are mounted by script only.' );

		$labelled = $this->render_row(
			'carousel',
			array(
				'label' => 'Client quotes',
				'cards' => self::inline_cards( 2 ),
			)
		);
		$this->assertStringContainsString( 'aria-label="Client quotes"', $labelled );
		$this->assertStringStartsWith( '<div class="salt-section"', $labelled, 'No heading: the band is a div.' );

		$unnamed = $this->render_row( 'carousel', array( 'cards' => self::inline_cards( 2 ) ) );
		$this->assertStringContainsString( '<ul class="salt-grid" role="list"><li><div class="salt-card"><h2>Card 1</h2>', $unnamed );
		$this->assertStringNotContainsString( 'salt-carousel', $unnamed );

		$lone = $this->render_row(
			'carousel',
			array(
				'heading' => 'Our base',
				'cards'   => self::inline_cards( 1 ),
			)
		);
		$this->assertStringNotContainsString( 'salt-carousel', $lone );
		$this->assertStringNotContainsString( 'Slide 1 of 1', $lone );
	}

	public function test_a_track_whose_slides_hold_a_link_is_no_tab_stop(): void {
		$this->seed_posts(
			array(
				array(
					'ID'         => 201,
					'post_type'  => 'service',
					'post_title' => 'Design',
				),
				array(
					'ID'         => 202,
					'post_type'  => 'service',
					'post_title' => 'Planting',
				),
			)
		);
		$html = $this->render_row(
			'carousel',
			array(
				'heading' => 'Services',
				'source'  => 'services',
			)
		);
		$this->assertStringContainsString( '<ul class="salt-carousel__track" role="list">', $html );
		$this->assertStringContainsString( 'class="salt-card__link"', $html );
	}

	public function test_inline_quote_cards_without_words_or_titles_are_skipped(): void {
		$html = $this->render_row(
			'carousel',
			array(
				'heading'   => 'Quotes',
				'cardStyle' => 'quote',
				'cards'     => array(
					array(
						'title' => 'Ann',
						'role'  => 'Owner',
						'text'  => 'Lovely.',
					),
					array(
						'title' => 'Bob',
						'text'  => '',
					),
					array( 'text' => 'Nameless.' ),
				),
			)
		);
		$this->assertSame( 1, substr_count( $html, '<figure class="salt-card salt-showcase__quote">' ) );
		$this->assertStringNotContainsString( 'salt-carousel', $html, 'One card survives: drawn alone.' );
		$log = implode( ' ', $this->logs() );
		$this->assertStringContainsString( 'quote card 1 has no words', $log );
		$this->assertStringContainsString( 'inline card 2 has no title', $log );
	}

	public function test_the_carousel_script_gets_its_labels_only_when_enqueued(): void {
		salt_carousel_script_labels();
		$this->assertSame( array(), $GLOBALS['salt_test_inline_scripts'] );

		wp_enqueue_script( 'salt-section-carousel', 'carousel.js', array(), '1', array( 'in_footer' => true ) );
		salt_carousel_script_labels();
		$this->assertSame(
			array( 'before' => array( 'window.saltCarousel = {"labels":{"previous":"Previous","next":"Next"}};' ) ),
			$GLOBALS['salt_test_inline_scripts']['salt-section-carousel']
		);
	}

	// -----------------------------------------------------------------
	// The contract's srcset.
	// -----------------------------------------------------------------

	/**
	 * Seed an 800px upload with every candidate below it made.
	 *
	 * @return int Attachment ID.
	 */
	private function seed_upload(): int {
		$sizes = array();
		foreach ( salt_image_candidates() as $w ) {
			if ( $w < 800 ) {
				$sizes[ 'salt-' . $w ] = array(
					'width'  => $w,
					'height' => (int) round( 600 * $w / 800 ),
					'url'    => 'https://u.test/a-' . $w . '.jpg',
				);
			}
		}
		$GLOBALS['salt_test_attachments'][9] = array(
			'src'    => 'https://u.test/a-800.jpg',
			'width'  => 800,
			'height' => 600,
			'alt'    => 'A',
			'sizes'  => $sizes,
		);
		return 9;
	}

	public function test_the_srcset_floor_is_the_smallest_bare_viewport_share(): void {
		$this->assertSame( 320.0, salt_image_srcset_floor( '(min-width: 64rem) 19rem, (min-width: 40rem) 50vw, 100vw' ) );
		$this->assertSame( 0.0, salt_image_srcset_floor( '(min-width: 75rem) 19rem, calc((100vw - 5rem) / 3), calc(100vw - 2rem)' ), 'vw inside calc() is not bare.' );
		$this->assertSame( 0.0, salt_image_srcset_floor( '8rem' ) );
		$this->assertSame( 640.0, salt_image_srcset_floor( '100vw' ) );
	}

	public function test_the_srcset_lists_candidates_from_the_floor_then_the_upload(): void {
		$id = $this->seed_upload();

		$this->assertSame(
			'https://u.test/a-384.jpg 384w, https://u.test/a-640.jpg 640w, https://u.test/a-750.jpg 750w, https://u.test/a-800.jpg 800w',
			salt_image_contract( $id, salt_image_slot_sizes( 'card' ) )['srcset']
		);
		$this->assertStringStartsWith( 'https://u.test/a-32.jpg 32w, ', (string) salt_image_contract( $id, salt_image_slot_sizes( 'thumb' ) )['srcset'] );

		unset( $GLOBALS['salt_test_attachments'][ $id ]['sizes']['salt-640'] );
		$this->assertStringNotContainsString( '640w', (string) salt_image_contract( $id, '100vw' )['srcset'], 'A size WordPress did not make is not listed.' );
		$this->assertNull( salt_image_contract( 0, '100vw' ) );
	}

	public function test_candidate_sizes_stay_out_of_other_images_srcsets(): void {
		$meta    = array(
			'width' => 800,
			'sizes' => array(
				'medium'   => array( 'width' => 300 ),
				'salt-384' => array( 'width' => 384 ),
				'salt-640' => array( 'width' => 640 ),
			),
		);
		$sources = array(
			300 => array(),
			384 => array(),
			640 => array(),
			800 => array(),
		);
		$this->assertSame( array( 300, 800 ), array_keys( salt_image_srcset_without_candidates( $sources, array( 800, 600 ), '', $meta ) ) );
	}

	public function test_every_candidate_is_registered_as_an_uncropped_size(): void {
		$GLOBALS['salt_test_image_sizes'] = array();
		salt_register_contract_image_sizes();
		$this->assertSame( array( 32, 0, false ), $GLOBALS['salt_test_image_sizes']['salt-32'] );
		$this->assertCount( count( salt_image_candidates() ), $GLOBALS['salt_test_image_sizes'] );
	}

	// -----------------------------------------------------------------
	// The by-category save check (salt.modeRequires).
	// -----------------------------------------------------------------

	/**
	 * The generated mode field of a layout's query.
	 *
	 * @param string $layout Layout name.
	 * @return array<string,mixed>
	 */
	private static function mode_field( string $layout ): array {
		$json = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/fields/generated/sections.json' ), true );
		foreach ( $json[0]['fields'][0]['layouts'] as $entry ) {
			if ( $layout === $entry['name'] ) {
				foreach ( $entry['sub_fields'] as $field ) {
					if ( 'query' === $field['name'] ) {
						return $field['sub_fields'][0];
					}
				}
			}
		}
		return array();
	}

	public function test_by_category_is_refused_for_a_source_without_categories(): void {
		foreach ( array( 'collection-showcase', 'carousel' ) as $layout ) {
			$field = self::mode_field( $layout );
			$this->assertSame( array( 'by-category' => array( 'services', 'case-studies', 'posts' ) ), $field['salt']['modeRequires'] );

			$source = 'field_salt_' . str_replace( '-', '_', $layout ) . '_source';
			$input  = 'acf[field_salt_sections_container][row-0][field_salt_' . str_replace( '-', '_', $layout ) . '_query][' . $field['key'] . ']';

			foreach ( array(
				'team'         => false,
				'testimonials' => false,
				'posts'        => true,
				'services'     => true,
				'case-studies' => true,
			) as $chosen => $ok ) {
				$_POST['acf'] = array( 'field_salt_sections_container' => array( 'row-0' => array( $source => $chosen ) ) );
				$result       = salt_validate_query_mode( true, 'by-category', $field, $input );
				$this->assertSame( $ok, true === $result, "{$layout}: by-category with {$chosen}." );
				if ( ! $ok ) {
					$this->assertStringStartsWith( 'By category works only for services, case studies and posts: ', (string) $result, 'Built from salt.modeRequires.' );
				}
			}
			$this->assertTrue( salt_validate_query_mode( true, 'automatic', $field, $input ), 'Other modes need no source.' );
		}

		$this->assertSame( 'Already wrong', salt_validate_query_mode( 'Already wrong', 'by-category', self::mode_field( 'carousel' ), '' ), 'ACF\'s own message stands.' );
		$this->assertTrue( salt_validate_query_mode( true, 'by-category', array( 'key' => 'field_salt_hero_heading' ), '' ), 'Other fields pass through.' );
	}
}
