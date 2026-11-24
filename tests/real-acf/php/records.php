<?php
/**
 * The client and sector records' state (SC-035), for the browser phase's
 * editor checks (browser.mjs). HARNESS ONLY.
 *
 * Usage: php records.php --setup        (seed the records and case studies; print their IDs)
 *        php records.php --trash ID     (move a record to the bin)
 *        php records.php --restore ID   (restore it from the bin and publish it, as an editor would)
 *        php records.php ID [ID ...]    (print each case study's stored names, IDs and drawn client)
 *
 * The case studies copy every field of the newest published one, so ACF's
 * required fields are filled, and then take their own client and sector:
 * conflict (text and record name differ), record-only (no text), legacy
 * (text only) and unresolved (a record ID that is no post).
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';

/**
 * Seed two clients, two sectors and the four case studies.
 *
 * @return array<string,int>
 */
function salt_harness_records_setup(): array {
	$ids = array();
	foreach ( array(
		'clientA' => array( 'client', 'Ashby Parish Council' ),
		'clientB' => array( 'client', 'Ashby Estates Trust' ),
		'sectorA' => array( 'sector', 'Local government' ),
		'sectorB' => array( 'sector', 'Housing' ),
	) as $key => list( $type, $title ) ) {
		$ids[ $key ] = (int) wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
	}

	$source = get_posts(
		array(
			'post_type'   => 'work',
			'post_status' => 'publish',
			'numberposts' => 1,
		)
	)[0];
	$meta   = get_post_meta( $source->ID );
	$keys   = array(
		'client'       => 'field_salt_collection_case_studies_client',
		'clientRecord' => 'field_salt_collection_case_studies_client_record',
		'sector'       => 'field_salt_collection_case_studies_sector',
		'sectorRecord' => 'field_salt_collection_case_studies_sector_record',
	);
	foreach ( array(
		'conflict'   => array( 'Ashby PC', $ids['clientA'], 'Councils', $ids['sectorA'] ),
		'recordOnly' => array( '', $ids['clientA'], '', $ids['sectorA'] ),
		'legacy'     => array( 'Legacy Gardens Ltd', '', '', '' ),
		'unresolved' => array( 'Old Co', 999999, 'Old sector', 999998 ),
	) as $key => $values ) {
		$id = (int) wp_insert_post(
			array(
				'post_type'    => 'work',
				'post_status'  => 'publish',
				'post_title'   => 'Records ' . $key,
				'post_name'    => 'records-' . strtolower( $key ),
				'post_excerpt' => $source->post_excerpt,
			)
		);
		foreach ( $meta as $name => $rows ) {
			if ( '_edit_lock' !== $name && '_edit_last' !== $name ) {
				update_post_meta( $id, $name, maybe_unserialize( $rows[0] ) );
			}
		}
		foreach ( array_combine( array_keys( $keys ), $values ) as $name => $value ) {
			update_post_meta( $id, $name, (string) $value );
			update_post_meta( $id, '_' . $name, $keys[ $name ] );
		}
		$ids[ $key ] = $id;
	}

	return $ids;
}

if ( '--setup' === ( $argv[1] ?? '' ) ) {
	echo wp_json_encode( salt_harness_records_setup() );
	exit( 0 );
}
if ( '--trash' === ( $argv[1] ?? '' ) ) {
	exit( wp_trash_post( (int) $argv[2] ) ? 0 : 1 );
}
if ( '--restore' === ( $argv[1] ?? '' ) ) {
	// WordPress restores to a draft; the editor then publishes it.
	wp_untrash_post( (int) $argv[2] );
	wp_publish_post( (int) $argv[2] );
	exit( 'publish' === get_post_status( (int) $argv[2] ) ? 0 : 1 );
}

$salt_harness_state = array(
	'modules' => array(
		'client' => salt_module_enabled( 'client' ),
		'sector' => salt_module_enabled( 'sector' ),
	),
	'types'   => array(
		'client' => post_type_exists( 'client' ),
		'sector' => post_type_exists( 'sector' ),
	),
	'records' => array(
		'client' => count(
			get_posts(
				array(
					'post_type'   => 'client',
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		),
		'sector' => count(
			get_posts(
				array(
					'post_type'   => 'sector',
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		),
	),
	'works'   => array(),
);
foreach ( array_slice( $argv, 1 ) as $salt_harness_id ) {
	$salt_harness_id                                 = (int) $salt_harness_id;
	$salt_harness_state['works'][ $salt_harness_id ] = array(
		'client'       => get_post_meta( $salt_harness_id, 'client', true ),
		'clientRecord' => get_post_meta( $salt_harness_id, 'clientRecord', true ),
		'sector'       => get_post_meta( $salt_harness_id, 'sector', true ),
		'sectorRecord' => get_post_meta( $salt_harness_id, 'sectorRecord', true ),
		'drawn'        => salt_case_study_name( $salt_harness_id, 'client' ),
		'url'          => (string) get_permalink( $salt_harness_id ),
	);
}
echo wp_json_encode( $salt_harness_state );
