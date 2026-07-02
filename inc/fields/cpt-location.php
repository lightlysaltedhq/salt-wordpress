<?php
/**
 * Location CPT content fields (spec Appendix A.6).
 *
 * The opening_hours row shape is a FROZEN storage contract shared with
 * salt_location_status() and the Locations section's schema builder:
 * day ('monday'..'sunday' lowercase English keys), open/close ('HH:MM'
 * 24h — time_picker return_format H:i), appointment_only (bool). Multiple
 * rows per day express split hours. geo is 'lat,lng' text — unused in v1
 * (maps deferred), stored now so content survives the map enhancement.
 *
 * Keys follow field_salt_location_{name} (DATA02). Gated behind the
 * 'location' module flag (§10b).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the location field group.
 *
 * @return void
 */
function salt_register_location_fields(): void {
	if ( ! salt_module_enabled( 'location' ) ) {
		return;
	}

	salt_register_field_group(
		array(
			'key'      => 'group_salt_location',
			'title'    => __( 'Location details', 'salt' ),
			'fields'   => array(
				array(
					'key'        => 'field_salt_location_address',
					'name'       => 'address',
					'label'      => __( 'Address', 'salt' ),
					'type'       => 'group',
					'layout'     => 'row',
					'sub_fields' => array(
						array(
							'key'   => 'field_salt_location_address_line1',
							'name'  => 'line1',
							'label' => __( 'Line 1', 'salt' ),
							'type'  => 'text',
						),
						array(
							'key'   => 'field_salt_location_address_line2',
							'name'  => 'line2',
							'label' => __( 'Line 2', 'salt' ),
							'type'  => 'text',
						),
						array(
							'key'   => 'field_salt_location_address_city',
							'name'  => 'city',
							'label' => __( 'City / town', 'salt' ),
							'type'  => 'text',
						),
						array(
							'key'   => 'field_salt_location_address_region',
							'name'  => 'region',
							'label' => __( 'County / region', 'salt' ),
							'type'  => 'text',
						),
						array(
							'key'   => 'field_salt_location_address_postcode',
							'name'  => 'postcode',
							'label' => __( 'Postcode', 'salt' ),
							'type'  => 'text',
						),
						array(
							'key'   => 'field_salt_location_address_country',
							'name'  => 'country',
							'label' => __( 'Country', 'salt' ),
							'type'  => 'text',
						),
					),
				),
				array(
					'key'          => 'field_salt_location_geo',
					'name'         => 'geo',
					'label'        => __( 'Coordinates', 'salt' ),
					'instructions' => __( 'Optional "lat,lng" — only used by the (deferred) map variant.', 'salt' ),
					'type'         => 'text',
				),
				array(
					'key'   => 'field_salt_location_phone',
					'name'  => 'phone',
					'label' => __( 'Phone', 'salt' ),
					'type'  => 'text',
				),
				array(
					'key'   => 'field_salt_location_email',
					'name'  => 'email',
					'label' => __( 'Email', 'salt' ),
					'type'  => 'email',
				),
				array(
					'key'          => 'field_salt_location_opening_hours',
					'name'         => 'opening_hours',
					'label'        => __( 'Opening hours', 'salt' ),
					'instructions' => __( 'One row per span; add two rows for the same day for split hours. Leave empty to hide the open/closed status.', 'salt' ),
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => __( 'Add hours', 'salt' ),
					'sub_fields'   => array(
						array(
							'key'           => 'field_salt_location_opening_hours_day',
							'name'          => 'day',
							'label'         => __( 'Day', 'salt' ),
							'type'          => 'select',
							'choices'       => array(
								'monday'    => __( 'Monday', 'salt' ),
								'tuesday'   => __( 'Tuesday', 'salt' ),
								'wednesday' => __( 'Wednesday', 'salt' ),
								'thursday'  => __( 'Thursday', 'salt' ),
								'friday'    => __( 'Friday', 'salt' ),
								'saturday'  => __( 'Saturday', 'salt' ),
								'sunday'    => __( 'Sunday', 'salt' ),
							),
							'default_value' => 'monday',
						),
						array(
							'key'               => 'field_salt_location_opening_hours_open',
							'name'              => 'open',
							'label'             => __( 'Opens', 'salt' ),
							'type'              => 'time_picker',
							'display_format'    => 'H:i',
							'return_format'     => 'H:i',
							'conditional_logic' => array(
								array(
									array(
										'field'    => 'field_salt_location_opening_hours_appointment_only',
										'operator' => '!=',
										'value'    => '1',
									),
								),
							),
						),
						array(
							'key'               => 'field_salt_location_opening_hours_close',
							'name'              => 'close',
							'label'             => __( 'Closes', 'salt' ),
							'type'              => 'time_picker',
							'display_format'    => 'H:i',
							'return_format'     => 'H:i',
							'conditional_logic' => array(
								array(
									array(
										'field'    => 'field_salt_location_opening_hours_appointment_only',
										'operator' => '!=',
										'value'    => '1',
									),
								),
							),
						),
						array(
							'key'           => 'field_salt_location_opening_hours_appointment_only',
							'name'          => 'appointment_only',
							'label'         => __( 'Appointment only', 'salt' ),
							'type'          => 'true_false',
							'ui'            => 1,
							'default_value' => 0,
						),
					),
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'location',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'salt_register_location_fields' );
