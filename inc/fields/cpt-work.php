<?php
/**
 * ACF content fields for the work CPT (spec Appendix A.4; DATA02 keys).
 *
 * group_salt_work — client, summary, gallery (IDs), challenge, solution,
 * results (stat + label repeater → x-stat), services (relationship →
 * service), live_url. Section-level query controls deliberately live on
 * the consuming section, not here (Appendix A preamble, §10a).
 *
 * DATA01: these field NAMES are the parity surface the case_study→work
 * importer maps the live theme's keys onto — renaming any of them is a
 * MAJOR bump + slug-migrations.json entry (FLEET07).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the work content field group (module-gated, acf/init).
 *
 * @return void
 */
function salt_register_work_fields(): void {
	if ( ! salt_module_enabled( 'work' ) ) {
		return;
	}

	salt_register_field_group(
		array(
			'key'      => 'group_salt_work',
			'title'    => __( 'Work details', 'salt' ),
			'fields'   => array(
				array(
					'key'   => 'field_salt_work_client',
					'name'  => 'client',
					'label' => __( 'Client', 'salt' ),
					'type'  => 'text',
				),
				array(
					'key'          => 'field_salt_work_summary',
					'name'         => 'summary',
					'label'        => __( 'Summary', 'salt' ),
					'instructions' => __( 'One or two sentences used on cards and meta descriptions.', 'salt' ),
					'type'         => 'textarea',
					'rows'         => 3,
					'new_lines'    => '',
				),
				array(
					'key'           => 'field_salt_work_gallery',
					'name'          => 'gallery',
					'label'         => __( 'Gallery', 'salt' ),
					'type'          => 'gallery',
					'return_format' => 'id',
				),
				array(
					'key'          => 'field_salt_work_challenge',
					'name'         => 'challenge',
					'label'        => __( 'The challenge', 'salt' ),
					'type'         => 'wysiwyg',
					'tabs'         => 'visual',
					'toolbar'      => 'full',
					'media_upload' => 0,
				),
				array(
					'key'          => 'field_salt_work_solution',
					'name'         => 'solution',
					'label'        => __( 'The solution', 'salt' ),
					'type'         => 'wysiwyg',
					'tabs'         => 'visual',
					'toolbar'      => 'full',
					'media_upload' => 0,
				),
				array(
					'key'          => 'field_salt_work_results',
					'name'         => 'results',
					'label'        => __( 'Results / metrics', 'salt' ),
					'instructions' => __( 'e.g. stat "+45%" with label "conversions".', 'salt' ),
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => __( 'Add result', 'salt' ),
					'sub_fields'   => array(
						array(
							'key'   => 'field_salt_work_results_stat',
							'name'  => 'stat',
							'label' => __( 'Stat', 'salt' ),
							'type'  => 'text',
						),
						array(
							'key'   => 'field_salt_work_results_label',
							'name'  => 'label',
							'label' => __( 'Label', 'salt' ),
							'type'  => 'text',
						),
					),
				),
				array(
					'key'           => 'field_salt_work_services',
					'name'          => 'services',
					'label'         => __( 'Services used', 'salt' ),
					'type'          => 'relationship',
					'post_type'     => array( 'service' ),
					'filters'       => array( 'search' ),
					'return_format' => 'id',
				),
				array(
					'key'   => 'field_salt_work_live_url',
					'name'  => 'live_url',
					'label' => __( 'External URL', 'salt' ),
					'type'  => 'url',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'work',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'salt_register_work_fields' );
