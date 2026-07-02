<?php
/**
 * WP shims for the work CPT + Portfolio/Work section tests.
 *
 * Adds (function_exists-guarded, per the conventions contract):
 * - register_post_type / register_taxonomy capture (same fixture globals
 *   as tests/shims/faq.php: salt_test_post_types / salt_test_taxonomies).
 * - get_the_permalink — per-post fixture: $GLOBALS['salt_test_permalinks'][ID].
 * - get_post_thumbnail_id — reads the WP_Post fixture's salt_thumbnail_id.
 * - get_post_type_archive_link — fixture: $GLOBALS['salt_test_archive_links'].
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'register_post_type' ) ) {
	/**
	 * register_post_type capture shim.
	 *
	 * @param string              $post_type Post type key.
	 * @param array<string,mixed> $args      Registration args.
	 * @return void
	 */
	function register_post_type( string $post_type, array $args = array() ): void {
		$GLOBALS['salt_test_post_types'][ $post_type ] = $args;
	}
}

if ( ! function_exists( 'register_taxonomy' ) ) {
	/**
	 * register_taxonomy capture shim.
	 *
	 * @param string              $taxonomy    Taxonomy key.
	 * @param string|string[]     $object_type Object type(s).
	 * @param array<string,mixed> $args        Registration args.
	 * @return void
	 */
	function register_taxonomy( string $taxonomy, $object_type, array $args = array() ): void {
		$GLOBALS['salt_test_taxonomies'][ $taxonomy ] = array(
			'object_type' => (array) $object_type,
			'args'        => $args,
		);
	}
}

if ( ! function_exists( 'get_the_permalink' ) ) {
	/**
	 * get_the_permalink shim (fixture-backed, per post ID).
	 *
	 * Fixtures: $GLOBALS['salt_test_permalinks'][ {post ID} ] = string URL.
	 *
	 * @param int|WP_Post $post Post ID or object.
	 * @return string
	 */
	function get_the_permalink( $post = 0 ): string {
		$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
		return (string) ( $GLOBALS['salt_test_permalinks'][ $post_id ] ?? '' );
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	/**
	 * get_post_thumbnail_id shim — reads the fixture WP_Post's
	 * salt_thumbnail_id property (0 = no thumbnail).
	 *
	 * @param int|WP_Post|null $post Post ID or object.
	 * @return int
	 */
	function get_post_thumbnail_id( $post = null ): int {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}
		if ( $post instanceof WP_Post ) {
			return (int) ( $post->salt_thumbnail_id ?? 0 );
		}
		return 0;
	}
}

if ( ! function_exists( 'get_post_type_archive_link' ) ) {
	/**
	 * get_post_type_archive_link shim (fixture-backed).
	 *
	 * Fixtures: $GLOBALS['salt_test_archive_links'][ {post_type} ] = string
	 * URL, or false. Unset defaults to a deterministic example.test URL.
	 *
	 * @param string $post_type Post type.
	 * @return string|false
	 */
	function get_post_type_archive_link( string $post_type ) {
		if ( isset( $GLOBALS['salt_test_archive_links'] ) && array_key_exists( $post_type, $GLOBALS['salt_test_archive_links'] ) ) {
			return $GLOBALS['salt_test_archive_links'][ $post_type ];
		}
		return 'https://example.test/' . $post_type . '/';
	}
}
