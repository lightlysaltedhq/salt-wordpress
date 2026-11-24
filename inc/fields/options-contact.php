<?php
/**
 * Site Settings → Contact — every option behind the contact pipeline on one
 * page (spec §9/§13; approved v3 IA): delivery & routing, the form itself,
 * spam protection and storage & retention. Four groups, one per service,
 * ordered by menu_order.
 *
 * Field KEYS and NAMES are frozen (DATA02/FLEET07): this consolidation moved
 * groups between pages and files but changed no storage — values live in
 * wp_options as options_{field_name} regardless of page.
 *
 * Only NON-secrets live here (SEC03): the webhook signing secret
 * (SALT_WEBHOOK_SECRET) and the CAPTCHA secret keys (SALT_TURNSTILE_SECRET /
 * SALT_RECAPTCHA_SECRET) are wp-config constants and never touch the
 * database (SEC11).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Delivery & routing group: recipient, per-type routing,
 * auto-reply and webhook targets (spec §9; DATA02 keys).
 *
 * @return void
 */
function salt_register_options_contact_fields(): void {
	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_contact',
			'title'      => __( 'Delivery & routing', 'salt' ),
			'menu_order' => 0,
			'fields'     => array(
				array(
					'key'   => 'field_salt_options_contact_delivery_tab',
					'label' => __( 'Delivery', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_salt_options_contact_recipient',
					'name'         => 'contact_recipient',
					'label'        => __( 'Enquiry recipient', 'salt' ),
					'instructions' => __( 'Enquiries are emailed here. The contact form only goes live once this is set.', 'salt' ),
					'type'         => 'email',
				),
				array(
					'key'          => 'field_salt_options_contact_routes',
					'name'         => 'contact_routes',
					'label'        => __( 'Routing by enquiry type', 'salt' ),
					'instructions' => __( 'Optional. Enquiries whose type matches a row go to that recipient instead of the default above.', 'salt' ),
					'type'         => 'repeater',
					'button_label' => __( 'Add route', 'salt' ),
					'sub_fields'   => array(
						array(
							'key'   => 'field_salt_options_contact_routes_enquiry_type',
							'name'  => 'enquiry_type',
							'label' => __( 'Enquiry type', 'salt' ),
							'type'  => 'text',
						),
						array(
							'key'          => 'field_salt_options_contact_routes_value',
							'name'         => 'value',
							'label'        => __( 'Value', 'salt' ),
							'instructions' => __( 'Optional. The value the form sends for this type; left empty, it is made from the label. Lowercase letters, numbers and hyphens.', 'salt' ),
							'type'         => 'text',
						),
						array(
							'key'   => 'field_salt_options_contact_routes_recipient',
							'name'  => 'recipient',
							'label' => __( 'Recipient', 'salt' ),
							'type'  => 'email',
						),
					),
				),
				array(
					'key'   => 'field_salt_options_contact_autoreply_tab',
					'label' => __( 'Auto-reply', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'           => 'field_salt_options_contact_autoreply',
					'name'          => 'contact_autoreply',
					'label'         => __( 'Send an auto-reply', 'salt' ),
					'instructions'  => __( 'When on, submitters with a valid email address receive a confirmation email.', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
				),
				array(
					'key'               => 'field_salt_options_contact_autoreply_subject',
					'name'              => 'contact_autoreply_subject',
					'label'             => __( 'Auto-reply subject', 'salt' ),
					'type'              => 'text',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_contact_autoreply',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
				),
				array(
					'key'               => 'field_salt_options_contact_autoreply_body',
					'name'              => 'contact_autoreply_body',
					'label'             => __( 'Auto-reply body', 'salt' ),
					'instructions'      => __( 'Plain text. Placeholders: {name} (submitter name), {site} (site name).', 'salt' ),
					'type'              => 'textarea',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_contact_autoreply',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
				),
				array(
					'key'   => 'field_salt_options_contact_webhooks_tab',
					'label' => __( 'Webhooks', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_salt_options_contact_webhook_targets',
					'name'         => 'webhook_targets',
					'label'        => __( 'Webhook targets', 'salt' ),
					'instructions' => __( 'HTTPS endpoints notified on events. Deliveries are HMAC-signed with the SALT_WEBHOOK_SECRET wp-config constant — nothing is sent until that constant is defined.', 'salt' ),
					'type'         => 'repeater',
					'button_label' => __( 'Add target', 'salt' ),
					'sub_fields'   => array(
						array(
							'key'   => 'field_salt_options_contact_webhook_targets_url',
							'name'  => 'url',
							'label' => __( 'URL', 'salt' ),
							'type'  => 'url',
						),
						array(
							'key'           => 'field_salt_options_contact_webhook_targets_events',
							'name'          => 'events',
							'label'         => __( 'Events', 'salt' ),
							'instructions'  => __( 'Comma-separated event names this target receives.', 'salt' ),
							'type'          => 'text',
							'default_value' => 'contact.submitted',
						),
					),
				),
			),
			'location'   => salt_options_location( 'salt-settings-contact' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_options_contact_fields' );

/**
 * Register the Form group — contact form presentation (PRIV06 code-half:
 * the privacy-notice link rendered at the point of collection).
 *
 * @return void
 */
function salt_register_contact_form_options(): void {
	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_contact_form',
			'title'      => __( 'Form', 'salt' ),
			'menu_order' => 10,
			'fields'     => array(
				array(
					'key'           => 'field_salt_options_contact_form_privacy_page',
					'name'          => 'contact_privacy_page',
					'label'         => __( 'Privacy policy page', 'salt' ),
					'instructions'  => __( 'Linked from the contact form (“see our privacy policy”). Transparency at the point of collection — strongly recommended. Policy CONTENT is the site’s responsibility (see docs/PRIVACY-RESPONSIBILITIES.md).', 'salt' ),
					'type'          => 'post_object',
					'post_type'     => array( 'page' ),
					'return_format' => 'id',
				),
				array(
					'key'           => 'field_salt_options_contact_form_phone',
					'name'          => 'contact_form_phone',
					'label'         => __( 'Ask for a phone number', 'salt' ),
					'instructions'  => __( 'A phone number is personal data: ask for one only when you will use it. It is stored with the enquiry and in its export, and deleted with it.', 'salt' ),
					'type'          => 'select',
					'choices'       => array(
						'off'      => __( 'No', 'salt' ),
						'optional' => __( 'Yes, optional', 'salt' ),
						'required' => __( 'Yes, required', 'salt' ),
					),
					'default_value' => 'off',
					'return_format' => 'value',
				),
			),
			'location'   => salt_options_location( 'salt-settings-contact' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_contact_form_options' );

/**
 * Register the Spam protection group — CAPTCHA provider choice + PUBLIC site
 * keys only (spec §13; review PRIV02). SEC03: SECRET keys are wp-config
 * constants (SALT_TURNSTILE_SECRET / SALT_RECAPTCHA_SECRET), never options.
 *
 * @return void
 */
function salt_register_captcha_options_fields(): void {
	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_captcha',
			'title'      => __( 'Spam protection', 'salt' ),
			'menu_order' => 20,
			'fields'     => array(
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
			'location'   => salt_options_location( 'salt-settings-contact' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_captcha_options_fields' );

/**
 * Register the Storage & retention group — enquiry submission storage (spec
 * §9; SEC09/PRIV03). Site-configurable non-secrets only (DATA03): whether
 * enquiries are stored at all (off by default — data minimisation;
 * email/webhook delivery is the primary channel) and how long stored records
 * are retained before the daily purge deletes them.
 *
 * @return void
 */
function salt_register_options_fields_submissions(): void {
	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_submissions',
			'title'      => __( 'Storage & retention', 'salt' ),
			'menu_order' => 30,
			'fields'     => array(
				array(
					'key'           => 'field_salt_options_submissions_store',
					'name'          => 'submissions_store',
					'label'         => __( 'Store enquiries in the database', 'salt' ),
					'instructions'  => __( 'Off by default (data minimisation): enquiries are delivered by email/webhook and not kept. Turn on only if you need an in-admin record — stored enquiries contain personal data and are purged automatically after the retention period.', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
				),
				array(
					'key'               => 'field_salt_options_submissions_retention_days',
					'name'              => 'submissions_retention_days',
					'label'             => __( 'Retention (days)', 'salt' ),
					'instructions'      => __( 'Stored enquiries older than this are deleted by the daily purge.', 'salt' ),
					'type'              => 'number',
					'default_value'     => 90,
					'min'               => 1,
					'max'               => 730,
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_submissions_store',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
				),
			),
			'location'   => salt_options_location( 'salt-settings-contact' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_options_fields_submissions' );
