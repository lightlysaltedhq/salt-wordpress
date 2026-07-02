<?php
/**
 * CPT: Team member (spec §10 #12, Appendix A.3) + 'department' taxonomy.
 *
 * v1 DECISION (documented per Appendix A.3 "Public: optional"): team_member
 * is NOT public. Single bio pages are a nice-to-have the spec leaves
 * optional; v1 ships the SEC09 display-only posture — public=false,
 * show_ui=true, publicly_queryable=false, exclude_from_search=true,
 * show_in_rest=false, has_archive=false. Making singles public later is a
 * deliberate (minor) release decision, not a per-site toggle.
 *
 * Teaser rule (§6) consequence: with no public archive, the Team section
 * renders NO "view all" link — it is a bounded grid only.
 *
 * Gated by the 'team_member' module flag (§10b, layer 1: registration).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the team_member post type and its department taxonomy.
 *
 * @return void
 */
function salt_register_cpt_team_member(): void {
	if ( ! salt_module_enabled( 'team_member' ) ) {
		return;
	}

	register_post_type(
		'team_member',
		array(
			'labels'              => array(
				'name'          => __( 'Team members', 'salt' ),
				'singular_name' => __( 'Team member', 'salt' ),
				'add_new_item'  => __( 'Add new team member', 'salt' ),
				'edit_item'     => __( 'Edit team member', 'salt' ),
				'not_found'     => __( 'No team members found.', 'salt' ),
			),
			// SEC09 display-only posture (v1 decision — see file docblock).
			'public'              => false,
			'show_ui'             => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'menu_icon'           => 'dashicons-groups',
			'supports'            => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
		)
	);

	register_taxonomy(
		'department',
		array( 'team_member' ),
		array(
			'labels'            => array(
				'name'          => __( 'Departments', 'salt' ),
				'singular_name' => __( 'Department', 'salt' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => false,
			'hierarchical'      => true,
			'show_admin_column' => true,
		)
	);
}
add_action( 'init', 'salt_register_cpt_team_member' );
