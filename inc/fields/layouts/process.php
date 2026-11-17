<?php
/**
 * Flexible Content layout: Process / Steps (spec §10 #14).
 *
 * Layout slug 'process' is a stability contract (FLEET07) — renaming it is
 * a MAJOR bump + slug-migrations.json entry. Content fields first, then the
 * shared §11 Advanced tab. HowTo schema is OPT-IN, default off (AEO not
 * SEO, §15).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_process',
	'name'       => 'process',
	'label'      => __( 'Process / Steps', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_process_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_process_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'           => 'field_salt_process_layout',
				'name'          => 'layout',
				'label'         => __( 'Layout', 'salt' ),
				'type'          => 'select',
				'choices'       => array(
					'timeline' => __( 'Timeline', 'salt' ),
					'cards'    => __( 'Cards', 'salt' ),
				),
				'default_value' => 'timeline',
				'wrapper'       => array( 'width' => '50' ),
			),
			array(
				'key'          => 'field_salt_process_steps',
				'name'         => 'steps',
				'label'        => __( 'Steps', 'salt' ),
				'type'         => 'repeater',
				'layout'       => 'block',
				'button_label' => __( 'Add step', 'salt' ),
				'sub_fields'   => array(
					array(
						'key'      => 'field_salt_process_steps_title',
						'name'     => 'title',
						'label'    => __( 'Title', 'salt' ),
						'type'     => 'text',
						'required' => 1,
					),
					array(
						'key'   => 'field_salt_process_steps_text',
						'name'  => 'text',
						'label' => __( 'Text', 'salt' ),
						'type'  => 'textarea',
						'rows'  => 3,
					),
					array(
						'key'          => 'field_salt_process_steps_icon',
						'name'         => 'icon',
						'label'        => __( 'Icon', 'salt' ),
						'instructions' => __( 'Optional built-in icon name.', 'salt' ),
						'type'         => 'text',
					),
				),
			),
			array(
				'key'           => 'field_salt_process_structured_data',
				'name'          => 'structuredData',
				'label'         => __( 'HowTo schema', 'salt' ),
				'instructions'  => __( 'Emit HowTo JSON-LD for these steps (AEO, §15). Off by default.', 'salt' ),
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
			),
		),
		salt_section_wrapper_fields( 'process' )
	),
);
