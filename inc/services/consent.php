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
 * Whether the footer draws its consent control and panel: only with a
 * consent plugin (salt_consent_plugin_active(), ruling C). The
 * `salt_consent_control` filter lets a site turn it off, never on without
 * a plugin, since Save has nothing to write through then.
 *
 * @return bool
 */
function salt_consent_control_drawn(): bool {
	return salt_consent_plugin_active() && true === apply_filters( 'salt_consent_control', true );
}
