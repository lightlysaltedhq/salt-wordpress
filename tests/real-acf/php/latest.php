<?php
/**
 * Is the harness's pinned WordPress the latest release? HARNESS ONLY.
 *
 * The harness is locked to one WordPress (composer.lock), so runs are
 * reproducible, but clients run the latest release and the theme must raise
 * no deprecation on it. This asks api.wordpress.org which release is latest
 * and fails, naming both, when the pinned one is older. run.sh skips it only
 * when asked to (--offline), and says so loudly.
 *
 * usage: php latest.php <pinned version>
 *
 * @package Salt\Tests\RealAcf
 */

if ( PHP_SAPI !== 'cli' || $argc < 2 ) {
	exit( 2 );
}

$salt_harness_pinned = (string) $argv[1];
$salt_harness_api    = 'https://api.wordpress.org/core/version-check/1.7/';
$salt_harness_body   = @file_get_contents( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- WordPress is not loaded; a failure is reported below.
	$salt_harness_api,
	false,
	stream_context_create( array( 'http' => array( 'timeout' => 20 ) ) )
);
$salt_harness_latest = '';
if ( is_string( $salt_harness_body ) ) {
	$salt_harness_offers = json_decode( $salt_harness_body, true )['offers'] ?? array();
	foreach ( is_array( $salt_harness_offers ) ? $salt_harness_offers : array() as $salt_harness_offer ) {
		$salt_harness_current = (string) ( $salt_harness_offer['current'] ?? '' );
		if ( '' !== $salt_harness_current && version_compare( $salt_harness_current, $salt_harness_latest, '>' ) ) {
			$salt_harness_latest = $salt_harness_current;
		}
	}
}

if ( '' === $salt_harness_latest ) {
	echo "FAIL WordPress is the latest release: could not read the latest release from {$salt_harness_api} (run with --offline to skip this check, reported as not checked)\n";
	exit( 1 );
}
if ( version_compare( $salt_harness_pinned, $salt_harness_latest, '<' ) ) {
	echo "FAIL WordPress is the latest release: the harness pins WordPress {$salt_harness_pinned}, but the latest release is {$salt_harness_latest}. Pin {$salt_harness_latest} in tests/real-acf/composer.json, run composer update there, and rerun.\n";
	exit( 1 );
}
echo "PASS WordPress is the latest release: pinned {$salt_harness_pinned}, latest {$salt_harness_latest} ({$salt_harness_api})\n";
exit( 0 );
