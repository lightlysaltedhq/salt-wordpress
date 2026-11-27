<?php
/**
 * The link underlines browser check's site half (HARNESS ONLY, run by
 * tests/real-acf/link-underlines.mjs through run.sh). The modes:
 *
 *   seed     a post that draws every link the contract's stylesheet
 *            underlines in the post view: filed under two topics (the
 *            category and the tags), credited to a published team member
 *            (the byline and the author box), dated between an older and a
 *            newer post (the previous and next links), on a site whose posts
 *            have an index (the breadcrumb). And an office with a phone
 *            number and an email address, on a page whose one section lists
 *            the offices. Prints the addresses as JSON, name => address:
 *            the post, a search that finds it, a page that does not exist
 *            and the offices page. Permalinks stay plain, the harness's own.
 *   restore  everything it made deleted.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';
require __DIR__ . '/seed.php';

$salt_underlines_mode   = (string) ( $argv[1] ?? '' );
$salt_underlines_mark   = 'Link underlines check';
$salt_underlines_word   = 'zebrawort';
$salt_underlines_author = 'Link Underlines Author';
$salt_underlines_topics = array(
	'link-underlines-topic' => $salt_underlines_mark . ' topic',
	'link-underlines-tag'   => $salt_underlines_mark . ' tag',
);

if ( 'seed' === $salt_underlines_mode ) {
	$salt_underlines_terms = array();
	foreach ( $salt_underlines_topics as $salt_underlines_slug => $salt_underlines_title ) {
		$salt_underlines_terms[] = salt_harness_topic(
			array(
				'title' => $salt_underlines_title,
				'href'  => '/category/' . $salt_underlines_slug . '/',
			)
		);
	}
	$salt_underlines_member = salt_harness_team_member(
		array(
			'name' => $salt_underlines_author,
			'href' => '/team/link-underlines-author/',
			'role' => 'Head gardener',
			'bio'  => 'Writes the posts this check reads.',
		),
		array()
	);

	// Dated together in 2001, away from every other post the harness makes, so
	// the middle one's neighbours are these two.
	$salt_underlines_posts = array();
	foreach ( array(
		'older' => '2001-03-01',
		'post'  => '2001-03-02',
		'newer' => '2001-03-03',
	) as $salt_underlines_name => $salt_underlines_date ) {
		$salt_underlines_posts[ $salt_underlines_name ] = (int) wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => $salt_underlines_mark . ' ' . $salt_underlines_name . ( 'post' === $salt_underlines_name ? ' ' . $salt_underlines_word : '' ),
				'post_date'     => $salt_underlines_date . ' 09:00:00',
				'post_category' => $salt_underlines_terms,
				'post_author'   => $salt_underlines_member,
			)
		);
	}
	update_field( SALT_POST_PRIMARY_TOPIC_FIELD, $salt_underlines_terms[0], $salt_underlines_posts['post'] );

	$salt_underlines_office = (int) wp_insert_post(
		array(
			'post_type'   => 'location',
			'post_status' => 'publish',
			'post_title'  => $salt_underlines_mark . ' office',
		)
	);
	update_field( 'field_salt_collection_locations_phone', '01632 960123', $salt_underlines_office );
	update_field( 'field_salt_collection_locations_email', 'office@link-underlines.example.test', $salt_underlines_office );
	$salt_underlines_page = (int) wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $salt_underlines_mark . ' offices',
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
		$salt_underlines_page
	);

	echo wp_json_encode(
		array(
			'post'      => get_permalink( $salt_underlines_posts['post'] ),
			'search'    => add_query_arg( 's', $salt_underlines_word, home_url( '/' ) ),
			'not-found' => add_query_arg( 'page_id', 2147483000, home_url( '/' ) ),
			'offices'   => get_permalink( $salt_underlines_page ),
		),
		JSON_UNESCAPED_SLASHES
	);
	exit( 0 );
}

if ( 'restore' === $salt_underlines_mode ) {
	foreach ( get_posts(
		array(
			'post_type'   => array( 'post', 'page', 'location' ),
			'post_status' => 'any',
			'numberposts' => -1,
			's'           => $salt_underlines_mark,
		)
	) as $salt_underlines_doc ) {
		wp_delete_post( $salt_underlines_doc->ID, true );
	}
	foreach ( array_keys( $salt_underlines_topics ) as $salt_underlines_slug ) {
		$salt_underlines_term = get_term_by( 'slug', $salt_underlines_slug, 'category' );
		if ( $salt_underlines_term ) {
			wp_delete_term( (int) $salt_underlines_term->term_id, 'category' );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( get_users(
		array(
			'search'         => $salt_underlines_author,
			'search_columns' => array( 'display_name' ),
			'fields'         => 'ID',
		)
	) as $salt_underlines_user ) {
		wp_delete_user( (int) $salt_underlines_user );
	}
	exit( 0 );
}

fwrite( STDERR, "usage: link-underlines-pages.php seed|restore\n" );
exit( 2 );
