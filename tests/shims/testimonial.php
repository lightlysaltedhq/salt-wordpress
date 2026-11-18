<?php
/**
 * WP shims for the testimonial slice tests (CPT capture + permalinks).
 *
 * Fixtures:
 *   $GLOBALS['salt_test_cpts']       — register_post_type() capture:
 *                                      post_type => args.
 *   $GLOBALS['salt_test_permalinks'] — post ID => permalink URL, read by
 *                                      the get_permalink shim; falls back
 *                                      to $GLOBALS['salt_test_schema']
 *                                      ['permalink'] so this shim stays
 *                                      compatible with tests/shims/schema.php
 *                                      whichever loads first.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $GLOBALS['salt_test_cpts'] ) ) {
	$GLOBALS['salt_test_cpts'] = array();
}

if ( ! isset( $GLOBALS['salt_test_permalinks'] ) ) {
	$GLOBALS['salt_test_permalinks'] = array();
}

if ( ! function_exists( 'register_post_type' ) ) {
	/**
	 * register_post_type capture shim.
	 *
	 * Writes BOTH fleet capture globals (salt_test_cpts + salt_test_post_types)
	 * — slice shims are function_exists-guarded, so whichever loads first must
	 * satisfy every consumer (see tests/shims/faq.php).
	 *
	 * @param string              $post_type Post type key.
	 * @param array<string,mixed> $args      Registration args.
	 * @return void
	 */
	function register_post_type( string $post_type, array $args = array() ): void {
		$GLOBALS['salt_test_cpts'][ $post_type ]       = $args;
		$GLOBALS['salt_test_post_types'][ $post_type ] = $args;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * get_permalink shim — per-post fixture with the schema-shim fallback.
	 *
	 * @param int $post Post ID (0 = current, resolved via the schema fixture).
	 * @return string
	 */
	function get_permalink( int $post = 0 ): string {
		if ( $post > 0 && isset( $GLOBALS['salt_test_permalinks'][ $post ] ) ) {
			return (string) $GLOBALS['salt_test_permalinks'][ $post ];
		}
		return (string) ( $GLOBALS['salt_test_schema']['permalink'] ?? '' );
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	/**
	 * get_post_thumbnail_id shim — reads the fixture's salt_thumbnail_id.
	 *
	 * @param WP_Post|int|null $post Post fixture or ID.
	 * @return int Attachment ID, 0 when none.
	 */
	function get_post_thumbnail_id( $post = null ): int {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}
		if ( ! $post instanceof WP_Post ) {
			return 0;
		}
		return (int) ( $post->salt_thumbnail_id ?? 0 );
	}
}
