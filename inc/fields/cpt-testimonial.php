<?php
/**
 * Testimonial CPT content fields (spec Appendix A.2; DATA02 keys).
 *
 * Group 'group_salt_testimonial', fields 'field_salt_testimonial_{name}'.
 * The author name is the post title and the author photo the featured
 * image (native supports) — only the ACF fields live here. Section-level
 * query controls belong to the CONSUMING section (§10a), never the CPT.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the testimonial field group ('acf/init').
 *
 * Skipped when the testimonial module is disabled (§10b).
 *
 * @return void
 */
function salt_register_testimonial_fields(): void {
	if ( ! salt_module_enabled( 'testimonial' ) ) {
		return;
	}

	salt_register_field_group(
		array(
			'key'      => 'group_salt_testimonial',
			'title'    => __( 'Testimonial', 'salt' ),
			'fields'   => array(
				array(
					'key'          => 'field_salt_testimonial_quote',
					'name'         => 'quote',
					'label'        => __( 'Quote', 'salt' ),
					'instructions' => __( 'The testimonial body. Plain text; line breaks are kept.', 'salt' ),
					'type'         => 'textarea',
					'required'     => 1,
					'rows'         => 4,
					'new_lines'    => '',
				),
				array(
					'key'         => 'field_salt_testimonial_role',
					'name'        => 'role',
					'label'       => __( 'Author role', 'salt' ),
					'type'        => 'text',
					'placeholder' => __( 'Founder', 'salt' ),
					'wrapper'     => array( 'width' => '50' ),
				),
				array(
					'key'     => 'field_salt_testimonial_company',
					'name'    => 'company',
					'label'   => __( 'Company', 'salt' ),
					'type'    => 'text',
					'wrapper' => array( 'width' => '50' ),
				),
				array(
					'key'           => 'field_salt_testimonial_logo',
					'name'          => 'logo',
					'label'         => __( 'Company logo', 'salt' ),
					'instructions'  => __( 'Optional.', 'salt' ),
					'type'          => 'image',
					'return_format' => 'id',
					'preview_size'  => 'thumbnail',
				),
				array(
					'key'          => 'field_salt_testimonial_rating',
					'name'         => 'rating',
					'label'        => __( 'Rating', 'salt' ),
					'instructions' => __( '1–5 stars. Leave empty for no rating. Emitted as Review schema (§15).', 'salt' ),
					'type'         => 'number',
					'min'          => 1,
					'max'          => 5,
					'step'         => 1,
				),
				array(
					'key'           => 'field_salt_testimonial_service',
					'name'          => 'service',
					'label'         => __( 'Related service', 'salt' ),
					'instructions'  => __( 'Shows this quote on the service single and attaches its Review schema to the service.', 'salt' ),
					'type'          => 'relationship',
					'post_type'     => array( 'service' ),
					'filters'       => array( 'search' ),
					'max'           => 1,
					'return_format' => 'id',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'testimonial',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'salt_register_testimonial_fields' );
