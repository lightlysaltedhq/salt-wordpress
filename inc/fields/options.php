<?php
/**
 * Options pages + global fields: Brand & Design, Company/Schema, Modules
 * (spec §13; DATA02 keys).
 *
 * Registered on acf/init (fires only when ACF is active — DEP01 guard is
 * structural). Values live in the DATABASE (wp_options) — see the
 * what-travels-where table in docs/contracts/DATA-BOUNDARIES.md (DATA03).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the theme options pages.
 *
 * @return void
 */
function salt_register_options_pages(): void {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}

	acf_add_options_page(
		array(
			'page_title' => __( 'Site Settings', 'salt' ),
			'menu_title' => __( 'Site Settings', 'salt' ),
			'menu_slug'  => 'salt-settings',
			'capability' => 'manage_options',
			'redirect'   => false,
		)
	);
}
add_action( 'acf/init', 'salt_register_options_pages' );

/**
 * Register the global option field groups.
 *
 * @return void
 */
function salt_register_options_fields(): void {
	// Brand & Design.
	salt_register_field_group(
		array(
			'key'      => 'group_salt_options_brand',
			'title'    => __( 'Brand & Design', 'salt' ),
			'fields'   => array(
				array(
					'key'          => 'field_salt_options_brand_primary',
					'name'         => 'brand_primary',
					'label'        => __( 'Primary colour', 'salt' ),
					'instructions' => __( 'The whole palette is derived from this colour (shade ramps, text pairings, focus ring). Contrast-safe text is derived automatically.', 'salt' ),
					'type'         => 'color_picker',
				),
				array(
					'key'   => 'field_salt_options_brand_secondary',
					'name'  => 'brand_secondary',
					'label' => __( 'Secondary colour (optional)', 'salt' ),
					'type'  => 'color_picker',
				),
				array(
					'key'   => 'field_salt_options_brand_accent',
					'name'  => 'brand_accent',
					'label' => __( 'Accent colour (optional)', 'salt' ),
					'type'  => 'color_picker',
				),
				array(
					'key'           => 'field_salt_options_brand_dark_mode',
					'name'          => 'dark_mode',
					'label'         => __( 'Enable dark mode', 'salt' ),
					'instructions'  => __( 'When on, the site follows the visitor\'s colour-scheme preference (semantic token inversion).', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
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

	// Company & Schema.
	salt_register_field_group(
		array(
			'key'      => 'group_salt_options_company',
			'title'    => __( 'Company & Schema', 'salt' ),
			'fields'   => array(
				array(
					'key'   => 'field_salt_options_company_org_name',
					'name'  => 'org_name',
					'label' => __( 'Organisation name', 'salt' ),
					'type'  => 'text',
				),
				array(
					'key'   => 'field_salt_options_company_org_legal_name',
					'name'  => 'org_legal_name',
					'label' => __( 'Legal name (optional)', 'salt' ),
					'type'  => 'text',
				),
				array(
					'key'           => 'field_salt_options_company_org_logo',
					'name'          => 'org_logo',
					'label'         => __( 'Schema logo', 'salt' ),
					'type'          => 'image',
					'return_format' => 'url',
				),
				array(
					'key'   => 'field_salt_options_company_phone',
					'name'  => 'company_phone',
					'label' => __( 'Phone', 'salt' ),
					'type'  => 'text',
				),
				array(
					'key'   => 'field_salt_options_company_email',
					'name'  => 'company_email',
					'label' => __( 'Email', 'salt' ),
					'type'  => 'email',
				),
				array(
					'key'        => 'field_salt_options_company_same_as',
					'name'       => 'org_same_as',
					'label'      => __( 'Profiles (sameAs URLs)', 'salt' ),
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

	// Modules on/off (§10b).
	$module_fields = array();
	foreach ( salt_modules() as $slug => $label ) {
		$module_fields[] = array(
			'key'           => 'field_salt_options_modules_' . $slug,
			'name'          => 'module_' . $slug,
			'label'         => $label,
			'type'          => 'true_false',
			'ui'            => 1,
			'default_value' => 1,
		);
	}

	salt_register_field_group(
		array(
			'key'      => 'group_salt_options_modules',
			'title'    => __( 'Modules', 'salt' ),
			'fields'   => $module_fields,
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
add_action( 'acf/init', 'salt_register_options_fields' );
