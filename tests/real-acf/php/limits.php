<?php
/**
 * ID-3971: prove what a caller of update_field() receives, then independently
 * read the stored meta. Seed isolated drafts for the limits browser phase.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';
require __DIR__ . '/seed.php';

/**
 * The importer's side: catch only the documented refusal, never a PHP error.
 *
 * @param callable $write A public ACF write.
 * @return string Refusal, or empty on unexpected success.
 */
function salt_harness_refusal( callable $write ): string {
	try {
		$write();
	} catch ( DomainException $error ) {
		return $error->getMessage();
	}
	return '';
}

$ids = array();
foreach ( array( 'page', 'service', 'work', 'testimonial', 'location' ) as $type ) {
	$ids[ $type ] = wp_insert_post(
		array(
			'post_type'   => $type,
			'post_title'  => 'Limits: ' . $type,
			'post_status' => 'draft',
		)
	);
}
foreach ( array(
	'lat'    => array( -90, 90 ),
	'lng'    => array( -180, 180 ),
	'zoom'   => array( 1, 20 ),
	'rating' => array( 1, 5 ),
) as $name => list( $min, $max ) ) {
	$is_rating = 'rating' === $name;
	$id        = $ids[ $is_rating ? 'testimonial' : 'location' ];
	$key       = 'field_salt_collection_' . ( $is_rating ? 'testimonials_' : 'locations_' ) . $name;
	foreach ( array( $min, $max ) as $boundary ) {
		update_field( $key, $boundary, $id );
		salt_harness_result( get_post_meta( $id, $name, true ) === (string) $boundary, "$name accepts boundary $boundary" );
	}
	foreach ( array( $min - 1, $max + 1 ) as $bad ) {
		$error = salt_harness_refusal( static fn() => update_field( $key, $bad, $id ) );
		salt_harness_result( '' !== $error && get_post_meta( $id, $name, true ) === (string) $max, "$name refuses $bad with a message and preserves $max" );
	}
}
$benefits = array_map( static fn( $n ) => array( 'text' => "Garden design workshop $n" ), range( 1, 13 ) );
update_field( 'field_salt_collection_services_benefits', $benefits, $ids['service'] );
salt_harness_result( '13' === get_post_meta( $ids['service'], 'benefits', true ), '13 benefits save against a target of 12' );
$error = salt_harness_refusal( static fn() => update_field( 'field_salt_collection_services_benefits', array(), $ids['service'] ) );
salt_harness_result( '' !== $error && '13' === get_post_meta( $ids['service'], 'benefits', true ), 'zero benefits refused with a message; previous list intact' );
if ( ! has_nav_menu( 'primary' ) ) {
	salt_harness_seed_menus();
}
wp_update_post(
	array(
		'ID'          => $ids['service'],
		'post_status' => 'publish',
	)
);
$ids['serviceUrl'] = get_permalink( $ids['service'] );
$related           = array_map(
	static fn( $n ) => salt_harness_single_service(
		array(
			'id'    => 'limits-' . $n,
			'title' => 'Workshop ' . $n,
		),
		array()
	),
	range( 1, 7 )
);
update_field( 'field_salt_collection_services_related_services', $related, $ids['service'] );
$images = array_map( static fn( $n ) => salt_harness_single_image( 'garden-' . $n, array() ), range( 1, 25 ) );
update_field( 'field_salt_collection_case_studies_gallery', $images, $ids['work'] );
salt_harness_result( 25 === count( get_field( 'gallery', $ids['work'], false ) ) && 7 === count( get_field( 'relatedServices', $ids['service'], false ) ), 'gallery and relationship writes persist all picks over their targets' );

$section = array(
	'acf_fc_layout' => 'faq',
	'heading'       => 'Practical questions',
	'intro'         => str_repeat( '🌱', 301 ),
	'query'         => array( 'count' => 24 ),
);
update_field( 'field_salt_sections_container', array( $section ), $ids['page'] );
salt_harness_result( get_post_meta( $ids['page'], 'sections_0_intro', true ) === $section['intro'] && '24' === get_post_meta( $ids['page'], 'sections_0_query_count', true ), 'soft nested intro and count boundary 24 both persist' );
$section['heading']        = 'Must not be written';
$section['query']['count'] = 25;
$error                     = salt_harness_refusal( static fn() => update_field( 'field_salt_sections_container', array( $section ), $ids['page'] ) );
salt_harness_result( '' !== $error && 'Practical questions' === get_post_meta( $ids['page'], 'sections_0_heading', true ) && '24' === get_post_meta( $ids['page'], 'sections_0_query_count', true ), 'bad nested count refuses whole outside write before sibling mutation' );
$error = salt_harness_refusal(
	static fn() => add_row(
		'field_salt_sections_container',
		array(
			'acf_fc_layout' => 'hero',
			'buttons'       => array_fill( 0, 3, array( 'style' => 'primary' ) ),
		),
		$ids['page']
	)
);
salt_harness_result( str_contains( $error, '2' ), 'ACF add_row refuses third hero button with cap in message' );

