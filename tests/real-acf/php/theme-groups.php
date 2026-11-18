<?php
/**
 * Phase 1: the theme's own field groups, as it registers them today, on real
 * ACF Pro (no harness mu-plugin installed yet). HARNESS ONLY.
 *
 * Loads every local group's fields through ACF (which runs each field type's
 * load_field), so a malformed hand-written definition shows up as a PHP
 * notice in debug.log, which run.sh checks after this phase.
 *
 * @package Salt\Tests\RealAcf
 */

require __DIR__ . '/wp.php';

$salt_harness_expect_acf = (string) getenv( 'SALT_HARNESS_ACF_VERSION' );
salt_harness_result(
	function_exists( 'acf_add_local_field_group' ) && defined( 'ACF_PRO' ) && defined( 'ACF_VERSION' ) && ACF_VERSION === $salt_harness_expect_acf,
	'ACF Pro active',
	'ACF_VERSION ' . ( defined( 'ACF_VERSION' ) ? ACF_VERSION : 'undefined' ) . ', expected ' . $salt_harness_expect_acf . ', ACF_PRO ' . ( defined( 'ACF_PRO' ) ? 'yes' : 'no' )
);
salt_harness_result( salt_acf_active() && salt_acf_version_ok(), 'theme sees ACF', 'salt_acf_active() and salt_acf_version_ok() are true' );

$salt_harness_groups = acf_get_local_field_groups();
$salt_harness_total  = 0;
$salt_harness_lines  = array();
foreach ( $salt_harness_groups as $salt_harness_group ) {
	$salt_harness_fields  = salt_harness_walk_fields( acf_get_fields( $salt_harness_group['key'] ) );
	$salt_harness_total  += count( $salt_harness_fields );
	$salt_harness_lines[] = sprintf( '%s (%d fields)', $salt_harness_group['key'], count( $salt_harness_fields ) );
}
salt_harness_result(
	count( $salt_harness_groups ) > 0,
	'theme field groups load on real ACF',
	sprintf( '%d groups, %d fields', count( $salt_harness_groups ), $salt_harness_total )
);
salt_harness_info( implode( '; ', $salt_harness_lines ) );

$salt_harness_container = acf_get_field( 'field_salt_sections_container' );
$salt_harness_files     = glob( get_template_directory() . '/inc/fields/layouts/*.php' );
salt_harness_result(
	is_array( $salt_harness_container ) && count( $salt_harness_container['layouts'] ) === count( (array) $salt_harness_files ),
	'theme sections container (hand-written)',
	sprintf( '%d layouts registered, %d layout files', is_array( $salt_harness_container ) ? count( $salt_harness_container['layouts'] ) : 0, count( (array) $salt_harness_files ) )
);

$salt_harness_page_groups = acf_get_field_groups( array( 'post_type' => 'page' ) );
salt_harness_info( 'groups shown on a page: ' . implode( ', ', wp_list_pluck( $salt_harness_page_groups, 'key' ) ) );

salt_harness_exit();
