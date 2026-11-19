<?php
/**
 * Prints a post's status and every meta row, straight from the database, as
 * JSON; for the browser phase's before-and-after diff. HARNESS ONLY.
 *
 * Usage: php meta.php <post id>
 *        php meta.php --published-title <title> [post type]   (count published; pages by default)
 *        php meta.php --the-content < list.json    (each through acf_the_content)
 *        php meta.php --term-name <name> <taxonomy>   (count the terms with that name)
 *        php meta.php --post <post id>   (the post's status, title and excerpt)
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
	echo (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_title = %s", (string) ( $argv[3] ?? 'page' ), (string) ( $argv[2] ?? '' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the database itself, not a cache.
	exit( 0 );
}

if ( '--term-name' === ( $argv[1] ?? '' ) ) {
	echo (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.name = %s", (string) ( $argv[3] ?? '' ), (string) ( $argv[2] ?? '' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the database itself, not a cache.
	exit( 0 );
}

if ( '--post' === ( $argv[1] ?? '' ) ) {
	$salt_harness_row = $wpdb->get_row( $wpdb->prepare( "SELECT post_status, post_title, post_excerpt FROM {$wpdb->posts} WHERE ID = %d", (int) ( $argv[2] ?? 0 ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the database itself, not a cache.
	echo wp_json_encode(
		array(
			'status'  => $salt_harness_row['post_status'] ?? null,
			'title'   => $salt_harness_row['post_title'] ?? null,
			'excerpt' => $salt_harness_row['post_excerpt'] ?? null,
		)
	);
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
