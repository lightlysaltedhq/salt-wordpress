<?php
/**
 * Flexible Content layout: Rich Text (spec §10 #3).
 *
 * Layout slug 'rich-text' (formerly 'rich_text', renamed to the salt-contract
 * section id in 6.0.0) is a stability contract (FLEET07) — renaming it
 * is a MAJOR bump + slug-migrations.json entry.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_rich_text',
	'name'       => 'rich-text',
	'label'      => __( 'Rich Text', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_rich_text_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'          => 'field_salt_rich_text_content',
				'name'         => 'content',
				'label'        => __( 'Content', 'salt' ),
				'type'         => 'wysiwyg',
				'tabs'         => 'visual',
				'toolbar'      => 'full',
				'media_upload' => 0,
			),
		),
		salt_section_wrapper_fields( 'rich_text' )
	),
);