wp_set_current_user( 1 );
$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
$request->set_param( 'title', str_repeat( 'A', 121 ) );
$request->set_param( 'excerpt', str_repeat( 'B', 301 ) );
$response = rest_do_request( $request );
$post     = get_post( $response->get_data()['id'] ?? 0 );
salt_harness_result( 201 === $response->get_status() && $post && $request['title'] === $post->post_title && $request['excerpt'] === $post->post_excerpt, 'core REST post write stores full over-target native fields' );

// Represent an already saved invalid draft before calling the native publish endpoint.
update_post_meta( $ids['page'], 'sections_0_query_count', '25' );
acf_get_store( 'values' )->reset(); // The REST request would normally be a new PHP process.
$error = salt_harness_refusal(
	static fn() => wp_update_post(
		array(
			'ID'          => $ids['page'],
			'post_status' => 'publish',
		)
	)
);
salt_harness_result( str_contains( $error, '24' ) && 'draft' === get_post_status( $ids['page'] ), 'native PHP post update refuses invalid draft promotion with a message' );
$request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $ids['page'] );
$request->set_param( 'status', 'publish' );
$response = rest_do_request( $request );
salt_harness_result( 400 === $response->get_status() && 'salt_field_limit' === $response->get_data()['code'] && 'draft' === get_post_status( $ids['page'] ), 'core REST refuses publication of an invalid stored draft with an error response', $response->get_status() . ' ' . ( $response->get_data()['message'] ?? '' ) );
update_post_meta( $ids['page'], 'sections_0_query_count', '24' );

$ids['mismatch'] = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'Local workshops',
	)
);
$mismatch        = array(
	'acf_fc_layout' => 'collection-showcase',
	'source'        => 'services',
	'query'         => array(
		'mode'       => 'by-category',
		'categories' => array( get_option( 'default_category' ) ),
	),
);
update_field( 'field_salt_sections_container', array( $mismatch ), $ids['mismatch'] );
$request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $ids['mismatch'] );
$request->set_param( 'status', 'publish' );
$response = rest_do_request( $request );
salt_harness_result( 400 === $response->get_status() && 'salt_topic_source' === $response->get_data()['code'], 'REST refuses stale cross-source picks on publication' );

// Fixtures for profile counts, not a change to profile registration or routing.
set_site_transient( 'available_translations', array(), HOUR_IN_SECONDS ); // The test site is offline.
$ids['user'] = wp_insert_user(
	array(
		'user_login' => 'limits-editor',
		'user_pass'  => wp_generate_password(),
		'first_name' => str_repeat( 'L', 65 ),
		'last_name'  => str_repeat( 'M', 65 ),
	)
);

$key                               = 'field_salt_collection_showcase_query_';
$input                             = 'acf[sections][row-0][field_salt_collection_showcase_query][' . $key . 'categories]';
$term                              = wp_insert_term( 'Building maintenance', 'service_category' );
$_POST['acf']['sections']['row-0'] = array(
	'field_salt_collection_showcase_source' => 'services',
	'field_salt_collection_showcase_query'  => array( $key . 'mode' => 'by-category' ),
);
foreach ( array(
	$term['term_id']                 => true,
	get_option( 'default_category' ) => false,
) as $id => $allowed ) {
	$result = apply_filters( 'acf/validate_value', true, array( $id ), acf_get_field( $key . 'categories' ), $input ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- ACF's own filter.
	salt_harness_result( $allowed ? true === $result : is_string( $result ), $allowed ? 'publish accepts matching source topic' : 'publish refuses post category for service source with message' );
}
file_put_contents( getenv( 'SALT_HARNESS_STATE' ) . '.limits', wp_json_encode( $ids ) );
salt_harness_exit();
