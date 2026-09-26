<?php
/**
 * x-map — click-to-load map FACADE (spec §9; PRIV01/§7).
 *
 * Renders an inert, first-party placeholder: the address/label text and a
 * "Load map" button on a bg-surface-alt panel. NO map asset — no MapLibre,
 * no tile, no third-party anything — loads at render time. The data-*
 * attributes carry everything assets/src/js/maps.js needs to build the map
 * post-click, post-consent (the tile template is inert data here, never a
 * loading attribute). Renders NOTHING (logged) when maps are disabled — a
 * dead button is worse than no map.
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   lat   float  required  Latitude (-90..90; out of range renders nothing, logged).
 *   lng   float  required  Longitude (-180..180; ditto).
 *   zoom  int    14        Initial zoom (clamped to 0..22, logged).
 *   label string ''        Address/venue text shown in the facade.
 *
 * Render via salt_component( 'map', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args = salt_require_args(
	$args ?? array(),
	array(
		'lat'   => array(
			'type'     => 'float',
			'required' => true,
		),
		'lng'   => array(
			'type'     => 'float',
			'required' => true,
		),
		'zoom'  => array(
			'type'    => 'int',
			'default' => 14,
		),
		'label' => array(
			'type'    => 'string',
			'default' => '',
		),
		'slots' => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'map'
);

if ( ! salt_maps_enabled() ) {
	salt_log( 'x-map: maps are disabled in Site Settings — rendered nothing.' );
	return;
}

$salt_lat = (float) $salt_args['lat'];
$salt_lng = (float) $salt_args['lng'];

if ( $salt_lat < -90.0 || $salt_lat > 90.0 || $salt_lng < -180.0 || $salt_lng > 180.0 ) {
	salt_log( sprintf( 'x-map: coordinates (%s, %s) are out of range — rendered nothing.', (string) $salt_lat, (string) $salt_lng ) );
	return;
}

$salt_zoom = (int) $salt_args['zoom'];
if ( $salt_zoom < 0 || $salt_zoom > 22 ) {
	salt_log( sprintf( 'x-map: zoom %d out of range — clamped.', $salt_zoom ) );
	$salt_zoom = max( 0, min( 22, $salt_zoom ) );
}

// The on-demand MapLibre bundle (SAME-origin, hashed). A manifest miss is
// already logged by salt_asset(); without it the button would be dead.
$salt_maplibre = salt_asset( 'maps/maplibre.js' );
if ( null === $salt_maplibre ) {
	return;
}

$salt_label      = (string) $salt_args['label'];
$salt_aria_label = '' === $salt_label
	? __( 'Load interactive map (loads content from a third-party map provider)', 'salt' )
	/* translators: %s: place name/address shown in the map facade. */
	: sprintf( __( 'Load interactive map of %s (loads content from a third-party map provider)', 'salt' ), $salt_label );
?>
<div
	class="x-map relative flex aspect-video w-full flex-col items-center justify-center gap-3 overflow-hidden rounded-md bg-surface-alt p-6 text-center"
	data-salt-map
	data-lat="<?php echo esc_attr( (string) $salt_lat ); ?>"
	data-lng="<?php echo esc_attr( (string) $salt_lng ); ?>"
	data-zoom="<?php echo esc_attr( (string) $salt_zoom ); ?>"
	data-tile="<?php echo esc_attr( salt_maps_tile_url() ); ?>"
	data-maplibre="<?php echo esc_url( $salt_maplibre ); ?>"
	data-consent-message="<?php echo esc_attr__( 'Enable maps consent to load the map.', 'salt' ); ?>"
	data-error-message="<?php echo esc_attr__( 'Sorry, the map could not be loaded.', 'salt' ); ?>"
>
	<?php if ( '' !== $salt_label ) : ?>
	<p class="x-map-label font-medium"><?php echo esc_html( $salt_label ); ?></p>
	<?php endif; ?>
	<button
		type="button"
		class="x-map-load x-button inline-flex items-center justify-center gap-2 rounded-md bg-brand px-5 py-2.5 font-medium text-on-brand hover:bg-brand-strong"
		aria-label="<?php echo esc_attr( $salt_aria_label ); ?>"
	><?php esc_html_e( 'Load map', 'salt' ); ?></button>
</div>
