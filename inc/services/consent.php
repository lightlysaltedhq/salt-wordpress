<?php
/**
 * Consent service — fail-closed stub (PRIV01).
 *
 * BUILD-BRIEF locked decision: the full consent store is deferred to the
 * Compliance & Integrations phase. This stub DENIES every non-essential
 * category, so NO third-party asset, script, font, tile, or beacon can
 * load in this build. It is intentionally FINAL and un-overridable: no
 * filter, no pluggable wrapper, no option — no site can wire a third
 * party before the Compliance & Integrations phase replaces this module.
 * The replacement ships as a parent theme release.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The fixed set of consent categories. Final — not filterable.
 *
 * @return string[] Category slugs.
 */
function salt_consent_categories(): array {
	return array( 'essential', 'analytics', 'maps', 'embeds', 'marketing' );
}

/**
 * Whether the visitor has consented to a category.
 *
 * Fail-closed stub (PRIV01): only 'essential' returns true; every other
 * category — known or unknown — is denied. Final: no filter, no
 * pluggability, no option. Replaced wholesale in the Compliance &
 * Integrations phase via a parent release.
 *
 * @param string $category Consent category slug.
 * @return bool True only for 'essential'.
 */
function salt_consent( string $category ): bool {
	if ( 'essential' === $category ) {
		return true;
	}

	if ( ! in_array( $category, salt_consent_categories(), true ) ) {
		salt_log( sprintf( 'salt_consent: unknown category "%s" denied (fail-closed stub, PRIV01).', $category ) );
	}

	return false;
}
