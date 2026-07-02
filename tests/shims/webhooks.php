<?php
/**
 * WP shims for the webhooks slice: cron scheduling capture.
 *
 * HTTP, WP_Error and transient shims are shared with the captcha slice —
 * tests/shims/http.php (requests append to
 * $GLOBALS['salt_test_http']['requests']; responses shift off
 * $GLOBALS['salt_test_http']['responses']; an empty queue yields a
 * WP_Error). Webhook tests require BOTH shim files.
 *
 * Fixtures:
 *   $GLOBALS['salt_test_cron'] captured wp_schedule_single_event calls:
 *                              array( 'timestamp' =>, 'hook' =>, 'args' => ).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $GLOBALS['salt_test_cron'] ) ) {
	$GLOBALS['salt_test_cron'] = array();
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	/**
	 * wp_schedule_single_event capture shim.
	 *
	 * @param int              $timestamp When to run.
	 * @param string           $hook      Hook name.
	 * @param array<int,mixed> $args      Hook args.
	 * @return bool
	 */
	function wp_schedule_single_event( int $timestamp, string $hook, array $args = array() ): bool {
		$GLOBALS['salt_test_cron'][] = array(
			'timestamp' => $timestamp,
			'hook'      => $hook,
			'args'      => $args,
		);
		return true;
	}
}
