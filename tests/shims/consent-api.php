<?php
/**
 * WP Consent API shim (fixture-backed).
 *
 * NOTE: once required, wp_has_consent() exists for the remainder of the
 * suite process — tests relying on its ABSENCE must run in files that never
 * load this shim (PHPUnit loads test files eagerly, so keep this require
 * inside the specific test method, as ConsentTest does).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! function_exists( 'wp_has_consent' ) ) {
	/**
	 * wp_has_consent shim — reads $GLOBALS['salt_test_wp_consent'].
	 *
	 * @param string $category WP Consent API category.
	 * @return bool
	 */
	function wp_has_consent( string $category ): bool {
		return true === ( $GLOBALS['salt_test_wp_consent'][ $category ] ?? false );
	}
}
