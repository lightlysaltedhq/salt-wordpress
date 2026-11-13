<?php
/**
 * View: Locations (spec §10 #20, Appendix A.6) — the CASW pattern.
 *
 * Draws the props from salt_resolve_section_locations() (inc/sections/
 * locations.php), inside the x-section wrapper the shared entry opens. No
 * field reads, queries, status calls (the clock), schema or logging here:
 * a child theme overriding this file overrides the markup only.
 *
 * A zero-JS native <details> accordion of offices: summary = office name +
 * the status pill ('unknown' status draws NO pill — COMPL02; the pill's
 * non-colour signal is its label text itself); body = address, tel:/mailto:
 * links, x-opening-hours and a "Get directions" link (a plain external
 * link: nothing loads from that origin unless the visitor clicks — PRIV01).
 *
 * MARKUP COUPLING: the office rows deliberately reuse x-accordion's
 * details/summary class hooks (x-accordion-item / x-accordion-summary /
 * x-accordion-chevron / x-accordion-content — see components/accordion.php)
 * so accordion styling applies fleet-wide, but are built directly because
 * the body is rich composed markup, not a sanitised HTML string. A markup
 * change in accordion.php must be mirrored here (both snapshot-guarded).
 * The office block is kept byte for byte, indentation included: it is
 * output, and the snapshots compare it (CONVENTIONS.md rule 1).
 *
 * TEASER RULE (§6): the location CPT is not public (no archive URL), so
 * no "view all" link renders — the section is the canonical surface.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null,
 *                 offices: array<int, array{id: string, title: string, address: string[],
 *                                          phone: string|null, phoneHref: string|null,
 *                                          email: string|null, opening_hours: array,
 *                                          status: string, status_label: string,
 *                                          directionsHref: string|null}>,
 *                 details_group: string }
 *          offices is never empty. details_group names the <details>
 *          group (always 'locations-0' today — see the resolver).
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

if ( null !== $salt_props['eyebrow'] ) {
	salt_component(
		'eyebrow',
		array(
			'text'    => $salt_props['eyebrow'],
			'classes' => 'mb-2',
		)
	);
}
if ( null !== $salt_props['heading'] ) {
	salt_component(
		'heading',
		array(
			'text'    => $salt_props['heading'],
			'level'   => $salt_plan['headingLevel'],
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

echo '<div class="x-locations flex flex-col" data-section-component="locations">';

foreach ( $salt_props['offices'] as $salt_office ) {
	// --- Office markup (x-accordion class coupling — see docblock).
	?>
			<details class="x-accordion-item group border-b border-border" name="<?php echo esc_attr( $salt_props['details_group'] ); ?>">
				<summary class="x-accordion-summary list-none flex items-center justify-between gap-4 py-4 font-medium cursor-pointer">
					<span class="flex flex-wrap items-center gap-x-3 gap-y-1">
						<?php
						// Not a heading: heading semantics inside <summary> are
						// inconsistently exposed to AT; offices are accordion
						// entries, the outline is owned by the section heading.
						printf(
							'<span class="text-lg font-semibold">%s</span>',
							esc_html( $salt_office['title'] )
						);
						if ( isset( $salt_pill_classes[ $salt_office['status'] ] ) ) {
							printf(
								'<span class="x-status-pill inline-flex items-center rounded-full border border-border px-2 py-0.5 text-sm font-normal %1$s" data-status="%2$s">%3$s</span>',
								esc_attr( $salt_pill_classes[ $salt_office['status'] ] ),
								esc_attr( $salt_office['status'] ),
								esc_html( $salt_office['status_label'] )
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
						<?php if ( array() !== $salt_office['address'] ) : ?>
						<address class="not-italic">
							<?php foreach ( $salt_office['address'] as $salt_line ) : ?>
							<span class="block"><?php echo esc_html( $salt_line ); ?></span>
							<?php endforeach; ?>
						</address>
						<?php endif; ?>
						<?php if ( null !== $salt_office['phone'] || null !== $salt_office['email'] ) : ?>
						<ul class="flex flex-col gap-1">
							<?php
							if ( null !== $salt_office['phoneHref'] ) :
								?>
							<li><a class="text-brand underline" href="<?php echo esc_url( $salt_office['phoneHref'] ); ?>"><?php echo esc_html( $salt_office['phone'] ); ?></a></li>
							<?php elseif ( null !== $salt_office['phone'] ) : ?>
							<li><?php echo esc_html( $salt_office['phone'] ); ?></li>
							<?php endif; ?>
							<?php if ( null !== $salt_office['email'] ) : ?>
							<li><a class="text-brand underline" href="<?php echo esc_url( 'mailto:' . $salt_office['email'] ); ?>"><?php echo esc_html( $salt_office['email'] ); ?></a></li>
							<?php endif; ?>
						</ul>
						<?php endif; ?>
						<?php
						if ( array() !== $salt_office['opening_hours'] ) {
							salt_component(
								'opening-hours',
								array(
									'hours'   => $salt_office['opening_hours'],
									'compact' => false,
								)
							);
						}
						?>
						<?php if ( null !== $salt_office['directionsHref'] ) : ?>
						<a class="text-brand underline" href="<?php echo esc_url( $salt_office['directionsHref'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: office name. */ __( 'Get directions to %s', 'salt' ), $salt_office['title'] ) ); ?>"><?php esc_html_e( 'Get directions', 'salt' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</details>
			<?php
}

echo '</div>';
