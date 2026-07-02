<?php
/**
 * Maps options — site-configurable NON-secrets (DATA02/DATA03; SEC03: no
 * secrets in the database — a paid tile provider key belongs in the tile
 * URL only if the provider designs it as a public, referrer-locked token).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Maps option fields ('acf/init').
 *
 * @return void
 */
function salt_register_maps_options_fields(): void {
	salt_register_field_group(
		array(
			'key'      => 'group_salt_options_maps',
			'title'    => __( 'Maps', 'salt' ),
			'fields'   => array(
				array(
					'key'           => 'field_salt_options_maps_enabled',
					'name'          => 'maps_enabled',
					'label'         => __( 'Enable maps', 'salt' ),
					'instructions'  => __( 'Maps are click-to-load and consent-gated: nothing loads from the tile provider until a visitor clicks "Load map" and has granted marketing/maps consent.', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
				),
				array(
					'key'           => 'field_salt_options_maps_tile_url',
					'name'          => 'maps_tile_url',
					'label'         => __( 'Raster tile URL template', 'salt' ),
					'instructions'  => __( 'An https URL template with {z}/{x}/{y} placeholders. The default OpenStreetMap tile server\'s usage policy does not permit heavy commercial use — for a fleet of commercial sites, configure a paid raster tile provider here instead.', 'salt' ),
					'type'          => 'text',
					'default_value' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
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
add_action( 'acf/init', 'salt_register_maps_options_fields' );
