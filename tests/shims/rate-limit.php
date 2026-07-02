<?php
/**
 * WP shims for the rate-limit slice: wp_salt + transients.
 *
 * Transients are array-backed in $GLOBALS['salt_test_transients'] as
 * name => array( 'value' => mixed, 'expires' => int|0 ) — 0 means never.
 * Every shim is function_exists-guarded so this file coexists with any
 * other agent's transient shims of identical semantics.
 *
 * Fixtures:
 *   $GLOBALS['salt_test_transients']  name => ['value' =>, 'expires' =>].
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $GLOBALS['salt_test_transients'] ) ) {
	$GLOBALS['salt_test_transients'] = array();
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * wp_unslash shim.
	 *
	 * @param string|array $value Value.
	 * @return string|array
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * wp_salt shim — deterministic per-scheme salt.
	 *
	 * @param string $scheme Salt scheme.
	 * @return string
	 */
	function wp_salt( string $scheme = 'auth' ): string {
		return 'salt-test-salt-' . $scheme;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * get_transient shim (array-backed, expiry-aware).
	 *
	 * @param string $transient Transient name.
	 * @return mixed Value, or false if missing/expired.
	 */
	function get_transient( string $transient ) {
		$row = $GLOBALS['salt_test_transients'][ $transient ] ?? null;
		if ( ! is_array( $row ) || ! array_key_exists( 'value', $row ) ) {
			return false;
		}
		$expires = (int) ( $row['expires'] ?? 0 );
		if ( $expires > 0 && time() >= $expires ) {
			unset( $GLOBALS['salt_test_transients'][ $transient ] );
			return false;
		}
		return $row['value'];
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * set_transient shim (array-backed).
	 *
	 * @param string $transient  Transient name.
	 * @param mixed  $value      Value.
	 * @param int    $expiration Seconds until expiry (0 = never).
	 * @return bool
	 */
	function set_transient( string $transient, $value, int $expiration = 0 ): bool {
		$GLOBALS['salt_test_transients'][ $transient ] = array(
			'value'   => $value,
			'expires' => $expiration > 0 ? time() + $expiration : 0,
		);
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * delete_transient shim (array-backed).
	 *
	 * @param string $transient Transient name.
	 * @return bool
	 */
	function delete_transient( string $transient ): bool {
		$existed = isset( $GLOBALS['salt_test_transients'][ $transient ] );
		unset( $GLOBALS['salt_test_transients'][ $transient ] );
		return $existed;
	}
}
