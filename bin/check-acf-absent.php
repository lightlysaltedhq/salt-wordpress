<?php
/**
 * DEP01 smoke test — the theme must NOT fatal when ACF is absent.
 *
 * This reproduces the exact production condition the PHPUnit suite cannot:
 * a request lifecycle in which NO ACF read function exists. tests/wp-shims.php
 * unconditionally defines get_field(), which masked the ≤3.0.0 white-screen
 * where salt_module_enabled() → get_field() fataled during CPT registration.
 *
 * Here we define a minimal WP surface WITHOUT the ACF read API, load the
 * theme's guard + module + CPT code, and drive the init-hook path that
 * fataled. Exit 0 = safe fallbacks held (DEP01); non-zero = regression.
 *
 * Usage: php bin/check-acf-absent.php
 *
 * @package Salt\Tooling
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$salt_root = dirname( __DIR__ );

define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'WP_DEBUG', false );

// Minimal WP surface — deliberately NO get_field/have_rows/get_sub_field/etc.
// so the ACF read API is genuinely absent, exactly like a site without ACF.
function add_action() {} // phpcs:ignore
function add_filter() {} // phpcs:ignore
function apply_filters( string $hook, $value = null ) { // phpcs:ignore
	return $value;
}
function __( string $text ): string { // phpcs:ignore
	return $text;
}
function esc_html( string $text ): string { // phpcs:ignore
	return $text;
}
function esc_html__( string $text ): string { // phpcs:ignore
	return $text;
}
function register_post_type() {} // phpcs:ignore
function register_taxonomy() {} // phpcs:ignore
function salt_log( string $message ): void { // phpcs:ignore -- Pre-defined so the pluggable production version stays quiet here.
	unset( $message );
}

require $salt_root . '/inc/helpers.php';
require $salt_root . '/inc/acf.php';   // Defines the ACF-absent read-API fallbacks at require-time.
require $salt_root . '/inc/modules.php';

$salt_failures = array();

// 1. ACF is reported absent.
if ( salt_acf_active() ) {
	$salt_failures[] = 'salt_acf_active() should be false in a no-ACF world.';
}

// 2. The fallback shim defined the read API.
foreach ( array( 'get_field', 'have_rows', 'get_sub_field', 'get_row_layout' ) as $salt_fn ) {
	if ( ! function_exists( $salt_fn ) ) {
		$salt_failures[] = "DEP01 fallback missing: {$salt_fn}() was not defined.";
	}
}

// 3. get_field returns null → consumers get their documented default.
if ( null !== get_field( 'module_service', 'option' ) ) {
	$salt_failures[] = 'get_field() fallback must return null.';
}

// 4. THE regression: salt_module_enabled() must not fatal and must default
// to enabled when the option is unreadable.
if ( true !== salt_module_enabled( 'service' ) ) {
	$salt_failures[] = 'salt_module_enabled() should default to true when ACF is absent.';
}

// 5. The exact production stack: CPT registration on init called
// salt_module_enabled(), which fataled in ≤3.0.0. Drive it directly.
require $salt_root . '/inc/cpt/service.php';
salt_register_cpt_service(); // Must not fatal.

if ( array() === $salt_failures ) {
	fwrite( STDOUT, "DEP01 OK — theme survives with ACF absent (no fatal, safe fallbacks).\n" );
	exit( 0 );
}

fwrite( STDERR, "DEP01 REGRESSION:\n" );
foreach ( $salt_failures as $salt_failure ) {
	fwrite( STDERR, "  - {$salt_failure}\n" );
}
exit( 1 );
