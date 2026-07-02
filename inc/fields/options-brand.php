<?php
/**
 * Site Settings → Brand & Design (page 2 of the setup walk).
 *
 * The palette + appearance behaviour. The admin contrast checker (§13/§14)
 * enqueues on THIS screen only and hooks the three colour pickers.
 * Existing names frozen: brand_primary/secondary/accent, dark_mode.
 *
 * Two-level gating pattern (mirrors dark mode): module_{slug} on Advanced =
 * availability; the toggles here = editor on/off. display_preferences_enabled
 * defaults 0 (§14: the widget is off by default).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Brand & Design groups.
 *
 * @return void
 */
function salt_register_brand_options(): void {
	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_brand',
			'title'      => __( 'Brand & Design', 'salt' ),
			'menu_order' => 0,
			'fields'     => array(
				array(
					'key'   => 'field_salt_options_brand_colours_tab',
					'label' => __( 'Colours', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_salt_options_brand_primary',
					'name'         => 'brand_primary',
					'label'        => __( 'Primary colour', 'salt' ),
					'instructions' => __( 'The whole palette is derived from this colour (shade ramps, text pairings, focus ring). Contrast-safe text is derived automatically — the checker below validates the pairs that matter in light and dark.', 'salt' ),
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
					'key'   => 'field_salt_options_brand_appearance_tab',
					'label' => __( 'Appearance', 'salt' ),
					'type'  => 'tab',
				),
				array(
					'key'           => 'field_salt_options_brand_dark_mode',
					'name'          => 'dark_mode',
					'label'         => __( 'Enable dark mode', 'salt' ),
					'instructions'  => __( 'When on, the site follows the visitor\'s colour-scheme preference (semantic token inversion). Only available while the Dark mode module is enabled (Site Settings → Advanced).', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
				),
				array(
					'key'           => 'field_salt_options_brand_reduce_motion',
					'name'          => 'reduce_motion',
					'label'         => __( 'Reduce motion by default', 'salt' ),
					'instructions'  => __( 'Serve the reduced-motion experience to all visitors by default. Visitors can change this via Display preferences; OS reduced-motion settings are always honoured regardless.', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
				),
			),
			'location'   => salt_options_location( 'salt-settings-brand' ),
		)
	);

	salt_register_field_group(
		array(
			'key'        => 'group_salt_options_display',
			'title'      => __( 'Display preferences', 'salt' ),
			'menu_order' => 10,
			'fields'     => array(
				array(
					'key'           => 'field_salt_options_display_preferences_enabled',
					'name'          => 'display_preferences_enabled',
					'label'         => __( 'Show the Display preferences widget', 'salt' ),
					'instructions'  => __( 'Front-end controls for font size, contrast, grayscale, dark mode and motion. Not an accessibility overlay — the site is fully accessible with it off.', 'salt' ),
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
				),
				array(
					'key'               => 'field_salt_options_display_controls',
					'name'              => 'display_controls',
					'label'             => __( 'Controls to offer', 'salt' ),
					'instructions'      => __( 'The dark-mode control only appears when dark mode is enabled above.', 'salt' ),
					'type'              => 'checkbox',
					'choices'           => array(
						'font_size' => __( 'Font size', 'salt' ),
						'contrast'  => __( 'High contrast', 'salt' ),
						'grayscale' => __( 'Grayscale', 'salt' ),
						'dark_mode' => __( 'Dark mode', 'salt' ),
						'motion'    => __( 'Reduce motion', 'salt' ),
					),
					'default_value'     => array( 'font_size', 'contrast', 'grayscale', 'dark_mode', 'motion' ),
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_salt_options_display_preferences_enabled',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
				),
			),
			'location'   => salt_options_location( 'salt-settings-brand' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_brand_options' );
