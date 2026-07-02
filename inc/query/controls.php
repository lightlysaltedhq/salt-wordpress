<?php
/**
 * Query control sub-fields for CPT-backed sections (spec §10a).
 *
 * One field factory reused across the seven CPT-backed sections (SCOPE01:
 * service, testimonial, team_member, work, faq, post, location). Each
 * section's Flexible Content layout embeds these sub-fields; the saved
 * values form the controls array consumed by salt_resolve_query().
 *
 * Canonical field keys follow field_salt_query_{cpt}_{name} (DATA02).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF sub-field definitions for a CPT-backed section's query controls.
 *
 * Returns an array of ACF sub-field definition arrays for embedding in a
 * Flexible Content layout. The taxonomy_terms field is omitted entirely
 * when the CPT has no filterable taxonomy (salt_query_taxonomy() null,
 * e.g. testimonial).
 *
 * @param string $cpt Post type the section queries.
 * @return array<int,array<string,mixed>> ACF sub-field definition arrays.
 */
function salt_query_control_fields( string $cpt ): array {
	$taxonomy   = salt_query_taxonomy( $cpt );
	$source_key = "field_salt_query_{$cpt}_source";

	$fields = array();

	$fields[] = array(
		'key'           => $source_key,
		'name'          => 'source',
		'label'         => __( 'Content source', 'salt' ),
		'type'          => 'button_group',
		'choices'       => array(
			'default'  => __( 'Default', 'salt' ),
			'taxonomy' => __( 'Filter by term', 'salt' ),
			'manual'   => __( 'Hand-picked', 'salt' ),
		),
		'default_value' => 'default',
	);

	$fields[] = array(
		'key'               => "field_salt_query_{$cpt}_manual",
		'name'              => 'manual',
		'label'             => __( 'Hand-picked items', 'salt' ),
		'type'              => 'relationship',
		'post_type'         => array( $cpt ),
		'filters'           => array( 'search' ),
		'return_format'     => 'id',
		'conditional_logic' => array(
			array(
				array(
					'field'    => $source_key,
					'operator' => '==',
					'value'    => 'manual',
				),
			),
		),
	);

	if ( null !== $taxonomy ) {
		$fields[] = array(
			'key'               => "field_salt_query_{$cpt}_taxonomy_terms",
			'name'              => 'taxonomy_terms',
			'label'             => __( 'Filter by terms', 'salt' ),
			'type'              => 'taxonomy',
			'taxonomy'          => $taxonomy,
			'field_type'        => 'multi_select',
			'return_format'     => 'id',
			'add_term'          => 0,
			'conditional_logic' => array(
				array(
					array(
						'field'    => $source_key,
						'operator' => '==',
						'value'    => 'taxonomy',
					),
				),
			),
		);
	}

	$fields[] = array(
		'key'           => "field_salt_query_{$cpt}_orderby",
		'name'          => 'orderby',
		'label'         => __( 'Order by', 'salt' ),
		'type'          => 'select',
		'choices'       => array(
			''           => __( 'Default', 'salt' ),
			'menu_order' => __( 'Menu order', 'salt' ),
			'date'       => __( 'Date', 'salt' ),
			'title'      => __( 'Title', 'salt' ),
		),
		'default_value' => '',
		'wrapper'       => array( 'width' => '34' ),
	);

	$fields[] = array(
		'key'           => "field_salt_query_{$cpt}_order",
		'name'          => 'order',
		'label'         => __( 'Order', 'salt' ),
		'type'          => 'select',
		'choices'       => array(
			''     => __( 'Default', 'salt' ),
			'ASC'  => __( 'Ascending', 'salt' ),
			'DESC' => __( 'Descending', 'salt' ),
		),
		'default_value' => '',
		'wrapper'       => array( 'width' => '33' ),
	);

	$fields[] = array(
		'key'           => "field_salt_query_{$cpt}_count",
		'name'          => 'count',
		'label'         => __( 'Number of items', 'salt' ),
		'instructions'  => __( '0 uses the section default.', 'salt' ),
		'type'          => 'number',
		'default_value' => 0,
		'min'           => 0,
		'max'           => 24,
		'wrapper'       => array( 'width' => '33' ),
	);

	return $fields;
}
