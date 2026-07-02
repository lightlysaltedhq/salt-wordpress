<?php
/**
 * WP shims for the media module tests (attachment resolution).
 *
 * Backed by $GLOBALS['salt_test_attachments'] = array(
 *     {attachment ID} => array(
 *         'src'    => string,  URL of the full-size file.
 *         'width'  => int,     Intrinsic width.
 *         'height' => int,     Intrinsic height.
 *         'srcset' => string,  '' makes wp_get_attachment_image_srcset() return false.
 *         'alt'    => string,  _wp_attachment_image_alt meta.
 *     ),
 * );
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $GLOBALS['salt_test_attachments'] ) ) {
	$GLOBALS['salt_test_attachments'] = array();
}

if ( ! function_exists( 'salt_test_attachments_reset' ) ) {
	/**
	 * Reset the attachment fixture set between tests.
	 *
	 * @return void
	 */
	function salt_test_attachments_reset(): void {
		$GLOBALS['salt_test_attachments'] = array();
	}
}

if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
	/**
	 * wp_get_attachment_image_src shim (fixture-backed).
	 *
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Registered size (unused — fixtures are single-size).
	 * @return array{0: string, 1: int, 2: int, 3: bool}|false
	 */
	function wp_get_attachment_image_src( int $attachment_id, $size = 'thumbnail' ) {
		unset( $size );
		$fixture = $GLOBALS['salt_test_attachments'][ $attachment_id ] ?? null;
		if ( null === $fixture || '' === (string) ( $fixture['src'] ?? '' ) ) {
			return false;
		}
		return array(
			(string) $fixture['src'],
			(int) ( $fixture['width'] ?? 0 ),
			(int) ( $fixture['height'] ?? 0 ),
			false,
		);
	}
}

if ( ! function_exists( 'wp_get_attachment_image_srcset' ) ) {
	/**
	 * wp_get_attachment_image_srcset shim (fixture-backed).
	 *
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Registered size (unused).
	 * @return string|false
	 */
	function wp_get_attachment_image_srcset( int $attachment_id, $size = 'medium' ) {
		unset( $size );
		$srcset = $GLOBALS['salt_test_attachments'][ $attachment_id ]['srcset'] ?? '';
		return '' !== $srcset ? (string) $srcset : false;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * get_post_meta shim — media-scoped: resolves _wp_attachment_image_alt
	 * from the attachment fixture set; any other key returns the empty value.
	 *
	 * @param int    $post_id Post (attachment) ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Return a single value.
	 * @return mixed
	 */
	function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
		if ( '_wp_attachment_image_alt' === $key ) {
			$alt = (string) ( $GLOBALS['salt_test_attachments'][ $post_id ]['alt'] ?? '' );
			return $single ? $alt : array( $alt );
		}
		return $single ? '' : array();
	}
}
