<?php
/**
 * Shared helpers.
 *
 * Pluggable salt_* helpers live here (each wrapped in function_exists so a
 * child theme — loaded first — can replace it; see docs/contracts/OVERRIDES.md).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'salt_log' ) ) {
	/**
	 * Log a salt diagnostic (PLUGGABLE — ARCH07).
	 *
	 * The fail-loud channel behind the args contract, the section fallback
	 * and the resolver warnings: in production these must reach error_log,
	 * never vanish (ARCH05/FAIL01).
	 *
	 * @param string $message Log line.
	 * @return void
	 */
	function salt_log( string $message ): void {
		error_log( 'salt: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Deliberate production logging channel (ARCH05).
	}
}

/**
 * Whether debug behaviour (throwing assertions) is active.
 *
 * Filterable so the test suite — and nothing else — can exercise the
 * production path; production sites simply follow WP_DEBUG.
 *
 * @return bool
 */
function salt_is_debug(): bool {
	return (bool) apply_filters( 'salt_is_debug', defined( 'WP_DEBUG' ) && WP_DEBUG );
}

/**
 * Join a class list, dropping empties.
 *
 * @param array<int,string> $class_names Class name fragments.
 * @return string
 */
function salt_classnames( array $class_names ): string {
	return implode( ' ', array_filter( array_map( 'trim', $class_names ), static fn( string $c ): bool => '' !== $c ) );
}
