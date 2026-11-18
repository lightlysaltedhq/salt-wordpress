<?php
/**
 * Flexible Content layout: Portfolio / Work (spec §10 #15) — CPT-backed ✦.
 *
 * Content fields first, then the shared query controls
 * (salt_query_control_fields('work') — §10a), then the contract's section
 * settings group. Layout slug 'work' is a stability contract (FLEET07) — renaming it is a MAJOR bump + slug-migrations.json entry.
 *
 * The §10 'filterable' flag is NOT implemented in v1 (see the section
 * partial docblock) — no field is registered for it.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_work',
	'name'       => 'work',
	'label'      => __( 'Portfolio / Work', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_work_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_work_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'           => 'field_salt_work_columns',
				'name'          => 'columns',
				'label'         => __( 'Columns', 'salt' ),
				'type'          => 'button_group',
				'choices'       => array(
					2 => __( '2 columns', 'salt' ),
					3 => __( '3 columns', 'salt' ),
				),
				'default_value' => 3,
			),
			array(
				'key'           => 'field_salt_work_view_all',
				'name'          => 'view_all',
				'label'         => __( '"View all" link', 'salt' ),
				'instructions'  => __( 'Link to the work archive. Sections never paginate (§6) — raise the item count or send visitors to the archive.', 'salt' ),
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
			),
		),
		salt_query_control_fields( 'work' ),
		salt_section_settings_fields( 'work' )
	),
);
