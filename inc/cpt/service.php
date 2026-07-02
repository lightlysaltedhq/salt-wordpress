<?php
/**
 * Service CPT + service_category taxonomy (spec Appendix A.1; §10b module gate).
 *
 * Public CPT: archive at /services/, menu_order drag-sort via page-attributes
 * (the DATA04 default ordering surface). Registration is skipped entirely when
 * the 'service' module is disabled (§10b layer 1).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the service post type and its service_category taxonomy.
 *
 * @return void
 */
function salt_register_cpt_service(): void {
	if ( ! salt_module_enabled( 'service' ) ) {
		return;
	}

	register_post_type(
		'service',
		array(
			'labels'       => array(
				'name'               => __( 'Services', 'salt' ),
				'singular_name'      => __( 'Service', 'salt' ),
				'add_new_item'       => __( 'Add new service', 'salt' ),
				'edit_item'          => __( 'Edit service', 'salt' ),
				'new_item'           => __( 'New service', 'salt' ),
				'view_item'          => __( 'View service', 'salt' ),
				'view_items'         => __( 'View services', 'salt' ),
				'search_items'       => __( 'Search services', 'salt' ),
				'not_found'          => __( 'No services found.', 'salt' ),
				'not_found_in_trash' => __( 'No services found in Trash.', 'salt' ),
				'all_items'          => __( 'All services', 'salt' ),
				'archives'           => __( 'Service archives', 'salt' ),
				'menu_name'          => __( 'Services', 'salt' ),
			),
			'public'       => true,
			'has_archive'  => true,
			'rewrite'      => array( 'slug' => 'services' ),
			'menu_icon'    => 'dashicons-hammer',
			'show_in_rest' => false,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
		)
	);

	register_taxonomy(
		'service_category',
		array( 'service' ),
		array(
			'labels'            => array(
				'name'          => __( 'Service categories', 'salt' ),
				'singular_name' => __( 'Service category', 'salt' ),
				'search_items'  => __( 'Search service categories', 'salt' ),
				'all_items'     => __( 'All service categories', 'salt' ),
				'edit_item'     => __( 'Edit service category', 'salt' ),
				'update_item'   => __( 'Update service category', 'salt' ),
				'add_new_item'  => __( 'Add new service category', 'salt' ),
				'new_item_name' => __( 'New service category name', 'salt' ),
				'menu_name'     => __( 'Service categories', 'salt' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => false,
			'rewrite'           => array( 'slug' => 'service-category' ),
		)
	);
}
add_action( 'init', 'salt_register_cpt_service' );
