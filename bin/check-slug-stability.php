<?php
/**
 * FLEET07 gate: fail a non-major release that drops a layout slug or field
 * name registered in the previous tagged release, unless an entry in
 * docs/contracts/slug-migrations.json legitimises the removal.
 *
 * Usage: php bin/check-slug-stability.php
 * Requires full git history (fetch-depth: 0 in CI).
 *
 * @package Salt\Tooling
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$salt_root = dirname( __DIR__ );

/**
 * Run a git command in the repo root and return trimmed stdout (null on failure).
 *
 * @param string[] $args Git arguments.
 * @return string|null
 */
function salt_git( array $args ): ?string {
	$cmd  = 'git -C ' . escapeshellarg( dirname( __DIR__ ) ) . ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) ) . ' 2>/dev/null';
	$out  = array();
	$code = 1;
	exec( $cmd, $out, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- CLI tooling, static args.
	return 0 === $code ? trim( implode( "\n", $out ) ) : null;
}

/**
 * Parse "x.y.z" (with optional leading v) into [major, minor, patch].
 *
 * @param string $version Version string.
 * @return int[]|null
 */
function salt_parse_semver( string $version ): ?array {
	if ( preg_match( '/^v?(\d+)\.(\d+)\.(\d+)/', trim( $version ), $m ) ) {
		return array( (int) $m[1], (int) $m[2], (int) $m[3] );
	}
	return null;
}

// 1. Current theme version (style.css header).
$salt_style   = (string) file_get_contents( $salt_root . '/style.css' );
$salt_current = null;
if ( preg_match( '/^Version:\s*(.+)$/mi', $salt_style, $m ) ) {
	$salt_current = salt_parse_semver( $m[1] );
}
if ( null === $salt_current ) {
	fwrite( STDERR, "Cannot read a semver Version from style.css.\n" );
	exit( 1 );
}

// 2. Previous release tag (exclude a tag pointing at HEAD itself, so a tag
// build compares against the release BEFORE the one being cut).
$salt_head_tag = salt_git( array( 'describe', '--tags', '--exact-match', 'HEAD' ) );
$salt_ref      = null !== $salt_head_tag && '' !== $salt_head_tag ? 'HEAD^' : 'HEAD';
$salt_prev_tag = salt_git( array( 'describe', '--tags', '--abbrev=0', '--match', 'v*', $salt_ref ) );

if ( null === $salt_prev_tag || '' === $salt_prev_tag ) {
	fwrite( STDOUT, "No previous release tag — slug-stability check passes vacuously.\n" );
	exit( 0 );
}

$salt_prev_version = salt_parse_semver( $salt_prev_tag );
$salt_is_major     = null !== $salt_prev_version && $salt_current[0] > $salt_prev_version[0];

// 3. Previous registry at that tag.
$salt_prev_json = salt_git( array( 'show', $salt_prev_tag . ':docs/contracts/slug-registry.json' ) );
if ( null === $salt_prev_json || '' === $salt_prev_json ) {
	fwrite( STDOUT, "No registry at {$salt_prev_tag} — check passes vacuously.\n" );
	exit( 0 );
}
$salt_prev = json_decode( $salt_prev_json, true );
$salt_curr = json_decode( (string) file_get_contents( $salt_root . '/docs/contracts/slug-registry.json' ), true );
if ( ! is_array( $salt_prev ) || ! is_array( $salt_curr ) ) {
	fwrite( STDERR, "Malformed slug registry JSON.\n" );
	exit( 1 );
}

// 4. Migration entries.
$salt_migrations_raw = json_decode( (string) file_get_contents( $salt_root . '/docs/contracts/slug-migrations.json' ), true );
$salt_migrated       = array();
foreach ( ( $salt_migrations_raw['migrations'] ?? array() ) as $salt_entry ) {
	if ( isset( $salt_entry['slug'] ) ) {
		$salt_migrated[] = (string) $salt_entry['slug'];
	}
}

// 5. Diff: anything registered previously that is gone now.
$salt_missing = array();

foreach ( ( $salt_prev['layouts'] ?? array() ) as $salt_layout ) {
	if ( ! in_array( $salt_layout, $salt_curr['layouts'] ?? array(), true ) ) {
		$salt_missing[] = 'layout:' . $salt_layout;
	}
}

$salt_prev_fields = array();
foreach ( ( $salt_prev['fields'] ?? array() ) as $salt_group_fields ) {
	foreach ( (array) $salt_group_fields as $salt_field ) {
		$salt_prev_fields[ (string) $salt_field ] = true;
	}
}
$salt_curr_fields = array();
foreach ( ( $salt_curr['fields'] ?? array() ) as $salt_group_fields ) {
	foreach ( (array) $salt_group_fields as $salt_field ) {
		$salt_curr_fields[ (string) $salt_field ] = true;
	}
}
foreach ( array_keys( $salt_prev_fields ) as $salt_field ) {
	if ( ! isset( $salt_curr_fields[ $salt_field ] ) ) {
		$salt_missing[] = 'field:' . $salt_field;
	}
}

$salt_violations = array_values( array_diff( $salt_missing, $salt_migrated ) );

if ( array() === $salt_violations ) {
	fwrite( STDOUT, "Slug stability OK against {$salt_prev_tag}.\n" );
	exit( 0 );
}

if ( $salt_is_major ) {
	fwrite( STDOUT, 'Slugs removed on a MAJOR bump (allowed, but document in the changelog): ' . implode( ', ', $salt_violations ) . "\n" );
	exit( 0 );
}

fwrite( STDERR, "FLEET07 VIOLATION — slugs registered in {$salt_prev_tag} are gone without a migration entry, and this is not a major bump:\n" );
foreach ( $salt_violations as $salt_slug ) {
	fwrite( STDERR, "  - {$salt_slug}\n" );
}
fwrite( STDERR, "Either bump the major version, or add entries to docs/contracts/slug-migrations.json.\n" );
exit( 1 );
