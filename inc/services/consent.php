<?php
/**
 * Consent gate — fail-closed BRIDGE to a per-site consent plugin (PRIV01).
 *
 * Compliance is deliberately NOT built into salt: each site chooses its own
 * consent-management plugin (CMP). This gate stays the single chokepoint
 * every third-party load checks, and it resolves, in order:
 *
 *   1. 'essential' is always granted (first-party, strictly necessary).
 *   2. The WP Consent API (the wp-consent-api plugin — the standard that
 *      Complianz / CookieYes / Cookiebot etc. integrate with): when
 *      wp_has_consent() exists, its verdict wins, via the category map
 *      below.
 *   3. The 'salt_consent_state' filter — the documented bridge for CMPs
 *      without Consent-API support (site glue code hooks at priority 20+
 *      and returns the visitor's stored choice).
 *   4. Otherwise: DENY. No CMP wired = nothing non-essential loads.
 *
 * Every ambiguous path fails closed. The banner, records of processing,
 * lawful-basis and policy content are the SITE's responsibility — see
 * docs/PRIVACY-RESPONSIBILITIES.md for the exact boundary.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The fixed consent category taxonomy (single source of truth).
 *
 * @return string[]
 */
function salt_consent_categories(): array {
	return array( 'essential', 'analytics', 'maps', 'embeds', 'marketing' );
}

/**
 * Map a salt category to a WP Consent API category.
 *
 * Maps/embeds default to 'marketing' (embedded third parties can set their
 * own cookies); a site whose CMP uses finer categories can remap via the
 * 'salt_consent_api_category' filter (priority 20+).
 *
 * @param string $category Salt category.
 * @return string WP Consent API category.
 */
function salt_consent_api_category( string $category ): string {
	$map = array(
		'essential' => 'functional',
		'analytics' => 'statistics',
		'maps'      => 'marketing',
		'embeds'    => 'marketing',
		'marketing' => 'marketing',
	);

	return (string) apply_filters( 'salt_consent_api_category', $map[ $category ] ?? 'marketing', $category );
}

/**
 * Whether the visitor has consented to a category. FAIL-CLOSED.
 *
 * @param string $category One of salt_consent_categories().
 * @return bool
 */
function salt_consent( string $category ): bool {
	if ( 'essential' === $category ) {
		return true;
	}

	if ( ! in_array( $category, salt_consent_categories(), true ) ) {
		salt_log( sprintf( 'salt_consent: unknown category "%s" — denied (fail-closed).', $category ) );
		return false;
	}

	// 1. WP Consent API bridge (Complianz, CookieYes, Cookiebot, …), only
	// when a cookie plugin has set a consent type: without one the API
	// answers true for every category, which must never read as a grant.
	// The check is filterable ('salt_consent_api_active', priority 20+) so a
	// site can force the filter bridge instead — and so the suite can
	// exercise both paths.
	$api_active = (bool) apply_filters( 'salt_consent_api_active', salt_consent_plugin_active() );
	if ( $api_active && function_exists( 'wp_has_consent' ) ) {
		return true === wp_has_consent( salt_consent_api_category( $category ) );
	}

	// 2. Documented CMP-bridge filter. Default false: a site with no glue
	// code and no Consent API gets a hard deny, never a silent allow.
	return true === apply_filters( 'salt_consent_state', false, $category );
}

/**
 * Whether the site runs a cookie plugin wired to the WP Consent API
 * (founder ruling C, EP-72 M4): wp_has_consent() and wp_get_consent_type()
 * exist and the plugin has set a consent type. Only then does the footer
 * draw its consent control and panel, whose Save writes through the
 * plugin's client-side wp_set_consent(); the theme stores nothing itself.
 *
 * @return bool
 */
function salt_consent_plugin_active(): bool {
	if ( ! function_exists( 'wp_has_consent' ) || ! function_exists( 'wp_get_consent_type' ) ) {
		return false;
	}
	$type = wp_get_consent_type();
	return is_string( $type ) && '' !== $type;
}

/**
 * The Salt categories the site's integrations use, the ones a visitor is
 * asked about: analytics when an analytics provider is configured, maps
 * when the maps integration is on, embeds when a booking provider is
 * configured. Nothing the theme ships uses marketing on its own; a site
 * that adds such an integration names it through the
 * `salt_consent_categories_in_use` filter, which may also add or remove
 * any other. Unknown and essential categories are dropped.
 *
 * @return string[] In salt_consent_categories() order.
 */
function salt_consent_categories_in_use(): array {
	$in_use = array();
	if ( function_exists( 'salt_analytics_active' ) && in_array( true, salt_analytics_active(), true ) ) {
		$in_use[] = 'analytics';
	}
	if ( function_exists( 'salt_maps_enabled' ) && salt_maps_enabled() ) {
		$in_use[] = 'maps';
	}
	if ( function_exists( 'salt_booking_config' ) && null !== salt_booking_config() ) {
		$in_use[] = 'embeds';
	}
	$given = (array) apply_filters( 'salt_consent_categories_in_use', $in_use );
	return array_values( array_intersect( array_diff( salt_consent_categories(), array( 'essential' ) ), $given ) );
}

/**
 * The consent panel's groups (salt-contract site-footer.json, SC-030): one
 * per WP Consent API category the categories in use map to, in the API's
 * order, each with the Salt categories it covers. Only statistics and
 * marketing can be a box; a Salt category a site remaps to another API
 * category is left to that plugin's own banner, and logged.
 *
 * @return array<string,string[]> API category => the Salt categories it covers.
 */
function salt_consent_groups(): array {
	$groups = array(
		'statistics' => array(),
		'marketing'  => array(),
	);
	foreach ( salt_consent_categories_in_use() as $category ) {
		$api = salt_consent_api_category( $category );
		if ( ! isset( $groups[ $api ] ) ) {
			salt_log( sprintf( 'consent: "%s" maps to the WP Consent API\'s "%s", which the footer panel draws no box for.', $category, $api ) );
			continue;
		}
		$groups[ $api ][] = $category;
	}
	$groups = array_filter( $groups );
	foreach ( $groups as $api => $covers ) {
		sort( $covers );
		$groups[ $api ] = $covers;
	}
	return $groups;
}

/**
 * Whether the footer draws its consent control and panel (SC-030): only
 * when an integration needs consent (a box to draw) and a consent plugin is
 * wired to the WP Consent API (salt_consent_plugin_active(), ruling C).
 * Without the plugin every such integration stays denied (salt_consent())
 * and nothing is drawn. The `salt_consent_control` filter lets a site turn
 * it off, never on without both.
 *
 * @return bool
 */
function salt_consent_control_drawn(): bool {
	return salt_consent_plugin_active() && array() !== salt_consent_groups() && true === apply_filters( 'salt_consent_control', true );
}
