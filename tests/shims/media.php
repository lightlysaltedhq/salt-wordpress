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
		$fixture = $GLOBALS['salt_test_attachments'][ $attachment_id ] ?? null;
		// A sub-size the fixture lists under `sizes` (name => width, height, url).
		if ( is_string( $size ) && isset( $fixture['sizes'][ $size ] ) ) {
			$sub = $fixture['sizes'][ $size ];
			return array( (string) $sub['url'], (int) $sub['width'], (int) $sub['height'], true );
		}
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
	 * get_post_meta shim — resolves _wp_attachment_image_alt from the
	 * attachment fixture set, then falls through to the shared generic meta
	 * store $GLOBALS['salt_test_meta'] (see tests/shims/submissions.php) so
	 * other domains' meta shims stay compatible when the whole suite runs;
	 * any other unset key returns the empty value.
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
		if ( isset( $GLOBALS['salt_test_meta'][ $post_id ][ $key ] ) ) {
			$value = $GLOBALS['salt_test_meta'][ $post_id ][ $key ];
			return $single ? $value : array( $value );
		}
		return $single ? '' : array();
	}
}

if ( ! function_exists( 'add_image_size' ) ) {
	/**
	 * add_image_size shim: records the size, as core's $_wp_additional_image_sizes.
	 *
	 * @param string $name   Size name.
	 * @param int    $width  Width.
	 * @param int    $height Height.
	 * @param bool   $crop   Crop.
	 * @return void
	 */
	function add_image_size( string $name, int $width = 0, int $height = 0, $crop = false ): void {
		$GLOBALS['salt_test_image_sizes'][ $name ] = array( $width, $height, $crop );
	}
}

if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
	/**
	 * wp_get_attachment_metadata shim (fixture-backed): the upload's width
	 * and height, and under `sizes` the sub-sizes WordPress would have made
	 * for it, from the attachment fixture's `sizes` (name => width, height, url).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,mixed>|false
	 */
	function wp_get_attachment_metadata( int $attachment_id ) {
		$fixture = $GLOBALS['salt_test_attachments'][ $attachment_id ] ?? null;
		if ( null === $fixture ) {
			return false;
		}
		$sizes = array();
		foreach ( (array) ( $fixture['sizes'] ?? array() ) as $name => $size ) {
			$sizes[ $name ] = $size;
		}
		return array(
			'width'  => (int) ( $fixture['width'] ?? 0 ),
			'height' => (int) ( $fixture['height'] ?? 0 ),
			'sizes'  => $sizes,
		);
	}
}
