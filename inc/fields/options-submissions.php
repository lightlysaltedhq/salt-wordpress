<?php
/**
 * Options: enquiry submission storage (spec §9; SEC09/PRIV03).
 *
 * Site-configurable non-secrets only (DATA03): whether enquiries are stored
 * at all (off by default — data minimisation; email/webhook delivery is the
 * primary channel) and how long stored records are retained before the
 * daily purge deletes them (PRIV03).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Submissions option fields (DATA02 keys).
 *
 * @return void
 */
function salt_register_options_fields_submissions(): void {
	salt_register_field_group(
		array(
			'key'      => 'group_salt_options_submissions',
			'title'    => __( 'Submissions', 'salt' ),
			'fields'   => array(
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
					'key'           => 'field_salt_options_submissions_retention_days',
					'name'          => 'submissions_retention_days',
					'label'         => __( 'Retention (days)', 'salt' ),
					'instructions'  => __( 'Stored enquiries older than this are deleted by the daily purge.', 'salt' ),
					'type'          => 'number',
					'default_value' => 90,
					'min'           => 1,
					'max'           => 730,
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
add_action( 'acf/init', 'salt_register_options_fields_submissions' );
