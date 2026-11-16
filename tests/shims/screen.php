<?php
/**
 * WP shim for the current admin screen (admin-notice tests).
 *
 * get_current_screen() returns $GLOBALS['salt_test_screen'] (an object with
 * an `id`, or null). Tests set and clear that global themselves.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'get_current_screen' ) ) {
	/**
	 * The current admin screen, or null outside one.
	 *
	 * @return object|null
	 */
	function get_current_screen(): ?object {
		$screen = $GLOBALS['salt_test_screen'] ?? null;
		return is_object( $screen ) ? $screen : null;
	}
}
