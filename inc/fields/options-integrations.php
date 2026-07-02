<?php
/**
 * Site Settings → Integrations — every third-party touchpoint on one page
 * (spec v2 analytics, §13; approved v3 IA): analytics, maps and booking.
 * Three groups, one per service, ordered by menu_order.
 *
 * Field KEYS and NAMES are frozen (DATA02/FLEET07): this consolidation moved
 * groups between pages and files but changed no storage — values live in
 * wp_options as options_{field_name} regardless of page.
 *
 * Everything here is a site-configurable NON-secret (SEC03/DATA03): PostHog
 * project keys and GA4 measurement IDs are public by design, the tile URL
 * carries at most a public referrer-locked token, and the booking URL is a
 * public page address. Everything is OFF by default and consent-gated: an
 * unconfigured site emits no third-party output of any kind (PRIV01).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Analytics group — PostHog + GA4 (spec v2 analytics; DATA02
 * keys). Both integrations follow the Consent Mode pattern: cookieless until
 * the visitor grants statistics consent.
 *
 * @return void
 */
function salt_register_analytics_options_fields(): void {
	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_analytics',
			'title'      => __( 'Analytics', 'salt' ),
			'menu_order' => 0,
			'fields'     => array(
				array(
					'key'   => 'field_salt_options_analytics_posthog_tab',
					'label' => __( 'PostHog', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'           => 'field_salt_options_analytics_posthog_enabled',
					'name'          => 'posthog_enabled',
					'label'         => __( 'Enable PostHog', 'salt' ),
					'instructions'  => __( 'PostHog is bundled first-party (no CDN). It starts in cookieless mode and only sets cookies after the visitor grants statistics consent — this requires "Cookieless server hash mode" to be enabled in the PostHog project settings.', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
				),
				array(
					'key'               => 'field_salt_options_analytics_posthog_key',
					'name'              => 'posthog_key',
					'label'             => __( 'PostHog project API key', 'salt' ),
					'instructions'      => __( 'The phc_… project API key (Settings → Project). This key is public by design — it can only ingest events, never read data.', 'salt' ),
					'type'              => 'text',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_analytics_posthog_enabled',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
				),
				array(
					'key'               => 'field_salt_options_analytics_posthog_host',
					'name'              => 'posthog_host',
					'label'             => __( 'PostHog host', 'salt' ),
					'type'              => 'select',
					'choices'           => array(
						'us'     => __( 'US Cloud (us.i.posthog.com)', 'salt' ),
						'eu'     => __( 'EU Cloud (eu.i.posthog.com)', 'salt' ),
						'custom' => __( 'Custom (reverse proxy)', 'salt' ),
					),
					'default_value'     => 'us',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_analytics_posthog_enabled',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
				),
				array(
					'key'               => 'field_salt_options_analytics_posthog_host_custom',
					'name'              => 'posthog_host_custom',
					'label'             => __( 'Custom PostHog endpoint', 'salt' ),
					'instructions'      => __( 'Full URL of your reverse-proxy ingestion endpoint, e.g. https://ph.example.com — required when host is set to Custom.', 'salt' ),
					'type'              => 'url',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_analytics_posthog_enabled',
								'operator' => '==',
								'value'    => '1',
							),
							array(
								'field'    => 'field_salt_options_analytics_posthog_host',
								'operator' => '==',
								'value'    => 'custom',
							),
						),
					),
				),
				array(
					'key'               => 'field_salt_options_analytics_posthog_session_recording',
					'name'              => 'posthog_session_recording',
					'label'             => __( 'Session recording', 'salt' ),
					'instructions'      => __( 'Off by default. Review your privacy policy before enabling — recordings capture visitor interactions.', 'salt' ),
					'type'              => 'true_false',
					'ui'                => 1,
					'default_value'     => 0,
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_analytics_posthog_enabled',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
				),
				array(
					'key'   => 'field_salt_options_analytics_ga4_tab',
					'label' => __( 'Google Analytics', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'           => 'field_salt_options_analytics_ga4_enabled',
					'name'          => 'ga4_enabled',
					'label'         => __( 'Enable Google Analytics 4', 'salt' ),
					'instructions'  => __( 'Loads gtag.js with Consent Mode v2: all storage denied by default until the visitor consents (cookieless pings only).', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
				),
				array(
					'key'               => 'field_salt_options_analytics_ga4_id',
					'name'              => 'ga4_id',
					'label'             => __( 'GA4 measurement ID', 'salt' ),
					'instructions'      => __( 'The G-XXXXXXXXXX measurement ID. Invalid IDs are skipped (and logged) — nothing loads.', 'salt' ),
					'type'              => 'text',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_analytics_ga4_enabled',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
				),
			),
			'location'   => salt_options_location( 'salt-settings-integrations' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_analytics_options_fields' );

/**
 * Register the Maps group (DATA02/DATA03; SEC03: no secrets in the database
 * — a paid tile provider key belongs in the tile URL only if the provider
 * designs it as a public, referrer-locked token).
 *
 * @return void
 */
function salt_register_maps_options_fields(): void {
	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_maps',
			'title'      => __( 'Maps', 'salt' ),
			'menu_order' => 10,
			'fields'     => array(
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
					'key'               => 'field_salt_options_maps_tile_url',
					'name'              => 'maps_tile_url',
					'label'             => __( 'Raster tile URL template', 'salt' ),
					'instructions'      => __( 'An https URL template with {z}/{x}/{y} placeholders. The default OpenStreetMap tile server\'s usage policy does not permit heavy commercial use — for a fleet of commercial sites, configure a paid raster tile provider here instead.', 'salt' ),
					'type'              => 'text',
					'default_value'     => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_maps_enabled',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
				),
			),
			'location'   => salt_options_location( 'salt-settings-integrations' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_maps_options_fields' );

/**
 * Register the Booking group (DATA02/DATA03). The booking URL is a public
 * page address; provider API keys (not needed by this integration) would be
 * wp-config constants, never options (SEC03). No conditional display: the
 * x-booking plain-link fallback uses booking_url even with provider none.
 *
 * @return void
 */
function salt_register_booking_options_fields(): void {
	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_booking',
			'title'      => __( 'Booking', 'salt' ),
			'menu_order' => 20,
			'fields'     => array(
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
			'location'   => salt_options_location( 'salt-settings-integrations' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_booking_options_fields' );
