<?php
/**
 * Flexible Content layout: Services (spec §10 #4).
 *
 * Layout slug 'services' is a stability contract (FLEET07) — renaming it
 * is a MAJOR bump + slug-migrations.json entry. Content fields first, then
 * the shared query controls (§10a), then the shared §11 Advanced tab.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_services',
	'name'       => 'services',
	'label'      => __( 'Services', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'           => 'field_salt_services_layout',
				'name'          => 'layout',
				'label'         => __( 'Layout', 'salt' ),
				'type'          => 'select',
				'choices'       => array(
					'grid'     => __( 'Grid', 'salt' ),
					'list'     => __( 'List', 'salt' ),
					'featured' => __( 'Featured', 'salt' ),
				),
				'default_value' => 'grid',
				'wrapper'       => array( 'width' => '50' ),
			),
			array(
				'key'           => 'field_salt_services_columns',
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
				'key'   => 'field_salt_services_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_services_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'           => 'field_salt_services_view_all',
				'name'          => 'view_all',
				'label'         => __( 'Show "view all" link', 'salt' ),
				'instructions'  => __( 'Links to the services archive (§6 teaser rule — CPT sections never paginate).', 'salt' ),
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
			),
		),
		salt_query_control_fields( 'service' ),
		salt_section_wrapper_fields( 'services' )
	),
);
