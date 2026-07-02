<?php
/**
 * ACF-as-code framework + the ACF Pro boot guard (spec §3/§5.4; review
 * Blocker DEP01, DATA02).
 *
 * ACF Pro is the theme's single hard runtime dependency. Every code path
 * that touches an ACF function goes through the guards here: when ACF is
 * absent or below the pinned minimum the theme shows a blocking admin
 * notice and renders safe fallbacks — it NEVER fatals (DEP01).
 *
 * Field registration convention (DATA02 — keys are a stability contract,
 * FLEET07): groups 'group_salt_{scope}', fields 'field_salt_{scope}_{name}'.
 * Registration modules live in inc/fields/*.php and hook 'acf/init'
 * (which only fires when ACF is active — inherently guarded). Flexible
 * Content layouts live in inc/fields/layouts/*.php, each returning a
 * layout definition array (see salt_section_layouts()).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimum known-good ACF Pro version for this theme release (DEP01/DEP05).
 * Recorded per release in docs/DEPENDENCY-MATRIX.md.
 */
define( 'SALT_MIN_ACF_VERSION', '6.3.0' );

/**
 * Whether ACF Pro's code-registration API is available.
 *
 * @return bool
 */
function salt_acf_active(): bool {
	return function_exists( 'acf_add_local_field_group' );
}

/**
 * Whether the active ACF meets the pinned minimum version.
 *
 * @return bool True when ACF is active and at/above SALT_MIN_ACF_VERSION.
 */
function salt_acf_version_ok(): bool {
	if ( ! salt_acf_active() || ! defined( 'ACF_VERSION' ) ) {
		return salt_acf_active();
	}
	return version_compare( (string) ACF_VERSION, SALT_MIN_ACF_VERSION, '>=' );
}

/**
 * The DEP01 notice copy for a given ACF state (pure — unit-tested).
 *
 * @param bool   $active     Whether ACF's registration API exists.
 * @param bool   $version_ok Whether the pinned minimum is met.
 * @param string $version    Active ACF version ('' when unknown).
 * @return string|null Message, or null when everything is fine.
 */
function salt_acf_notice_message( bool $active, bool $version_ok, string $version ): ?string {
	if ( $active && $version_ok ) {
		return null;
	}

	if ( $active ) {
		return sprintf(
			/* translators: 1: minimum ACF Pro version. 2: active version. */
			__( 'salt requires ACF Pro %1$s or newer (%2$s is active). Sections and options are disabled until ACF Pro is updated.', 'salt' ),
			SALT_MIN_ACF_VERSION,
			'' !== $version ? $version : '?'
		);
	}

	return __( 'salt requires ACF Pro, which is not active. The site renders safe fallbacks (no sections, no options-driven config) until ACF Pro is installed and activated — nothing will fatal, but pages composed of sections will be empty.', 'salt' );
}

/**
 * Blocking admin notice when ACF Pro is absent or outdated (DEP01).
 *
 * @return void
 */
function salt_acf_admin_notice(): void {
	$message = salt_acf_notice_message(
		salt_acf_active(),
		salt_acf_version_ok(),
		defined( 'ACF_VERSION' ) ? (string) ACF_VERSION : ''
	);

	if ( null === $message ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
		esc_html__( 'salt theme:', 'salt' ),
		esc_html( $message )
	);
}
add_action( 'admin_notices', 'salt_acf_admin_notice' );

/**
 * Register a field group with key-convention enforcement (DATA02).
 *
 * Thin wrapper over acf_add_local_field_group() that, under debug, asserts
 * the group key follows 'group_salt_{scope}' and every field key follows
 * 'field_salt_' — key drift orphans DB content invisibly, so drift fails
 * loud in staging.
 *
 * @param array<string,mixed> $group Field group definition.
 * @return void
 */
function salt_register_field_group( array $group ): void {
	if ( ! salt_acf_active() ) {
		return;
	}

	if ( salt_is_debug() ) {
		$key = (string) ( $group['key'] ?? '' );
		if ( ! str_starts_with( $key, 'group_salt_' ) ) {
			salt_log( sprintf( 'field group key "%s" violates the group_salt_{scope} convention (DATA02).', $key ) );
		}
		salt_assert_field_keys( $group['fields'] ?? array() );
	}

	acf_add_local_field_group( $group );
}

