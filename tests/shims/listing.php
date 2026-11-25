<?php
/**
 * The main route's conditionals and page addresses for listing tests.
 *
 * @package Salt\Tests
 */

require_once __DIR__ . '/schema.php';

if ( ! function_exists( 'is_home' ) ) {
	/**
	 * Whether this route is the posts index.
	 *
	 * @return bool
	 */
	function is_home(): bool {
		return (bool) ( $GLOBALS['salt_test_schema']['is_home'] ?? false );
	}
}
if ( ! function_exists( 'is_search' ) ) {
	/**
	 * Whether this route is a search.
	 *
	 * @return bool
	 */
	function is_search(): bool {
		return (bool) ( $GLOBALS['salt_test_schema']['is_search'] ?? false );
	}
}
if ( ! function_exists( 'get_pagenum_link' ) ) {
	/**
	 * Read the route's page address, not a URL computed by the section.
	 *
	 * @param int $number Page number.
	 * @return string
	 */
	function get_pagenum_link( int $number = 1 ): string {
		return $GLOBALS['salt_test_page_links'][ $number ] ?? '/?paged=' . $number;
	}
}
