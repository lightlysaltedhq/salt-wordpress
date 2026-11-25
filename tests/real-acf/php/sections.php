<?php
/**
 * Render tabs and listing from real ACF rows for the Chromium checks.
 * Listing uses a real paged WordPress query, not pre-resolved card props.
 * The HTML files live only in the disposable site. HARNESS ONLY.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';
require __DIR__ . '/seed.php';

$state         = json_decode( (string) file_get_contents( (string) getenv( 'SALT_HARNESS_STATE' ) ), true );
$fixtures_page = (int) $state['fixturesPage'];
$dir           = (string) getenv( 'SALT_HARNESS_WP' ) . '/section-cases';
wp_mkdir_p( $dir );

// Three fixture cards per page, up to the nine-page pagination case.
$fixture = salt_harness_fixture( 'listing/cards-pagination-gaps' );
$posts   = array();
for ( $i = 0; $i < 27; ++$i ) {
	$card    = $fixture['route']['cards'][ $i % 3 ];
	$id      = (int) wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => $card['title'],
			'post_excerpt' => $card['text'],
			'post_date'    => $card['date'] . ' 12:00:00',
		)
	);
	$posts[] = $id;
	set_post_thumbnail( $id, salt_harness_single_image( $card['image'], $fixture['media'] ) );
}

foreach ( $state['rows'] as $row => $name ) {
	if ( ! str_starts_with( $name, 'tabs/' ) && ! str_starts_with( $name, 'listing/' ) ) {
		continue;
	}
	$case    = salt_harness_fixture( $name );
	$listing = 'listing' === $case['section'];
	$route   = $case['route'] ?? null;
	if ( $listing && null !== $route ) {
		$GLOBALS['wp_query'] = new WP_Query(
			array(
				'post_type'      => 'post',
				'post__in'       => array() === $route['cards'] ? array( 0 ) : array_slice( $posts, 0, 3 * $route['pagination']['total'] ),
				'orderby'        => 'post__in',
				'posts_per_page' => 3,
				'paged'          => $route['pagination']['current'],
			)
		);
	} else {
		$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $fixtures_page ) );
	}
	$GLOBALS['post'] = get_post( $fixtures_page );
	setup_postdata( $GLOBALS['post'] );
	// Select the fixture's saved row through the real ACF iterator.
	while ( have_rows( 'sections', $fixtures_page ) ) {
		the_row();
		if ( get_row_index() === $row + 1 ) {
			break;
		}
	}
	salt_harness_result( get_row_index() === $row + 1 && get_row_layout() === $case['section'], $name . ': selected the real saved ACF row' );
	salt_render_state_reset();
	salt_schema_reset();
	$context = $case['context'];
	if ( ! empty( $context['headingRendered'] ) ) {
		salt_claim_h1( 'harness: earlier heading' );
	}
	salt_render_state( 'set', 'priority_media', empty( $context['priorityMedia'] ) );
	ob_start();
	salt_render_section(
		$case['section'],
		array(
			'is_primary' => 1 === $context['headingLevel'],
			'index'      => $context['index'],
		)
	);
	$html = (string) ob_get_clean();
	reset_rows();
	salt_harness_result( -1 === $GLOBALS['wp_query']->current_post, $name . ': rendering does not advance the route loop' );
	if ( $listing ) {
		salt_harness_result(
			null === $route ? '' === $html : ( array() === $route['cards'] ? str_contains( $html, 'salt-listing__empty' ) : 3 === substr_count( $html, 'class="salt-card"' ) ),
			$name . ': real route distinguishes absent, empty and populated listings'
		);
	}
	// Capture the real theme chrome once: WordPress prints enqueued styles
	// only once per request, and every saved document needs that same head.
	if ( ! isset( $head ) ) {
		ob_start();
		get_header();
		$head = (string) ob_get_clean();
		ob_start();
		get_footer();
		$foot = (string) ob_get_clean();
	}
	file_put_contents( $dir . '/' . str_replace( '/', '--', $name ) . '.html', $head . $html . $foot );
}
foreach ( $posts as $id ) {
	wp_delete_post( $id, true );
}
salt_harness_exit();
