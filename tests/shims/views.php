<?php
/**
 * WP shims for the views' services (inc/services/views.php): the /search/
 * route's lookups.
 *
 * Fixtures:
 *   $GLOBALS['salt_test_paths'] path => post type of the published post at
 *                               that path (get_page_by_path()).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * untrailingslashit shim.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	/**
	 * get_post_types shim: the public post types, keyed by name.
	 *
	 * @param array<string,mixed> $args Ignored.
	 * @return array<string,string>
	 */
	function get_post_types( array $args = array() ): array {
		unset( $args );
		return array(
			'post'       => 'post',
			'page'       => 'page',
			'attachment' => 'attachment',
		);
	}
}

if ( ! function_exists( 'get_page_by_path' ) ) {
	/**
	 * get_page_by_path shim, from $GLOBALS['salt_test_paths'].
	 *
	 * @param string          $path      Path.
	 * @param string          $output    Ignored.
	 * @param string|string[] $post_type Post types to look in.
	 * @return WP_Post|null
	 */
	function get_page_by_path( string $path, string $output = 'OBJECT', $post_type = 'page' ) {
		unset( $output );
		$type = $GLOBALS['salt_test_paths'][ $path ] ?? null;
		if ( null === $type || ! in_array( $type, (array) $post_type, true ) ) {
			return null;
		}
		return new WP_Post(
			array(
				'ID'        => 9001,
				'post_type' => $type,
			)
		);
	}
}
