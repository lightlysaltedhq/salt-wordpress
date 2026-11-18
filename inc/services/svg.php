<?php
/**
 * SVG services: upload sanitisation (SEC04) + bundled icon loader.
 *
 * salt_sanitize_svg() is the SOLE path for any uploaded SVG markup: every
 * byte of SVG that did not ship inside this theme goes through the vendored
 * enshrined/svgSanitize library (loaded via salt_register_vendor_autoloader(),
 * DEP02) before it may touch the media library or the page.
 *
 * Upload posture: SVG upload support is only ADDED for users with
 * 'manage_options' (admins) — a deliberately small attack surface — and
 * every accepted file is rewritten in place with its sanitised contents.
 *
 * Icon set (assets/src/icons/*.svg): ORIGINAL, hand-authored 24x24 stroke
 * icons owned by the theme. They are NOT copied from Heroicons or any other
 * library, which sidesteps the DEP06 licence/pin question for v1.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitise SVG markup (SEC04 — the sole path for uploaded SVG).
 *
 * Runs the vendored enshrined sanitiser with remote-reference removal on
 * (no external hrefs/url() leaking out of an icon). Returns '' — logged,
 * never silent — when the sanitiser rejects the input outright.
 *
 * @param string $svg Raw SVG markup.
 * @return string Sanitised SVG, or '' when the input is unusable.
 */
function salt_sanitize_svg( string $svg ): string {
	if ( '' === trim( $svg ) ) {
		return '';
	}

	salt_register_vendor_autoloader();

	$sanitizer = new enshrined\svgSanitize\Sanitizer();
	$sanitizer->removeRemoteReferences( true );

	$clean = $sanitizer->sanitize( $svg );

	if ( false === $clean || '' === trim( (string) $clean ) ) {
		salt_log( 'salt_sanitize_svg(): markup rejected by the sanitiser (SEC04).' );
		return '';
	}

	return trim( $clean );
}

/**
 * Register the SVG upload filters (SEC04).
 *
 * - 'upload_mimes': ADDS image/svg+xml, gated on current_user_can(
 *   'manage_options' ) — only admins may upload SVGs at all.
 * - 'wp_handle_upload_prefilter': sanitises every uploaded SVG in place;
 *   an unsanitisable file is rejected (error key set), never stored raw.
 *
 * @return void
 */
function salt_svg_upload_filters(): void {
	add_filter( 'upload_mimes', 'salt_svg_upload_mimes' );
	add_filter( 'wp_handle_upload_prefilter', 'salt_svg_upload_prefilter' );
}

/**
 * 'upload_mimes' callback: allow SVG uploads for admins only (SEC04).
 *
 * @param array<string,string> $mimes Allowed mime map (ext => type).
 * @return array<string,string>
 */
