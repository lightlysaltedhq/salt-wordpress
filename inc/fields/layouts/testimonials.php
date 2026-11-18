<?php
/**
 * Flexible Content layout: Testimonials (spec §10 #8).
 *
 * Layout slug 'testimonials' is a stability contract (FLEET07) — renaming
 * it is a MAJOR bump + slug-migrations.json entry. Content fields first,
 * then the shared testimonial query controls (§10a — no taxonomy filter:
 * salt_query_taxonomy('testimonial') is null), then the §11 wrapper fields.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_testimonials',
	'name'       => 'testimonials',
	'label'      => __( 'Testimonials', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'           => 'field_salt_testimonials_layout',
				'name'          => 'layout',
				'label'         => __( 'Layout', 'salt' ),
				'type'          => 'select',
				'choices'       => array(
					'grid'   => __( 'Grid', 'salt' ),
					'slider' => __( 'Slider', 'salt' ),
				),
				'default_value' => 'grid',
			),
			array(
				'key'   => 'field_salt_testimonials_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_testimonials_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
		),
		salt_query_control_fields( 'testimonial' ),
		salt_section_settings_fields( 'testimonials' )
	),
);
