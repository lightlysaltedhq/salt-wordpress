<?php
/**
 * WP shims for admin-screen service tests (script localisation capture).
 *
 * Captures to $GLOBALS['salt_test_localized'][ $handle ][ $object_name ] —
 * tests reset that global themselves (it is not part of salt_test_reset()).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * wp_localize_script capture shim.
	 *
	 * @param string              $handle      Registered script handle.
	 * @param string              $object_name JS global name.
	 * @param array<string,mixed> $l10n        Data payload.
	 * @return bool
	 */
	function wp_localize_script( string $handle, string $object_name, array $l10n ): bool {
		$GLOBALS['salt_test_localized'][ $handle ][ $object_name ] = $l10n;
		return true;
	}
}
