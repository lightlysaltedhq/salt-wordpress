<?php
/**
 * Flexible Content layout: Blog Teaser (spec §10 #17) — CPT-backed ✦.
 *
 * Content fields first, then the shared query controls
 * (salt_query_control_fields('post') — §10a: latest / by category /
 * hand-pick), then the shared §11 wrapper Advanced tab. Layout slug
 * 'blog_teaser' is a stability contract (FLEET07) — renaming it is a
 * MAJOR bump + slug-migrations.json entry.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_blog_teaser',
	'name'       => 'blog_teaser',
	'label'      => __( 'Blog Teaser', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_blog_teaser_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_blog_teaser_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'           => 'field_salt_blog_teaser_columns',
				'name'          => 'columns',
				'label'         => __( 'Columns', 'salt' ),
				'type'          => 'button_group',
				'choices'       => array(
					2 => __( '2 columns', 'salt' ),
					3 => __( '3 columns', 'salt' ),
				),
				'default_value' => 3,
			),
			array(
				'key'           => 'field_salt_blog_teaser_view_all',
				'name'          => 'view_all',
				'label'         => __( '"View all" link', 'salt' ),
				'instructions'  => __( 'Link to the posts page. Sections never paginate (§6) — raise the item count or send visitors to the blog.', 'salt' ),
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
			),
		),
		salt_query_control_fields( 'post' ),
		salt_section_wrapper_fields( 'blog_teaser' )
	),
);
