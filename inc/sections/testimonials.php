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
 * Schema: none. A Review node needs the service it reviews (SEO02: never
 * attached to the Organization), and salt-contract 1.0.0-rc.7 dropped the
 * testimonial's service (collections/testimonials.json, dropped), so no
 * testimonial queues a node until something records what it reviews.
 *
 * Fields (collections/testimonials.json): name is the title, photo the
 * featured image; quote, role, company and rating are the generated
 * group's. The quote card draws the photo only, never a logo.
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
 *   items    array<int, array{author: string, quote: string, role: string, company: string, rating: float, photo_id: int}>
 *                         WordPress-local (contract `cards`): the
 *                         testimonials in query order; never empty. author
 *                         is the contract's name (the title). rating is
 *                         clamped to 0..5 and kept in half stars (the
 *                         card draws 4.5 as 90% of five stars);
 *                         photo_id is the photo's (featured image's)
 *                         attachment ID, 0 for none. The view renders the
 *                         card components itself (rule 3).
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
		// Half stars (rc.7 testimonials.json rating: 1 to 5, step 0.5): the
		// stored value is kept as stored, only clamped to 0..5.
		$rating = is_numeric( $rating_raw ) ? (float) $rating_raw : 0.0;
		if ( $rating < 0 || $rating > 5 ) {
			salt_log( sprintf( 'testimonials: rating %s on testimonial %d out of range — clamped to 0..5.', (string) $rating, (int) $post_item->ID ) );
			$rating = max( 0.0, min( 5.0, $rating ) );
		}

		$items[] = array(
			'author'   => (string) $post_item->post_title,
			'quote'    => $quote,
			'role'     => (string) get_field( 'role', $post_item->ID ),
			'company'  => (string) get_field( 'company', $post_item->ID ),
			'rating'   => $rating,
			'photo_id' => (int) get_post_thumbnail_id( $post_item ),
		);
	}

	if ( array() === $items ) {
		return null; // 0-state: skip the section entirely (COMPL02).
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
			'items'   => $items,
		),
	);
}
