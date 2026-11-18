<?php
/**
 * Reads the conformance report a harness-backed run wrote and checks the M3
 * bar: every case rendered, so each one that fails, fails on its markup.
 * HARNESS ONLY: run by run.sh --conformance after salt-conformance.
 *
 * Fails when any case's failure is the adapter's (an exit other than 0:
 * 4, not rendered; 70, a PHP diagnostic; 1, the site answered the wrong
 * status) rather than a markup mismatch. Lists every view and page case
 * with its first difference.
 *
 * Usage: php conformance.php <conformance.json>
 *
 * @package Salt\Tests\RealAcf
 */

if ( PHP_SAPI !== 'cli' || $argc < 2 ) {
	exit( 2 );
}

$salt_report = json_decode( (string) file_get_contents( $argv[1] ), true );
if ( ! is_array( $salt_report ) ) {
	echo 'FAIL the conformance report ' . $argv[1] . " could not be read\n";
	exit( 1 );
}

$salt_adapter = array();
$salt_cases   = 0;
foreach ( array_merge( (array) $salt_report['sections'], (array) ( $salt_report['files'] ?? array() ) ) as $salt_entry ) {
	$salt_cases += (int) ( $salt_entry['fixtures']['total'] ?? 0 );
	foreach ( (array) ( $salt_entry['fixtures']['failures'] ?? array() ) as $salt_failure ) {
		if ( 'mismatch' !== $salt_failure['kind'] ) {
			$salt_adapter[] = sprintf( '%s/%s: %s: %s', $salt_entry['id'], $salt_failure['case'], $salt_failure['error'] ?? $salt_failure['kind'], trim( (string) ( $salt_failure['stderr'] ?? '' ) ) );
		}
	}
}

echo 'PASS the report is against ' . $salt_report['contract']['package'] . ' ' . $salt_report['contract']['version'] . "\n";
foreach ( (array) ( $salt_report['files'] ?? array() ) as $salt_entry ) {
	if ( 'view' !== ( $salt_entry['kind'] ?? '' ) && 'page' !== ( $salt_entry['kind'] ?? '' ) ) {
		continue;
	}
	foreach ( (array) ( $salt_entry['fixtures']['failures'] ?? array() ) as $salt_failure ) {
		echo '     ' . $salt_entry['id'] . '/' . $salt_failure['case'] . ': '
			. ( 'mismatch' === $salt_failure['kind'] ? $salt_failure['difference'] . ' at ' . $salt_failure['path'] : 'NOT RENDERED (' . ( $salt_failure['error'] ?? '' ) . ')' ) . "\n";
	}
}
if ( array() !== $salt_adapter ) {
	echo 'FAIL ' . count( $salt_adapter ) . ' of ' . $salt_cases . " cases did not render:\n";
	foreach ( $salt_adapter as $salt_line ) {
		echo '       ' . $salt_line . "\n";
	}
	exit( 1 );
}
echo 'PASS every case rendered (' . $salt_cases . " cases): each one that fails differs in its markup only\n";
exit( 0 );
