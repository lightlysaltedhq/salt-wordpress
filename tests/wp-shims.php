<?php
/**
 * Minimal WordPress shims for the unit suite and CLI tooling.
 *
 * The PHPUnit suite tests salt's pure shared logic without booting
 * WordPress; these shims provide just enough of the WP surface for that
 * code to load. They grow with the foundation (Phase 1) — every shim
 * mirrors documented WordPress behaviour for the inputs our code uses.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation shim — returns the source string.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain (unused).
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * esc_html shim.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * esc_attr shim.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Hook-capture shim. Callbacks are recorded so tooling (e.g. the slug
	 * extractor) can invoke registrations without booting WordPress.
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback  Callback.
	 * @param int      $priority  Priority.
	 * @param int      $args      Accepted args.
	 * @return bool
	 */
	function add_action( string $hook_name, callable $callback, int $priority = 10, int $args = 1 ): bool {
		unset( $args );
		$GLOBALS['salt_test_hooks'][ $hook_name ][ $priority ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Filter-capture shim (same registry as add_action).
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback  Callback.
	 * @param int      $priority  Priority.
	 * @param int      $args      Accepted args.
	 * @return bool
	 */
	function add_filter( string $hook_name, callable $callback, int $priority = 10, int $args = 1 ): bool {
		return add_action( $hook_name, $callback, $priority, $args );
	}
}

/**
 * Run every callback captured for a hook, in priority order.
 *
 * @param string $hook_name Hook name.
 * @return void
 */
function salt_test_do_action( string $hook_name ): void {
	$callbacks = $GLOBALS['salt_test_hooks'][ $hook_name ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $batch ) {
		foreach ( $batch as $callback ) {
			$callback();
		}
	}
}
