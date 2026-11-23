<?php
/**
 * The CSP browser check's site half (HARNESS ONLY, run by
 * tests/real-acf/csp.mjs through run.sh). The modes:
 *
 *   seed     pretty permalinks (core prints its speculation rules only
 *            then), maps on with a tile host the browser check answers
 *            itself, two offices (one with a stored zoom, one without) and a
 *            published page whose one section lists them; prints the
 *            page's address.
 *   restore  plain permalinks, maps off, the offices and the page deleted.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';

$salt_csp_mode = (string) ( $argv[1] ?? '' );

if ( 'seed' === $salt_csp_mode ) {
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '/%postname%/' );
	flush_rewrite_rules();
	update_field( 'maps_enabled', 1, 'option' );
	update_field( 'maps_tile_url', 'https://tiles.salt-harness.test/{z}/{x}/{y}.png', 'option' );
	foreach ( array( array( 'CSP check studio', 51.4503, -2.5977, 15 ), array( 'CSP check office', 51.3822, -2.359, null ) ) as $salt_csp_office ) {
		$salt_csp_id = (int) wp_insert_post(
			array(
				'post_type'   => 'location',
				'post_status' => 'publish',
				'post_title'  => $salt_csp_office[0],
			)
		);
		update_field( 'field_salt_collection_locations_lat', $salt_csp_office[1], $salt_csp_id );
		update_field( 'field_salt_collection_locations_lng', $salt_csp_office[2], $salt_csp_id );
		if ( null !== $salt_csp_office[3] ) {
			update_field( 'field_salt_collection_locations_zoom', $salt_csp_office[3], $salt_csp_id );
		}
	}
	$salt_csp_page = (int) wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'CSP check',
			'post_name'   => 'csp-check',
		)
	);
	update_field(
		'field_salt_sections_container',
		array(
			array(
				'acf_fc_layout' => 'locations',
				'heading'       => 'Our offices',
			),
		),
		$salt_csp_page
	);
	echo esc_url_raw( (string) get_permalink( $salt_csp_page ) );
	exit( 0 );
}

if ( 'restore' === $salt_csp_mode ) {
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '' );
	flush_rewrite_rules();
	update_field( 'maps_enabled', 0, 'option' );
	foreach ( get_posts(
		array(
			'post_type'   => array( 'location', 'page' ),
			'post_status' => 'any',
			'numberposts' => -1,
			's'           => 'CSP check',
		)
	) as $salt_csp_post ) {
		wp_delete_post( $salt_csp_post->ID, true );
	}
	exit( 0 );
}

fwrite( STDERR, "usage: csp-page.php seed|restore\n" );
exit( 2 );
