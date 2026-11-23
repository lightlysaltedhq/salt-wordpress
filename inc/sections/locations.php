<?php
/**
 * Resolver: Locations (salt-contract locations, rc.8; spec §10 #20,
 * Appendix A.6) — the logic half of the section; the view is
 * template-parts/sections/locations.php, drawn in the contract's shared
 * wrapper (salt_section(), EP-72 M4).
 *
 * Offices come from the location CPT via the Principle-7 query funnel.
 * Per office: address, phone, email and opening hours from its fields
 * (salt-contract collections/locations.json: the address shape street, up
 * to two lines, locality, region, postalCode, country; hours rows of day,
 * open, close and appointment; lat, lng and zoom); the server-side status
 * from salt_location_status() at the site's time (the salt_location_now
 * filter may give the instant; called once per office, here, never in the
 * view); a "Get directions" link to openstreetmap.org built from the
 * address text (PRIV01: a plain link that loads nothing until clicked;
 * the salt_location_directions_url filter may replace it); and, when maps
 * are on and the office has both coordinates, its map facade at its zoom,
 * 14 when it has none (ID-3967).
 *
 * The status the server draws is the only status today: locations.json's
 * on-load script that recomputes it from data-hours (SC-007) is deferred
 * to its own Work Item (EP-72 M4 Phase 2), so a cached page keeps the
 * status of the moment it was drawn.
 *
 * STATES (COMPL02, markup zeroState): module off or 0 offices with a title
 * => null (renders NOTHING, no band); 1 => static accordion; N => same,
 * exclusive open via the native <details name> attribute. Offices without
 * a title are broken rows: skipped + logged.
 *
 * Schema (SEO01): one LocalBusiness node per office via salt_schema_add(),
 * flagged 'salt_office' => true so the graph builder links it to the brand
 * Organization via parentOrganization and never lets it replace the brand.
 * openingHoursSpecification derives from the SAME stored rows that drive
 * the status (TEST03); appointment-only days are omitted from it.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current locations Flexible Content row to its view props.
 *
 * Props (contract view-props/locations.json):
 *   eyebrow   string|null  Contract `eyebrow`. Null when blank.
 *   heading   string|null  Contract `heading`. Null when blank.
 *   offices   array<int, array{id: string, title: string, address: string[],
 *                              phone: string|null, phoneHref: string|null,
 *                              email: string|null, hours: array, opening_hours: array,
 *                              status: string, coordinates: array{lat: float, lng: float, zoom: int}|null,
 *                              directionsHref: string|null}>
 *                          Contract `offices` (office), in query order; never
 *                          empty. Per office: title trimmed; address the
 *                          non-empty trimmed lines (street's one or two
 *                          lines, locality, region, postalCode, country);
 *                          phone/email trimmed, null when blank; phoneHref
 *                          'tel:' + dialable digits, null when none survive
 *                          (the number is drawn as text); hours the valid
 *                          rows as data-hours carries them ({day, open,
 *                          close} or {day, appointment: true}); status
 *                          open|appointment|closed|unknown; coordinates
 *                          null unless maps are on and both lat and lng
 *                          are set (zoom: the stored zoom, else 14).
 *                          opening_hours is WordPress-local: the stored rows
 *                          as they are, for the opening-hours component.
 *   timezone  string       Contract `timezone`: the site's zone name (data-timezone).
 *   labels    array<string,string> salt_contract_labels().
 *
 * Contract `maps` is folded into each office's coordinates.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_locations( array $context ): ?array {
	if ( ! salt_module_enabled( 'location' ) ) {
		return null; // Module off: skip render entirely (§10b).
	}

	$sub     = static fn( string $field ): string => function_exists( 'get_sub_field' ) && is_string( get_sub_field( $field ) ) ? (string) get_sub_field( $field ) : '';
	$heading = salt_nullable_text( $sub( 'heading' ) );

	$office_posts = array();
	foreach ( salt_resolve_query( salt_query_controls_from_query(), 'location' ) as $office_post ) {
		if ( '' === trim( (string) $office_post->post_title ) ) {
			salt_log( sprintf( 'locations: office %d has no title — skipped (broken row).', (int) $office_post->ID ) );
			continue;
		}
		$office_posts[] = $office_post;
	}

	if ( array() === $office_posts ) {
		return null; // 0-state: skip the section entirely (COMPL02) — never a blank band.
	}

	// Wrapper inputs: read here, where the pre-split template read them
	// (before any per-office field read, status or schema).
	$section = array_merge(
		salt_section_contract_args( 'locations', $context ),
		// A section with no heading is a div and must not claim the page H1 (A11Y01).
		array( 'has_heading' => null !== $heading )
	);

	$timezone = wp_timezone();
	$now      = apply_filters( 'salt_location_now', null );
	$now      = $now instanceof DateTimeImmutable ? $now : null;
	$maps     = salt_maps_enabled();

	// Storage day key => schema.org day (same key set salt_location_status() accepts).
	$schema_days = array(
		'monday'    => 'Monday',
		'tuesday'   => 'Tuesday',
		'wednesday' => 'Wednesday',
		'thursday'  => 'Thursday',
		'friday'    => 'Friday',
		'saturday'  => 'Saturday',
		'sunday'    => 'Sunday',
	);

	$offices = array();
	foreach ( $office_posts as $office_post ) {
		$office_id = (int) $office_post->ID;
		$name      = trim( (string) $office_post->post_title );

		$address = get_field( 'address', $office_id );
		$address = is_array( $address ) ? $address : array();
		$phone   = trim( (string) get_field( 'phone', $office_id ) );
		$email   = trim( (string) get_field( 'email', $office_id ) );
		$hours   = get_field( 'hours', $office_id );
		$hours   = is_array( $hours ) ? $hours : array();

		// The single status seam (live time unless salt_location_now gives one). Once per office.
		$status = salt_location_status( $hours, $now, $timezone );

		// The street holds up to two lines, one each; then the other parts.
		$street_lines  = preg_split( '/\R/', (string) ( $address['street'] ?? '' ) );
		$street_lines  = array_values( array_filter( array_map( 'trim', false === $street_lines ? array() : $street_lines ), static fn( string $line ): bool => '' !== $line ) );
		$address_lines = $street_lines;
		foreach ( array( 'locality', 'region', 'postalCode', 'country' ) as $part ) {
			$value = trim( (string) ( $address[ $part ] ?? '' ) );
			if ( '' !== $value ) {
				$address_lines[ $part ] = $value;
			}
		}

		// Directions from the address text — open data, no API key; a
		// plain link that loads nothing until clicked (PRIV01).
		$directions_url = array() === $address_lines
			? ''
			: 'https://www.openstreetmap.org/directions?to=' . rawurlencode( implode( ', ', $address_lines ) );
		$directions_url = (string) apply_filters( 'salt_location_directions_url', $directions_url, $office_id );

		// --- Schema node (SEO01) — from the SAME rows as the pill (TEST03).
		$node = array(
			'@type'       => 'LocalBusiness',
			'salt_office' => true,
			'name'        => $name,
		);

		if ( array() !== $address_lines ) {
			$postal = array( '@type' => 'PostalAddress' );
			$street = implode( ', ', $street_lines );
			if ( '' !== $street ) {
				$postal['streetAddress'] = $street;
			}
			foreach ( array(
				'locality'   => 'addressLocality',
				'region'     => 'addressRegion',
				'postalCode' => 'postalCode',
				'country'    => 'addressCountry',
			) as $part => $schema_key ) {
				if ( isset( $address_lines[ $part ] ) ) {
					$postal[ $schema_key ] = $address_lines[ $part ];
				}
			}
			$node['address'] = $postal;
		}

		if ( '' !== $phone ) {
			$node['telephone'] = $phone;
		}
		if ( '' !== $email ) {
			$node['email'] = $email;
		}

		// A day with ANY appointment-only row renders as "By appointment"
		// (salt_location_status semantics) — the WHOLE day must be
		// excluded from openingHoursSpecification, or the schema would
		// publish hours the page contradicts (pill/schema agreement).
		$appointment_days = array();
		foreach ( $hours as $row ) {
			if ( is_array( $row ) && ! empty( $row['appointment'] ) && is_string( $row['day'] ?? null ) ) {
				$appointment_days[ strtolower( $row['day'] ) ] = true;
			}
		}

		$specs = array();
		foreach ( $hours as $row ) {
			// Same validation as salt_location_status(), which has
			// already logged any malformed rows — skip silently here.
			$day = is_array( $row ) && is_string( $row['day'] ?? null ) ? strtolower( $row['day'] ) : '';
			if ( ! isset( $schema_days[ $day ] ) || isset( $appointment_days[ $day ] ) ) {
				continue; // Appointment-only DAYS are omitted from openingHoursSpecification.
			}
			if ( null === salt_location_minutes( $row['open'] ?? null ) || null === salt_location_minutes( $row['close'] ?? null ) ) {
				continue;
			}
			$specs[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => 'https://schema.org/' . $schema_days[ $day ],
				'opens'     => (string) $row['open'],
				'closes'    => (string) $row['close'],
			);
		}
		if ( array() !== $specs ) {
			$node['openingHoursSpecification'] = $specs;
		}

		salt_schema_add( $node );

		// Rule 5: the dialable-phone test decides the markup, so it is made here.
		$dialable = (string) preg_replace( '/[^0-9+]/', '', $phone );

		// data-hours: the rows the status read, as the contract writes them.
		$hours_rows = array();
		foreach ( $hours as $row ) {
			$day = is_array( $row ) && is_string( $row['day'] ?? null ) ? strtolower( $row['day'] ) : '';
			if ( ! isset( $schema_days[ $day ] ) ) {
				continue;
			}
			if ( ! empty( $row['appointment'] ) ) {
				$hours_rows[] = array(
					'day'         => $day,
					'appointment' => true,
				);
			} elseif ( null !== salt_location_minutes( $row['open'] ?? null ) && null !== salt_location_minutes( $row['close'] ?? null ) ) {
				$hours_rows[] = array(
					'day'   => $day,
					'open'  => (string) $row['open'],
					'close' => (string) $row['close'],
				);
			}
		}

		// The map (ID-3967): both coordinates set, at the stored zoom or the
		// contract's 14 (collections/locations.json zoom default).
		$lat         = get_field( 'lat', $office_id );
		$lng         = get_field( 'lng', $office_id );
		$zoom        = get_field( 'zoom', $office_id );
		$coordinates = null;
		if ( $maps && is_numeric( $lat ) && is_numeric( $lng ) ) {
			$coordinates = array(
				'lat'  => (float) $lat,
				'lng'  => (float) $lng,
				'zoom' => is_numeric( $zoom ) ? (int) $zoom : 14,
			);
		}

		$offices[] = array(
			'id'             => (string) $office_id,
			'title'          => $name,
			'address'        => array_values( $address_lines ),
			'phone'          => '' !== $phone ? $phone : null,
			'phoneHref'      => '' !== $phone && '' !== $dialable ? 'tel:' . $dialable : null,
			'email'          => '' !== $email ? $email : null,
			'hours'          => $hours_rows,
			'opening_hours'  => $hours,
			'status'         => (string) $status['status'],
			'coordinates'    => $coordinates,
			'directionsHref' => '' !== $directions_url ? $directions_url : null,
		);
	}

	return array(
		'section' => $section,
		'props'   => array(
			'eyebrow'  => salt_nullable_text( $sub( 'eyebrow' ) ),
			'heading'  => $heading,
			'offices'  => $offices,
			'timezone' => $timezone->getName(),
			'labels'   => salt_contract_labels(),
		),
	);
}
