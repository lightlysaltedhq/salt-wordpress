<?php
/**
 * WP shims for the location slice tests (CPT/taxonomy registration capture).
 *
 * Captures:
 *   $GLOBALS['salt_test_cpts'][ $post_type ]        = $args
 *   $GLOBALS['salt_test_taxonomies'][ $taxonomy ]   = array( 'object_type' => string[], 'args' => array )
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
	 * @param string|string[]     $object_type Post type(s) the taxonomy attaches to.
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
