<?php
/**
 * CPT: FAQ (spec Appendix A.5) — display-only posture (SEC09).
 *
 * The question is the post title; the answer is an ACF WYSIWYG field
 * (inc/fields/cpt-faq.php). Display-only: no single view, no archive, no
 * REST surface — content exists solely to be composed into the FAQ
 * section (§10 #13) and its FAQPage JSON-LD (SEO03).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the faq post type + faq_category taxonomy (module-gated).
 *
 * @return void
 */
function salt_register_cpt_faq(): void {
	if ( ! salt_module_enabled( 'faq' ) ) {
		return;
	}

	register_post_type(
		'faq',
		array(
			'labels'              => array(
				'name'          => __( 'FAQs', 'salt' ),
				'singular_name' => __( 'FAQ', 'salt' ),
				'add_new_item'  => __( 'Add new FAQ', 'salt' ),
				'edit_item'     => __( 'Edit FAQ', 'salt' ),
				'new_item'      => __( 'New FAQ', 'salt' ),
				'search_items'  => __( 'Search FAQs', 'salt' ),
				'not_found'     => __( 'No FAQs found.', 'salt' ),
				'menu_name'     => __( 'FAQs', 'salt' ),
			),
			// SEC09 display-only posture: admin UI only, zero public surface.
			'public'              => false,
			'show_ui'             => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			// page-attributes exposes menu_order — the frozen default sort
			// for faq (salt_query_default_args: menu_order ASC).
			'supports'            => array( 'title', 'page-attributes' ),
			'menu_icon'           => 'dashicons-editor-help',
			'menu_position'       => 24,
		)
	);

	register_taxonomy(
		'faq_category',
		'faq',
		array(
			'labels'            => array(
				'name'          => __( 'FAQ categories', 'salt' ),
				'singular_name' => __( 'FAQ category', 'salt' ),
				'search_items'  => __( 'Search FAQ categories', 'salt' ),
				'edit_item'     => __( 'Edit FAQ category', 'salt' ),
				'add_new_item'  => __( 'Add new FAQ category', 'salt' ),
				'menu_name'     => __( 'Categories', 'salt' ),
			),
			'hierarchical'      => true,
			// Same display-only posture as the CPT (SEC09).
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => false,
			'rewrite'           => false,
			'show_admin_column' => true,
		)
	);
}
add_action( 'init', 'salt_register_cpt_faq' );
