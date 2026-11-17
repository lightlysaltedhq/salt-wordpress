<?php
/**
 * Flexible Content layout: Media + Text (spec §10 #6).
 *
 * Layout slug 'media-text' (formerly 'media_text', renamed to the
 * salt-contract section id in 6.0.0) is a stability contract (FLEET07) — renaming it
 * is a MAJOR bump + slug-migrations.json entry. Content fields first
 * (mode + rows repeater), then the shared §11 wrapper Advanced tab.
 *
 * mode:
 *  - 'single'      each row's own media_side field decides the media column.
 *  - 'alternating' sides flip automatically per row (even rows media-left,
 *    odd rows media-right, 0-indexed); each row's media_side is IGNORED —
 *    see the section partial docblock.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_media_text',
	'name'       => 'media-text',
	'label'      => __( 'Media + Text', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'           => 'field_salt_media_text_mode',
				'name'          => 'mode',
				'label'         => __( 'Mode', 'salt' ),
				'instructions'  => __( 'Alternating flips the media side automatically per row and ignores each row\'s "Media side" setting.', 'salt' ),
				'type'          => 'select',
				'choices'       => array(
					'single'      => __( 'Single (per-row media side)', 'salt' ),
					'alternating' => __( 'Alternating (auto zigzag)', 'salt' ),
				),
				'default_value' => 'single',
			),
			array(
				'key'        => 'field_salt_media_text_rows',
				'name'       => 'rows',
				'label'      => __( 'Rows', 'salt' ),
				'type'       => 'repeater',
				'layout'     => 'block',
				'sub_fields' => array(
					array(
						'key'           => 'field_salt_media_text_row_image',
						'name'          => 'image',
						'label'         => __( 'Image', 'salt' ),
						'type'          => 'image',
						'return_format' => 'id',
					),
					array(
						'key'   => 'field_salt_media_text_row_heading',
						'name'  => 'heading',
						'label' => __( 'Heading', 'salt' ),
						'type'  => 'text',
					),
					array(
						'key'          => 'field_salt_media_text_row_content',
						'name'         => 'content',
						'label'        => __( 'Content', 'salt' ),
						'type'         => 'wysiwyg',
						'tabs'         => 'visual',
						'toolbar'      => 'full',
						'media_upload' => 0,
					),
					array(
						'key'           => 'field_salt_media_text_row_media_side',
						'name'          => 'media_side',
						'label'         => __( 'Media side', 'salt' ),
						'instructions'  => __( 'Used in Single mode only — Alternating mode sets the side automatically.', 'salt' ),
						'type'          => 'select',
						'choices'       => array(
							'left'  => __( 'Left', 'salt' ),
							'right' => __( 'Right', 'salt' ),
						),
						'default_value' => 'left',
					),
					array(
						'key'           => 'field_salt_media_text_row_cta',
						'name'          => 'cta',
						'label'         => __( 'Call to action', 'salt' ),
						'type'          => 'link',
						'return_format' => 'array',
						'required'      => 0,
					),
				),
			),
		),
		salt_section_wrapper_fields( 'media_text' )
	),
);
