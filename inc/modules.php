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
		'dark_mode'           => __( 'Dark mode', 'salt' ),
		'display_preferences' => __( 'Display preferences', 'salt' ),
	);
}

/**
 * Single three-layer module gate.
 *
 * Reads the ACF option 'module_{slug}': unset/null defaults to enabled;
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
	$enabled = null === $value ? true : (bool) $value;

	return (bool) apply_filters( 'salt_module_enabled', $enabled, $slug );
}

/**
 * Module slug => CPT name for the six CPT-backed modules.
 *
 * Final.
 *
 * @return array<string,string>
 */
function salt_module_cpts(): array {
	return array(
		'service'     => 'service',
		'testimonial' => 'testimonial',
		'team_member' => 'team_member',
		'work'        => 'work',
		'faq'         => 'faq',
		'location'    => 'location',
	);
}
