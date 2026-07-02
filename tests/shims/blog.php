<?php
/**
 * WP shims for the Blog Teaser section tests.
 *
 * Adds (function_exists-guarded, per the conventions contract):
 * - get_the_permalink — per-post fixture: $GLOBALS['salt_test_permalinks'][ID].
 * - get_permalink — same fixture (used for the page_for_posts link).
 * - get_post_thumbnail_id — reads the WP_Post fixture's salt_thumbnail_id.
 * - get_the_date — derives from the fixture WP_Post's post_date in the
 *   site timezone (wp_timezone shim); '' format uses get_option('date_format').
 * - get_the_excerpt — returns the fixture WP_Post's post_excerpt.
 *
 * The posts-page fixture is get_option-based (the wp-shims get_option):
 * $GLOBALS['salt_test_options']['page_for_posts'] = {page ID}, with the
 * page's URL in $GLOBALS['salt_test_permalinks'][{page ID}].
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'get_the_permalink' ) ) {
	/**
	 * get_the_permalink shim — per-post fixture permalinks with a
	 * deterministic default derived from the post type + ID (matches the
	 * service-shim default so suite-wide load order is irrelevant).
	 *
	 * @param int|WP_Post $post Post ID or object.
	 * @return string
	 */
	function get_the_permalink( $post = 0 ): string {
		$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
		if ( isset( $GLOBALS['salt_test_permalinks'][ $post_id ] ) ) {
			return (string) $GLOBALS['salt_test_permalinks'][ $post_id ];
		}
		$resolved = $post instanceof WP_Post ? $post : get_post( $post_id );
		if ( $resolved instanceof WP_Post ) {
			$type = (string) ( $resolved->post_type ?? 'post' );
			return 'https://example.test/' . $type . 's/' . $type . '-' . $post_id . '/';
		}
		// Unresolvable ID: the service-shim default, byte-for-byte, so the
		// suite behaves identically whichever shim file loads first.
		return 'https://example.test/services/service-' . $post_id . '/';
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * get_permalink shim — per-post fixture with the schema-shim fallback
	 * (same shape as the testimonial shim so load order is irrelevant).
	 *
	 * @param int $post Post ID (0 = current, resolved via the schema fixture).
	 * @return string
	 */
	function get_permalink( int $post = 0 ): string {
		if ( $post > 0 && isset( $GLOBALS['salt_test_permalinks'][ $post ] ) ) {
			return (string) $GLOBALS['salt_test_permalinks'][ $post ];
		}
		return (string) ( $GLOBALS['salt_test_schema']['permalink'] ?? '' );
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	/**
	 * get_post_thumbnail_id shim — reads the fixture WP_Post's
	 * salt_thumbnail_id property (0 = no thumbnail).
	 *
	 * @param int|WP_Post|null $post Post ID or object.
	 * @return int
	 */
	function get_post_thumbnail_id( $post = null ): int {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}
		if ( $post instanceof WP_Post ) {
			return (int) ( $post->salt_thumbnail_id ?? 0 );
		}
		return 0;
	}
}

if ( ! function_exists( 'get_the_date' ) ) {
	/**
	 * get_the_date shim — formats the fixture WP_Post's post_date in the
	 * site timezone. '' format falls back to get_option('date_format').
	 *
	 * @param string           $format PHP date format ('' = site default).
	 * @param int|WP_Post|null $post   Post ID or object.
	 * @return string|false
	 */
	function get_the_date( string $format = '', $post = null ) {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}
		if ( ! $post instanceof WP_Post || '' === (string) ( $post->post_date ?? '' ) ) {
			return false;
		}

		$datetime = date_create_immutable( (string) $post->post_date, wp_timezone() );
		if ( false === $datetime ) {
			return false;
		}

		if ( '' === $format ) {
			$option = get_option( 'date_format' );
			$format = is_string( $option ) && '' !== $option ? $option : 'F j, Y';
		}

		return $datetime->format( $format );
	}
}

if ( ! function_exists( 'get_the_excerpt' ) ) {
	/**
	 * get_the_excerpt shim — returns the fixture WP_Post's post_excerpt.
	 *
	 * @param int|WP_Post|null $post Post ID or object.
	 * @return string
	 */
	function get_the_excerpt( $post = null ): string {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}
		if ( $post instanceof WP_Post ) {
			return (string) ( $post->post_excerpt ?? '' );
		}
		return '';
	}
}
