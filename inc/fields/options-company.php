<?php
/**
 * Site Settings → Company & Schema (page 1 of the setup walk).
 *
 * Who the organisation is: identity, contact details, address, profiles,
 * review platform, schema identity. Existing field names/keys frozen
 * (FLEET07); company_address is NEW and completes the circuit the Contact
 * section already reads (bug fix).
 *
 * Deliberately NO org rating value/count fields: SEO02 strips
 * aggregateRating from Organization nodes by design — real ratings flow
 * from testimonials to Services.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Company & Schema group.
 *
 * @return void
 */
function salt_register_company_options(): void {
	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_company',
			'title'      => __( 'Company & Schema', 'salt' ),
			'menu_order' => 0,
			'fields'     => array(
				array(
					'key'   => 'field_salt_options_company_details_tab',
					'label' => __( 'Details', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_salt_options_company_org_name',
					'name'         => 'org_name',
					'label'        => __( 'Organisation name', 'salt' ),
					'instructions' => __( 'Used across the site and in structured data. The schema graph only renders once this is set.', 'salt' ),
					'type'         => 'text',
				),
				array(
					'key'   => 'field_salt_options_company_org_legal_name',
					'name'  => 'org_legal_name',
					'label' => __( 'Legal name (optional)', 'salt' ),
					'type'  => 'text',
				),
				array(
					'key'   => 'field_salt_options_company_phone',
					'name'  => 'company_phone',
					'label' => __( 'Phone', 'salt' ),
					'type'  => 'text',
				),
				array(
					'key'           => 'field_salt_options_company_header_show_phone',
					'name'          => 'header_show_phone',
					'label'         => __( 'Show phone in header', 'salt' ),
					'instructions'  => __( 'Shows the phone number above as a call link in the site header.', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
				),
				array(
					'key'   => 'field_salt_options_company_email',
					'name'  => 'company_email',
					'label' => __( 'Email', 'salt' ),
					'type'  => 'email',
				),
				array(
					'key'          => 'field_salt_options_company_address',
					'name'         => 'company_address',
					'label'        => __( 'Postal address', 'salt' ),
					'instructions' => __( 'One part per line. Shown in the contact section and used for LocalBusiness schema on single-location sites.', 'salt' ),
					'type'         => 'textarea',
					'rows'         => 3,
				),
				array(
					'key'   => 'field_salt_options_company_profiles_tab',
					'label' => __( 'Profiles & Reviews', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'        => 'field_salt_options_company_same_as',
					'name'       => 'org_same_as',
					'label'      => __( 'Social & profile URLs (sameAs)', 'salt' ),
					'type'       => 'repeater',
					'sub_fields' => array(
						array(
							'key'   => 'field_salt_options_company_same_as_url',
							'name'  => 'url',
							'label' => __( 'URL', 'salt' ),
							'type'  => 'url',
						),
					),
				),
				array(
					'key'          => 'field_salt_options_company_reviews_provider',
					'name'         => 'reviews_provider',
					'label'        => __( 'Review platform', 'salt' ),
					'instructions' => __( 'Your primary review platform — used for “read our reviews” links and added to the organisation’s sameAs profiles.', 'salt' ),
					'type'         => 'select',
					'choices'      => array(
						''           => __( 'None', 'salt' ),
						'google'     => __( 'Google', 'salt' ),
						'trustpilot' => __( 'Trustpilot', 'salt' ),
						'facebook'   => __( 'Facebook', 'salt' ),
						'yelp'       => __( 'Yelp', 'salt' ),
						'other'      => __( 'Other', 'salt' ),
					),
					'default_value' => '',
				),
				array(
					'key'               => 'field_salt_options_company_reviews_profile_url',
					'name'              => 'reviews_profile_url',
					'label'             => __( 'Review profile URL', 'salt' ),
					'instructions'      => __( 'Public URL of your profile on the platform above.', 'salt' ),
					'type'              => 'url',
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_company_reviews_provider',
								'operator' => '!=',
								'value'    => '',
							),
						),
					),
				),
				array(
					'key'   => 'field_salt_options_company_schema_tab',
					'label' => __( 'Schema', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_salt_options_company_org_type',
					'name'         => 'org_type',
					'label'        => __( 'Organisation type', 'salt' ),
					'instructions' => __( 'LocalBusiness applies only to single-location businesses; sites with Location entries always emit Organization with per-office LocalBusiness nodes.', 'salt' ),
					'type'         => 'select',
					'choices'      => array(
						'organization'   => __( 'Organization (default)', 'salt' ),
						'local_business' => __( 'LocalBusiness (single-location only)', 'salt' ),
					),
					'default_value' => 'organization',
				),
				array(
					'key'           => 'field_salt_options_company_org_logo',
					'name'          => 'org_logo',
					'label'         => __( 'Schema logo', 'salt' ),
					'instructions'  => __( 'The image search engines show for the organisation (square works best). The header logo is set separately under Logo & Fonts.', 'salt' ),
					'type'          => 'image',
					'return_format' => 'url',
				),
			),
			'location'   => salt_options_location( 'salt-settings-company' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_company_options' );
