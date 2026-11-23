<?php
/**
 * Site Settings → Advanced (page 7 — implementer territory, touched once).
 *
 * Module availability switches (§10b). Field names module_{slug} frozen
 * (FLEET07); defaults stay enabled, except the client and sector records
 * (salt_modules_off_by_default()), which a site switches on. Feature modules (dark_mode,
 * display_preferences) are AVAILABILITY gates — the editor-facing on/off
 * switches live on Brand & Design (two-level gating pattern).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module slugs presented under the "Feature modules" tab (everything else
 * lands under "Content modules"). Unknown future slugs default to Content.
 *
 * @return string[]
 */
function salt_feature_module_slugs(): array {
	return array( 'dark_mode', 'display_preferences' );
}

/**
 * Register the Modules group (dynamic over salt_modules(), two tabs).
 *
 * @return void
 */
function salt_register_modules_options(): void {
	$feature_slugs = salt_feature_module_slugs();

	$content_fields = array(
		array(
			'key'     => 'field_salt_options_modules_content_tab',
			'label'   => __( 'Content modules', 'salt' ),
			'type'    => 'tab',
		),
	);
	$feature_fields = array(
		array(
			'key'   => 'field_salt_options_modules_feature_tab',
			'label' => __( 'Feature modules', 'salt' ),
			'type'  => 'tab',
		),
	);

	foreach ( salt_modules() as $slug => $label ) {
		$field = array(
			'key'           => 'field_salt_options_modules_' . $slug,
			'name'          => 'module_' . $slug,
			'label'         => $label,
			'type'          => 'true_false',
			'ui'            => 1,
			'default_value' => 1,
		);

		if ( in_array( $slug, salt_modules_off_by_default(), true ) ) {
			$field['default_value'] = 0;
			$field['instructions']  = __( 'Off by default. On: case studies choose from a list of records. Off: they keep the name as text. Switching never changes a stored name or choice.', 'salt' );
		}

		if ( in_array( $slug, $feature_slugs, true ) ) {
			$field['instructions'] = __( 'Availability gate. The editor-facing on/off switch lives on Brand & Design.', 'salt' );
			$feature_fields[]      = $field;
		} else {
			$content_fields[] = $field;
		}
	}

	salt_register_field_group(
		array(
			'key'         => 'group_salt_options_modules',
			'title'       => __( 'Modules', 'salt' ),
			'menu_order'  => 0,
			'description' => __( 'Switching a module off removes its post type, sections and assets everywhere on this site.', 'salt' ),
			'fields'      => array_merge( $content_fields, $feature_fields ),
			'location'    => salt_options_location( 'salt-settings-advanced' ),
		)
	);
}
add_action( 'acf/init', 'salt_register_modules_options' );
