<?php
/**
 * Module feature-flags (spec §10b).
 *
 * Every module gate in the theme — CPT registration, section render/picker,
 * and route/asset guards — funnels through salt_module_enabled(). The child
 * or site override point is the 'salt_module_enabled' filter (priority 20+).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Known modules: slug => human label.
 *
 * Final. The set of known modules is a parent-theme contract.
 *
 * @return array<string,string>
 */
function salt_modules(): array {
	return array(
		'service'             => __( 'Services', 'salt' ),
		'testimonial'         => __( 'Testimonials', 'salt' ),
		'team_member'         => __( 'Team members', 'salt' ),
		'work'                => __( 'Work', 'salt' ),
		'faq'                 => __( 'FAQs', 'salt' ),
		'location'            => __( 'Locations', 'salt' ),
		'client'              => __( 'Clients', 'salt' ),
		'sector'              => __( 'Sectors', 'salt' ),
		'dark_mode'           => __( 'Dark mode', 'salt' ),
		'display_preferences' => __( 'Display preferences', 'salt' ),
	);
}

/**
 * Modules that are off until a site switches them on.
 *
 * The name-only client and sector records (SC-035): a site that never
 * saves their switch keeps its case studies' free-text names. Every other
 * module stays on by default.
 *
 * @return string[]
 */
function salt_modules_off_by_default(): array {
	return array( 'client', 'sector' );
}

/**
 * Single three-layer module gate.
 *
 * Reads the ACF option 'module_{slug}': unset/null defaults to enabled,
 * except the salt_modules_off_by_default() modules, which default to off;
 * anything else is cast to bool. The result then passes through the
 * 'salt_module_enabled' filter — the child/site override point (register
 * at priority 20+ per the conventions contract). Final; extend via filter.
 *
 * @param string $slug Module slug.
 * @return bool
 */
function salt_module_enabled( string $slug ): bool {
	static $warned = array();

	if ( ! array_key_exists( $slug, salt_modules() ) && ! isset( $warned[ $slug ] ) ) {
		$warned[ $slug ] = true;
		salt_log( sprintf( 'salt_module_enabled(): unknown module slug "%s".', $slug ) );
	}

	$value   = salt_option( 'module_' . $slug );
	$enabled = null === $value ? ! in_array( $slug, salt_modules_off_by_default(), true ) : (bool) $value;

	return (bool) apply_filters( 'salt_module_enabled', $enabled, $slug );
}

/**
 * Module slug => CPT name for the CPT-backed modules.
 *
 * Final.
 *
 * @return array<string,string>
 */
function salt_module_cpts(): array {
	return array(
		'service'     => 'service',
		'testimonial' => 'testimonial',
		'work'        => 'work',
		'faq'         => 'faq',
		'location'    => 'location',
		'client'      => 'client',
		'sector'      => 'sector',
	);
}
