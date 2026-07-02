<?php
/**
 * WP shims for the Service slice (CPT registration capture, permalinks,
 * archive links, loop context).
 *
 * Fixtures:
 *   $GLOBALS['salt_test_cpts']          post_type => register_post_type() args.
 *   $GLOBALS['salt_test_taxonomies']    taxonomy  => [object_type, args].
 *   $GLOBALS['salt_test_permalinks']    post ID   => permalink (defaults derive
 *                                       deterministically from the ID).
 *   $GLOBALS['salt_test_archive_links'] post_type => archive URL.
 *   $GLOBALS['salt_test_current_post']  int — get_the_ID() loop context.
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
		$GLOBALS['salt_test_cpts'][ $post_type ] = $args;
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
	 * get_the_permalink shim — per-post fixture permalinks with a
	 * deterministic default derived from the post ID.
	 *
	 * @param int|WP_Post $post Post ID or object.
	 * @return string
	 */
	function get_the_permalink( $post = 0 ): string {
		$id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
		return (string) ( $GLOBALS['salt_test_permalinks'][ $id ] ?? 'https://example.test/services/service-' . $id . '/' );
	}
}

if ( ! function_exists( 'get_post_type_archive_link' ) ) {
	/**
	 * get_post_type_archive_link shim (fixture-backed).
	 *
	 * @param string $post_type Post type.
	 * @return string|false
	 */
	function get_post_type_archive_link( string $post_type ) {
		return $GLOBALS['salt_test_archive_links'][ $post_type ] ?? 'https://example.test/' . $post_type . 's/';
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	/**
	 * get_the_ID shim (fixture-backed loop context).
	 *
	 * @return int
	 */
	function get_the_ID(): int {
		return (int) ( $GLOBALS['salt_test_current_post'] ?? 0 );
	}
}
