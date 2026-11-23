<?php
/**
 * Collection-showcase and carousel snapshots (FLEET01), one per shape the
 * contract's markup draws differently: a showcase grid with an image and
 * view all, its accordion, a grouped index, a carousel track of quotes and
 * an unnamed carousel list. Each section's markup is also proved against
 * the contract's fixtures by `npm run conformance:contract`.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

foreach ( array( 'acf', 'media', 'blog', 'gallery', 'terms' ) as $salt_shim ) {
	require_once __DIR__ . '/../shims/' . $salt_shim . '.php';
}
unset( $salt_shim );

final class CollectionSectionsSnapshotTest extends SaltSnapshotTestCase {

	protected function setUp(): void {
		parent::setUp();
		salt_test_seed_fc( array() );
		salt_test_attachments_reset();
		$GLOBALS['salt_test_post_terms'] = array();
		$GLOBALS['salt_test_permalinks'] = array();
		// The posts page is the posts' index ("view all").
		$GLOBALS['salt_test_options']['page_for_posts'] = 99;
		$GLOBALS['salt_test_permalinks'][99]            = '/journal/';
	}

	/**
	 * Render one row.
	 *
	 * @param string              $layout Layout.
	 * @param array<string,mixed> $fields Row fields.
	 * @return string
	 */
	private function render_row( string $layout, array $fields ): string {
		salt_test_seed_fc(
			array(
				array(
					'layout' => $layout,
					'fields' => $fields,
				),
			)
		);
		the_row();
		// The second band on the page, below one that took the priority image.
		salt_render_state( 'set', 'priority_media', true );
		ob_start();
		salt_render_section( $layout, array( 'index' => 1 ) );
		return (string) ob_get_clean();
	}

	/**
	 * Seed three posts, the first with an image, two filed under topics.
	 *
	 * @return void
	 */
	private function seed_journal(): void {
		$GLOBALS['salt_test_attachments'][9] = array(
			'src'    => 'https://u.test/a-400.jpg',
			'width'  => 400,
			'height' => 300,
			'alt'    => 'A border',
			'sizes'  => array(
				'salt-256' => array(
					'width'  => 256,
					'height' => 192,
					'url'    => 'https://u.test/a-256.jpg',
				),
				'salt-384' => array(
					'width'  => 384,
					'height' => 288,
					'url'    => 'https://u.test/a-384.jpg',
				),
			),
		);
		$posts                               = array();
		foreach ( array( 'Mulching', 'Frost', 'Hedges' ) as $i => $title ) {
			$id                                     = 101 + $i;
			$posts[]                                = array(
				'ID'                => $id,
				'post_type'         => 'post',
				'post_title'        => $title,
				'post_excerpt'      => $title . ', in brief.',
				'post_date'         => sprintf( '2026-03-%02d 09:00:00', 12 - $i ),
				'salt_thumbnail_id' => 0 === $i ? 9 : 0,
			);
			$GLOBALS['salt_test_permalinks'][ $id ] = '/journal/' . strtolower( $title ) . '/';
		}
		$this->seed_posts( $posts );
		$GLOBALS['salt_test_post_terms'][102]['category'] = array(
			new WP_Term(
				array(
					'term_id' => 7,
					'name'    => 'Seasonal care',
				)
			),
		);
		$GLOBALS['salt_test_post_terms'][103]['category'] = array(
			new WP_Term(
				array(
					'term_id' => 8,
					'name'    => 'Wildlife',
				)
			),
		);
	}

	public function test_showcase_grid_with_tags_and_view_all(): void {
		$this->seed_journal();
		$this->assertMatchesHtmlSnapshot(
			'collection-showcase-grid',
			$this->render_row(
				'collection-showcase',
				array(
					'eyebrow'  => 'Journal',
					'heading'  => 'Latest',
					'intro'    => 'Notes from the garden.',
					'source'   => 'posts',
					'layout'   => 'grid',
					'columns'  => '3',
					'showTags' => true,
					'settings' => array( 'anchorId' => 'latest' ),
				)
			)
		);
	}

	public function test_showcase_accordion_of_posts(): void {
		$this->seed_journal();
		$this->assertMatchesHtmlSnapshot(
			'collection-showcase-accordion',
			$this->render_row(
				'collection-showcase',
				array(
					'heading' => 'Questions',
					'source'  => 'posts',
					'layout'  => 'accordion',
					'viewAll' => false,
				)
			)
		);
	}

	public function test_showcase_grouped_index(): void {
		$this->seed_journal();
		$this->assertMatchesHtmlSnapshot(
			'collection-showcase-index-grouped',
			$this->render_row(
				'collection-showcase',
				array(
					'heading'         => 'All guides',
					'source'          => 'posts',
					'layout'          => 'index',
					'groupByCategory' => true,
					'viewAll'         => false,
				)
			)
		);
	}

	public function test_carousel_track_of_quotes(): void {
		$this->assertMatchesHtmlSnapshot(
			'carousel-quotes-track',
			$this->render_row(
				'carousel',
				array(
					'label'     => 'Client quotes',
					'cardStyle' => 'quote',
					'cards'     => array(
						array(
							'title' => 'Ines',
							'role'  => 'Owner, The Walled Garden Cafe',
							'text'  => 'Customers ask to sit in the garden.',
						),
						array(
							'title' => 'Callum',
							'text'  => 'The borders have never looked better.',
						),
					),
					'settings'  => array( 'tone' => 'surface-inverse' ),
				)
			)
		);
	}

	public function test_carousel_unnamed_list(): void {
		$this->assertMatchesHtmlSnapshot(
			'carousel-unnamed-list',
			$this->render_row(
				'carousel',
				array(
					'cards'    => array(
						array(
							'title' => 'Design',
							'text'  => 'A plan drawn for your plot.',
						),
						array(
							'title' => 'Planting',
							'text'  => 'Plants chosen for your soil.',
						),
					),
					'settings' => array( 'spacing' => 'none' ),
				)
			)
		);
	}
}
