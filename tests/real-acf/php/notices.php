<?php
/**
 * Reports what one harness phase added to the site's debug.log (PHP notices,
 * warnings, deprecations, errors and the theme's own salt_log lines), grouped
 * by where they came from. Any entry fails the phase. HARNESS ONLY.
 *
 * Usage: php notices.php <debug.log> <byte offset> <phase name>
 *
 * @package Salt\Tests\RealAcf
 */

if ( PHP_SAPI !== 'cli' || $argc < 4 ) {
	exit( 2 );
}

$salt_harness_log = (string) file_get_contents( $argv[1], false, null, (int) $argv[2] );
$salt_harness_by  = array();
$salt_harness_n   = 0;

foreach ( preg_split( '/\R/', $salt_harness_log ) as $salt_harness_line ) {
	if ( ! str_starts_with( $salt_harness_line, '[' ) ) {
		continue;
	}
	++$salt_harness_n;
	$salt_harness_text = preg_replace( '/^\[[^\]]+\]\s*/', '', $salt_harness_line );
	if ( str_contains( $salt_harness_text, '/wp-content/themes/salt/' ) || str_starts_with( $salt_harness_text, 'salt' ) ) {
		$salt_harness_from = 'theme';
	} elseif ( str_contains( $salt_harness_text, '/wp-content/plugins/advanced-custom-fields-pro/' ) ) {
		$salt_harness_from = 'ACF Pro';
	} elseif ( str_contains( $salt_harness_text, '/wp-content/mu-plugins/' ) || str_contains( $salt_harness_text, '/tests/real-acf/' ) ) {
		$salt_harness_from = 'harness';
	} else {
		$salt_harness_from = 'WordPress core / PHP';
	}
	$salt_harness_by[ $salt_harness_from ][ $salt_harness_text ] = ( $salt_harness_by[ $salt_harness_from ][ $salt_harness_text ] ?? 0 ) + 1;
}

if ( 0 === $salt_harness_n ) {
	echo 'PASS no PHP notices, warnings, deprecations or salt_log lines: ' . $argv[3] . "\n";
	exit( 0 );
}

echo 'FAIL debug.log gained ' . $salt_harness_n . ' entries: ' . $argv[3] . "\n";
foreach ( $salt_harness_by as $salt_harness_from => $salt_harness_entries ) {
	echo '     from ' . $salt_harness_from . ' (' . array_sum( $salt_harness_entries ) . "):\n";
	foreach ( $salt_harness_entries as $salt_harness_text => $salt_harness_count ) {
		echo '       ' . $salt_harness_count . ' x ' . $salt_harness_text . "\n";
	}
}
exit( 1 );
