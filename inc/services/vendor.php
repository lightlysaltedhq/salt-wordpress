<?php
/**
 * Autoloader for vendored runtime libraries.
 *
 * The theme ships ONLY the pinned runtime libs under vendor/ (currently
 * enshrined/svg-sanitize — SEC04/DEP02); Composer's own autoloader is a
 * dev-only artifact and never ships. This tiny PSR-4 loader is the
 * production loading path and is what the unit suite exercises.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a vendored class name to its file path, or null if not ours.
 *
 * Pure function so the mapping itself is unit-testable.
 *
 * @param string $class_name Fully-qualified class name.
 * @return string|null Absolute path, or null when the class is not a vendored one.
 */
function salt_vendor_class_path( string $class_name ): ?string {
	$prefixes = array(
		'enshrined\\svgSanitize\\' => dirname( __DIR__, 2 ) . '/vendor/enshrined/svg-sanitize/src/',
	);

	foreach ( $prefixes as $prefix => $base_dir ) {
		if ( str_starts_with( $class_name, $prefix ) ) {
			$relative = substr( $class_name, strlen( $prefix ) );
			return $base_dir . str_replace( '\\', '/', $relative ) . '.php';
		}
	}

	return null;
}

/**
 * Register the vendored-library autoloader (idempotent).
 *
 * @return void
 */
function salt_register_vendor_autoloader(): void {
	static $registered = false;

	if ( $registered ) {
		return;
	}
	$registered = true;

	spl_autoload_register(
		static function ( string $class_name ): void {
			$path = salt_vendor_class_path( $class_name );
			if ( null !== $path && is_readable( $path ) ) {
				require $path;
			}
		}
	);
}
