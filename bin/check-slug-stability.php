<?php
/**
 * FLEET07 gate: fail a non-major release that drops a layout slug, field
 * name or field storage path registered in the previous tagged release,
 * unless an entry in docs/contracts/slug-migrations.json legitimises the
 * removal. A changed path (a field moved into or out of a group, or a group
 * or repeater renamed) is a removal of the old path: saved values stay
 * under the old meta key and the field reads empty.
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

// Storage paths: a field that moves into or out of a group, or whose group or
// repeater is renamed, keeps its name but not its meta key. A layout that is
// gone is already reported above, so its scope is skipped here. The meta key
// does not include the field group key, so groups without layouts are pooled.
$salt_path_ids   = static function ( array $registry ): array {
	$ids = array();
	foreach ( (array) ( $registry['paths'] ?? array() ) as $scope => $paths ) {
		$layout = str_contains( (string) $scope, ' / ' ) ? (string) $scope : '';
		foreach ( (array) $paths as $path ) {
			$ids[ $layout ][ (string) $path ] = (string) $scope;
		}
	}
	return $ids;
};
$salt_prev_paths = $salt_path_ids( $salt_prev );
$salt_curr_paths = $salt_path_ids( $salt_curr );
$salt_moved_to   = array();
foreach ( $salt_prev_paths as $salt_layout => $salt_paths ) {
	if ( ! isset( $salt_curr_paths[ $salt_layout ] ) ) {
		continue;
	}
	$salt_added = array_diff_key( $salt_curr_paths[ $salt_layout ], $salt_paths );
	foreach ( $salt_paths as $salt_path => $salt_scope ) {
		if ( isset( $salt_curr_paths[ $salt_layout ][ $salt_path ] ) ) {
			continue;
		}
		$salt_slug      = 'path:' . $salt_scope . ':' . $salt_path;
		$salt_missing[] = $salt_slug;
		// Name the likely new path: an added path that ends in the same field name.
		$salt_leaf                   = (string) substr( (string) strrchr( '_' . $salt_path, '_' ), 1 );
		$salt_moved_to[ $salt_slug ] = array_values(
			array_filter(
				array_keys( $salt_added ),
				static fn( string $p ): bool => $p === $salt_leaf || str_ends_with( $p, '_' . $salt_leaf )
			)
		);
	}
}

$salt_violations = array_values( array_diff( $salt_missing, $salt_migrated ) );
$salt_describe   = static function ( string $slug ) use ( $salt_moved_to ): string {
	if ( ! isset( $salt_moved_to[ $slug ] ) ) {
		return $slug;
	}
	return $slug . ' -> ' . ( array() === $salt_moved_to[ $slug ] ? '(no new path)' : implode( ' | ', $salt_moved_to[ $slug ] ) );
};

if ( array() === $salt_violations ) {
	fwrite( STDOUT, "Slug stability OK against {$salt_prev_tag}.\n" );
	exit( 0 );
}

if ( $salt_is_major ) {
	fwrite( STDOUT, 'Slugs or storage paths removed on a MAJOR bump (allowed, but document in the changelog): ' . implode( ', ', array_map( $salt_describe, $salt_violations ) ) . "\n" );
	exit( 0 );
}

fwrite( STDERR, "FLEET07 VIOLATION — slugs or storage paths registered in {$salt_prev_tag} are gone without a migration entry, and this is not a major bump:\n" );
foreach ( $salt_violations as $salt_slug ) {
	fwrite( STDERR, '  - ' . $salt_describe( $salt_slug ) . "\n" );
}
fwrite( STDERR, "Either bump the major version, or add entries to docs/contracts/slug-migrations.json.\n" );
exit( 1 );
