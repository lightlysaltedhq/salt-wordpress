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
 * (which only fires when ACF is active — inherently guarded). The page
 * sections' Flexible Content layouts are generated from salt-contract
 * (inc/fields/generated/sections.json); the collection layouts not yet
 * folded into the contract's sections live in inc/fields/layouts/*.php
 * (see salt_section_layouts()).
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
 * Path of the generated page-sections field group (ACF JSON), relative to
 * the theme root. Written verbatim by the pinned salt-contract emitter
 * (`emit/acf.mjs --write`, options in sections.options.json beside it) and
 * kept honest by bin/check-fields-from-contract.php. Never edit it by hand.
 */
const SALT_GENERATED_SECTIONS_FILE = 'inc/fields/generated/sections.json';

/**
 * The generated page-sections field group, decoded, or null when the file
 * is missing or is not the group the emitter writes (logged once per
 * request). Callers fall back to the hand-written layouts alone, so a
 * broken file never fatals (DEP01).
 *
 * @param bool $reset Forget the cached read (tests).
 * @return array<string,mixed>|null
 */
function salt_generated_sections_group( bool $reset = false ): ?array {
	static $group = false;

	if ( $reset ) {
		$group = false;
	}
	if ( false !== $group ) {
		return $group;
	}

	$path  = get_template_directory() . '/' . SALT_GENERATED_SECTIONS_FILE;
	$found = salt_generated_sections_decode( is_readable( $path ) ? (string) file_get_contents( $path ) : null ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local theme file.

	if ( null === $found ) {
		salt_log( sprintf( '%s is missing or is not the generated page-sections group — only the hand-written layouts are registered. Regenerate it (bin/check-fields-from-contract.php).', SALT_GENERATED_SECTIONS_FILE ) );
	}

	$group = $found;
	return $group;
}

/**
 * The page-sections group in the emitter's JSON, or null when the text is
 * missing, not JSON, or not that group (pure — unit-tested).
 *
 * @param string|null $json The file's text, or null when unreadable.
 * @return array<string,mixed>|null
 */
function salt_generated_sections_decode( ?string $json ): ?array {
	$decoded = null === $json ? null : json_decode( $json, true );
	$found   = is_array( $decoded ) ? ( $decoded[0] ?? null ) : null;

	if (
		! is_array( $found )
		|| 'group_salt_sections' !== ( $found['key'] ?? null )
		|| 'field_salt_sections_container' !== ( $found['fields'][0]['key'] ?? null )
		|| ! is_array( $found['fields'][0]['layouts'] ?? null )
	) {
		return null;
	}
	return $found;
}

/**
 * The contract's section settings as a field list for a hand-written
 * layout: the generated rich-text layout's `settings` group, re-keyed to
 * the layout's own scope (field_salt_{scope}_settings_*), so every layout
 * stores its wrapper settings in a group named `settings`, as the contract
 * does, and salt_section_wrapper_args() reads one shape.
 *
 * Only the six collection layouts the contract folds into
 * collection-showcase and carousel still need this (unit 8 removes them).
 *
 * @param string $scope Layout key scope (snake_case), e.g. 'blog_teaser'.
 * @return array<int,array<string,mixed>> The settings group, or none when the generated file is unusable.
 */
function salt_section_settings_fields( string $scope ): array {
	$group = salt_generated_sections_group();
	foreach ( null === $group ? array() : $group['fields'][0]['layouts'] as $layout ) {
		if ( 'rich-text' !== ( $layout['name'] ?? '' ) ) {
			continue;
		}
		foreach ( $layout['sub_fields'] as $field ) {
			if ( 'settings' === ( $field['name'] ?? '' ) ) {
				$json = str_replace( '"field_salt_rich_text_settings', '"field_salt_' . $scope . '_settings', (string) wp_json_encode( $field ) );
				return array( json_decode( $json, true ) );
			}
		}
	}
	return array();
}

/**
 * Collect the Flexible Content layout definitions.
 *
 * The generated layouts first, in the contract's order and exactly as the
 * emitter wrote them (salt_generated_sections_group()), then the
 * hand-written layouts in inc/fields/layouts/*.php, each file returning
 * ONE layout definition array (name, label, sub_fields), by name. A
 * hand-written file for a generated section is refused and logged: a
 * section's fields come from the contract only.
 *
 * @return array<int,array<string,mixed>>
 */
function salt_section_layouts(): array {
	$group     = salt_generated_sections_group();
	$generated = null === $group ? array() : array_values( $group['fields'][0]['layouts'] );
	$names     = array_column( $generated, 'name' );

	$written = array();
	$files   = glob( get_template_directory() . '/inc/fields/layouts/*.php' );
	foreach ( false === $files ? array() : $files as $file ) {
		$layout = include $file;
		if ( ! is_array( $layout ) || ! isset( $layout['name'] ) ) {
			salt_log( sprintf( 'layout file "%s" did not return a valid layout array.', basename( $file ) ) );
		} elseif ( in_array( $layout['name'], $names, true ) ) {
			salt_log( sprintf( 'layout file "%1$s" defines \'%2$s\', which is generated from the contract — ignored.', basename( $file ), $layout['name'] ) );
		} else {
			$written[] = $layout;
		}
	}

	usort( $written, static fn( array $a, array $b ): int => strcmp( (string) $a['name'], (string) $b['name'] ) );

	return array_merge( $generated, $written );
}
