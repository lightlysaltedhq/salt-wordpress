<?php
/**
 * The collection field groups, generated from salt-contract.
 *
 * One group per collection the theme registers (services, case studies,
 * testimonials, posts, team, FAQs, locations), located on its post type,
 * exactly as inc/fields/generated/collections.json holds it
 * (salt_generated_collection_groups()). A group whose post type belongs to
 * a module that is switched off is not registered (§10b). What WordPress
 * stores in its own place (the title, body, excerpt, featured image,
 * categories and date) has no field here: each group names it under
 * `salt.native`, and the readers take it from WordPress.
 *
 * The options pages and the user profile group stay hand-written.
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
		$module = $modules[ salt_collection_group_post_type( $group ) ] ?? null;
		if ( null !== $module && ! salt_module_enabled( $module ) ) {
			continue;
		}
		salt_register_field_group( $group );
	}
}
add_action( 'acf/init', 'salt_register_collection_fields' );