/**
 * Recursively assert field-key convention (debug aid, DATA02).
 *
 * @param array<int,array<string,mixed>> $fields Field definitions.
 * @return void
 */
function salt_assert_field_keys( array $fields ): void {
	foreach ( $fields as $field ) {
		$key = (string) ( $field['key'] ?? '' );
		if ( '' !== $key && ! str_starts_with( $key, 'field_salt_' ) ) {
			salt_log( sprintf( 'field key "%s" violates the field_salt_{scope}_{name} convention (DATA02).', $key ) );
		}
		if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			salt_assert_field_keys( $field['sub_fields'] );
		}
		if ( isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout ) {
				if ( isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					salt_assert_field_keys( $layout['sub_fields'] );
				}
			}
		}
	}
}

/**
 * The shared §11 wrapper controls every section layout embeds (collapsed
 * into an "Advanced" tab — spec §5a).
 *
 * Field NAMES are identical across layouts (section_spacing, …); field
 * KEYS are namespaced per section (DATA02/FLEET07).
 *
 * @param string $section Section slug (underscored), e.g. 'rich_text'.
 * @return array<int,array<string,mixed>>
 */
function salt_section_wrapper_fields( string $section ): array {
	$key = static fn( string $name ): string => 'field_salt_' . $section . '_' . $name;

	return array(
		array(
			'key'   => $key( 'advanced_tab' ),
			'label' => __( 'Advanced', 'salt' ),
			'type'  => 'tab',
		),
		array(
			'key'           => $key( 'section_spacing' ),
			'name'          => 'section_spacing',
			'label'         => __( 'Spacing', 'salt' ),
			'type'          => 'select',
			'choices'       => array(
				'none' => __( 'None', 'salt' ),
				'sm'   => __( 'Small', 'salt' ),
				'md'   => __( 'Medium', 'salt' ),
				'lg'   => __( 'Large', 'salt' ),
			),
			'default_value' => 'md',
		),
		array(
			'key'           => $key( 'section_background' ),
			'name'          => 'section_background',
			'label'         => __( 'Background', 'salt' ),
			'type'          => 'select',
			'choices'       => array(
				'surface'     => __( 'Default surface', 'salt' ),
				'surface-alt' => __( 'Subtle tint', 'salt' ),
				'brand-tint'  => __( 'Brand tint', 'salt' ),
				'inverse'     => __( 'Inverse (dark)', 'salt' ),
			),
			'default_value' => 'surface',
		),
		array(
			'key'           => $key( 'section_container' ),
			'name'          => 'section_container',
			'label'         => __( 'Width', 'salt' ),
			'type'          => 'select',
			'choices'       => array(
				'full'    => __( 'Full bleed', 'salt' ),
				'wide'    => __( 'Wide', 'salt' ),
				'default' => __( 'Default', 'salt' ),
				'narrow'  => __( 'Narrow', 'salt' ),
			),
			'default_value' => 'default',
		),
		array(
			'key'          => $key( 'section_anchor' ),
			'name'         => 'section_anchor',
			'label'        => __( 'Anchor ID', 'salt' ),
			'instructions' => __( 'Optional, for in-page links. Sanitised automatically; duplicates get a numeric suffix.', 'salt' ),
			'type'         => 'text',
		),
		array(
			'key'           => $key( 'section_divider' ),
			'name'          => 'section_divider',
			'label'         => __( 'Divider line', 'salt' ),
			'type'          => 'true_false',
			'ui'            => 1,
			'default_value' => 0,
		),
	);
}

/**
 * Collect the Flexible Content layout definitions.
 *
 * Each file in inc/fields/layouts/*.php returns ONE layout definition
 * array (name, label, sub_fields). Adding a section (§17) means adding a
 * layout file + a section partial — no changes to the container or loop.
 *
 * @return array<int,array<string,mixed>>
 */
function salt_section_layouts(): array {
	$layouts = array();
	$files   = glob( get_template_directory() . '/inc/fields/layouts/*.php' );

	foreach ( false === $files ? array() : $files as $file ) {
		$layout = include $file;
		if ( is_array( $layout ) && isset( $layout['name'] ) ) {
			$layouts[] = $layout;
		} else {
			salt_log( sprintf( 'layout file "%s" did not return a valid layout array.', basename( $file ) ) );
		}
	}

	usort( $layouts, static fn( array $a, array $b ): int => strcmp( (string) $a['name'], (string) $b['name'] ) );

	return $layouts;
}
