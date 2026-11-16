<?php
/**
 * View: Scroll Carousel (spec §10 #18) — cards on a CSS scroll-snap track
 * that is FULLY functional without JS (FAIL02).
 *
 * Draws the props from salt_resolve_section_scroll_carousel() (inc/
 * sections/scroll-carousel.php), inside the x-section wrapper the shared
 * entry opens. No field reads, validation or logging here: a child theme
 * overriding this file overrides the markup only.
 *
 * Generic cards are simple divs (title one level below the section
 * heading); testimonial cards are blockquote-styled figures. 1 card => one
 * static card (no track, no data-salt-carousel — no JS trigger); N => the
 * snap track. assets/src/js/sections/scroll-carousel.js keys off
 * [data-salt-carousel] and injects prev/next buttons as a progressive
 * enhancement — none exist in this markup, so the no-JS base ships no
 * dead buttons.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null, card_type: string,
 *                 cards: array<int, array{image_id: int, title: string, text: string, role: string}> }
 *          cards is never empty.
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

// Card titles step one level below the section heading (A11Y01).
$salt_card_level = min( 6, $salt_plan['headingLevel'] + 1 );
$salt_card_type  = $salt_props['card_type'];

/**
 * Render one card (escaping happens here / inside the components).
 *
 * @param array{image_id: int, title: string, text: string, role: string} $item Card fields.
 * @return string
 */
$salt_render_card = static function ( array $item ) use ( $salt_card_type, $salt_card_level ): string {
	$image_html = '';
	if ( $item['image_id'] > 0 ) {
		$image_html = salt_get_component(
			'image',
			array(
				'image_id'      => $item['image_id'],
				'alt'           => '',
				'sizes_context' => 'card',
				'classes'       => 'testimonial' === $salt_card_type ? 'size-14 rounded-full object-cover' : 'rounded-md',
			)
		);
	}

	if ( 'testimonial' === $salt_card_type ) {
		// Blockquote-styled card: quote + attribution, never a heading.
		$caption = '<span class="x-scroll-carousel-name font-semibold text-ink">' . esc_html( $item['title'] ) . '</span>';
		if ( '' !== $item['role'] ) {
			$caption .= '<span class="x-scroll-carousel-role block text-ink-muted">' . esc_html( $item['role'] ) . '</span>';
		}

		return '<figure class="x-scroll-carousel-card flex h-full flex-col gap-4 rounded-lg border border-border bg-surface p-6">'
			. $image_html
			. '<blockquote class="x-scroll-carousel-quote text-ink"><p>' . esc_html( $item['text'] ) . '</p></blockquote>'
			. '<figcaption class="x-scroll-carousel-caption mt-auto text-sm">' . $caption . '</figcaption>'
			. '</figure>';
	}

	$title_html = '';
	if ( '' !== $item['title'] ) {
		$title_html = salt_get_component(
			'heading',
			array(
				'text'    => $item['title'],
				'level'   => $salt_card_level,
				'size'    => 'sm',
				'classes' => 'x-scroll-carousel-title',
			)
		);
	}

	$text_html = '' !== $item['text']
		? '<p class="x-scroll-carousel-text text-ink-muted">' . esc_html( $item['text'] ) . '</p>'
		: '';

	return '<div class="x-scroll-carousel-card flex h-full flex-col gap-3 rounded-lg border border-border bg-surface p-6">'
		. $image_html . $title_html . $text_html
		. '</div>';
};

if ( 1 === count( $salt_props['cards'] ) ) {
	// 1-state: one static card — no track, no JS trigger (COMPL02/FAIL02).
	printf(
		'<div class="x-scroll-carousel-single max-w-2xl">%s</div>',
		$salt_render_card( $salt_props['cards'][0] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered card HTML; escapes at the point each value is printed.
	);
} else {
	// No-JS base (FAIL02): a keyboard-focusable CSS scroll-snap track.
	// The JS module keys off data-salt-carousel and injects its prev/next
	// buttons — none exist in this markup.
	$salt_track_items = '';
	foreach ( $salt_props['cards'] as $salt_card ) {
		$salt_track_items .= '<div class="x-scroll-carousel-item w-5/6 shrink-0 snap-start sm:w-2/5 lg:w-1/3">' . $salt_render_card( $salt_card ) . '</div>';
	}
	printf(
		'<div class="x-scroll-carousel-track flex snap-x snap-mandatory gap-6 overflow-x-auto pb-4" data-salt-carousel="scroll-carousel" role="region" tabindex="0" aria-label="%1$s" data-salt-prev-label="%2$s" data-salt-next-label="%3$s">%4$s</div>',
		null !== $salt_props['heading'] ? esc_attr( $salt_props['heading'] ) : esc_attr__( 'Carousel', 'salt' ),
		esc_attr__( 'Previous cards', 'salt' ),
		esc_attr__( 'Next cards', 'salt' ),
		$salt_track_items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered card HTML; escapes at the point each value is printed.
	);
}
