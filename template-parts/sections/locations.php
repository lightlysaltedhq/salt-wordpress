<?php
/**
 * View: Locations (salt-contract markup/locations.json, rc.8; spec §10 #20,
 * Appendix A.6).
 *
 * Draws the props from salt_resolve_section_locations() (inc/sections/
 * locations.php) as the contract's block, inside the band the shared entry
 * opens. No field reads, queries, status calls (the clock), schema or
 * logging here: a child theme overriding this file overrides the markup only.
 *
 * A zero-JS native <details> accordion of offices (the contract's accordion
 * classes): summary = office name + its status ('unknown' draws none —
 * COMPL02; the label text is the non-colour signal), the status span
 * carrying data-hours, data-timezone and data-labels for the on-load
 * status script (SC-007, deferred to its own Work Item: today the server's
 * status stands); body = address, tel:/mailto: links, opening hours, the
 * map facade when the office has one, and a "Get directions" link (a plain
 * external link: nothing loads from that origin unless the visitor clicks
 * — PRIV01).
 *
 * TEASER RULE (§6): the location CPT is not public (no archive URL), so
 * no "view all" link renders — the section is the canonical surface.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null, offices: array, timezone: string,
 *                 labels: array<string,string> } (see the resolver).
 *   plan   array{ index: int, anchor: string, headingLevel: int, headingId: string|null, bodyBaseLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props  = $args['props'];
$salt_plan   = $args['plan'];
$salt_level  = max( 1, min( 6, (int) $salt_plan['headingLevel'] ) );
$salt_id     = $salt_plan['headingId'] ?? null;
$salt_labels = (array) $salt_props['labels'];
// One exclusive-open group per band (locations-<plan index>), on every details.
$salt_group = 'locations-' . (int) $salt_plan['index'];
// data-labels: the three status words, keyed by status.
$salt_status_labels = (string) wp_json_encode(
	array(
		'open'        => (string) ( $salt_labels['open'] ?? '' ),
		'appointment' => (string) ( $salt_labels['appointment'] ?? '' ),
		'closed'      => (string) ( $salt_labels['closed'] ?? '' ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

echo '<div class="salt-block" data-block="locations">';

if ( null !== $salt_props['eyebrow'] ) {
	echo '<p class="salt-eyebrow">' . esc_html( (string) $salt_props['eyebrow'] ) . '</p>';
}
if ( null !== $salt_props['heading'] ) {
	printf(
		'<h%1$d%2$s>%3$s</h%1$d>',
		(int) $salt_level,
		is_string( $salt_id ) ? ' id="' . esc_attr( $salt_id ) . '"' : '',
		esc_html( (string) $salt_props['heading'] )
	);
}

echo '<div class="salt-accordion salt-locations">';
foreach ( $salt_props['offices'] as $salt_office ) {
	echo '<details class="salt-accordion__item" name="' . esc_attr( $salt_group ) . '">';

	// Not a heading: the office names are summary text; the outline is the section heading's.
	echo '<summary class="salt-accordion__summary"><span class="salt-locations__name">' . esc_html( (string) $salt_office['title'] ) . '</span>';
	if ( isset( $salt_labels[ $salt_office['status'] ] ) && in_array( $salt_office['status'], array( 'open', 'appointment', 'closed' ), true ) ) {
		printf(
			'<span class="salt-locations__status" data-status="%1$s" data-hours="%2$s" data-timezone="%3$s" data-labels="%4$s">%5$s</span>',
			esc_attr( (string) $salt_office['status'] ),
			esc_attr( (string) wp_json_encode( $salt_office['hours'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ),
			esc_attr( (string) $salt_props['timezone'] ),
			esc_attr( $salt_status_labels ),
			esc_html( (string) $salt_labels[ $salt_office['status'] ] )
		);
	}
	echo '</summary>';

	echo '<div class="salt-accordion__content"><div class="salt-locations__office">';
	if ( array() !== $salt_office['address'] ) {
		// A line break between the lines: a space a visitor sees when the stylesheet keeps them inline.
		$salt_lines = array_map( static fn( $line ): string => '<span class="salt-locations__line">' . esc_html( (string) $line ) . '</span>', $salt_office['address'] );
		echo '<address class="salt-locations__address">' . implode( "\n", $salt_lines ) . '</address>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each line escaped above.
	}
	if ( null !== $salt_office['phone'] || null !== $salt_office['email'] ) {
		echo '<ul class="salt-locations__contact" role="list">';
		if ( null !== $salt_office['phoneHref'] ) {
			echo '<li><a href="' . esc_url( (string) $salt_office['phoneHref'] ) . '">' . esc_html( (string) $salt_office['phone'] ) . '</a></li>';
		} elseif ( null !== $salt_office['phone'] ) {
			echo '<li>' . esc_html( (string) $salt_office['phone'] ) . '</li>';
		}
		if ( null !== $salt_office['email'] ) {
			echo '<li><a href="' . esc_url( 'mailto:' . $salt_office['email'] ) . '">' . esc_html( (string) $salt_office['email'] ) . '</a></li>';
		}
		echo '</ul>';
	}
	if ( array() !== $salt_office['opening_hours'] ) {
		salt_component(
			'opening-hours',
			array(
				'hours'   => $salt_office['opening_hours'],
				'compact' => false,
			)
		);
	}
	if ( null !== $salt_office['coordinates'] ) {
		salt_component( 'map', $salt_office['coordinates'] );
	}
	if ( null !== $salt_office['directionsHref'] ) {
		printf(
			'<a class="salt-locations__directions" href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( (string) $salt_office['directionsHref'] ),
			// {name} is replaced where it stands and nothing is appended: a label
			// without it is drawn as written (README Labels, SC-031).
			esc_attr( str_replace( '{name}', (string) $salt_office['title'], (string) ( $salt_labels['directions'] ?? '' ) ) ),
			esc_html( (string) ( $salt_labels['getDirections'] ?? '' ) )
		);
	}
	echo '</div></div></details>';
}
echo '</div>';

echo '</div>';
