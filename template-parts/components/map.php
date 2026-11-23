<?php
/**
 * The contract's map facade (salt-contract markup/map.json, rc.8; spec §9,
 * PRIV01/§7): click to load.
 *
 * Draws an inert, first-party placeholder, div.salt-map with an optional
 * label and a "Load map" button. No map asset loads at render time: no
 * MapLibre, no tile, nothing third-party. The data-* attributes carry what
 * assets/src/js/maps.js needs to build the map after the click and only
 * with maps consent (the tile template is inert data here, never a loading
 * attribute). The script finds the same-origin MapLibre bundle itself
 * (salt_maps_enqueue()), not in the markup (SC-006). Draws NOTHING (logged)
 * when maps are off or the coordinates are out of range: a dead button is
 * worse than no map.
 *
 * ARGS:
 *   lat   float   required  Latitude (-90..90).
 *   lng   float   required  Longitude (-180..180).
 *   zoom  int     14        Initial zoom (the contract's 1..20; clamped, logged).
 *   label string  ''        Text shown in the facade (map.json label).
 *
 * The words are salt_contract_labels()'s load, loadMap, consent and error.
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
	salt_log( 'map: maps are disabled in Site Settings — rendered nothing.' );
	return;
}

$salt_lat = (float) $salt_args['lat'];
$salt_lng = (float) $salt_args['lng'];

if ( $salt_lat < -90.0 || $salt_lat > 90.0 || $salt_lng < -180.0 || $salt_lng > 180.0 ) {
	salt_log( sprintf( 'map: coordinates (%s, %s) are out of range — rendered nothing.', (string) $salt_lat, (string) $salt_lng ) );
	return;
}

$salt_zoom = (int) $salt_args['zoom'];
if ( $salt_zoom < 1 || $salt_zoom > 20 ) {
	salt_log( sprintf( 'map: zoom %d out of range — clamped.', $salt_zoom ) );
	$salt_zoom = max( 1, min( 20, $salt_zoom ) );
}

$salt_labels = salt_contract_labels();
$salt_label  = (string) $salt_args['label'];

printf(
	'<div class="salt-map" data-lat="%1$s" data-lng="%2$s" data-zoom="%3$d" data-tile="%4$s" data-consent-message="%5$s" data-error-message="%6$s">',
	esc_attr( (string) $salt_lat ),
	esc_attr( (string) $salt_lng ),
	(int) $salt_zoom,
	esc_attr( salt_maps_tile_url() ),
	esc_attr( $salt_labels['consent'] ?? '' ),
	esc_attr( $salt_labels['error'] ?? '' )
);
if ( '' !== $salt_label ) {
	echo '<p class="salt-map__label">' . esc_html( $salt_label ) . '</p>';
}
printf(
	'<button class="salt-button salt-map__load" type="button" data-style="primary" aria-label="%1$s">%2$s</button>',
	esc_attr( $salt_labels['load'] ?? '' ),
	esc_html( $salt_labels['loadMap'] ?? '' )
);
echo '</div>';
