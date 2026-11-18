<?php
/**
 * Loads the harness's throwaway WordPress for a CLI check, and the shared
 * report helpers. HARNESS ONLY: run by tests/real-acf/run.sh, never shipped.
 *
 * The site is the one run.sh built: SALT_HARNESS_WP names its directory.
 *
 * @package Salt\Tests\RealAcf
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$salt_harness_wp = (string) getenv( 'SALT_HARNESS_WP' );
if ( '' === $salt_harness_wp || ! is_file( $salt_harness_wp . '/wp-load.php' ) ) {
	fwrite( STDERR, "SALT_HARNESS_WP does not name a WordPress directory\n" );
	exit( 2 );
}

// WordPress reads the request's host to build URLs; a CLI run has none.
$salt_harness_http_host = salt_harness_host( (string) getenv( 'SALT_HARNESS_URL' ) );
$_SERVER['HTTP_HOST']   = $salt_harness_http_host;
$_SERVER['SERVER_NAME'] = $salt_harness_http_host;
$_SERVER['REQUEST_URI'] = '/';

/**
 * The host:port of a URL, before WordPress (and wp_parse_url) is loaded.
 *
 * @param string $url URL.
 * @return string
 */
function salt_harness_host( string $url ): string {
	$host = (string) parse_url( $url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress is not loaded yet.
	$port = parse_url( $url, PHP_URL_PORT ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress is not loaded yet.
	return null === $port ? $host : $host . ':' . $port;
}

if ( ! defined( 'SALT_HARNESS_NO_LOAD' ) ) {
	require $salt_harness_wp . '/wp-load.php';
}

$GLOBALS['salt_harness_failures'] = 0;

/**
 * Print one check's result line and count failures.
 *
 * @param bool   $ok     Whether the check passed.
 * @param string $check  What was checked.
 * @param string $detail The numbers or evidence.
 * @return void
 */
function salt_harness_result( bool $ok, string $check, string $detail = '' ): void {
	if ( ! $ok ) {
		++$GLOBALS['salt_harness_failures'];
	}
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $check . ( '' !== $detail ? ': ' . $detail : '' ) . "\n";
}

/**
 * Print an informational line (evidence, not a verdict).
 *
 * @param string $line Text.
 * @return void
 */
function salt_harness_info( string $line ): void {
	echo '     ' . $line . "\n";
}

/**
 * Exit 1 when any check failed, 0 otherwise.
 *
 * @return never
 */
function salt_harness_exit(): never {
	exit( $GLOBALS['salt_harness_failures'] > 0 ? 1 : 0 );
}

/**
 * Every field definition in a tree of fields, depth first, with each one's
 * path of names from the layout.
 *
 * @param array<int,array<string,mixed>> $fields Field definitions.
 * @param string                         $path   Path so far.
 * @return array<int,array{0:string,1:array<string,mixed>}>
 */
function salt_harness_walk_fields( array $fields, string $path = '' ): array {
	$out = array();
	foreach ( $fields as $field ) {
		$here  = ( '' === $path ? '' : $path . '.' ) . ( $field['name'] ?? $field['key'] );
		$out[] = array( $here, $field );
		if ( ! empty( $field['sub_fields'] ) ) {
			array_push( $out, ...salt_harness_walk_fields( $field['sub_fields'], $here ) );
		}
		foreach ( $field['layouts'] ?? array() as $layout ) {
			array_push( $out, ...salt_harness_walk_fields( $layout['sub_fields'] ?? array(), $here . '[' . $layout['name'] . ']' ) );
		}
	}
	return $out;
}
