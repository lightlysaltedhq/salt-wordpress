<?php
/**
 * The "sections" Flexible Content container (spec §5).
 *
 * Layouts are contributed by inc/fields/layouts/*.php (one file per
 * section — the §17 recipe). Registered for pages; the container itself
 * never changes when sections are added (FLEET07: layout slugs are a
 * stability contract checked by CI).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the page-builder Flexible Content field group.
 *
 * @return void
 */
function salt_register_sections_field(): void {
	$layouts = salt_section_layouts();

	salt_register_field_group(
		array(
			'key'      => 'group_salt_sections',
			'title'    => __( 'Page sections', 'salt' ),
			'fields'   => array(
				array(
					'key'          => 'field_salt_sections_container',
					'name'         => 'sections',
					'label'        => __( 'Sections', 'salt' ),
					'type'         => 'flexible_content',
					'button_label' => __( 'Add section', 'salt' ),
					'layouts'      => $layouts,
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'page',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'salt_register_sections_field' );
