<?php
/**
 * Service CPT content fields (spec Appendix A.1; DATA02 keys).
 *
 * Group 'group_salt_service', registered on acf/init via
 * salt_register_field_group() and gated behind the 'service' module (§10b).
 * Section-level query controls are NOT here — they live on the consuming
 * section via salt_query_control_fields('service') (§10a).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the service content field group.
 *
 * @return void
 */
function salt_register_service_fields(): void {
	if ( ! salt_module_enabled( 'service' ) ) {
		return;
	}

	salt_register_field_group(
		array(
			'key'      => 'group_salt_service',
			'title'    => __( 'Service details', 'salt' ),
			'fields'   => array(
				array(
					'key'          => 'field_salt_service_icon',
					'name'         => 'icon',
					'label'        => __( 'Icon', 'salt' ),
					'instructions' => __( 'Bundled icon name (heroicon-style, e.g. "star") rendered via x-icon on cards.', 'salt' ),
					'type'         => 'text',
				),
				array(
					'key'          => 'field_salt_service_summary',
					'name'         => 'summary',
					'label'        => __( 'Short summary', 'salt' ),
					'instructions' => __( 'One-liner used on cards.', 'salt' ),
					'type'         => 'textarea',
					'rows'         => 2,
					'new_lines'    => '',
				),
				array(
					'key'          => 'field_salt_service_headline',
					'name'         => 'headline',
					'label'        => __( 'Headline', 'salt' ),
					'instructions' => __( 'Optional H1 override on the single service page.', 'salt' ),
					'type'         => 'text',
				),
				array(
					'key'          => 'field_salt_service_benefits',
					'name'         => 'benefits',
					'label'        => __( 'Key benefits', 'salt' ),
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => __( 'Add benefit', 'salt' ),
					'sub_fields'   => array(
						array(
							'key'   => 'field_salt_service_benefit',
							'name'  => 'benefit',
							'label' => __( 'Benefit', 'salt' ),
							'type'  => 'text',
						),
					),
				),
				array(
					'key'          => 'field_salt_service_price_from',
					'name'         => 'price_from',
					'label'        => __( 'Price from', 'salt' ),
					'instructions' => __( 'Optional "from £X" figure.', 'salt' ),
					'type'         => 'number',
					'min'          => 0,
				),
				array(
					'key'           => 'field_salt_service_related_services',
					'name'          => 'related_services',
					'label'         => __( 'Related services', 'salt' ),
					'instructions'  => __( 'Cross-sell on the single service page.', 'salt' ),
					'type'          => 'relationship',
					'post_type'     => array( 'service' ),
					'filters'       => array( 'search' ),
					'return_format' => 'id',
				),
				array(
					'key'           => 'field_salt_service_cta',
					'name'          => 'cta',
					'label'         => __( 'Call to action', 'salt' ),
					'type'          => 'link',
					'return_format' => 'array',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'service',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'salt_register_service_fields' );
