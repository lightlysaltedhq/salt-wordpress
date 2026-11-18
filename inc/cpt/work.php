<?php
/**
 * CPT: Work / Case Studies (spec Appendix A.4) — public portfolio content.
 *
 * Public posture: single views + a /work/ archive (has_archive true), with
 * the work_type taxonomy (the contract's topics) for filtering (§10a query
 * controls filter by work_type — salt_query_taxonomy()). A case study is
 * fixed fields (salt-contract collections/case-studies.json): no page body,
 * so no editor; the summary is the excerpt and featuredImage the thumbnail.
 * The industry taxonomy was dropped from the contract and is not registered.
 *
 * DATA01 (migration parity): 'case_study' is a FIELD-SHAPE parity alias
 * ONLY — no second CPT is registered here or anywhere. The live theme's
 * case_study posts are converted by the one-shot WP-CLI importer (a later,
 * deliberately-out-of-this-build task) which rewrites post_type
 * case_study→work, remaps taxonomy terms and relationship IDs, and maps
 * field keys onto the generated case-study group's names. Do not improvise
 * per-site conversions.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the work post type + work_type taxonomy (module-gated).
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
			'supports'            => array( 'title', 'thumbnail', 'excerpt' ),
			'menu_icon'           => 'dashicons-portfolio',
			'menu_position'       => 22,
		)
	);

	register_taxonomy(
		'work_type',
		'work',
		array(
			'labels'               => salt_topic_labels(), // Topics (inc/cpt/topics.php).
			'meta_box_cb'          => 'salt_topic_meta_box',
			'meta_box_sanitize_cb' => 'taxonomy_meta_box_sanitize_cb_checkboxes',
			// Hierarchical only to keep the checkbox box; topics are flat (inc/cpt/topics.php).
			'hierarchical'         => true,
			'public'               => true,
			'show_ui'              => true,
			'show_in_rest'         => false,
			'show_admin_column'    => true,
			'rewrite'              => array(
				'slug'       => 'work-type',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'salt_register_cpt_work' );
