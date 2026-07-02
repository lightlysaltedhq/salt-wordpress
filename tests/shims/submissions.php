<?php
/**
 * WP shims for the submissions slice (post insertion/deletion, post meta,
 * WP-Cron scheduling).
 *
 * Fixtures:
 *   $GLOBALS['salt_test_posts']       shared WP_Post fixture set (wp-shims.php)
 *                                     — wp_insert_post() appends, wp_delete_post()
 *                                     removes, so get_post()/get_posts() work.
 *   $GLOBALS['salt_test_meta']        post ID => [ meta_key => value ].
 *   $GLOBALS['salt_test_cron']        hook => [ 'timestamp' => int, 'recurrence' => string ].
 *   $GLOBALS['salt_test_insert_fail'] bool — force wp_insert_post() to fail (0).
 *
 * @package Salt\Tests
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * wp_insert_post shim — appends a WP_Post to the shared fixture set.
	 *
	 * @param array<string,mixed> $postarr  Post fields.
	 * @param bool                $wp_error Return WP_Error on failure (shim: always 0).
	 * @return int Post ID, or 0 when $GLOBALS['salt_test_insert_fail'] is set.
	 */
	function wp_insert_post( array $postarr, bool $wp_error = false ) {
		unset( $wp_error );

		if ( ! empty( $GLOBALS['salt_test_insert_fail'] ) ) {
			return 0;
		}

		$max = 0;
		foreach ( $GLOBALS['salt_test_posts'] as $post ) {
			$max = max( $max, (int) $post->ID );
		}
		$id = $max + 1;

		$GLOBALS['salt_test_posts'][] = new WP_Post(
			array(
				'ID'          => $id,
				'post_type'   => (string) ( $postarr['post_type'] ?? 'post' ),
				'post_status' => (string) ( $postarr['post_status'] ?? 'draft' ),
				'post_title'  => (string) ( $postarr['post_title'] ?? '' ),
				'post_date'   => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		return $id;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	/**
	 * wp_delete_post shim — removes the post and its meta from the fixtures.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Force delete (recorded only; shim always removes).
	 * @return WP_Post|false Deleted post, false when unknown.
	 */
	function wp_delete_post( int $post_id, bool $force = false ) {
		unset( $force );

		foreach ( $GLOBALS['salt_test_posts'] as $index => $post ) {
			if ( (int) $post->ID === $post_id ) {
				unset( $GLOBALS['salt_test_posts'][ $index ], $GLOBALS['salt_test_meta'][ $post_id ] );
				$GLOBALS['salt_test_posts'] = array_values( $GLOBALS['salt_test_posts'] );
				return $post;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * update_post_meta shim (fixture-backed).
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return bool
	 */
	function update_post_meta( int $post_id, string $meta_key, $meta_value ): bool {
		$GLOBALS['salt_test_meta'][ $post_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * get_post_meta shim — generic store $GLOBALS['salt_test_meta'], plus the
	 * media slice's _wp_attachment_image_alt branch (tests/shims/media.php
	 * defines the same behaviour; whichever loads first must serve both
	 * domains, so the two shims mirror each other).
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param bool   $single   Return a single value.
	 * @return mixed Empty value when unset (matches WP's behaviour).
	 */
	function get_post_meta( int $post_id, string $meta_key = '', bool $single = false ) {
		if ( '_wp_attachment_image_alt' === $meta_key ) {
			$alt = (string) ( $GLOBALS['salt_test_attachments'][ $post_id ]['alt'] ?? '' );
			return $single ? $alt : array( $alt );
		}
		if ( isset( $GLOBALS['salt_test_meta'][ $post_id ][ $meta_key ] ) ) {
			$value = $GLOBALS['salt_test_meta'][ $post_id ][ $meta_key ];
			return $single ? $value : array( $value );
		}
		return $single ? '' : array();
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * wp_next_scheduled shim (fixture-backed).
	 *
	 * @param string $hook Hook name.
	 * @return int|false Timestamp when scheduled, false otherwise.
	 */
	function wp_next_scheduled( string $hook ) {
		return isset( $GLOBALS['salt_test_cron'][ $hook ] )
			? (int) $GLOBALS['salt_test_cron'][ $hook ]['timestamp']
			: false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * wp_schedule_event shim (fixture-backed).
	 *
	 * @param int    $timestamp  First run.
	 * @param string $recurrence Schedule name.
	 * @param string $hook       Hook name.
	 * @return bool
	 */
	function wp_schedule_event( int $timestamp, string $recurrence, string $hook ): bool {
		$GLOBALS['salt_test_cron'][ $hook ] = array(
			'timestamp'  => $timestamp,
			'recurrence' => $recurrence,
		);
		return true;
	}
}
