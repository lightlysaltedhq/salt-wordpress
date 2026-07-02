<?php
/**
 * Booking options — site-configurable NON-secrets (DATA02/DATA03). The
 * booking URL is a public page address; provider API keys (not needed by
 * this integration) would be wp-config constants, never options (SEC03).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Booking option fields ('acf/init').
 *
 * @return void
 */
function salt_register_booking_options_fields(): void {
	salt_register_field_group(
		array(
			'key'      => 'group_salt_options_booking',
			'title'    => __( 'Booking', 'salt' ),
			'fields'   => array(
				array(
					'key'           => 'field_salt_options_booking_provider',
					'name'          => 'booking_provider',
					'label'         => __( 'Booking provider', 'salt' ),
					'instructions'  => __( 'The x-booking component renders a plain link to your booking page; with marketing consent it upgrades to the provider\'s inline widget on click.', 'salt' ),
					'type'          => 'select',
					'choices'       => array(
						''         => __( 'None', 'salt' ),
						'calcom'   => __( 'Cal.com', 'salt' ),
						'calendly' => __( 'Calendly', 'salt' ),
					),
					'default_value' => '',
					'allow_null'    => 0,
				),
				array(
					'key'          => 'field_salt_options_booking_url',
					'name'         => 'booking_url',
					'label'        => __( 'Booking page URL', 'salt' ),
					'instructions' => __( 'The event/booking page URL on the provider\'s domain, e.g. https://calendly.com/you/intro or https://cal.com/you/intro.', 'salt' ),
					'type'         => 'url',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'salt-settings',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'salt_register_booking_options_fields' );
