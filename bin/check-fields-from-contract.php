<?php
/**
 * Drift check: the page sections' fields are the pinned salt-contract's.
 *
 * Usage:
 *   php bin/check-fields-from-contract.php            Check (exit 1 on drift).
 *   php bin/check-fields-from-contract.php --write    Regenerate the JSON from the pin.
 *   php bin/check-fields-from-contract.php --contract <dir>
 *                                                     Check against another copy of
 *                                                     the package (proves the check).
 *
 * Two halves, both must hold:
 *  1. inc/fields/generated/sections.json is byte-for-byte the pinned
 *     emitter's output for inc/fields/generated/sections.options.json: node
 *     runs the package's own emit/acf.mjs --check (checkAcfSnapshot), so the
 *     comparison is the contract's, never re-implemented here.
 *  2. The layouts the theme registers (salt_section_layouts(), under the
 *     test shims) begin with the JSON's layouts exactly, and no hand-written
 *     inc/fields/layouts/*.php file names a generated section.
 *
 * Needs `npm ci` (the pinned package in node_modules) and node on PATH.
 *
 * @package Salt\Tooling
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$salt_root     = dirname( __DIR__ );
$salt_json     = $salt_root . '/inc/fields/generated/sections.json';
$salt_options  = $salt_root . '/inc/fields/generated/sections.options.json';
$salt_contract = $salt_root . '/node_modules/@lightlysaltedhq/salt-contract';
$salt_write    = false;

$salt_args = array_slice( $argv, 1 );
$salt_argc = count( $salt_args );
for ( $salt_i = 0; $salt_i < $salt_argc; $salt_i++ ) {
	if ( '--write' === $salt_args[ $salt_i ] ) {
		$salt_write = true;
	} elseif ( '--contract' === $salt_args[ $salt_i ] && isset( $salt_args[ $salt_i + 1 ] ) ) {
		$salt_contract = (string) $salt_args[ ++$salt_i ];
	} else {
		fwrite( STDERR, "usage: php bin/check-fields-from-contract.php [--write] [--contract <dir>]\n" );
		exit( 2 );
	}
}

$salt_emitter = $salt_contract . '/emit/acf.mjs';
if ( ! is_readable( $salt_emitter ) ) {
	fwrite( STDERR, "No salt-contract emitter at {$salt_emitter}: run npm ci first.\n" );
	exit( 2 );
}

$salt_version = json_decode( (string) file_get_contents( $salt_contract . '/package.json' ), true )['version'] ?? '?';

// 1. The committed JSON is the pinned emitter's output.
$salt_cmd  = implode(
	' ',
	array_map(
		'escapeshellarg',
		array( 'node', $salt_emitter, $salt_write ? '--write' : '--check', $salt_json, '--options', $salt_options )
	)
) . ' 2>&1';
$salt_out  = array();
$salt_code = 1;
exec( $salt_cmd, $salt_out, $salt_code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- CLI tooling, escaped args.
fwrite( 0 === $salt_code ? STDOUT : STDERR, implode( "\n", $salt_out ) . "\n" );
if ( 0 !== $salt_code ) {
	fwrite( STDERR, "DRIFT: inc/fields/generated/sections.json is not salt-contract {$salt_version}'s output. Run `php bin/check-fields-from-contract.php --write`, review the diff and update the resolvers.\n" );
	exit( 1 );
}
if ( $salt_write ) {
	fwrite( STDOUT, "Written from salt-contract {$salt_version}. Review the diff, then run the check.\n" );
	exit( 0 );
}

// 2. The theme registers exactly those layouts, and no hand-written copy.
require $salt_root . '/tests/wp-shims.php';

if ( ! function_exists( 'salt_log' ) ) {
	/**
	 * Collect log lines for the report.
	 *
	 * @param string $message Log line.
	 * @return void
	 */
	function salt_log( string $message ): void {
		$GLOBALS['salt_check_logs'][] = $message;
	}
}
$GLOBALS['salt_check_logs'] = array();

require_once $salt_root . '/inc/helpers.php';
require_once $salt_root . '/inc/acf.php';
require_once $salt_root . '/inc/query/resolver.php';
require_once $salt_root . '/inc/query/controls.php';

$salt_generated = json_decode( (string) file_get_contents( $salt_json ), true )[0]['fields'][0]['layouts'] ?? array();
$salt_names     = array_column( $salt_generated, 'name' );
$salt_problems  = array();

$salt_registered = salt_section_layouts();
if ( array_slice( $salt_registered, 0, count( $salt_generated ) ) !== $salt_generated ) {
	$salt_problems[] = 'the registered layouts do not begin with the generated JSON verbatim (salt_section_layouts())';
}
foreach ( array_slice( $salt_registered, count( $salt_generated ) ) as $salt_layout ) {
	if ( in_array( $salt_layout['name'], $salt_names, true ) ) {
		$salt_problems[] = sprintf( 'layout %s is registered by hand as well as generated', $salt_layout['name'] );
	}
}
$salt_files = glob( $salt_root . '/inc/fields/layouts/*.php' );
foreach ( false === $salt_files ? array() : $salt_files as $salt_file ) {
	$salt_layout = include $salt_file;
	if ( is_array( $salt_layout ) && in_array( $salt_layout['name'] ?? null, $salt_names, true ) ) {
		$salt_problems[] = sprintf( 'inc/fields/layouts/%s writes the generated section %s by hand: delete it', basename( $salt_file ), $salt_layout['name'] );
	}
}
foreach ( $GLOBALS['salt_check_logs'] as $salt_line ) {
	$salt_problems[] = 'salt_log: ' . $salt_line;
}

if ( array() !== $salt_problems ) {
	fwrite( STDERR, "DRIFT: the theme's registered layouts are not the generated ones:\n" );
	foreach ( $salt_problems as $salt_problem ) {
		fwrite( STDERR, "  - {$salt_problem}\n" );
	}
	exit( 1 );
}

fwrite( STDOUT, sprintf( "Fields from contract OK: %d generated layouts from salt-contract %s, registered verbatim; %d hand-written layouts beside them.\n", count( $salt_generated ), $salt_version, count( $salt_registered ) - count( $salt_generated ) ) );
exit( 0 );
