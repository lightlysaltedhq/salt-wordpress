<?php
/**
 * The collection field groups, generated from salt-contract.
 *
 * One group per collection the theme registers (services, case studies,
 * testimonials, posts, team, FAQs, locations), located on its post type or user form,
 * exactly as inc/fields/generated/collections.json holds it
 * (salt_generated_collection_groups()). A group whose post type belongs to
 * a module that is switched off is not registered (§10b), nor is a case
 * study's clientRecord or sectorRecord while its module is off
 * (salt_record_fields_in_group()). What WordPress
 * stores in its own place (the title, body, excerpt, featured image,
 * categories and date) has no field here: each group names it under
 * `salt.native`, and the readers take it from WordPress.
 *
 * The options pages stay hand-written.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the generated collection field groups.
 *
 * @return void
 */
function salt_register_collection_fields(): void {
	$modules = array_flip( salt_module_cpts() );

	foreach ( salt_generated_collection_groups() as $group ) {
		$module = 'team' === $group['salt']['collection'] ? 'team_member' : ( $modules[ salt_collection_group_post_type( $group ) ] ?? null );
		if ( null !== $module && ! salt_module_enabled( $module ) ) {
			continue;
		}
		salt_register_field_group( salt_record_fields_in_group( $group ) );
	}
}
add_action( 'acf/init', 'salt_register_collection_fields' );

/**
 * Configure native fields from the same emitted targets as the ACF fields.
 *
 * @param string $hook_suffix The admin screen.
 * @return void
 */
function salt_native_counts_enqueue( string $hook_suffix ): void {
	$profile = in_array( $hook_suffix, array( 'profile.php', 'user-edit.php', 'tools_page_salt-team' ), true );
	if ( ! $profile && ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	foreach ( salt_generated_collection_groups() as $group ) {
		$salt     = $group['salt'] ?? array();
		$location = $group['location'][0][0];
		$matches  = $profile ? isset( $salt['userTick'] ) : ( 'post_type' === $location['param'] && salt_collection_group_post_type( $group ) === ( $GLOBALS['typenow'] ?? '' ) );
		if ( ! $matches ) {
			continue;
		}
		foreach ( $salt['targets'] ?? array() as $name => $target ) {
			$id    = $salt['native'][ $name ];
			$marks = (array) $target;
			$last  = array_pop( $marks );
			$notes = array();
			foreach ( $marks as $mark ) {
				/* translators: %d: suggested title length for search results. */
				$notes[ $mark ] = sprintf( __( 'Search results may shorten a title over about %d characters', 'salt' ), $mark );
			}
			salt_character_count( $id, $last, $notes );
		}
	}
}
add_action( 'admin_enqueue_scripts', 'salt_native_counts_enqueue' );
