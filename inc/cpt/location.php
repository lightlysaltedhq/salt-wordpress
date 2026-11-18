<?php
/**
 * Location CPT + location_area taxonomy (spec §10 #20, Appendix A.6).
 *
 * Display-only in v1 (SEC09 posture): offices are managed in wp-admin and
 * rendered exclusively through the Locations section — no public single or
 * archive URLs exist (optional single-location pages are deferred), so
 * public/publicly_queryable/show_in_rest are all false and nothing leaks
 * into search. page-attributes gives editors menu_order drag-sorting, which
 * is the frozen default order for the section query (AMBIG07). A location
 * has no featured image (salt-contract collections/locations.json dropped
 * it), so thumbnail is not supported.
 *
 * Gated behind the 'location' module flag (§10b) — when the module is off,
 * neither the CPT nor the taxonomy registers.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the location CPT and its location_area taxonomy.
 *
 * @return void
 */
function salt_register_cpt_location(): void {
	if ( ! salt_module_enabled( 'location' ) ) {
		return;
	}

	register_post_type(
		'location',
		array(
			'labels'              => array(
				'name'          => __( 'Locations', 'salt' ),
				'singular_name' => __( 'Location', 'salt' ),
				'add_new_item'  => __( 'Add new location', 'salt' ),
				'edit_item'     => __( 'Edit location', 'salt' ),
				'search_items'  => __( 'Search locations', 'salt' ),
				'not_found'     => __( 'No locations found', 'salt' ),
			),
			'description'         => __( 'Offices rendered by the Locations section.', 'salt' ),
			'public'              => false,
			'show_ui'             => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'menu_icon'           => 'dashicons-location',
			'supports'            => array( 'title', 'page-attributes' ),
		)
	);

	register_taxonomy(
		'location_area',
		array( 'location' ),
		array(
			'labels'               => salt_topic_labels(), // Topics (inc/cpt/topics.php).
			'meta_box_cb'          => 'salt_topic_meta_box',
			'meta_box_sanitize_cb' => 'taxonomy_meta_box_sanitize_cb_checkboxes',
			'description'          => __( 'Group offices by region for section filtering.', 'salt' ),
			'public'               => false,
			'show_ui'              => true,
			'show_admin_column'    => true,
			'show_in_rest'         => false,
			// Hierarchical only to keep the checkbox box; topics are flat (inc/cpt/topics.php).
			'hierarchical'         => true,
			'rewrite'              => false,
		)
	);
}
add_action( 'init', 'salt_register_cpt_location' );