function salt_svg_upload_mimes( array $mimes ): array {
	if ( current_user_can( 'manage_options' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}

	return $mimes;
}

/**
 * 'wp_handle_upload_prefilter' callback: sanitise an uploaded SVG in place.
 *
 * Reads the temp file, runs salt_sanitize_svg(), and rewrites the file with
 * the clean markup. When sanitisation yields nothing the upload is rejected
 * via the 'error' key — a hostile file never reaches the media library.
 *
 * @param array<string,mixed> $file Upload array (name, type, tmp_name, ...).
 * @return array<string,mixed>
 */
function salt_svg_upload_prefilter( array $file ): array {
	if ( 'image/svg+xml' !== ( $file['type'] ?? '' ) ) {
		return $file;
	}

	$path     = (string) ( $file['tmp_name'] ?? '' );
	$contents = ( '' !== $path && is_readable( $path ) )
		? (string) file_get_contents( $path ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local upload temp file; WP_Filesystem is not available in the prefilter context.
		: '';

	$clean = salt_sanitize_svg( $contents );

	if ( '' === $clean ) {
		salt_log( sprintf( 'salt_svg_upload_prefilter(): rejected unsanitisable SVG upload "%s" (SEC04).', (string) ( $file['name'] ?? '' ) ) );
		$file['error'] = __( 'This SVG could not be sanitised and was rejected.', 'salt' );
		return $file;
	}

	file_put_contents( $path, $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Rewriting the upload temp file in place is the documented prefilter pattern.

	return $file;
}

/**
 * Per-request bundled-icon cache (single authoritative store).
 *
 * @param string $op    'get'|'set'|'reset'.
 * @param string $key   Icon key when setting.
 * @param string $value Icon markup when setting.
 * @return array<string,string>
 */
function salt_icon_store( string $op = 'get', string $key = '', string $value = '' ): array {
	static $icons = array();

	if ( 'reset' === $op ) {
		$icons = array();
	}

	if ( 'set' === $op && '' !== $key ) {
		$icons[ $key ] = $value;
	}

	return $icons;
}

/**
 * Reset the bundled-icon cache (per request / per test).
 *
 * @return void
 */
function salt_icon_cache_reset(): void {
	salt_icon_store( 'reset' );
}

/**
 * Load a bundled icon's SVG markup (assets/src/icons/{name}.svg).
 *
 * Theme-owned files only — $name is forced through sanitize_key() so a
 * caller can never traverse outside the icon directory. Unknown icons
 * return '' and are logged (fail loud, never blank markup mid-sentence).
 *
 * @param string $name Icon name (e.g. 'arrow-right').
 * @return string SVG markup, or '' when unknown.
 */
function salt_icon_svg( string $name ): string {
	$key = sanitize_key( $name );

	if ( '' === $key ) {
		salt_log( sprintf( 'salt_icon_svg(): invalid icon name "%s".', $name ) );
		return '';
	}

	$cache = salt_icon_store();
	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}

	$path = get_template_directory() . '/assets/src/icons/' . $key . '.svg';

	if ( ! is_readable( $path ) ) {
		salt_log( sprintf( 'salt_icon_svg(): unknown icon "%s".', $key ) );
		salt_icon_store( 'set', $key, '' );
		return '';
	}

	$svg = trim( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Theme-bundled asset read; no WP_Filesystem needed for local theme files.
	salt_icon_store( 'set', $key, $svg );

	return $svg;
}

/**
 * A bundled icon as the contract's icon (salt-contract markup/icon.json):
 * the svg itself carries class salt-icon, data-icon, data-size, the 24 x 24
 * box, focusable false and its naming (aria-hidden, or role img with an
 * aria-label), with the artwork's fill and stroke. The artwork's other
 * root attributes (stroke-width, stroke-linecap …) move onto a g around it,
 * as the contract keeps them inside the artwork. '' for an unknown name
 * (logged by salt_icon_svg()).
 *
 * @param string      $name  Icon name.
 * @param string      $size  sm|md|lg.
 * @param string|null $label The accessible name; null for a decorative icon.
 * @return string
 */
function salt_contract_icon( string $name, string $size, ?string $label = null ): string {
	$svg = salt_icon_svg( $name );
	if ( 1 !== preg_match( '#^<svg\b([^>]*)>(.*)</svg>$#s', $svg, $match ) ) {
		return '';
	}
	preg_match_all( '/([a-zA-Z:-]+)="([^"]*)"/', $match[1], $pairs, PREG_SET_ORDER );
	$paint = array();
	$inner = array();
	foreach ( $pairs as $pair ) {
		if ( in_array( $pair[1], array( 'fill', 'stroke' ), true ) ) {
			$paint[ $pair[1] ] = $pair[2];
		} elseif ( ! in_array( $pair[1], array( 'xmlns', 'viewBox', 'width', 'height', 'class' ), true ) ) {
			$inner[] = $pair[1] . '="' . esc_attr( $pair[2] ) . '"';
		}
	}

	$attributes  = 'class="salt-icon" data-icon="' . esc_attr( $name ) . '" data-size="' . esc_attr( $size ) . '" viewBox="0 0 24 24" width="24" height="24" focusable="false"';
	$attributes .= null === $label ? ' aria-hidden="true"' : ' role="img" aria-label="' . esc_attr( $label ) . '"';
	$attributes .= ' fill="' . esc_attr( $paint['fill'] ?? 'none' ) . '" stroke="' . esc_attr( $paint['stroke'] ?? 'none' ) . '"';
	$art         = array() === $inner ? $match[2] : '<g ' . implode( ' ', $inner ) . '>' . $match[2] . '</g>';

	return '<svg ' . $attributes . '>' . $art . '</svg>';
}
