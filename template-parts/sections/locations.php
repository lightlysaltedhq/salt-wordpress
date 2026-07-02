<?php
/**
 * Section: Locations (spec §10 #20, Appendix A.6) — the CASW pattern.
 *
 * A zero-JS native <details> accordion of offices: summary = office name +
 * server-side status pill from salt_location_status() (no $now passed —
 * live "now" in the venue/site timezone); body = address, tel:/mailto:
 * links, x-opening-hours and a "Get directions" link. 'unknown' status
 * (no hours) renders NO pill (COMPL02); the pill's non-colour signal is
 * its label text itself.
 *
 * MARKUP COUPLING: the office rows deliberately reuse x-accordion's
 * details/summary class hooks (x-accordion-item / x-accordion-summary /
 * x-accordion-chevron / x-accordion-content — see components/accordion.php)
 * so accordion styling applies fleet-wide, but are built directly because
 * the body is rich composed markup, not a sanitised HTML string. A markup
 * change in accordion.php must be mirrored here (both snapshot-guarded).
 *
 * PRIV01 NOTE: the "Get directions" href points at openstreetmap.org —
 * an external LINK explicitly allowed by the build brief (open data, no
 * API key). It is a plain <a href>: NOTHING loads from that origin unless
 * the visitor deliberately clicks. No map/tiles in v1 (maps deferred).
 *
 * TEASER RULE (§6): the location CPT is not public (no archive URL), so
 * no "view all" link renders — the section is the canonical surface.
 *
 * STATES (COMPL02): 0 renderable offices => renders NOTHING; 1 => static
 * accordion (native <details>, inherently JS-free); N => same, exclusive
 * open via the native <details name> attribute. Offices without a title
 * are broken rows: skipped + logged.
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

if ( ! salt_module_enabled( 'location' ) ) {
	return; // Module off: skip render entirely (§10b).
}

$salt_heading = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'heading' ) : '';
$salt_eyebrow = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'eyebrow' ) : '';

$salt_offices = array();
foreach ( salt_resolve_query( salt_query_controls_from_row(), 'location' ) as $salt_office ) {
	if ( '' === trim( (string) $salt_office->post_title ) ) {
		salt_log( sprintf( 'locations: office %d has no title — skipped (broken row).', (int) $salt_office->ID ) );
		continue;
	}
	$salt_offices[] = $salt_office;
}

if ( array() === $salt_offices ) {
	return; // 0-state: skip the section entirely (COMPL02) — never a blank band.
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'locations', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_heading, $salt_eyebrow, $salt_offices ): void {
		if ( '' !== trim( $salt_eyebrow ) ) {
			salt_component(
				'eyebrow',
				array(
					'text'    => $salt_eyebrow,
					'classes' => 'mb-2',
				)
			);
		}
		if ( '' !== trim( $salt_heading ) ) {
			salt_component(
				'heading',
				array(
					'text'    => $salt_heading,
					'level'   => $level,
					'classes' => 'mb-6',
				)
			);
		}

		// Status => pill role-token text class. 'unknown' is absent on
		// purpose: no hours means NO pill (COMPL02). The label text is the
		// non-colour signal (A11Y: never colour alone).
		$salt_pill_classes = array(
			'open'        => 'text-success',
			'appointment' => 'text-info',
			'closed'      => 'text-ink-muted',
		);

		// Storage day key => schema.org day (same key set salt_location_status() accepts).
		$salt_schema_days = array(
			'monday'    => 'Monday',
			'tuesday'   => 'Tuesday',
			'wednesday' => 'Wednesday',
			'thursday'  => 'Thursday',
			'friday'    => 'Friday',
			'saturday'  => 'Saturday',
			'sunday'    => 'Sunday',
		);

		echo '<div class="x-locations flex flex-col" data-section-component="locations">';

		foreach ( $salt_offices as $salt_office ) {
			$salt_office_id = (int) $salt_office->ID;
			$salt_name      = trim( (string) $salt_office->post_title );

			$salt_address = get_field( 'address', $salt_office_id );
			$salt_address = is_array( $salt_address ) ? $salt_address : array();
			$salt_phone   = trim( (string) get_field( 'phone', $salt_office_id ) );
			$salt_email   = trim( (string) get_field( 'email', $salt_office_id ) );
			$salt_hours   = get_field( 'opening_hours', $salt_office_id );
			$salt_hours   = is_array( $salt_hours ) ? $salt_hours : array();

			// The single status seam (no $now in the partial: live time).
			$salt_status = salt_location_status( $salt_hours );

			$salt_address_lines = array();
			foreach ( array( 'line1', 'line2', 'city', 'region', 'postcode', 'country' ) as $salt_part ) {
				$salt_value = trim( (string) ( $salt_address[ $salt_part ] ?? '' ) );
				if ( '' !== $salt_value ) {
					$salt_address_lines[ $salt_part ] = $salt_value;
				}
			}

			// Directions from the address text — open data, no API key; a
			// plain link that loads nothing until clicked (PRIV01 note above).
			$salt_directions_url = array() === $salt_address_lines
				? ''
				: 'https://www.openstreetmap.org/directions?to=' . rawurlencode( implode( ', ', $salt_address_lines ) );

			// --- Schema node (SEO01) — from the SAME rows as the pill (TEST03).
			$salt_node = array(
				'@type'       => 'LocalBusiness',
				'salt_office' => true,
				'name'        => $salt_name,
			);

			if ( array() !== $salt_address_lines ) {
				$salt_postal = array( '@type' => 'PostalAddress' );
				$salt_street = implode( ', ', array_filter( array( $salt_address_lines['line1'] ?? '', $salt_address_lines['line2'] ?? '' ) ) );
				if ( '' !== $salt_street ) {
					$salt_postal['streetAddress'] = $salt_street;
				}
				foreach ( array(
					'city'     => 'addressLocality',
					'region'   => 'addressRegion',
					'postcode' => 'postalCode',
					'country'  => 'addressCountry',
				) as $salt_part => $salt_schema_key ) {
					if ( isset( $salt_address_lines[ $salt_part ] ) ) {
						$salt_postal[ $salt_schema_key ] = $salt_address_lines[ $salt_part ];
					}
				}
				$salt_node['address'] = $salt_postal;
			}

			if ( '' !== $salt_phone ) {
				$salt_node['telephone'] = $salt_phone;
			}
			if ( '' !== $salt_email ) {
				$salt_node['email'] = $salt_email;
			}

			$salt_specs = array();
			foreach ( $salt_hours as $salt_row ) {
				// Same validation as salt_location_status(), which has
				// already logged any malformed rows — skip silently here.
				$salt_day = is_array( $salt_row ) && is_string( $salt_row['day'] ?? null ) ? strtolower( $salt_row['day'] ) : '';
				if ( ! isset( $salt_schema_days[ $salt_day ] ) || ! empty( $salt_row['appointment_only'] ) ) {
					continue; // Appointment-only days are omitted from openingHoursSpecification.
				}
				if ( null === salt_location_minutes( $salt_row['open'] ?? null ) || null === salt_location_minutes( $salt_row['close'] ?? null ) ) {
					continue;
				}
				$salt_specs[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => 'https://schema.org/' . $salt_schema_days[ $salt_day ],
					'opens'     => (string) $salt_row['open'],
					'closes'    => (string) $salt_row['close'],
				);
			}
			if ( array() !== $salt_specs ) {
				$salt_node['openingHoursSpecification'] = $salt_specs;
			}

			salt_schema_add( $salt_node );

			// --- Office markup (x-accordion class coupling — see docblock).
			?>
			<details class="x-accordion-item group border-b border-border" name="locations">
				<summary class="x-accordion-summary list-none flex items-center justify-between gap-4 py-4 font-medium cursor-pointer">
					<span class="flex flex-wrap items-center gap-x-3 gap-y-1">
						<?php
						salt_component(
							'heading',
							array(
								'text'    => $salt_name,
								'level'   => $level + 1,
								'size'    => 'sm',
								'classes' => 'inline',
							)
						);
						if ( isset( $salt_pill_classes[ $salt_status['status'] ] ) ) {
							printf(
								'<span class="x-status-pill inline-flex items-center rounded-full border border-border px-2 py-0.5 text-sm font-normal %1$s" data-status="%2$s">%3$s</span>',
								esc_attr( $salt_pill_classes[ $salt_status['status'] ] ),
								esc_attr( $salt_status['status'] ),
								esc_html( $salt_status['label'] )
							);
						}
						?>
					</span>
					<span class="x-accordion-chevron transition-transform group-open:rotate-180" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
					</span>
				</summary>
				<div class="x-accordion-content pb-4 text-ink-muted">
					<div class="x-location-card flex flex-col items-start gap-4">
						<?php if ( array() !== $salt_address_lines ) : ?>
						<address class="not-italic">
							<?php foreach ( $salt_address_lines as $salt_line ) : ?>
							<span class="block"><?php echo esc_html( $salt_line ); ?></span>
							<?php endforeach; ?>
						</address>
						<?php endif; ?>
						<?php if ( '' !== $salt_phone || '' !== $salt_email ) : ?>
						<ul class="flex flex-col gap-1">
							<?php if ( '' !== $salt_phone ) : ?>
							<li><a class="text-brand underline" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $salt_phone ) ); ?>"><?php echo esc_html( $salt_phone ); ?></a></li>
							<?php endif; ?>
							<?php if ( '' !== $salt_email ) : ?>
							<li><a class="text-brand underline" href="<?php echo esc_url( 'mailto:' . $salt_email ); ?>"><?php echo esc_html( $salt_email ); ?></a></li>
							<?php endif; ?>
						</ul>
						<?php endif; ?>
						<?php
						if ( array() !== $salt_hours ) {
							salt_component(
								'opening-hours',
								array(
									'hours'   => $salt_hours,
									'compact' => false,
								)
							);
						}
						?>
						<?php if ( '' !== $salt_directions_url ) : ?>
						<a class="text-brand underline" href="<?php echo esc_url( $salt_directions_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: office name. */ __( 'Get directions to %s', 'salt' ), $salt_name ) ); ?>"><?php esc_html_e( 'Get directions', 'salt' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</details>
			<?php
		}

		echo '</div>';
	}
);
