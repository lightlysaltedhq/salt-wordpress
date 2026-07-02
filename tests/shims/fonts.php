<?php
/**
 * Font service shims (fixture-backed).
 *
 * Fixtures: $GLOBALS['salt_test_attachment_urls'] = array( id => url ).
 * A missing id returns false, matching wp_get_attachment_url() for a
 * broken/deleted attachment.
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	/**
	 * wp_get_attachment_url shim (fixture-backed).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false URL, or false when the attachment is unknown.
	 */
	function wp_get_attachment_url( int $attachment_id ) {
		return $GLOBALS['salt_test_attachment_urls'][ $attachment_id ] ?? false;
	}
}
