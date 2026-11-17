<?php
/**
 * Flexible Content layout: Call to Action (spec §10 #10).
 *
 * Layout slug 'call-to-action' (formerly 'cta', renamed to the salt-contract
 * section id in 6.0.0) is a stability contract (FLEET07) — renaming it is a
 * MAJOR bump + slug-migrations.json entry. Content fields first, then the
 * shared §11 Advanced tab.
 *
 * DEFERRED (compliance phase): the spec's 'newsletter' variant (variant
 * select + newsletter embed field). No email input may ship while nothing
 * can process the submission (PRIV01 posture) — adding the variant later
 * is additive (new fields only), not a slug migration.
 *
 * The shared tone wrapper control defaults to 'brand-tint'
 * for this layout (the CTA band is the one section that wants to stand
 * out by default); every other wrapper control keeps its shared default.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_call_to_action',
	'name'       => 'call-to-action',
	'label'      => __( 'Call to Action', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_call_to_action_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_call_to_action_text',
				'name'  => 'text',
				'label' => __( 'Text', 'salt' ),
				'type'  => 'textarea',
				'rows'  => 3,
			),
			array(
				'key'          => 'field_salt_call_to_action_buttons',
				'name'         => 'buttons',
				'label'        => __( 'Buttons', 'salt' ),
				'type'         => 'repeater',
				'layout'       => 'table',
				'max'          => 2,
				'button_label' => __( 'Add button', 'salt' ),
				'sub_fields'   => array(
					array(
						'key'      => 'field_salt_call_to_action_buttons_label',
						'name'     => 'label',
						'label'    => __( 'Label', 'salt' ),
						'type'     => 'text',
						'required' => 1,
					),
					array(
						'key'      => 'field_salt_call_to_action_buttons_url',
						'name'     => 'url',
						'label'    => __( 'URL', 'salt' ),
						'type'     => 'url',
						'required' => 1,
					),
					array(
						'key'           => 'field_salt_call_to_action_buttons_style',
						'name'          => 'style',
						'label'         => __( 'Style', 'salt' ),
						'type'          => 'select',
						'choices'       => array(
							'primary'   => __( 'Primary', 'salt' ),
							'secondary' => __( 'Secondary', 'salt' ),
							'ghost'     => __( 'Ghost', 'salt' ),
						),
						'default_value' => 'primary',
					),
				),
			),
		),
		array_map(
			static function ( array $field ): array {
				if ( 'tone' === ( $field['name'] ?? '' ) ) {
					$field['default_value'] = 'brand-tint';
				}
				return $field;
			},
			salt_section_wrapper_fields( 'call_to_action' )
		)
	),
);
