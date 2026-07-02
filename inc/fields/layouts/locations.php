<?php
/**
 * Flexible Content layout: Locations (spec §10 #20).
 *
 * Layout slug 'locations' is a stability contract (FLEET07) — renaming it
 * is a MAJOR bump + slug-migrations.json entry. CPT-backed: embeds the
 * shared Principle-7 query controls for the location CPT (§10a).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_locations',
	'name'       => 'locations',
	'label'      => __( 'Locations', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_locations_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_locations_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
		),
		salt_query_control_fields( 'location' ),
		salt_section_wrapper_fields( 'locations' )
	),
);
