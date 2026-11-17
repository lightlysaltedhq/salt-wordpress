<?php
/**
 * Flexible Content layout: Contact (spec §10 #19) — MARKUP + FIELDS ONLY.
 *
 * Layout slug 'contact' is a stability contract (FLEET07) — renaming it is
 * a MAJOR bump + slug-migrations.json entry. Content fields first, then
 * the shared §11 Advanced tab.
 *
 * The contact DETAIL VALUES are not fields here: they come from the
 * Company options (company_phone / company_email / org_name — DATA03,
 * single source of truth); this layout only toggles which ones show.
 *
 * NO map fields: maps are absent from this build (deferred with the
 * MapLibre integration — §9). NO form-routing fields either: live
 * submission is deferred to the compliance phase (SEC01/SEC02/PRIV06);
 * see the section partial's salt_contact_form_enabled wiring point.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_contact',
	'name'       => 'contact',
	'label'      => __( 'Contact', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_contact_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_contact_intro',
				'name'  => 'intro',
				'label' => __( 'Intro text', 'salt' ),
				'type'  => 'textarea',
				'rows'  => 3,
			),
			array(
				'key'           => 'field_salt_contact_show_phone',
				'name'          => 'showPhone',
				'label'         => __( 'Show phone', 'salt' ),
				'instructions'  => __( 'The number comes from Site Settings → Company & Schema.', 'salt' ),
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
				'wrapper'       => array( 'width' => '33' ),
			),
			array(
				'key'           => 'field_salt_contact_show_email',
				'name'          => 'showEmail',
				'label'         => __( 'Show email', 'salt' ),
				'instructions'  => __( 'The address comes from Site Settings → Company & Schema.', 'salt' ),
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
				'wrapper'       => array( 'width' => '33' ),
			),
			array(
				'key'           => 'field_salt_contact_show_address',
				'name'          => 'showAddress',
				'label'         => __( 'Show address', 'salt' ),
				'instructions'  => __( 'The address comes from Site Settings → Company & Schema.', 'salt' ),
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
				'wrapper'       => array( 'width' => '34' ),
			),
			array(
				'key'          => 'field_salt_contact_form_intro',
				'name'         => 'formIntro',
				'label'        => __( 'Form intro', 'salt' ),
				'instructions' => __( 'Short copy above the form (or above the email button while the form is not live).', 'salt' ),
				'type'         => 'textarea',
				'rows'         => 3,
			),
		),
		salt_section_wrapper_fields( 'contact' )
	),
);
