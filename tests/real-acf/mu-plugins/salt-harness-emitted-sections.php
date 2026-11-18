<?php
/**
 * Plugin Name: Salt harness: register the emitted sections group
 *
 * HARNESS ONLY. Never shipped. tests/real-acf/run.sh copies this file into
 * the throwaway site's wp-content/mu-plugins.
 *
 * Until the theme loads the generated ACF JSON itself (EP-72 M3 unit 3), this
 * stands in for it: it unhooks the theme's hand-written page sections group
 * and registers the pinned salt-contract emitter's output (acfSnapshot)
 * verbatim with acf_add_local_field_group(). Both use the key
 * group_salt_sections and the container key field_salt_sections_container,
 * so only one of them may be registered.
 *
 * When unit 3 lands, delete this file: the harness then checks whatever the
 * theme registers against the same emitted JSON, and nothing else changes.
 *
 * @package Salt\Tests\RealAcf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unhook the theme's hand-written sections group before acf/init runs it.
 *
 * @return void
 */
function salt_harness_unhook_theme_sections(): void {
	remove_action( 'acf/init', 'salt_register_sections_field' );
}
add_action( 'acf/init', 'salt_harness_unhook_theme_sections', 1 );

/**
 * Register every group in the emitted JSON, exactly as decoded.
 *
 * @return void
 */
function salt_harness_register_emitted_sections(): void {
	$path   = defined( 'SALT_HARNESS_ACF_JSON' ) ? (string) SALT_HARNESS_ACF_JSON : '';
	$groups = '' !== $path && is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;

	if ( ! is_array( $groups ) ) {
		trigger_error( 'salt harness: SALT_HARNESS_ACF_JSON is missing or not JSON', E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error -- The harness reads this from debug.log and fails.
		return;
	}

	foreach ( $groups as $group ) {
		acf_add_local_field_group( $group );
	}
}
add_action( 'acf/init', 'salt_harness_register_emitted_sections' );
