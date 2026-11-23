<?php
/**
 * Phase: the theme renders the contract's field shape, read from real ACF
 * Pro. HARNESS ONLY.
 *
 * Renders the fixtures page the contract phase saved (SALT_HARNESS_STATE)
 * through the theme's own page loop (template-parts/flexible-content.php),
 * so every resolver reads its row with real get_sub_field() values: the
 * settings group, link groups, button lists, galleries and collection
 * queries. Checks every generated section that has something to draw drew
 * a band, that internal and external links resolved, the settings group
 * reached the wrapper, and the page has one H1. The theme's salt_log lines
 * (rows the resolvers skip, as fixtures' empty cases ask) are collected and
 * listed, not failed: this phase defines salt_log() before the theme loads.
 *
 * @package Salt\Tests\RealAcf
 */

/**
 * Collect the theme's log lines (the theme's salt_log() is pluggable).
 *
 * @param string $message Log line.
 * @return void
 */
function salt_log( string $message ): void {
	$GLOBALS['salt_harness_logs'][] = $message;
}
$GLOBALS['salt_harness_logs'] = array();

require __DIR__ . '/wp.php';

$state = json_decode( (string) file_get_contents( (string) getenv( 'SALT_HARNESS_STATE' ) ), true );
$page  = (int) $state['fixturesPage'];

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- A CLI render of one page needs the loop's post.
$GLOBALS['post'] = get_post( $page );
setup_postdata( $GLOBALS['post'] );

ob_start();
get_template_part( 'template-parts/flexible-content' );
$html = (string) ob_get_clean();

// x-section bands name their section in data-section; a band drawn to the
// contract's markup (EP-72 M3 unit 8 and M4) in its block's data-block (the
// hero's block carries salt-hero too).
preg_match_all( '/<section\b[^>]*\bdata-section="([a-z0-9-]+)"|<div class="salt-block(?: [a-z0-9-]+)*" data-block="([a-z0-9-]+)"/', $html, $drawn );
$drawn[1] = array_map( static fn( string $a, string $b ): string => '' !== $a ? $a : $b, $drawn[1], $drawn[2] );
$counts   = array_count_values( $drawn[1] );
salt_harness_info( sprintf( '%d section bands drawn from %d rows: %s', count( $drawn[1] ), count( $state['rows'] ), wp_json_encode( $counts ) ) );

// faq, locations and collection-showcase query posts the harness does not
// create (fixtures carry their collections only as data), so they correctly
// draw nothing (COMPL02); carousel draws its inline cases.
$need    = array_diff( $state['layouts'], array( 'faq', 'locations', 'collection-showcase' ) );
$missing = array_values( array_diff( $need, array_keys( $counts ) ) );
salt_harness_result(
	array() === $missing,
	'every generated section with content draws from real ACF values',
	array() === $missing ? sprintf( '%d of %d layouts drew (faq, locations and collection-showcase have no posts to query here)', count( $need ), count( $state['layouts'] ) ) : 'no band for: ' . implode( ', ', $missing )
);

$contact = (string) get_permalink( (int) get_page_by_path( 'contact' )?->ID );
salt_harness_result(
	str_contains( $html, 'href="https://guides.example/planting"' ) && str_contains( $html, 'target="_blank"' )
		&& '' !== $contact && str_contains( $html, 'href="' . esc_url( $contact ) . '"' ),
	'button links resolve (external with newTab, internal to its document)',
	sprintf( 'external https://guides.example/planting in a new tab; internal document contact => %s', '' !== $contact ? $contact : 'no contact page' )
);

salt_harness_result(
	1 === preg_match( '/<section\b[^>]*\bid="guide"/', $html ),
	'the settings group reaches the wrapper',
	'settings.anchorId "guide" (hero/split-no-image-new-tab) is the band\'s id'
);

salt_harness_result(
	1 === preg_match_all( '/<h1[\s>]/', $html ),
	'one H1 on the page',
	sprintf( '%d h1 elements', preg_match_all( '/<h1[\s>]/', $html ) )
);

// The contract phase seeded this fixture through ACF alongside the other sections.
$row = array_search( 'rich-text/h4-first-under-h2', $state['rows'], true );
salt_harness_result(
	false !== $row && '<h4>First</h4><h3>Second</h3>' === get_post_meta( (int) $state['fixturesPage'], "sections_{$row}_body", true )
		&& 1 === preg_match( '~<h2\b[^>]*>Planting notes</h2>\s*<div\b[^>]*>\s*<h3>First</h3>\s*<h3>Second</h3>~', $html ),
	'rich-text/h4-first-under-h2: stored H4/H3 draw H3/H3 under H2'
);

salt_harness_info( sprintf( '%d salt_log lines (resolvers skipping rows the fixtures leave empty or broken); first 10:', count( $GLOBALS['salt_harness_logs'] ) ) );
foreach ( array_slice( $GLOBALS['salt_harness_logs'], 0, 10 ) as $line ) {
	salt_harness_info( '  ' . $line );
}

wp_reset_postdata();
salt_harness_exit();
