<?php
/**
 * CPT: Work / Case Studies (spec Appendix A.4) — public portfolio content.
 *
 * Public posture: single views + a /work/ archive (has_archive true), with
 * the work_type and industry taxonomies for filtering (§10a query controls
 * filter by work_type — salt_query_taxonomy()).
 *
 * DATA01 (migration parity): 'case_study' is a FIELD-SHAPE parity alias
 * ONLY — no second CPT is registered here or anywhere. The live theme's
 * case_study posts are converted by the one-shot WP-CLI importer (a later,
 * deliberately-out-of-this-build task) which rewrites post_type
 * case_study→work, remaps taxonomy terms and relationship IDs, and maps
 * field keys onto the group_salt_work names below. Do not improvise
 * per-site conversions.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the work post type + work_type / industry taxonomies (module-gated).
 *
 * @return void
 */
function salt_register_cpt_work(): void {
	if ( ! salt_module_enabled( 'work' ) ) {
		return;
	}

	register_post_type(
		'work',
		array(
			'labels'              => array(
				'name'          => __( 'Work', 'salt' ),
				'singular_name' => __( 'Work item', 'salt' ),
				'add_new_item'  => __( 'Add new work item', 'salt' ),
				'edit_item'     => __( 'Edit work item', 'salt' ),
				'new_item'      => __( 'New work item', 'salt' ),
				'view_item'     => __( 'View work item', 'salt' ),
				'search_items'  => __( 'Search work', 'salt' ),
				'not_found'     => __( 'No work found.', 'salt' ),
				'menu_name'     => __( 'Work', 'salt' ),
			),
			// Public portfolio: singles + archive (Appendix A.4).
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'exclude_from_search' => false,
			// Gutenberg is disabled fleet-wide (§13) — no REST editor surface.
			'show_in_rest'        => false,
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'work',
				'with_front' => false,
			),
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'menu_icon'           => 'dashicons-portfolio',
			'menu_position'       => 22,
		)
	);

	register_taxonomy(
		'work_type',
		'work',
		array(
			'labels'            => array(
				'name'          => __( 'Work types', 'salt' ),
				'singular_name' => __( 'Work type', 'salt' ),
				'search_items'  => __( 'Search work types', 'salt' ),
				'edit_item'     => __( 'Edit work type', 'salt' ),
				'add_new_item'  => __( 'Add new work type', 'salt' ),
				'menu_name'     => __( 'Work types', 'salt' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => false,
			'show_admin_column' => true,
			'rewrite'           => array(
				'slug'       => 'work-type',
				'with_front' => false,
			),
		)
	);

	register_taxonomy(
		'industry',
		'work',
		array(
			'labels'            => array(
				'name'          => __( 'Industries', 'salt' ),
				'singular_name' => __( 'Industry', 'salt' ),
				'search_items'  => __( 'Search industries', 'salt' ),
				'edit_item'     => __( 'Edit industry', 'salt' ),
				'add_new_item'  => __( 'Add new industry', 'salt' ),
				'menu_name'     => __( 'Industries', 'salt' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => false,
			'show_admin_column' => true,
			'rewrite'           => array(
				'slug'       => 'industry',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'salt_register_cpt_work' );
