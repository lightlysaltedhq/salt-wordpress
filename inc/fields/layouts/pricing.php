<?php
/**
 * Flexible Content layout: Pricing (spec §10 #11).
 *
 * Layout slug 'pricing' is a stability contract (FLEET07) — renaming it is
 * a MAJOR bump + slug-migrations.json entry. Content fields first, then
 * the shared §11 Advanced tab.
 *
 * NO SCHEMA in v1: prices are display strings (e.g. "£29", "POA") — the
 * Offer/PriceSpecification structured-data pass is deferred until prices
 * become structured fields.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'key'        => 'layout_salt_pricing',
	'name'       => 'pricing',
	'label'      => __( 'Pricing', 'salt' ),
	'display'    => 'block',
	'sub_fields' => array_merge(
		array(
			array(
				'key'   => 'field_salt_pricing_eyebrow',
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'   => 'field_salt_pricing_heading',
				'name'  => 'heading',
				'label' => __( 'Heading', 'salt' ),
				'type'  => 'text',
			),
			array(
				'key'          => 'field_salt_pricing_plans',
				'name'         => 'plans',
				'label'        => __( 'Plans', 'salt' ),
				'type'         => 'repeater',
				'layout'       => 'block',
				'button_label' => __( 'Add plan', 'salt' ),
				'sub_fields'   => array(
					array(
						'key'      => 'field_salt_pricing_plans_name',
						'name'     => 'name',
						'label'    => __( 'Name', 'salt' ),
						'type'     => 'text',
						'required' => 1,
						'wrapper'  => array( 'width' => '40' ),
					),
					array(
						'key'          => 'field_salt_pricing_plans_price',
						'name'         => 'price',
						'label'        => __( 'Price', 'salt' ),
						'instructions' => __( 'Display text, e.g. £29 or POA.', 'salt' ),
						'type'         => 'text',
						'wrapper'      => array( 'width' => '30' ),
					),
					array(
						'key'          => 'field_salt_pricing_plans_period',
						'name'         => 'period',
						'label'        => __( 'Period', 'salt' ),
						'instructions' => __( 'E.g. /month. Optional.', 'salt' ),
						'type'         => 'text',
						'wrapper'      => array( 'width' => '30' ),
					),
					array(
						'key'          => 'field_salt_pricing_plans_features',
						'name'         => 'features',
						'label'        => __( 'Features', 'salt' ),
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => __( 'Add feature', 'salt' ),
						'sub_fields'   => array(
							array(
								'key'   => 'field_salt_pricing_plans_features_text',
								'name'  => 'text',
								'label' => __( 'Feature', 'salt' ),
								'type'  => 'text',
							),
						),
					),
					array(
						'key'           => 'field_salt_pricing_plans_cta',
						'name'          => 'cta',
						'label'         => __( 'Call to action', 'salt' ),
						'type'          => 'link',
						'return_format' => 'array',
					),
					array(
						'key'           => 'field_salt_pricing_plans_featured',
						'name'          => 'featured',
						'label'         => __( 'Featured plan', 'salt' ),
						'instructions'  => __( 'Highlights this plan ("Most popular"). Only one plan may be featured.', 'salt' ),
						'type'          => 'true_false',
						'ui'            => 1,
						'default_value' => 0,
					),
				),
			),
		),
		salt_section_wrapper_fields( 'pricing' )
	),
);
