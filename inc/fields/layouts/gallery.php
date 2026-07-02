<?php
/**
 * Flexible Content layout: Gallery (spec §10 #16).
 *
 * Layout slug 'gallery' is a stability contract (FLEET07) — renaming it is
 * a MAJOR bump + slug-migrations.json entry. Content fields first, then the
 * shared §11 wrapper Advanced tab. No heading field: the gallery is a media
 * band, never the page's H1 source (the section passes has_heading false).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_gallery',
	'name'       => 'gallery',
	'label'      => __( 'Gallery', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'           => 'field_salt_gallery_layout',
				'name'          => 'layout',
				'label'         => __( 'Layout', 'salt' ),
				'type'          => 'select',
				'choices'       => array(
					'grid'     => __( 'Grid', 'salt' ),
					'masonry'  => __( 'Masonry', 'salt' ),
					'carousel' => __( 'Carousel', 'salt' ),
				),
				'default_value' => 'grid',
			),
			array(
				'key'           => 'field_salt_gallery_images',
				'name'          => 'images',
				'label'         => __( 'Images', 'salt' ),
				'type'          => 'gallery',
				'return_format' => 'id',
			),
			array(
				'key'           => 'field_salt_gallery_columns',
				'name'          => 'columns',
				'label'         => __( 'Columns', 'salt' ),
				'instructions'  => __( 'Grid and Masonry layouts only.', 'salt' ),
				'type'          => 'button_group',
				'choices'       => array(
					2 => __( '2 columns', 'salt' ),
					3 => __( '3 columns', 'salt' ),
					4 => __( '4 columns', 'salt' ),
				),
				'default_value' => 3,
			),
			array(
				'key'           => 'field_salt_gallery_captions',
				'name'          => 'captions',
				'label'         => __( 'Show captions', 'salt' ),
				'instructions'  => __( 'Captions come from each image\'s attachment caption in the media library.', 'salt' ),
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
			),
		),
		salt_section_wrapper_fields( 'gallery' )
	),
);
