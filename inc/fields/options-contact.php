<?php
/**
 * Contact & delivery options: recipient, per-type routing, auto-reply and
 * webhook targets (spec §9; DATA02 keys).
 *
 * Registered on acf/init (fires only when ACF is active — DEP01 guard is
 * structural). Only NON-secrets live here: the webhook signing secret is
 * the SALT_WEBHOOK_SECRET wp-config constant and never touches the
 * database (SEC03/SEC11).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Contact options field group.
 *
 * @return void
 */
function salt_register_options_contact_fields(): void {
	salt_register_field_group(
		array(
			'key'      => 'group_salt_options_contact',
			'title'    => __( 'Contact & Delivery', 'salt' ),
			'fields'   => array(
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
							'key'   => 'field_salt_options_contact_routes_recipient',
							'name'  => 'recipient',
							'label' => __( 'Recipient', 'salt' ),
							'type'  => 'email',
						),
					),
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
					'key'   => 'field_salt_options_contact_autoreply_subject',
					'name'  => 'contact_autoreply_subject',
					'label' => __( 'Auto-reply subject', 'salt' ),
					'type'  => 'text',
				),
				array(
					'key'          => 'field_salt_options_contact_autoreply_body',
					'name'         => 'contact_autoreply_body',
					'label'        => __( 'Auto-reply body', 'salt' ),
					'instructions' => __( 'Plain text. Placeholders: {name} (submitter name), {site} (site name).', 'salt' ),
					'type'         => 'textarea',
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
add_action( 'acf/init', 'salt_register_options_contact_fields' );
