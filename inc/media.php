<?php
/**
 * Media helpers: the sizes-attribute map and attachment resolution behind
 * x-image (spec §8; review AMBIG01 — x-image is THE single authority for
 * image markup; ARCH07 — salt_image_data() is pluggable; DATA05 — a broken
 * attachment ID degrades loudly, never into a broken <img>).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FINAL sizes-attribute map for x-image rendering contexts (spec §8).
 *
 * The map is frozen contract: changing a value changes rendered markup on
 * every fleet site (snapshot-gated, FLEET01). Unknown contexts degrade to
 * '100vw' — logged, never blank (FAIL01).
 *
 * @param string $context hero|full|half|card|gallery.
 * @return string The sizes attribute value.
 */
function salt_image_sizes( string $context ): string {
	$map = array(
		'hero'    => '100vw',
		'full'    => '100vw',
		'half'    => '(min-width: 768px) 50vw, 100vw',
		'card'    => '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw',
		'gallery' => '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw',
	);

	if ( ! isset( $map[ $context ] ) ) {
		salt_log( sprintf( 'salt_image_sizes: unknown context "%s" — using 100vw.', $context ) );
		return '100vw';
	}

	return $map[ $context ];
}

if ( ! function_exists( 'salt_image_data' ) ) {
	/**
	 * Resolve an attachment into the data x-image needs (PLUGGABLE — ARCH07).
	 *
	 * A child theme (or an offload/CDN plugin's child integration) may replace
	 * this to resolve src/srcset from another source; the returned shape is
	 * the frozen contract x-image consumes.
	 *
	 * Returns null when the attachment cannot be resolved (broken ID — DATA05
	 * spirit): logged, and x-image renders a comment instead of a broken img.
	 *
	 * @param int    $image_id Attachment ID.
	 * @param string $context  Sizes context (salt_image_sizes()).
	 * @return array{src: string, srcset: string, sizes: string, width: int, height: int, alt: string}|null
	 */
	function salt_image_data( int $image_id, string $context ): ?array {
		$src = wp_get_attachment_image_src( $image_id, 'full' );

		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			salt_log( sprintf( 'salt_image_data: attachment %d could not be resolved (DATA05).', $image_id ) );
			return null;
		}

		$srcset = wp_get_attachment_image_srcset( $image_id, 'full' );
		$alt    = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		return array(
			'src'    => (string) $src[0],
			'srcset' => is_string( $srcset ) ? $srcset : '',
			'sizes'  => salt_image_sizes( $context ),
			'width'  => (int) ( $src[1] ?? 0 ),
			'height' => (int) ( $src[2] ?? 0 ),
			'alt'    => is_string( $alt ) ? $alt : '',
		);
	}
}
