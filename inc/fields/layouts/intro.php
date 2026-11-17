<?php
/**
 * Flexible Content layout: Intro / Lead (spec §10 #2).
 *
 * Layout slug 'intro' is a stability contract (FLEET07) — renaming it is a
 * MAJOR bump + slug-migrations.json entry. Width is NOT a content field:
 * it comes from the shared §11 wrapper (width).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_intro',
	'name'       => 'intro',
	'label'      => __( 'Intro / Lead', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_intro_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_intro_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'          => 'field_salt_intro_content',
				'name'         => 'content',
				'label'        => __( 'Content', 'salt' ),
				'type'         => 'wysiwyg',
				'tabs'         => 'visual',
				'toolbar'      => 'full',
				'media_upload' => 0,
			),
			array(
				'key'           => 'field_salt_intro_align',
				'name'          => 'align',
				'label'         => __( 'Alignment', 'salt' ),
				'type'          => 'select',
				'choices'       => array(
					'left'   => __( 'Left', 'salt' ),
					'center' => __( 'Centred', 'salt' ),
				),
				'default_value' => 'left',
			),
		),
		salt_section_wrapper_fields( 'intro' )
	),
);
