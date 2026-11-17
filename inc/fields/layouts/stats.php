<?php
/**
 * Flexible Content layout: Stats / Metrics (spec §10 #7).
 *
 * Layout slug 'stats' is a stability contract (FLEET07) — renaming it is a
 * MAJOR bump + slug-migrations.json entry. Content fields first, then the
 * shared §11 Advanced tab.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_stats',
	'name'       => 'stats',
	'label'      => __( 'Stats / Metrics', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_stats_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'           => 'field_salt_stats_columns',
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
				'key'          => 'field_salt_stats_items',
				'name'         => 'items',
				'label'        => __( 'Stats', 'salt' ),
				'type'         => 'repeater',
				'layout'       => 'table',
				'button_label' => __( 'Add stat', 'salt' ),
				'sub_fields'   => array(
					array(
						'key'      => 'field_salt_stats_items_value',
						'name'     => 'value',
						'label'    => __( 'Number', 'salt' ),
						'type'     => 'text',
						'required' => 1,
					),
					array(
						'key'          => 'field_salt_stats_items_suffix',
						'name'         => 'suffix',
						'label'        => __( 'Suffix', 'salt' ),
						'instructions' => __( 'Optional unit, e.g. %, +, k.', 'salt' ),
						'type'         => 'text',
					),
					array(
						'key'   => 'field_salt_stats_items_label',
						'name'  => 'label',
						'label' => __( 'Label', 'salt' ),
						'type'  => 'text',
					),
				),
			),
		),
		salt_section_wrapper_fields( 'stats' )
	),
);
