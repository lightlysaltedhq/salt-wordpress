<?php
/**
 * Maps service — MapLibre raster maps behind a click-to-load facade
 * (spec §9; PRIV01/§7).
 *
 * Nothing third-party loads at render time: the x-map component ships an
 * inert placeholder, and assets/src/js/maps.js loads the MapLibre bundle
 * (same-origin) + raster tiles (third-party origin) only after the visitor
 * clicks AND the 'maps' consent category (→ 'marketing' via the WP Consent
 * API map) is granted client-side. Fail-closed: no CMP on the page means
 * the click shows a consent notice, never a tile request.
 *
 * The tile provider is per-site configurable (inc/fields/options-integrations.php);
 * the CSP is widened for exactly that one origin, only while maps are
 * enabled, via the dedicated 'salt_csp_policy' callback below.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The default raster tile URL template (OpenStreetMap).
 *
 * @return string
 */
function salt_maps_default_tile_url(): string {
	return 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
}

/**
 * Whether the maps integration is enabled for this site.
 *
 * @return bool
 */
function salt_maps_enabled(): bool {
	return salt_acf_active() && true === (bool) salt_option( 'maps_enabled' );
}

/**
 * The https origin of a tile URL template, or null when it is unusable.
 *
 * Fail-closed: anything that is not a well-formed https URL with a host is
 * rejected — the origin feeds the CSP, so ambiguity must never widen it.
 *
 * @param string $url Tile URL template.
 * @return string|null e.g. 'https://tile.openstreetmap.org', or null.
 */
function salt_maps_tile_origin( string $url ): ?string {
	$parts = wp_parse_url( trim( $url ) );

	if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || '' === (string) ( $parts['host'] ?? '' ) ) {
		return null;
	}

	$origin = 'https://' . strtolower( (string) $parts['host'] );
	if ( isset( $parts['port'] ) ) {
		$origin .= ':' . (int) $parts['port'];
	}

	return $origin;
}

/**
 * The validated tile URL template for this site.
 *
 * Reads the maps_tile_url option; anything that is not an https template
 * containing the {z}/{x}/{y} placeholders is logged and replaced by the
 * OpenStreetMap default (fail loud, degrade to a known-good provider).
 *
 * @return string
 */
function salt_maps_tile_url(): string {
	$url = trim( (string) ( salt_acf_active() ? ( salt_option( 'maps_tile_url' ) ?? '' ) : '' ) );

	if ( '' === $url ) {
		return salt_maps_default_tile_url();
	}

	$has_placeholders = str_contains( $url, '{z}' ) && str_contains( $url, '{x}' ) && str_contains( $url, '{y}' );

	if ( null === salt_maps_tile_origin( $url ) || ! $has_placeholders ) {
		salt_log( sprintf( 'maps: tile URL "%s" is not a valid https {z}/{x}/{y} template — using the OpenStreetMap default.', $url ) );
		return salt_maps_default_tile_url();
	}

	return $url;
}

/**
 * Append source origins to named CSP directives (maps-internal helper).
 *
 * Existing directives are widened in place; directives absent from the
 * base policy are appended. Never removes or replaces anything.
 *
 * @param string                       $csp       Serialised CSP.
 * @param array<string,array<string>>  $additions Directive => origins.
 * @return string
 */
function salt_maps_csp_append( string $csp, array $additions ): string {
	$directives = explode( '; ', $csp );

	foreach ( $directives as $i => $directive ) {
		foreach ( $additions as $name => $origins ) {
			if ( str_starts_with( $directive, $name . ' ' ) ) {
				$directives[ $i ] = $directive . ' ' . implode( ' ', $origins );
				unset( $additions[ $name ] );
			}
		}
	}

	foreach ( $additions as $name => $origins ) {
		$directives[] = $name . ' ' . implode( ' ', $origins );
	}

	return implode( '; ', $directives );
}

/**
 * 'salt_csp_policy' callback: allow the tile origin while maps are enabled.
 *
 * Appends ONLY the configured tile origin, to img-src (tile images) and
 * connect-src (MapLibre fetches raster tiles via fetch/XHR), and only when
 * the integration is active. Registered at priority 10 (parent band).
 *
 * @param string $csp Serialised CSP.
 * @return string
 */
function salt_maps_csp_policy( string $csp ): string {
	if ( ! salt_maps_enabled() ) {
		return $csp;
	}

	$origin = salt_maps_tile_origin( salt_maps_tile_url() );
	if ( null === $origin ) {
		// Unreachable in practice (salt_maps_tile_url() validates), but the
		// CSP must never widen on an ambiguous origin (fail closed).
		return $csp;
	}

	return salt_maps_csp_append(
		$csp,
		array(
			'img-src'     => array( $origin ),
			'connect-src' => array( $origin ),
		)
	);
}
add_filter( 'salt_csp_policy', 'salt_maps_csp_policy', 10 );

/**
 * Enqueue the maps facade loader on pages that can contain a map
 * ('wp_enqueue_scripts', 20).
 *
 * Gate: maps enabled AND the page's Flexible Content pre-scan
 * (salt_page_section_slugs()) contains a locations or contact section.
 * APPROXIMATION, documented: the pre-scan sees section types, not whether
 * a given instance actually renders an x-map — a locations/contact page
 * without a map loads this small facade script for nothing (it no-ops on
 * an absent [data-salt-map]), and a map placed anywhere else needs its
 * hosting section added to the gate list when sections adopt the
 * component. The MapLibre bundle itself is NEVER enqueued here — it loads
 * on demand, post-click, post-consent.
 *
 * @return void
 */
function salt_maps_enqueue(): void {
	if ( ! salt_maps_enabled() ) {
		return;
	}

	if ( array() === array_intersect( array( 'locations', 'contact' ), salt_page_section_slugs() ) ) {
		return;
	}

	$src = salt_asset( 'maps.js' );
	if ( null !== $src ) {
		wp_enqueue_script(
			'salt-maps',
			$src,
			array(),
			null,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'salt_maps_enqueue', 20 );
