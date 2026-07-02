<?php
/**
 * Analytics options: PostHog + GA4 (spec v2 analytics; DATA02 keys).
 *
 * Everything here is a PUBLIC, site-configurable non-secret (a PostHog
 * project API key and a GA4 measurement ID are both public by design), so
 * ACF options storage is fine — no SEC03 constant needed. Everything is OFF
 * by default: an unconfigured site emits no analytics output of any kind.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Analytics option field group ('acf/init').
 *
 * @return void
 */
function salt_register_analytics_options_fields(): void {
	salt_register_field_group(
		array(
			'key'      => 'group_salt_options_analytics',
			'title'    => __( 'Analytics', 'salt' ),
			'fields'   => array(
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
					'key'          => 'field_salt_options_analytics_posthog_key',
					'name'         => 'posthog_key',
					'label'        => __( 'PostHog project API key', 'salt' ),
					'instructions' => __( 'The phc_… project API key (Settings → Project). This key is public by design — it can only ingest events, never read data.', 'salt' ),
					'type'         => 'text',
				),
				array(
					'key'           => 'field_salt_options_analytics_posthog_host',
					'name'          => 'posthog_host',
					'label'         => __( 'PostHog host', 'salt' ),
					'type'          => 'select',
					'choices'       => array(
						'us'     => __( 'US Cloud (us.i.posthog.com)', 'salt' ),
						'eu'     => __( 'EU Cloud (eu.i.posthog.com)', 'salt' ),
						'custom' => __( 'Custom (reverse proxy)', 'salt' ),
					),
					'default_value' => 'us',
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
								'field'    => 'field_salt_options_analytics_posthog_host',
								'operator' => '==',
								'value'    => 'custom',
							),
						),
					),
				),
				array(
					'key'           => 'field_salt_options_analytics_posthog_session_recording',
					'name'          => 'posthog_session_recording',
					'label'         => __( 'Session recording', 'salt' ),
					'instructions'  => __( 'Off by default. Review your privacy policy before enabling — recordings capture visitor interactions.', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
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
					'key'          => 'field_salt_options_analytics_ga4_id',
					'name'         => 'ga4_id',
					'label'        => __( 'GA4 measurement ID', 'salt' ),
					'instructions' => __( 'The G-XXXXXXXXXX measurement ID. Invalid IDs are skipped (and logged) — nothing loads.', 'salt' ),
					'type'         => 'text',
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
add_action( 'acf/init', 'salt_register_analytics_options_fields' );
