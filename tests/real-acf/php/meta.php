<?php
/**
 * Prints a post's status and every meta row, straight from the database, as
 * JSON; for the browser phase's before-and-after diff. HARNESS ONLY.
 *
 * Usage: php meta.php <post id>
 *        php meta.php --published-title <title>   (count of published pages)
 *        php meta.php --the-content < list.json    (each through acf_the_content)
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';

global $wpdb;

if ( '--the-content' === ( $argv[1] ?? '' ) ) {
	// A JSON list of HTML strings on stdin, each run through acf_the_content (what a wysiwyg
	// field's formatted value is), as a JSON list.
	$salt_harness_in = json_decode( (string) stream_get_contents( STDIN ), true );
	echo wp_json_encode( array_map( static fn( $html ) => apply_filters( 'acf_the_content', (string) $html ), (array) $salt_harness_in ) );
	exit( 0 );
}

if ( '--published-title' === ( $argv[1] ?? '' ) ) {
	echo (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_title = %s", (string) ( $argv[2] ?? '' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the database itself, not a cache.
	exit( 0 );
}

$salt_harness_post = (int) ( $argv[1] ?? 0 );
$salt_harness_rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $salt_harness_post ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the database itself, not a cache.
echo wp_json_encode(
	array(
		'status' => get_post_status( $salt_harness_post ),
		'meta'   => array_column( $salt_harness_rows, 'meta_value', 'meta_key' ),
	)
);
