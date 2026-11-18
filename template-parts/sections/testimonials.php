<?php
/**
 * View: Testimonials (spec §10 #8, Appendix A.2) — a grid, or a CSS
 * scroll-snap slider whose base markup is fully functional without JS
 * (FAIL02).
 *
 * Draws the props from salt_resolve_section_testimonials() (inc/sections/
 * testimonials.php), inside the x-section wrapper the shared entry opens.
 * The view renders the card components itself (the resolver returns data
 * only). assets/src/js/sections/testimonials.js keys off the
 * data-salt-testimonials attribute on the track and injects the prev/next
 * buttons as a progressive enhancement. No field reads, queries, schema
 * or logging here: a child theme overriding this file overrides the
 * markup only.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null,
 *                 layout: 'grid'|'slider',
 *                 items: array<int, array{author: string, quote: string, role: string,
 *                          company: string, rating: int, photo_id: int}> }
 *          items is never empty; rating is 0..5; photo_id 0 = no photo.
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

// Render the cards (escaping happens inside the components).
$salt_cards = array();
foreach ( $salt_props['items'] as $salt_item ) {
	// The photo only, never a logo (salt-contract collections/testimonials.json).
	$salt_photo_html = '';
	if ( $salt_item['photo_id'] > 0 ) {
		$salt_photo_html = salt_get_component(
			'image',
			array(
				'image_id'      => $salt_item['photo_id'],
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
			'media'   => $salt_photo_html,
		)
	);
}

if ( 1 === count( $salt_cards ) ) {
	// 1-state: a single static card — no track, no JS trigger (COMPL02/FAIL02).
	printf(
		'<div class="x-testimonials-single max-w-2xl">%s</div>',
		$salt_cards[0] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component HTML rendered above; escapes at the point each value is printed.
	);
} elseif ( 'slider' === $salt_props['layout'] ) {
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
		$salt_track_items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component HTML rendered above; escapes at the point each value is printed.
	);
} else {
	printf(
		'<div class="x-testimonials-grid grid gap-6 sm:grid-cols-2 lg:grid-cols-3">%s</div>',
		implode( '', $salt_cards ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component HTML rendered above; escapes at the point each value is printed.
	);
}
