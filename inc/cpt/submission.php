<?php
/**
 * CPT: Enquiry submission (spec §9) — locked-down storage posture (SEC09).
 *
 * Stores contact-form enquiries ONLY when the site opts in via the
 * 'submissions_store' option (data minimisation, PRIV03 — email/webhook
 * delivery is the primary channel; see inc/services/submissions.php).
 * Zero public surface: not queryable, not searchable, no REST, no archive,
 * no rewrite — records exist solely for the wp-admin list table.
 *
 * Capability choice (documented): capability_type 'salt_submission' with
 * map_meta_cap => true, and every PRIMITIVE capability mapped to
 * 'manage_options' so ONLY administrators can list, read, edit or delete
 * submissions. Editors and below never see personal data (SEC09/PRIV06).
 * The CPT itself is NOT module-gated — the storage gate is the
 * 'submissions_store' option, so already-stored records stay manageable
 * even after storage is switched back off.
 *
 * CRITICAL — do NOT map the META capabilities ('edit_post', 'read_post',
 * 'delete_post') to a shared primitive like 'manage_options'. WordPress'
 * _post_type_meta_capabilities() registers that primitive as a meta-cap
 * ALIAS in the global $post_type_meta_caps, so every bare
 * current_user_can('manage_options') is then rewritten to a meta cap that
 * needs a post ID — and denied for EVERYONE, admins included (it hides
 * core Settings, plugin option pages, and this theme's own Site Settings).
 * With map_meta_cap => true, WordPress derives the meta caps from the
 * primitives below, so admin-only access is preserved either way.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the salt_submission post type.
 *
 * @return void
 */
function salt_register_cpt_submission(): void {
	register_post_type(
		'salt_submission',
		array(
			'labels'              => array(
				'name'          => __( 'Submissions', 'salt' ),
				'singular_name' => __( 'Submission', 'salt' ),
				'edit_item'     => __( 'View submission', 'salt' ),
				'search_items'  => __( 'Search submissions', 'salt' ),
				'not_found'     => __( 'No submissions found.', 'salt' ),
				'menu_name'     => __( 'Submissions', 'salt' ),
			),
			// SEC09 locked-down posture: admin list table only, zero public surface.
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'supports'            => array( 'title' ),
			'menu_icon'           => 'dashicons-email-alt',
			'menu_position'       => 26,
			// Administrators only. PRIMITIVE capabilities only — mapping the
			// meta caps (edit_post/read_post/delete_post) here poisons
			// manage_options globally (see the file docblock). map_meta_cap
			// derives the meta caps from these primitives.
			'capability_type'     => 'salt_submission',
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'edit_posts'             => 'manage_options',
				'edit_others_posts'      => 'manage_options',
				'delete_posts'           => 'manage_options',
				'delete_others_posts'    => 'manage_options',
				'delete_private_posts'   => 'manage_options',
				'delete_published_posts' => 'manage_options',
				'edit_private_posts'     => 'manage_options',
				'edit_published_posts'   => 'manage_options',
				'publish_posts'          => 'manage_options',
				'read_private_posts'     => 'manage_options',
				'create_posts'           => 'manage_options',
			),
		)
	);
}
add_action( 'init', 'salt_register_cpt_submission' );
