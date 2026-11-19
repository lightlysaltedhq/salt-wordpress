<?php
/**
 * The collection field groups, generated from salt-contract.
 *
 * One group per collection the theme registers (services, case studies,
 * testimonials, posts, team, FAQs, locations), located on its post type or user form,
 * exactly as inc/fields/generated/collections.json holds it
 * (salt_generated_collection_groups()). A group whose post type belongs to
 * a module that is switched off is not registered (§10b). What WordPress
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
		salt_register_field_group( $group );
	}
}
add_action( 'acf/init', 'salt_register_collection_fields' );

/**
 * The suggested longest summary, in characters (salt-contract rc.7:
 * summary, maxLength 300, on services, case studies and posts). A target,
 * never a limit (the founder's ruling of 19/11/2026): WordPress keeps the
 * summary in the excerpt (salt.native), and the editor shows a count
 * against it.
 */
const SALT_SUMMARY_TARGET_LENGTH = 300;

/**
 * The post types whose summary is the excerpt, counted against 300
 * characters (the rc.7 owes on services.json, case-studies.json and
 * posts.json).
 *
 * @return string[]
 */
function salt_summary_post_types(): array {
	return array( 'service', 'work', 'post' );
}

/**
 * Show a count beside the Excerpt box against the suggested 300 characters
 * ('admin_enqueue_scripts' on a service, case study or post edit screen).
 * It never refuses a save (salt_character_count()).
 *
 * @param string $hook_suffix The admin screen.
 * @return void
 */
function salt_summary_count_enqueue( string $hook_suffix ): void {
	if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) && in_array( (string) ( $GLOBALS['typenow'] ?? '' ), salt_summary_post_types(), true ) ) {
		salt_character_count( 'excerpt', SALT_SUMMARY_TARGET_LENGTH );
	}
}
add_action( 'admin_enqueue_scripts', 'salt_summary_count_enqueue' );

/**
 * The suggested longest collection title, in characters (salt-contract
 * rc.7: the title, name or question of every collection, maxLength 120).
 * A target, never a limit (the founder's ruling of 19/11/2026): WordPress
 * keeps it in the post title (salt.native), and the editor shows a count
 * against it.
 */
const SALT_TITLE_TARGET_LENGTH = 120;

/**
 * The post types whose title is a collection's title, name or question,
 * counted against 120 characters: services, case studies, posts,
 * testimonials (name), team members (name), FAQs (question) and locations
 * (name).
 *
 * @return string[]
 */
function salt_title_post_types(): array {
	return array( 'service', 'work', 'post', 'testimonial', 'team_member', 'faq', 'location' );
}

/**
 * The length past which search results may shorten a title, in characters
 * (about 60): a softer note on the title of a collection with a page of its
 * own (the founder's ruling of 19/11/2026).
 */
const SALT_TITLE_SEARCH_LENGTH = 60;

/**
 * The post types whose title also has the softer note past about 60
 * characters: the collections with a page of their own (services, case
 * studies and posts). FAQs, testimonials, team members and locations have
 * the 120 note only.
 *
 * @return string[]
 */
function salt_title_search_post_types(): array {
	return array( 'service', 'work', 'post' );
}

/**
 * Show a count beside the title against the suggested 120 characters, with
 * the softer note past about 60 on a service, case study or post
 * ('admin_enqueue_scripts' on the edit screen of a collection's post type).
 * It never refuses a save (salt_character_count()).
 *
 * @param string $hook_suffix The admin screen.
 * @return void
 */
function salt_title_count_enqueue( string $hook_suffix ): void {
	$type = (string) ( $GLOBALS['typenow'] ?? '' );
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! in_array( $type, salt_title_post_types(), true ) ) {
		return;
	}
	$notes = array();
	if ( in_array( $type, salt_title_search_post_types(), true ) ) {
		/* translators: %d: the length, in characters, past which search results may shorten a title. */
		$notes[ SALT_TITLE_SEARCH_LENGTH ] = sprintf( __( 'Search results may shorten a title over about %d characters', 'salt' ), SALT_TITLE_SEARCH_LENGTH );
	}
	salt_character_count( 'title', SALT_TITLE_TARGET_LENGTH, $notes );
}
add_action( 'admin_enqueue_scripts', 'salt_title_count_enqueue' );
