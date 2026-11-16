<?php
/**
 * Resolver: Testimonials (spec §10 #8, Appendix A.2; SEO02) — the logic
 * half of the section; the view is template-parts/sections/testimonials.php.
 *
 * CPT-backed (testimonial) through the Principle-7 resolver. Layouts:
 * grid, or a CSS scroll-snap slider whose base markup is FULLY functional
 * without JS (FAIL02) — assets/src/js/sections/testimonials.js injects the
 * prev/next buttons as a progressive enhancement, so the no-JS base ships
 * no dead buttons.
 *
 * States (COMPL02): module off or 0 usable items => null (renders NOTHING,
 * no section band); 1 item => one static card (no track, no JS trigger
 * attribute); N => grid or snap track. Rows with an empty quote are
 * skipped + logged.
 *
 * Schema (SEO02 — the high-value path): each rendered testimonial WITH a
 * valid related service queues ONE Review node via salt_schema_add():
 * itemReviewed Service ({permalink}#service), author Person (post title),
 * reviewBody, and reviewRating (1–5, clamped) when a rating is set.
 * Testimonials WITHOUT a related service queue NO node — a Review must
 * never attach to the Organization. NO AggregateRating is emitted in v1:
 * scoping a computed rating (per service, across the whole corpus rather
 * than the rows this section happens to show) is deferred, and the graph
 * builder would strip an org-level aggregateRating as self-serving anyway.
 *
 * Teaser rule (§6): the testimonial CPT is display-only (not public), so
 * there is no archive to link to — the section stays bounded by the
 * resolver count (default 6), with no pagination and no "view all" link.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current testimonials Flexible Content row to its view props.
 *
 * Props. The contract folds this layout into collection-showcase (grid) or
 * carousel (slider) (view-props/collection-showcase.json, carousel.json) in
 * a LATER work item; until then only the identical-meaning props carry the
 * contract's names:
 *   eyebrow  string|null  Contract `eyebrow`. Null when blank.
 *   heading  string|null  Contract `heading`. Null when blank.
 *   layout   string       WordPress-local: 'grid' | 'slider' (invalid =>
 *                         'grid'). The contract splits this across two
 *                         sections, so it is not its `layout`.
 *   items    array<int, array{author: string, quote: string, role: string, company: string, rating: int, logo_id: int}>
 *                         WordPress-local (contract `cards`): the
 *                         testimonials in query order; never empty. rating
 *                         is already clamped to 0..5; logo_id is the
 *                         company logo's attachment ID, 0 for none. The
 *                         view renders the card components itself (rule 3).
 *                         The Review nodes are already queued.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_testimonials( array $context ): ?array {
	if ( ! salt_module_enabled( 'testimonial' ) ) {
		return null; // Layer-2 module gate (§10b): disabled module renders nothing.
	}

	$sub     = static fn( string $field ): string => function_exists( 'get_sub_field' ) ? (string) get_sub_field( $field ) : '';
	$heading = $sub( 'heading' );
	$eyebrow = $sub( 'eyebrow' );
	$layout  = salt_testimonials_layout( $sub( 'layout' ) ); // Shared with the contract-id lookup (inc/overrides.php).

	$posts = salt_resolve_query( salt_query_controls_from_row(), 'testimonial' );

	// Normalise rows; broken/malformed rows are skipped + logged (DATA05).
	$items = array();
	foreach ( $posts as $post_item ) {
		$quote = trim( (string) get_field( 'quote', $post_item->ID ) );
		if ( '' === $quote ) {
			salt_log( sprintf( 'testimonials: testimonial %d ("%s") has no quote — skipped.', (int) $post_item->ID, (string) $post_item->post_title ) );
			continue;
		}

		$rating_raw = get_field( 'rating', $post_item->ID );
		$rating     = is_numeric( $rating_raw ) ? (int) $rating_raw : 0;
		if ( $rating < 0 || $rating > 5 ) {
			salt_log( sprintf( 'testimonials: rating %d on testimonial %d out of range — clamped to 0..5.', $rating, (int) $post_item->ID ) );
			$rating = max( 0, min( 5, $rating ) );
		}

		$service_raw = get_field( 'service', $post_item->ID );
		if ( is_array( $service_raw ) ) {
			$service_raw = $service_raw[0] ?? 0;
		}
		if ( $service_raw instanceof WP_Post ) {
			$service_raw = $service_raw->ID;
		}

		$items[] = array(
			'author'     => (string) $post_item->post_title,
			'quote'      => $quote,
			'role'       => (string) get_field( 'role', $post_item->ID ),
			'company'    => (string) get_field( 'company', $post_item->ID ),
			'rating'     => $rating,
			'logo_id'    => (int) get_field( 'logo', $post_item->ID ),
			'service_id' => (int) $service_raw,
		);
	}

	if ( array() === $items ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	// Queue Review nodes for testimonials with a VALID related service (SEO02).
	foreach ( $items as $item ) {
		if ( $item['service_id'] <= 0 ) {
			continue; // No related service => no Review node (never org-attached).
		}

		$service = get_post( $item['service_id'] );
		if ( null === $service || 'service' !== $service->post_type || 'publish' !== $service->post_status ) {
			salt_log( sprintf( 'testimonials: related service %d is missing or unpublished — Review node skipped (broken relationship).', $item['service_id'] ) );
			continue;
		}

		$node = array(
			'@type'        => 'Review',
			'itemReviewed' => array(
				'@type' => 'Service',
				'name'  => (string) $service->post_title,
				'@id'   => (string) get_permalink( (int) $service->ID ) . '#service',
			),
			'author'       => array(
				'@type' => 'Person',
				'name'  => $item['author'],
			),
			'reviewBody'   => $item['quote'],
		);

		if ( $item['rating'] >= 1 ) {
			$node['reviewRating'] = array(
				'@type'       => 'Rating',
				'ratingValue' => $item['rating'],
				'bestRating'  => 5,
				'worstRating' => 1,
			);
		}

		salt_schema_add( $node );
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'testimonials', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'eyebrow' => salt_nullable_text( $eyebrow ),
			'heading' => salt_nullable_text( $heading ),
			'layout'  => $layout,
			'items'   => array_map(
				// The related service only fed the schema above: the view never needs it.
				static fn( array $item ): array => array(
					'author'  => $item['author'],
					'quote'   => $item['quote'],
					'role'    => $item['role'],
					'company' => $item['company'],
					'rating'  => $item['rating'],
					'logo_id' => $item['logo_id'],
				),
				$items
			),
		),
	);
}
