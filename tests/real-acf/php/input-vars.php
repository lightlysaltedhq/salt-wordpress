<?php
/**
 * Seed and independently inspect the input-limit probes. HARNESS ONLY.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';

if ( in_array( $argv[1] ?? '', array( 'guard-on', 'guard-off' ), true ) ) {
	update_option( 'salt_harness_input_guard_off', 'guard-off' === $argv[1] );
	exit;
}

if ( 'setup' === ( $argv[1] ?? '' ) ) {
	$ids = array();
	foreach ( array(
		'guarded' => 80,
		'control' => 80,
		'small'   => 1,
	) as $name => $count ) {
		$id   = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Input limit: ' . $name,
			)
		);
		$rows = array_map(
			static fn( $n ) => array(
				'acf_fc_layout' => 'contact',
				'heading'       => "Keep section $n",
				'intro'         => "Distinct content for section $n",
			),
			range( 1, $count )
		);
		update_field( 'field_salt_sections_container', $rows, $id );
		$ids[ $name ] = $id;
	}
	echo wp_json_encode( $ids );
	exit;
}

$id = (int) $argv[1];
global $wpdb;
// Read the DB itself, not ACF's in-request cache; include native data and revisions.
// Core releases _edit_lock through a separate AJAX request when the editor unloads.
$post      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$meta      = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key <> '_edit_lock' ORDER BY meta_id", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$revisions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' ORDER BY ID", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
echo wp_json_encode(
	array(
		'rows'     => count( (array) get_field( 'sections', $id, false ) ),
		'title'    => $post['post_title'],
		'snapshot' => hash( 'sha256', wp_json_encode( array( $post, $meta, $revisions ) ) ),
	)
);
