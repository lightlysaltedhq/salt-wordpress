<?php
/**
 * Flexible Content layout: Logo / Trust Strip (spec §10 #9).
 *
 * Layout slug 'logos' is a stability contract (FLEET07) — renaming it is a
 * MAJOR bump + slug-migrations.json entry. Content fields first, then the
 * shared §11 Advanced tab.
 *
 * This layout is also the designated home for "as featured in / press"
 * strips (§18: a separate press section is deliberately NOT shipped —
 * use this one with press logos + URLs).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_logos',
	'name'       => 'logos',
	'label'      => __( 'Logo / Trust Strip', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'          => 'field_salt_logos_heading',
				'name'         => 'heading',
				'label'        => __( 'Heading', 'salt' ),
				'instructions' => __( 'Optional — logo strips usually run without one (e.g. leave empty, or "As featured in").', 'salt' ),
				'type'         => 'text',
			),
			array(
				'key'          => 'field_salt_logos_logos',
				'name'         => 'logos',
				'label'        => __( 'Logos', 'salt' ),
				'type'         => 'repeater',
				'layout'       => 'table',
				'button_label' => __( 'Add logo', 'salt' ),
				'sub_fields'   => array(
					array(
						'key'           => 'field_salt_logos_logos_image',
						'name'          => 'image',
						'label'         => __( 'Logo image', 'salt' ),
						'type'          => 'image',
						'return_format' => 'id',
						'required'      => 1,
						'wrapper'       => array( 'width' => '30' ),
					),
					array(
						'key'          => 'field_salt_logos_logos_url',
						'name'         => 'url',
						'label'        => __( 'Link (optional)', 'salt' ),
						'instructions' => __( 'e.g. the client site or the press article.', 'salt' ),
						'type'         => 'url',
						'wrapper'      => array( 'width' => '40' ),
					),
					array(
						'key'          => 'field_salt_logos_logos_name',
						'name'         => 'name',
						'label'        => __( 'Name', 'salt' ),
						'instructions' => __( 'Used as the image alt text when the attachment has none.', 'salt' ),
						'type'         => 'text',
						'wrapper'      => array( 'width' => '30' ),
					),
				),
			),
			array(
				'key'           => 'field_salt_logos_greyscale',
				'name'          => 'greyscale',
				'label'         => __( 'Greyscale logos', 'salt' ),
				'instructions'  => __( 'Renders logos desaturated, regaining colour on hover.', 'salt' ),
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
			),
		),
		salt_section_wrapper_fields( 'logos' )
	),
);
