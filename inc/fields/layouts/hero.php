<?php
/**
 * Flexible Content layout: Hero (spec §10 #1).
 *
 * Layout slug 'hero' is a stability contract (FLEET07) — renaming it is a
 * MAJOR bump + slug-migrations.json entry.
 *
 * DEFERRAL NOTE (§3 locked decisions): the §10 spec row allows media
 * "image OR video"; video is ABSENT in this build (no embeds, no video —
 * PRIV01 posture). The hero is image-only in v1; a video sub-field is a
 * post-v1 addition, not a rename.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_hero',
	'name'       => 'hero',
	'label'      => __( 'Hero', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'           => 'field_salt_hero_variant',
				'name'          => 'variant',
				'label'         => __( 'Variant', 'salt' ),
				'type'          => 'select',
				'choices'       => array(
					'full-bleed' => __( 'Full bleed (edge-to-edge image)', 'salt' ),
					'split'      => __( 'Split (text beside image)', 'salt' ),
					'minimal'    => __( 'Minimal (text only)', 'salt' ),
				),
				'default_value' => 'full-bleed',
			),
			array(
				'key'   => 'field_salt_hero_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'          => 'field_salt_hero_heading',
				'name'         => 'heading',
				'label'        => __( 'Heading', 'salt' ),
				'instructions' => __( 'Usually the page\'s main (H1) heading.', 'salt' ),
				'type'         => 'text',
			),
			array(
				'key'   => 'field_salt_hero_subheading',
				'name'  => 'subheading',
				'label' => __( 'Subheading', 'salt' ),
				'type'  => 'textarea',
				'rows'  => 3,
			),
			array(
				'key'           => 'field_salt_hero_image',
				'name'          => 'image',
				'label'         => __( 'Image', 'salt' ),
				'instructions'  => __( 'The page\'s largest image — loaded eagerly. Ignored by the Minimal variant.', 'salt' ),
				'type'          => 'image',
				'return_format' => 'id',
				'preview_size'  => 'medium',
			),
			array(
				'key'           => 'field_salt_hero_cta_primary',
				'name'          => 'cta_primary',
				'label'         => __( 'Primary call to action', 'salt' ),
				'type'          => 'link',
				'return_format' => 'array',
			),
			array(
				'key'           => 'field_salt_hero_cta_secondary',
				'name'          => 'cta_secondary',
				'label'         => __( 'Secondary call to action', 'salt' ),
				'type'          => 'link',
				'return_format' => 'array',
			),
		),
		salt_section_wrapper_fields( 'hero' )
	),
);
