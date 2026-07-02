<?php
/**
 * Site Settings → Logo & Fonts (page 3 of the setup walk).
 *
 * Uploaded identity assets. All fields NEW (additive, FLEET07-clean).
 * Display logos are distinct from the schema logo (Company & Schema) —
 * no silent coupling between the two. Fonts are SELF-HOSTED media-library
 * uploads (nothing loads from a font CDN — consent contract); empty
 * repeaters keep the compiled system-font defaults (FAIL03).
 *
 * Runtime consumers: x-logo component (logo_*), inc/services/fonts.php
 * (font_*).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The font-file repeater sub-fields (shared shape for heading/body).
 *
 * @param string $scope 'heading_files'|'body_files'.
 * @param int    $default_weight Default weight for the slot.
 * @return array<int,array<string,mixed>>
 */
function salt_font_files_sub_fields( string $scope, int $default_weight ): array {
	$weights = array();
	foreach ( range( 100, 900, 100 ) as $weight ) {
		$weights[ $weight ] = (string) $weight;
	}

	return array(
		array(
			'key'           => 'field_salt_options_fonts_' . $scope . '_file',
			'name'          => 'file',
			'label'         => __( 'Font file', 'salt' ),
			'type'          => 'file',
			'return_format' => 'id',
			'mime_types'    => 'woff2,woff,ttf',
		),
		array(
			'key'           => 'field_salt_options_fonts_' . $scope . '_weight',
			'name'          => 'weight',
			'label'         => __( 'Weight', 'salt' ),
			'type'          => 'select',
			'choices'       => $weights,
			'default_value' => $default_weight,
		),
		array(
			'key'           => 'field_salt_options_fonts_' . $scope . '_style',
			'name'          => 'style',
			'label'         => __( 'Style', 'salt' ),
			'type'          => 'select',
			'choices'       => array(
				'normal' => __( 'Normal', 'salt' ),
				'italic' => __( 'Italic', 'salt' ),
			),
			'default_value' => 'normal',
		),
	);
}

/**
 * Register the Logo and Fonts groups.
 *
 * @return void
 */
function salt_register_logo_fonts_options(): void {
	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_logo',
			'title'      => __( 'Logo', 'salt' ),
			'menu_order' => 0,
			'fields'     => array(
				array(
					'key'           => 'field_salt_options_logo_light',
					'name'          => 'logo_light',
					'label'         => __( 'Logo', 'salt' ),
					'instructions'  => __( 'Header logo on light surfaces. SVG or transparent PNG recommended. The logo used in search-engine structured data is set separately under Company & Schema.', 'salt' ),
					'type'          => 'image',
					'return_format' => 'id',
				),
				array(
					'key'           => 'field_salt_options_logo_dark',
					'name'          => 'logo_dark',
					'label'         => __( 'Dark-mode logo (optional)', 'salt' ),
					'instructions'  => __( 'Used when dark mode is active; falls back to the main logo.', 'salt' ),
					'type'          => 'image',
					'return_format' => 'id',
				),
				array(
					'key'           => 'field_salt_options_logo_height',
					'name'          => 'logo_height',
					'label'         => __( 'Logo height', 'salt' ),
					'instructions'  => __( 'Rendered height in the header; width scales automatically.', 'salt' ),
					'type'          => 'number',
					'default_value' => 48,
					'min'           => 16,
					'max'           => 200,
					'append'        => 'px',
				),
				array(
					'key'          => 'field_salt_options_logo_text_fallback',
					'name'         => 'logo_text_fallback',
					'label'        => __( 'Text fallback', 'salt' ),
					'instructions' => __( 'Styled text shown in the header when no logo is uploaded; falls back to the site title.', 'salt' ),
					'type'         => 'text',
				),
			),
			'location'   => salt_options_location( 'salt-settings-logo-fonts' ),
		)
	);

	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_fonts',
			'title'      => __( 'Fonts', 'salt' ),
			'menu_order' => 10,
			'fields'     => array(
				array(
					'key'   => 'field_salt_options_fonts_heading_tab',
					'label' => __( 'Heading font', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_salt_options_fonts_heading_files',
					'name'         => 'font_heading_files',
					'label'        => __( 'Heading font files', 'salt' ),
					'instructions' => __( 'Leave empty to use the theme default. One row per file; files are self-hosted — nothing loads from a font CDN.', 'salt' ),
					'type'         => 'repeater',
					'button_label' => __( 'Add font file', 'salt' ),
					'sub_fields'   => salt_font_files_sub_fields( 'heading_files', 700 ),
				),
				array(
					'key'   => 'field_salt_options_fonts_body_tab',
					'label' => __( 'Body font', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_salt_options_fonts_body_files',
					'name'         => 'font_body_files',
					'label'        => __( 'Body font files', 'salt' ),
					'instructions' => __( 'Leave empty to use the theme default.', 'salt' ),
					'type'         => 'repeater',
					'button_label' => __( 'Add font file', 'salt' ),
					'sub_fields'   => salt_font_files_sub_fields( 'body_files', 400 ),
				),
				array(
					'key'   => 'field_salt_options_fonts_loading_tab',
					'label' => __( 'Loading', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'           => 'field_salt_options_fonts_format',
					'name'          => 'font_format',
					'label'         => __( 'Preferred format', 'salt' ),
					'instructions'  => __( 'When the same weight is uploaded in multiple formats, this one is listed first.', 'salt' ),
					'type'          => 'select',
					'choices'       => array(
						'woff2' => __( 'woff2 (recommended)', 'salt' ),
						'woff'  => __( 'woff', 'salt' ),
						'ttf'   => __( 'ttf', 'salt' ),
					),
					'default_value' => 'woff2',
				),
			),
			'location'   => salt_options_location( 'salt-settings-logo-fonts' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_logo_fonts_options' );
