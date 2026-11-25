<?php
/**
 * Section behaviour beyond the contract's ten tabs/listing snapshots.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

foreach ( array( 'acf', 'media', 'blog', 'listing' ) as $salt_shim ) {
	require_once __DIR__ . '/../shims/' . $salt_shim . '.php';
}
unset( $salt_shim );

final class TabsListingSectionsTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_schema']     = array();
		$GLOBALS['salt_test_page_links'] = array();
		$GLOBALS['wp_query']             = (object) array(
			'posts'         => array(),
			'max_num_pages' => 1,
			'current_post'  => -1,
		);
	}

	/**
	 * Draw a row through the same resolver, wrapper and view as a page.
	 *
	 * @param string $layout Layout.
	 * @param array  $fields Stored fields.
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
		ob_start();
		salt_render_section( $layout, array( 'is_primary' => true ) );
		return (string) ob_get_clean();
	}

	public function test_tabs_drop_unusable_panels_before_numbering_and_rank_sanitised_content(): void {
		$html = $this->render_row(
			'tabs',
			array(
				'heading'  => 'Planting',
				'settings' => array( 'anchorId' => 'planting' ),
				'tabs'     => array(
					array(
						'label'   => '<b></b>',
						'content' => '<p>Missing label</p>',
					),
					array(
						'label'   => 'Empty',
						'content' => '<p>&nbsp;</p>',
					),
					array(
						'label'   => 'First',
						'content' => '<h4>Heading</h4><p onclick="evil()">Text <strong>kept</strong></p>',
					),
					array(
						'label'   => 'Second',
						'content' => '<p><a href="javascript:evil()">Safe words</a></p>',
					),
				),
			)
		);
		$this->assertStringContainsString( 'id="planting__tab-1" checked', $html );
		$this->assertStringContainsString( 'id="planting__panel-2"', $html );
		$this->assertStringNotContainsString( '__panel-3', $html );
		$this->assertStringContainsString( '<h2>Heading</h2>', $html );
		$this->assertStringContainsString( '<strong>kept</strong>', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_tabs_labels_alone_never_create_a_blank_band(): void {
		$this->assertSame(
			'',
			$this->render_row(
				'tabs',
				array(
					'label' => 'Name only',
					'tabs'  => array( array( 'label' => 'No body' ) ),
				)
			)
		);
		$this->assertStringContainsString( 'Intro alone', $this->render_row( 'tabs', array( 'intro' => 'Intro alone' ) ) );
	}

	public function test_repeated_tab_sets_have_independent_radio_groups(): void {
		$fields = array(
			'heading'  => 'Set',
			'settings' => array( 'anchorId' => 'same' ),
			'tabs'     => array(
				array(
					'label'   => 'One',
					'content' => '<p>First</p>',
				),
				array(
					'label'   => 'Two',
					'content' => '<p>Second</p>',
				),
			),
		);
		$first  = $this->render_row( 'tabs', $fields );
		$second = $this->render_row( 'tabs', $fields );
		$this->assertStringContainsString( 'name="same__tabs"', $first );
		$this->assertStringContainsString( 'name="same-2__tabs"', $second );
		$this->assertStringContainsString( 'aria-labelledby="same-2__label-2"', $second );
	}

	public function test_listing_distinguishes_no_route_from_empty_archive_and_keeps_page_addresses(): void {
		$this->assertSame( '', $this->render_row( 'listing', array( 'heading' => 'Not an archive' ) ) );
		$GLOBALS['salt_test_schema']['is_archive'] = true;
		$GLOBALS['salt_test_query_vars']['paged']  = 2;
		$GLOBALS['wp_query']->max_num_pages        = 3;
		$GLOBALS['salt_test_page_links']           = array(
			1 => '/topics/gardens/',
			2 => '/topics/gardens/page/2/',
			3 => '/topics/gardens/page/3/',
		);
		$html                                      = $this->render_row( 'listing', array() );
		$this->assertStringContainsString( 'There are no posts to show yet.', $html );
		$this->assertStringContainsString( 'href="/topics/gardens/"', $html );
		$this->assertStringContainsString( 'href="/topics/gardens/page/3/"', $html );
		$this->assertStringContainsString( 'aria-current="page">2</a>', $html );
		$this->assertStringNotContainsString( 'salt-showcase', $html );
	}

	public function test_native_archive_draws_its_fixed_heading_without_an_acf_row(): void {
		salt_test_seed_fc( array() );
		salt_claim_h1( 'archive title' );
		ob_start();
		salt_render_section( 'listing', array( 'archive' => true ) );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'id="posts" aria-labelledby="posts__heading"', $html );
		$this->assertStringContainsString( '<h2 id="posts__heading">Latest posts</h2>', $html );
		$this->assertStringContainsString( 'There are no posts to show yet.', $html );
	}

	public function test_listing_reads_the_whole_current_page_without_advancing_the_loop(): void {
		$GLOBALS['salt_test_schema']['is_search'] = true;
		$GLOBALS['wp_query']->posts               = array(
			new WP_Post(
				array(
					'ID'         => 91,
					'post_type'  => 'post',
					'post_title' => 'First result',
				)
			),
			new WP_Post(
				array(
					'ID'         => 92,
					'post_type'  => 'post',
					'post_title' => 'Second result',
				)
			),
		);
		$GLOBALS['wp_query']->current_post        = 0;
		$html                                     = $this->render_row( 'listing', array() );
		$this->assertStringContainsString( 'First result', $html );
		$this->assertStringContainsString( 'Second result', $html );
		$this->assertSame( 2, substr_count( $html, '<h2' ), 'Without a section heading, card titles start at H2.' );
		$this->assertSame( 0, $GLOBALS['wp_query']->current_post );
	}
}
