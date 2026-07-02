<?php
/**
 * Section: Testimonials (spec §10 #8, Appendix A.2; SEO02).
 *
 * CPT-backed (testimonial) through the Principle-7 resolver. Layouts:
 * grid, or a CSS scroll-snap slider whose base markup is FULLY functional
 * without JS (FAIL02) — assets/src/js/sections/testimonials.js injects the
 * prev/next buttons as a progressive enhancement, so the no-JS base ships
 * no dead buttons.
 *
 * States (COMPL02): 0 usable items => renders NOTHING (no section band);
 * 1 item => one static card (no track, no JS trigger attribute); N =>
 * grid or snap track. Rows with an empty quote are skipped + logged.
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

if ( ! salt_module_enabled( 'testimonial' ) ) {
	return; // Layer-2 module gate (§10b): disabled module renders nothing.
}

$salt_sub     = static fn( string $field ): string => function_exists( 'get_sub_field' ) ? (string) get_sub_field( $field ) : '';
$salt_heading = $salt_sub( 'heading' );
$salt_eyebrow = $salt_sub( 'eyebrow' );
$salt_layout  = $salt_sub( 'layout' );
if ( ! in_array( $salt_layout, array( 'grid', 'slider' ), true ) ) {
	$salt_layout = 'grid';
}

$salt_posts = salt_resolve_query( salt_query_controls_from_row(), 'testimonial' );

// Normalise rows; broken/malformed rows are skipped + logged (DATA05).
$salt_items = array();
foreach ( $salt_posts as $salt_post ) {
	$salt_quote = trim( (string) get_field( 'quote', $salt_post->ID ) );
	if ( '' === $salt_quote ) {
		salt_log( sprintf( 'testimonials: testimonial %d ("%s") has no quote — skipped.', (int) $salt_post->ID, (string) $salt_post->post_title ) );
		continue;
	}

	$salt_rating_raw = get_field( 'rating', $salt_post->ID );
	$salt_rating     = is_numeric( $salt_rating_raw ) ? (int) $salt_rating_raw : 0;
	if ( $salt_rating < 0 || $salt_rating > 5 ) {
		salt_log( sprintf( 'testimonials: rating %d on testimonial %d out of range — clamped to 0..5.', $salt_rating, (int) $salt_post->ID ) );
		$salt_rating = max( 0, min( 5, $salt_rating ) );
	}

	$salt_service_raw = get_field( 'service', $salt_post->ID );
	if ( is_array( $salt_service_raw ) ) {
		$salt_service_raw = $salt_service_raw[0] ?? 0;
	}
	if ( $salt_service_raw instanceof WP_Post ) {
		$salt_service_raw = $salt_service_raw->ID;
	}

	$salt_items[] = array(
		'author'     => (string) $salt_post->post_title,
		'quote'      => $salt_quote,
		'role'       => (string) get_field( 'role', $salt_post->ID ),
		'company'    => (string) get_field( 'company', $salt_post->ID ),
		'rating'     => $salt_rating,
		'logo_id'    => (int) get_field( 'logo', $salt_post->ID ),
		'service_id' => (int) $salt_service_raw,
	);
}

if ( array() === $salt_items ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

// Queue Review nodes for testimonials with a VALID related service (SEO02).
foreach ( $salt_items as $salt_item ) {
	if ( $salt_item['service_id'] <= 0 ) {
		continue; // No related service => no Review node (never org-attached).
	}

	$salt_service = get_post( $salt_item['service_id'] );
	if ( null === $salt_service || 'service' !== $salt_service->post_type || 'publish' !== $salt_service->post_status ) {
		salt_log( sprintf( 'testimonials: related service %d is missing or unpublished — Review node skipped (broken relationship).', $salt_item['service_id'] ) );
		continue;
	}

	$salt_node = array(
		'@type'        => 'Review',
		'itemReviewed' => array(
			'@type' => 'Service',
			'name'  => (string) $salt_service->post_title,
			'@id'   => (string) get_permalink( (int) $salt_service->ID ) . '#service',
		),
		'author'       => array(
			'@type' => 'Person',
			'name'  => $salt_item['author'],
		),
		'reviewBody'   => $salt_item['quote'],
	);

	if ( $salt_item['rating'] >= 1 ) {
		$salt_node['reviewRating'] = array(
			'@type'       => 'Rating',
			'ratingValue' => $salt_item['rating'],
			'bestRating'  => 5,
			'worstRating' => 1,
		);
	}

	salt_schema_add( $salt_node );
}

// Pre-render the cards (escaping happens inside the components).
$salt_cards = array();
foreach ( $salt_items as $salt_item ) {
	$salt_logo_html = '';
	if ( $salt_item['logo_id'] > 0 ) {
		$salt_logo_html = salt_get_component(
			'image',
			array(
				'image_id'      => $salt_item['logo_id'],
				'alt'           => '',
				'sizes_context' => 'card',
				'classes'       => 'h-10 w-auto',
			)
		);
	}

	$salt_cards[] = salt_get_component(
		'card-testimonial',
		array(
			'quote'   => $salt_item['quote'],
			'author'  => $salt_item['author'],
			'role'    => $salt_item['role'],
			'company' => $salt_item['company'],
			'rating'  => $salt_item['rating'],
			'media'   => $salt_logo_html,
		)
	);
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'testimonials', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_heading, $salt_eyebrow, $salt_layout, $salt_cards ): void {
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

		if ( 1 === count( $salt_cards ) ) {
			// 1-state: a single static card — no track, no JS trigger (COMPL02/FAIL02).
			printf(
				'<div class="x-testimonials-single max-w-2xl">%s</div>',
				$salt_cards[0] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered component HTML; escapes at the point each value is printed.
			);
			return;
		}

		if ( 'slider' === $salt_layout ) {
			// No-JS base (FAIL02): a keyboard-focusable CSS scroll-snap track.
			// The JS module keys off data-salt-testimonials and injects its
			// prev/next buttons — none exist in this markup.
			$salt_track_items = '';
			foreach ( $salt_cards as $salt_card ) {
				$salt_track_items .= '<div class="x-testimonials-item w-5/6 shrink-0 snap-start sm:w-2/5 lg:w-1/3">' . $salt_card . '</div>';
			}
			printf(
				'<div class="x-testimonials-track flex snap-x snap-mandatory gap-6 overflow-x-auto pb-4" data-salt-testimonials role="region" tabindex="0" aria-label="%1$s" data-salt-prev-label="%2$s" data-salt-next-label="%3$s">%4$s</div>',
				esc_attr__( 'Testimonials', 'salt' ),
				esc_attr__( 'Previous testimonials', 'salt' ),
				esc_attr__( 'Next testimonials', 'salt' ),
				$salt_track_items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered component HTML; escapes at the point each value is printed.
			);
			return;
		}

		printf(
			'<div class="x-testimonials-grid grid gap-6 sm:grid-cols-2 lg:grid-cols-3">%s</div>',
			implode( '', $salt_cards ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered component HTML; escapes at the point each value is printed.
		);
	}
);
