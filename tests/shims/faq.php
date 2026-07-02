<?php
/**
 * WP shims for the FAQ CPT tests (register_post_type / register_taxonomy
 * capture — not provided by tests/wp-shims.php).
 *
 * Fixtures land in $GLOBALS['salt_test_post_types'] and
 * $GLOBALS['salt_test_taxonomies'], keyed by name.
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
		// Alias used by the service-slice tests (tests/shims/service.php shape)
		// — whichever slice shim loads first must satisfy every consumer.
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
		// Both key spellings: 'object_type' (service-slice shape) and
		// 'object_types' (team-slice shape) — whichever slice shim loads
		// first must satisfy every consumer.
		$GLOBALS['salt_test_taxonomies'][ $taxonomy ] = array(
			'object_type'  => (array) $object_type,
			'object_types' => (array) $object_type,
			'args'         => $args,
		);
	}
}
