<?php
/**
 * HTTP + transient shims for the captcha suite (fixture-backed).
 *
 * HTTP fixture: $GLOBALS['salt_test_http'] = array(
 *   'requests'  => array(),  // Captured: array( 'url' => …, 'args' => … ).
 *   'responses' => array(),  // Scripted queue, shifted per wp_remote_post().
 * );
 * A scripted response is either a WP_Error instance or
 * array( 'code' => int, 'body' => string ). An EMPTY queue yields a
 * WP_Error — the shim itself fails closed, mirroring a network failure.
 *
 * Transient fixture: $GLOBALS['salt_test_transients'][ $key ] =
 * array( 'value' => mixed, 'expires' => int ) ('expires' 0 = never; tests
 * manipulate it directly to simulate expiry).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

/**
 * Reset the HTTP + transient fixture state (call from setUp()).
 *
 * @return void
 */
function salt_test_http_reset(): void {
	$GLOBALS['salt_test_http']       = array(
		'requests'  => array(),
		'responses' => array(),
	);
	$GLOBALS['salt_test_transients'] = array();
}
salt_test_http_reset();

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error shim.
	 */
	class WP_Error {

		/** @var string */
		private string $code;

		/** @var string */
		private string $message;

		/**
		 * Build the error.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * First error code.
		 *
		 * @return string
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * First error message.
		 *
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * is_wp_error shim.
	 *
	 * @param mixed $thing Maybe-error.
	 * @return bool
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * wp_remote_post capture shim — records the request, returns the next
	 * scripted response (WP_Error when the queue is empty).
	 *
	 * @param string              $url  Request URL.
	 * @param array<string,mixed> $args Request args (timeout, body, …).
	 * @return array<string,mixed>|WP_Error
	 */
	function wp_remote_post( string $url, array $args = array() ) {
		$GLOBALS['salt_test_http']['requests'][] = array(
			'url'  => $url,
			'args' => $args,
		);

		$scripted = array_shift( $GLOBALS['salt_test_http']['responses'] );
		if ( null === $scripted ) {
			return new WP_Error( 'http_request_failed', 'No scripted response queued (shim fails closed).' );
		}
		if ( $scripted instanceof WP_Error ) {
			return $scripted;
		}

		return array(
			'response' => array( 'code' => (int) ( $scripted['code'] ?? 200 ) ),
			'body'     => (string) ( $scripted['body'] ?? '' ),
		);
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * wp_remote_retrieve_response_code shim.
	 *
	 * @param mixed $response Response array or WP_Error.
	 * @return int|string HTTP code, or '' on error (matches WP).
	 */
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			return '';
		}
		return $response['response']['code'] ?? '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * wp_remote_retrieve_body shim.
	 *
	 * @param mixed $response Response array or WP_Error.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ): string {
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			return '';
		}
		return (string) ( $response['body'] ?? '' );
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * set_transient shim (array-backed).
	 *
	 * @param string $transient  Key.
	 * @param mixed  $value      Value.
	 * @param int    $expiration TTL in seconds (0 = never).
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

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * get_transient shim — false on missing or expired (expired entries are
	 * pruned, like WP).
	 *
	 * @param string $transient Key.
	 * @return mixed Value, or false.
	 */
	function get_transient( string $transient ) {
		$entry = $GLOBALS['salt_test_transients'][ $transient ] ?? null;
		if ( null === $entry ) {
			return false;
		}
		if ( 0 !== $entry['expires'] && $entry['expires'] < time() ) {
			unset( $GLOBALS['salt_test_transients'][ $transient ] );
			return false;
		}
		return $entry['value'];
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * delete_transient shim.
	 *
	 * @param string $transient Key.
	 * @return bool True when it existed.
	 */
	function delete_transient( string $transient ): bool {
		$existed = isset( $GLOBALS['salt_test_transients'][ $transient ] );
		unset( $GLOBALS['salt_test_transients'][ $transient ] );
		return $existed;
	}
}
