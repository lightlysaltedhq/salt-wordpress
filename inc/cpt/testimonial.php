<?php
/**
 * Testimonial CPT registration (spec Appendix A.2; §10b module gate).
 *
 * DISPLAY-ONLY posture (SEC09): testimonials are content ATOMS rendered
 * inside sections — they get no public URL, no archive, no REST surface
 * and never appear in search. Editors manage them via show_ui only.
 *
 * Supports: title (author name), thumbnail (author photo). No taxonomy —
 * salt_query_taxonomy('testimonial') is null by frozen contract, so the
 * section's query controls omit the term filter.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the display-only testimonial CPT ('init').
 *
 * Layer-1 module gate (§10b): registration is skipped entirely when the
 * testimonial module is disabled.
 *
 * @return void
 */
function salt_register_cpt_testimonial(): void {
	if ( ! salt_module_enabled( 'testimonial' ) ) {
		return;
	}

	register_post_type(
		'testimonial',
		array(
			'labels'              => array(
				'name'               => __( 'Testimonials', 'salt' ),
				'singular_name'      => __( 'Testimonial', 'salt' ),
				'add_new'            => __( 'Add testimonial', 'salt' ),
				'add_new_item'       => __( 'Add new testimonial', 'salt' ),
				'edit_item'          => __( 'Edit testimonial', 'salt' ),
				'new_item'           => __( 'New testimonial', 'salt' ),
				'view_item'          => __( 'View testimonial', 'salt' ),
				'search_items'       => __( 'Search testimonials', 'salt' ),
				'not_found'          => __( 'No testimonials found', 'salt' ),
				'not_found_in_trash' => __( 'No testimonials found in Trash', 'salt' ),
				'menu_name'          => __( 'Testimonials', 'salt' ),
			),
			// DISPLAY-ONLY posture (SEC09): admin-managed, never routable.
			'public'              => false,
			'show_ui'             => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'menu_icon'           => 'dashicons-format-quote',
			'supports'            => array( 'title', 'thumbnail' ),
		)
	);
}
add_action( 'init', 'salt_register_cpt_testimonial' );
