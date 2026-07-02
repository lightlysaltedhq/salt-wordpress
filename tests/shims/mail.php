<?php
/**
 * WP shims for the mailer slice: wp_mail capture, is_email, get_bloginfo.
 *
 * Every shim is function_exists-guarded so this file coexists with other
 * agents' shims of identical semantics.
 *
 * Fixtures:
 *   $GLOBALS['salt_test_mail']      captured sends: to/subject/message/headers.
 *   $GLOBALS['salt_test_mail_fail'] true => wp_mail returns false.
 *   $GLOBALS['salt_test_bloginfo']  show => value ('name' defaults below).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $GLOBALS['salt_test_mail'] ) ) {
	$GLOBALS['salt_test_mail'] = array();
}

if ( ! function_exists( 'wp_mail' ) ) {
	/**
	 * wp_mail capture shim.
	 *
	 * @param string|string[] $to          Recipient(s).
	 * @param string          $subject     Subject.
	 * @param string          $message     Body.
	 * @param string|string[] $headers     Headers.
	 * @param string[]        $attachments Attachments (unused).
	 * @return bool
	 */
	function wp_mail( $to, string $subject, string $message, $headers = array(), array $attachments = array() ): bool {
		unset( $attachments );
		$GLOBALS['salt_test_mail'][] = array(
			'to'      => $to,
			'subject' => $subject,
			'message' => $message,
			'headers' => (array) $headers,
		);
		return ! ( $GLOBALS['salt_test_mail_fail'] ?? false );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * is_email shim — like WP: returns the email on success, false otherwise.
	 * Rejects CR/LF (the property the header-injection guard relies on).
	 *
	 * @param mixed $email Candidate email.
	 * @return string|false
	 */
	function is_email( $email ) {
		if ( ! is_string( $email ) || strlen( $email ) < 6 ) {
			return false;
		}
		if ( 1 === preg_match( '/[\r\n]/', $email ) ) {
			return false;
		}
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * get_bloginfo shim (fixture-backed) — compatible SUPERSET of the
	 * schema slice's shim: $GLOBALS['salt_test_bloginfo'] wins, then the
	 * schema fixture's blogname for 'name', else '' (whichever shim file
	 * loads first, both suites see their fixtures).
	 *
	 * @param string $show Info key.
	 * @return string
	 */
	function get_bloginfo( string $show = '' ): string {
		if ( isset( $GLOBALS['salt_test_bloginfo'][ $show ] ) ) {
			return (string) $GLOBALS['salt_test_bloginfo'][ $show ];
		}
		if ( 'name' === $show ) {
			return (string) ( $GLOBALS['salt_test_schema']['blogname'] ?? '' );
		}
		return '';
	}
}
