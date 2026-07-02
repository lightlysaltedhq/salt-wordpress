<?php
/**
 * Flexible Content layout: Team (spec §10 #12).
 *
 * Layout slug 'team' is a stability contract (FLEET07) — renaming it is a
 * MAJOR bump + slug-migrations.json entry. Content fields first, then the
 * shared query controls (§10a), then the §11 wrapper Advanced tab.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_team',
	'name'       => 'team',
	'label'      => __( 'Team', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_team_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_team_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'           => 'field_salt_team_columns',
				'name'          => 'columns',
				'label'         => __( 'Columns', 'salt' ),
				'type'          => 'button_group',
				'choices'       => array(
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'default_value' => '3',
			),
		),
		salt_query_control_fields( 'team_member' ),
		salt_section_wrapper_fields( 'team' )
	),
);
