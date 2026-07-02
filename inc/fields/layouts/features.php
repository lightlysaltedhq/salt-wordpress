<?php
/**
 * Flexible Content layout: Feature / Benefits Grid (spec §10 #5).
 *
 * Layout slug 'features' is a stability contract (FLEET07) — renaming it
 * is a MAJOR bump + slug-migrations.json entry. Content fields first, then
 * the shared §11 Advanced tab.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_features',
	'name'       => 'features',
	'label'      => __( 'Feature / Benefits Grid', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_features_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_features_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'           => 'field_salt_features_columns',
				'name'          => 'columns',
				'label'         => __( 'Columns', 'salt' ),
				'type'          => 'select',
				'choices'       => array(
					'2' => __( '2 columns', 'salt' ),
					'3' => __( '3 columns', 'salt' ),
					'4' => __( '4 columns', 'salt' ),
				),
				'default_value' => '3',
				'wrapper'       => array( 'width' => '50' ),
			),
			array(
				'key'          => 'field_salt_features_items',
				'name'         => 'items',
				'label'        => __( 'Features', 'salt' ),
				'type'         => 'repeater',
				'layout'       => 'block',
				'button_label' => __( 'Add feature', 'salt' ),
				'sub_fields'   => array(
					array(
						'key'          => 'field_salt_features_items_icon',
						'name'         => 'icon',
						'label'        => __( 'Icon', 'salt' ),
						'instructions' => __( 'Bundled icon name (e.g. check, star). Optional.', 'salt' ),
						'type'         => 'text',
						'wrapper'      => array( 'width' => '30' ),
					),
					array(
						'key'      => 'field_salt_features_items_title',
						'name'     => 'title',
						'label'    => __( 'Title', 'salt' ),
						'type'     => 'text',
						'required' => 1,
						'wrapper'  => array( 'width' => '70' ),
					),
					array(
						'key'   => 'field_salt_features_items_text',
						'name'  => 'text',
						'label' => __( 'Text', 'salt' ),
						'type'  => 'textarea',
						'rows'  => 3,
					),
					array(
						'key'           => 'field_salt_features_items_link',
						'name'          => 'link',
						'label'         => __( 'Link', 'salt' ),
						'type'          => 'link',
						'return_format' => 'array',
					),
				),
			),
		),
		salt_section_wrapper_fields( 'features' )
	),
);
