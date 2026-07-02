<?php
/**
 * CAPTCHA options — provider choice + PUBLIC site keys only (spec §13;
 * DATA02 keys; review PRIV02).
 *
 * SEC03: SECRET keys are wp-config constants — SALT_TURNSTILE_SECRET /
 * SALT_RECAPTCHA_SECRET — and are NEVER options: nothing secret is stored
 * in the database, exported with it, or exposed to anyone with options
 * access. The site key is the provider's public identifier and is safe in
 * wp_options.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the CAPTCHA tab on the Site Settings page ('acf/init').
 *
 * @return void
 */
function salt_register_captcha_options_fields(): void {
	salt_register_field_group(
		array(
			'key'      => 'group_salt_options_captcha',
			'title'    => __( 'Contact form protection', 'salt' ),
			'fields'   => array(
				array(
					'key'   => 'field_salt_options_captcha_tab',
					'label' => __( 'CAPTCHA', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'           => 'field_salt_options_captcha_provider',
					'name'          => 'captcha_provider',
					'label'         => __( 'CAPTCHA provider', 'salt' ),
					'instructions'  => __( 'Cloudflare Turnstile is recommended: it is free, privacy-preserving and works without tracking visitors. Google reCAPTCHA sends visitor data to Google — check your privacy policy covers it before enabling. With no provider, the form falls back to a simple arithmetic question. The provider\'s SECRET key must be defined in wp-config.php (SALT_TURNSTILE_SECRET or SALT_RECAPTCHA_SECRET) — it is never stored here.', 'salt' ),
					'type'          => 'select',
					'choices'       => array(
						''          => __( 'None (arithmetic question only)', 'salt' ),
						'turnstile' => __( 'Cloudflare Turnstile (recommended)', 'salt' ),
						'recaptcha' => __( 'Google reCAPTCHA v2', 'salt' ),
					),
					'default_value' => '',
					'allow_null'    => 0,
				),
				array(
					'key'               => 'field_salt_options_captcha_turnstile_site_key',
					'name'              => 'captcha_turnstile_site_key',
					'label'             => __( 'Turnstile site key', 'salt' ),
					'instructions'      => __( 'The PUBLIC site key from the Cloudflare dashboard. The secret key goes in wp-config.php as SALT_TURNSTILE_SECRET.', 'salt' ),
					'type'              => 'text',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_captcha_provider',
								'operator' => '==',
								'value'    => 'turnstile',
							),
						),
					),
				),
				array(
					'key'               => 'field_salt_options_captcha_recaptcha_site_key',
					'name'              => 'captcha_recaptcha_site_key',
					'label'             => __( 'reCAPTCHA site key', 'salt' ),
					'instructions'      => __( 'The PUBLIC site key from the Google reCAPTCHA console. The secret key goes in wp-config.php as SALT_RECAPTCHA_SECRET.', 'salt' ),
					'type'              => 'text',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_captcha_provider',
								'operator' => '==',
								'value'    => 'recaptcha',
							),
						),
					),
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
add_action( 'acf/init', 'salt_register_captcha_options_fields' );
