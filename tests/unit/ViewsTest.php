<?php
/**
 * The views' services (inc/services/views.php): the contract's /search/?q=
 * route mapped onto WordPress's own search, the pagination window
 * (markup/pagination.json) and view-aware labels that older one-argument
 * `salt_contract_labels` callbacks still filter. Whole requests over HTTP,
 * page 2 of real results included, are proved by the real ACF harness
 * (tests/real-acf/php/singles.php).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- The tests set and read the request as a search form sends it.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../shims/views.php';

final class ViewsTest extends SaltTestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['salt_test_paths'] = array();
		$_GET                       = array();
		$_SERVER['REQUEST_URI']     = '/search/?q=hedge';
	}

	protected function tearDown(): void {
		unset( $GLOBALS['salt_test_paths'], $_SERVER['REQUEST_URI'] );
		$_GET = array();
		parent::tearDown();
	}

	/**
	 * Request a path with its query string, as WordPress parsed it.
	 *
	 * @param string              $uri  The request URI.
	 * @param array<string,mixed> $vars The query vars WordPress parsed.
	 * @return array<string,mixed>
	 */
	private function request( string $uri, array $vars ): array {
		$_SERVER['REQUEST_URI'] = $uri;
		parse_str( (string) parse_url( $uri, PHP_URL_QUERY ), $_GET ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		return salt_search_request( $vars );
	}

	public function test_q_becomes_a_wordpress_search_on_its_first_page(): void {
		// With nothing at /search/, WordPress parses the path as a page name it will not find.
		$vars = $this->request( '/search/?q=hedge+laying', array( 'pagename' => 'search' ) );

		$this->assertSame(
			array(
				's'     => 'hedge laying',
				'paged' => 0,
			),
			$vars
		);
		$this->assertSame( 'hedge laying', $_GET['s'], 'WP_Query reads $_GET[s] to know s arrived from a form' );
	}

	public function test_page_two_is_the_second_page_of_results(): void {
		$vars = $this->request( '/search?q=hedge&page=2', array( 'pagename' => 'search' ) );

		$this->assertSame(
			array(
				's'     => 'hedge',
				'paged' => 2,
			),
			$vars
		);
	}

	public function test_q_reaches_wordpress_as_written_for_wp_query_to_clean(): void {
		$vars = $this->request( '/search/?q=%3Cb%3Ehedge%3C%2Fb%3E', array( 'pagename' => 'search' ) );

		$this->assertSame( '<b>hedge</b>', $vars['s'], 'cleaned by WP_Query as ?s= is, not here' );
	}

	/**
	 * @return array<string,array{array<string,mixed>,array<string,string>}>
	 */
	public static function owned_search_paths(): array {
		return array(
			'a published page' => array( array( 'pagename' => 'search' ), array( 'search' => 'page' ) ),
			'a published post' => array( array( 'name' => 'search' ), array( 'search' => 'post' ) ),
			'an attachment'    => array( array( 'attachment' => 'search' ), array( 'search' => 'attachment' ) ),
			'a term'           => array( array( 'category_name' => 'search' ), array() ),
			'a post type'      => array( array( 'post_type' => 'search' ), array() ),
			'an author'        => array( array( 'author_name' => 'search' ), array() ),
			'a feed'           => array(
				array(
					'feed'     => 'feed',
					'pagename' => 'search',
				),
				array(),
			),
		);
	}

	/**
	 * @param array<string,mixed>  $vars  What WordPress parsed /search/ as.
	 * @param array<string,string> $paths Published posts by path.
	 */
	#[DataProvider( 'owned_search_paths' )]
	public function test_what_owns_search_is_served_as_before( array $vars, array $paths ): void {
		$GLOBALS['salt_test_paths'] = $paths;

		$this->assertSame( $vars, $this->request( '/search/?q=hedge', $vars ) );
		$this->assertArrayNotHasKey( 's', $_GET );
	}

	public function test_other_paths_and_a_wordpress_search_are_left_alone(): void {
		$this->assertSame( array( 'pagename' => 'about' ), $this->request( '/about/?q=hedge', array( 'pagename' => 'about' ) ) );
		$this->assertSame( array( 'pagename' => 'search/more' ), $this->request( '/search/more/?q=hedge', array( 'pagename' => 'search/more' ) ) );
		$this->assertSame( array( 's' => 'hedge' ), $this->request( '/?s=hedge', array( 's' => 'hedge' ) ) );
		$this->assertSame( array( 's' => 'pots' ), $this->request( '/search/?s=pots&q=hedge', array( 's' => 'pots' ) ), '?s= wins over q' );
	}

	/**
	 * The window from pagination.json's note: first, last, the current page
	 * with one neighbour each side, one-page gaps drawn as the page.
	 *
	 * @return array<string,array{int,int,array<int,int|null>}>
	 */
	public static function windows(): array {
		return array(
			'5 of 9 (the contract\'s example)' => array( 5, 9, array( 1, null, 4, 5, 6, null, 9 ) ),
			'2 of 3 (the contract\'s example)' => array( 2, 3, array( 1, 2, 3 ) ),
			'4 of 9: a one-page gap is page 2' => array( 4, 9, array( 1, 2, 3, 4, 5, null, 9 ) ),
			'1 of 9'                           => array( 1, 9, array( 1, 2, null, 9 ) ),
			'9 of 9'                           => array( 9, 9, array( 1, null, 8, 9 ) ),
			'1 of 1: no pagination'            => array( 1, 1, array() ),
		);
	}

	/**
	 * @param int                 $current The current page.
	 * @param int                 $total   How many pages.
	 * @param array<int,int|null> $window  The window.
	 */
	#[DataProvider( 'windows' )]
	public function test_the_pagination_window( int $current, int $total, array $window ): void {
		$this->assertSame( $window, salt_pagination_window( $current, $total ) );
	}

	/**
	 * README "Labels" (SC-031): placeholders replaced where they stand,
	 * nothing appended, an unknown one drawn as written.
	 *
	 * @return array<string,array{string,array<string,string>,string}>
	 */
	public static function labels(): array {
		return array(
			'the number first'           => array( '{n}. page', array( 'n' => '2' ), '2. page' ),
			'the name first'             => array( '{name} wrote this', array( 'name' => 'Priya Shah' ), 'Priya Shah wrote this' ),
			'two values'                 => array(
				'{n} results for “{q}”',
				array(
					'n' => '3',
					'q' => 'hedge',
				),
				'3 results for “hedge”',
			),
			'no placeholder: as written' => array( 'Page', array( 'n' => '2' ), 'Page' ),
			'an unknown one is kept'     => array( 'Rated {stars}', array( 'n' => '4' ), 'Rated {stars}' ),
		);
	}

	/**
	 * @param string               $label    The label.
	 * @param array<string,string> $vars     Its values.
	 * @param string               $expected What is drawn.
	 */
	#[DataProvider( 'labels' )]
	public function test_a_label_is_replaced_where_it_stands_and_nothing_is_appended( string $label, array $vars, string $expected ): void {
		$this->assertSame( $expected, salt_view_label( $label, $vars ) );
	}

	public function test_a_page_label_is_drawn_as_written(): void {
		$html  = salt_get_component(
			'pagination',
			array(
				'current' => 2,
				'total'   => 3,
				'href'    => static fn( int $page ): string => '/journal/page/' . $page . '/',
				'labels'  => array( 'page' => '{n}. page' ),
			)
		);
		$plain = salt_get_component(
			'pagination',
			array(
				'current' => 2,
				'total'   => 3,
				'href'    => static fn( int $page ): string => '/journal/page/' . $page . '/',
				'labels'  => array( 'page' => 'Page' ),
			)
		);

		$this->assertStringContainsString( 'aria-label="3. page"', $html );
		$this->assertSame( 3, substr_count( $plain, 'aria-label="Page"' ), 'no number is appended to a label without {n}' );
	}

	public function test_a_one_argument_labels_callback_still_filters_a_views_labels(): void {
		add_filter(
			'salt_contract_labels',
			static function ( array $labels ): array {
				$labels['next'] = 'Onwards';
				return $labels;
			}
		);

		$labels = salt_contract_labels( 'search' );

		$this->assertSame( 'Onwards', $labels['next'] );
		$this->assertSame( 'Search results pages', $labels['nav'], 'the view\'s own labels are still merged' );
	}

	public function test_a_two_argument_labels_callback_is_told_the_view(): void {
		$seen = array();
		add_filter(
			'salt_contract_labels',
			static function ( array $labels, string $view ) use ( &$seen ): array {
				$seen[] = $view;
				return $labels;
			},
			10,
			2
		);

		salt_contract_labels( 'archive' );
		salt_contract_labels();

		$this->assertSame( array( 'archive', '' ), $seen );
	}
}
