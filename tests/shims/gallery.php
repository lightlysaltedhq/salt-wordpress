<?php
/**
 * WP shims for the gallery slice tests (attachment captions).
 *
 * Extends the media fixture set (tests/shims/media.php): captions read
 * $GLOBALS['salt_test_attachments'][{ID}]['caption'] (string, optional).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_get_attachment_caption' ) ) {
	/**
	 * wp_get_attachment_caption shim (fixture-backed).
	 *
	 * @param int $post_id Attachment ID.
	 * @return string|false Caption, or false when the attachment fixture is absent.
	 */
	function wp_get_attachment_caption( int $post_id = 0 ) {
		$fixture = $GLOBALS['salt_test_attachments'][ $post_id ] ?? null;
		if ( null === $fixture ) {
			return false;
		}
		return (string) ( $fixture['caption'] ?? '' );
	}
}
