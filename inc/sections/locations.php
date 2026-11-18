<?php
/**
 * Resolver: Locations (spec §10 #20, Appendix A.6) — the logic half of the
 * section; the view is template-parts/sections/locations.php.
 *
 * Offices come from the location CPT via the Principle-7 query funnel.
 * Per office: address, phone, email and opening hours from its fields
 * (salt-contract collections/locations.json: the address shape street, up
 * to two lines, locality, region, postalCode, country; hours rows of day,
 * open, close and appointment). lat, lng and zoom are stored but not drawn
 * yet: this build has no office map (M4);
 * the server-side status from salt_location_status() (no $now passed —
 * live "now" in the venue/site timezone; called once per office, here,
 * never in the view); a "Get directions" link to openstreetmap.org built
 * from the address text (PRIV01: a plain link that loads nothing until
 * clicked).
 *
 * STATES (COMPL02): module off or 0 renderable offices => null (renders
 * NOTHING, no band); 1 => static accordion; N => same, exclusive open via
 * the native <details name> attribute. Offices without a title are broken
 * rows: skipped + logged.
 *
 * Schema (SEO01): one LocalBusiness node per office via salt_schema_add(),
 * flagged 'salt_office' => true so the graph builder links it to the brand
 * Organization via parentOrganization and never lets it replace the brand.
 * openingHoursSpecification derives from the SAME stored rows that drive
 * the status pill (TEST03); appointment-only days are omitted from it.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current locations Flexible Content row to its view props.
 *
 * Props (contract view-props/locations.json; WordPress-local names in snake_case):
 *   eyebrow        string|null  Contract `eyebrow`. Null when blank.
 *   heading        string|null  Contract `heading`. Null when blank.
 *   offices        array<int, array{id: string, title: string, address: string[],
 *                                   phone: string|null, phoneHref: string|null,
 *                                   email: string|null, opening_hours: array,
 *                                   status: string, status_label: string,
 *                                   directionsHref: string|null}>
 *                               Contract `offices` (office), in query order;
 *                               never empty. Per office:
 *                                 id, title (trimmed), address (the non-empty
 *                                 trimmed lines: street's one or two lines,
 *                                 locality, region, postalCode, country),
 *                                 phone, phoneHref
 *                                 ('tel:' + dialable digits, null when none
 *                                 survive: the number is drawn as text),
 *                                 email, status, directionsHref: contract
 *                                 names; phone/email trimmed, null when blank.
 *                                 opening_hours: WordPress-local — the stored
 *                                 hours rows as they are, for x-opening-hours (the
 *                                 contract's `hours` is the normalised
 *                                 data-hours rows).
 *                                 status_label: WordPress-local — the pill text
 *                                 from salt_location_status() ('' for unknown).
 *   details_group  string       WordPress-local: the native <details name>
 *                               group. ALWAYS 'locations-0' (see below).
 *
 * Naming gaps against the contract: `timezone` and `maps` have no
 * WordPress counterpart yet (no maps in this build); an office has no
 * `coordinates`; office `hours` is `opening_hours` (stored rows, not the
 * normalised hours-row list) and the pill label is the extra
 * `status_label`.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_locations( array $context ): ?array {
	if ( ! salt_module_enabled( 'location' ) ) {
		return null; // Module off: skip render entirely (§10b).
	}

	$heading = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'heading' ) : '';
	$eyebrow = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'eyebrow' ) : '';

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
		salt_section_wrapper_args( 'locations', $context ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $heading ) )
	);

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

		// The single status seam (no $now: live time). Once per office.
		$status = salt_location_status( $hours );

		// The street holds up to two lines, one each; then the other parts.
		$street_lines  = preg_split( '/\R/', (string) ( $address['street'] ?? '' ) );
		$street_lines  = array_values( array_filter( array_map( 'trim', false === $street_lines ? array() : $street_lines ), static fn( string $line ): bool => '' !== $line ) );
		$address_lines = array();
		foreach ( array_slice( $street_lines, 0, 2 ) as $index => $line ) {
			$address_lines[ 'street' . $index ] = $line;
		}
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

		// --- Schema node (SEO01) — from the SAME rows as the pill (TEST03).
		$node = array(
			'@type'       => 'LocalBusiness',
			'salt_office' => true,
			'name'        => $name,
		);

		if ( array() !== $address_lines ) {
			$postal = array( '@type' => 'PostalAddress' );
			$street = implode( ', ', array_filter( array( $address_lines['street0'] ?? '', $address_lines['street1'] ?? '' ) ) );
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

		$offices[] = array(
			'id'             => (string) $office_id,
			'title'          => $name,
			'address'        => array_values( $address_lines ),
			'phone'          => '' !== $phone ? $phone : null,
			'phoneHref'      => '' !== $phone && '' !== $dialable ? 'tel:' . $dialable : null,
			'email'          => '' !== $email ? $email : null,
			'opening_hours'  => $hours,
			'status'         => (string) $status['status'],
			'status_label'   => (string) $status['label'],
			'directionsHref' => '' !== $directions_url ? $directions_url : null,
		);
	}

	return array(
		'section' => $section,
		'props'   => array(
			'eyebrow'       => salt_nullable_text( $eyebrow ),
			'heading'       => salt_nullable_text( $heading ),
			'offices'       => $offices,
			'details_group' => 'locations-0', // Pre-split behaviour: always 0 (closure never saw $args). Reported as a Work Item; do not "fix" here.
		),
	);
}
