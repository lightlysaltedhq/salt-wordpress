<?php
/**
 * WP shims for the schema graph tests (URL/title/conditional surface).
 *
 * Backed by $GLOBALS['salt_test_schema'] = array(
 *     'home_url'       => string,  // Site root, no trailing slash.
 *     'blogname'       => string,
 *     'document_title' => string,
 *     'is_archive'     => bool,
 *     'is_singular'    => bool,
 *     'permalink'      => string,
 * ).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * home_url shim (fixture-backed).
	 *
	 * @param string $path Optional path relative to the home URL.
	 * @return string
	 */
	function home_url( string $path = '' ): string {
		$base = rtrim( (string) ( $GLOBALS['salt_test_schema']['home_url'] ?? 'https://example.test' ), '/' );
		if ( '' === $path ) {
			return $base;
		}
		return $base . '/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * get_bloginfo shim (fixture-backed, 'name' only).
	 *
	 * @param string $show Info key.
	 * @return string
	 */
	function get_bloginfo( string $show = '' ): string {
		if ( 'name' === $show ) {
			return (string) ( $GLOBALS['salt_test_schema']['blogname'] ?? '' );
		}
		return '';
	}
}

if ( ! function_exists( 'wp_get_document_title' ) ) {
	/**
	 * wp_get_document_title shim (fixture-backed).
	 *
	 * @return string
	 */
	function wp_get_document_title(): string {
		return (string) ( $GLOBALS['salt_test_schema']['document_title'] ?? '' );
	}
}

if ( ! function_exists( 'is_archive' ) ) {
	/**
	 * is_archive shim (fixture-backed).
	 *
	 * @return bool
	 */
	function is_archive(): bool {
		return (bool) ( $GLOBALS['salt_test_schema']['is_archive'] ?? false );
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * is_singular shim (fixture-backed).
	 *
	 * @param string|string[] $post_types Post types (unused).
	 * @return bool
	 */
	function is_singular( $post_types = '' ): bool {
		unset( $post_types );
		return (bool) ( $GLOBALS['salt_test_schema']['is_singular'] ?? false );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * get_permalink shim (fixture-backed).
	 *
	 * @param int $post Post ID (unused).
	 * @return string
	 */
	function get_permalink( int $post = 0 ): string {
		unset( $post );
		return (string) ( $GLOBALS['salt_test_schema']['permalink'] ?? '' );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * wp_unslash shim.
	 *
	 * @param mixed $value Slashed value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * esc_url_raw shim — strips dangerous protocols, no entity encoding.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	function esc_url_raw( string $url ): string {
		$url = trim( $url );
		if ( preg_match( '/^\s*(javascript|data|vbscript):/i', $url ) ) {
			return '';
		}
		return $url;
	}
}
