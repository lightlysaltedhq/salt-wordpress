<?php
/**
 * WP shims for the performance module tests (image editor capability).
 *
 * Backed by $GLOBALS['salt_test_image_support'] = array(
 *     'image/avif' => bool,
 *     'image/webp' => bool,
 * ).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_image_editor_supports' ) ) {
	/**
	 * wp_image_editor_supports shim (fixture-backed).
	 *
	 * @param array<string,mixed> $args Capability query, e.g. array( 'mime_type' => 'image/avif' ).
	 * @return bool
	 */
	function wp_image_editor_supports( array $args = array() ): bool {
		$mime = $args['mime_type'] ?? '';
		return (bool) ( $GLOBALS['salt_test_image_support'][ $mime ] ?? false );
	}
}
