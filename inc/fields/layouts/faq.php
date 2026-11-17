<?php
/**
 * Flexible Content layout: FAQ / Accordion (spec §10 #13).
 *
 * Layout slug 'faq' is a stability contract (FLEET07) — renaming it is a
 * MAJOR bump + slug-migrations.json entry. CPT-backed: embeds the shared
 * query controls (§10a) for the faq post type.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_faq',
	'name'       => 'faq',
	'label'      => __( 'FAQ / Accordion', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_faq_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_faq_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'           => 'field_salt_faq_structured_data',
				'name'          => 'structuredData',
				'label'         => __( 'FAQ schema', 'salt' ),
				'instructions'  => __( 'Emit FAQPage JSON-LD for these questions (AEO, §15).', 'salt' ),
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
			),
		),
		salt_query_control_fields( 'faq' ),
		salt_section_wrapper_fields( 'faq' )
	),
);
