<?php
/**
 * View: Gallery (spec §10 #16; FAIL02).
 *
 * Draws the props from salt_resolve_section_gallery() (inc/sections/
 * gallery.php), inside the x-section wrapper the shared entry opens. No
 * field reads, image validation or logging here: a child theme overriding
 * this file overrides the markup only.
 *
 * Layouts:
 *  - grid:     CSS grid, 2/3/4 columns.
 *  - masonry:  pure-CSS column flow (columns-N + break-inside-avoid) —
 *              deliberately NO JS hook of any kind.
 *  - carousel: CSS scroll-snap track that is FULLY functional without JS
 *              (FAIL02) — every image reachable by scroll/keyboard. The
 *              [data-salt-carousel] attribute is the only trigger for the
 *              assets/src/js/sections/gallery.js enhancement, which injects
 *              prev/next buttons (none exist in this markup — no dead
 *              buttons no-JS).
 * 1 item => ONE static full-width figure in EVERY layout — no track, no
 * grid, no [data-salt-carousel], so the JS module never initialises on a
 * single image (COMPL02/FAIL02).
 *
 * FIGURE RULE: there is NO x-figure component — this view renders
 * <figure>/<figcaption> directly, with x-image (the single image authority)
 * inside the figure. Empty captions render no <figcaption>.
 *
 * $args:
 *   props  array{ layout: string, columns: int, captions: bool,
 *                 items: array<int, array{image_id: int, caption: string}> }
 *          items is never empty; image_id is a resolvable attachment ID.
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *          (no headings in a gallery: headingLevel is unused).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

/*
 * Build one <figure> (x-figure does not exist — rendered directly here,
 * x-image inside; see FIGURE RULE in the file docblock).
 */
$salt_figure = static function ( array $item, string $sizes_context, string $classes = '' ): string {
	$html  = '<figure class="' . esc_attr( salt_classnames( array( 'x-gallery-item', $classes ) ) ) . '">';
	$html .= salt_get_component(
		'image',
		array(
			'image_id'      => $item['image_id'],
			'alt'           => '',
			'sizes_context' => $sizes_context,
			'classes'       => 'w-full rounded-lg',
		)
	);
	if ( '' !== $item['caption'] ) {
		$html .= '<figcaption class="mt-2 text-sm text-ink-muted">' . esc_html( $item['caption'] ) . '</figcaption>';
	}
	$html .= '</figure>';
	return $html;
};

if ( 1 === count( $salt_props['items'] ) ) {
	// 1-state: one static figure in EVERY layout — no track, no
	// grid, no JS trigger attribute (COMPL02/FAIL02).
	printf(
		'<div class="x-gallery-single">%s</div>',
		$salt_figure( $salt_props['items'][0], 'full' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered figure HTML; escapes at the point each value is printed.
	);
} elseif ( 'carousel' === $salt_props['layout'] ) {
	// No-JS base (FAIL02): a keyboard-focusable CSS scroll-snap
	// track. gallery.js keys off data-salt-carousel and injects its
	// prev/next buttons — none exist in this markup.
	$salt_slides = '';
	foreach ( $salt_props['items'] as $salt_item ) {
		$salt_slides .= '<div class="x-gallery-slide w-4/5 shrink-0 snap-start sm:w-3/5 lg:w-2/5">' . $salt_figure( $salt_item, 'gallery' ) . '</div>';
	}
	printf(
		'<div class="x-gallery-track flex snap-x snap-mandatory gap-4 overflow-x-auto pb-4" data-salt-carousel="gallery" role="region" tabindex="0" aria-label="%1$s" data-salt-prev-label="%2$s" data-salt-next-label="%3$s">%4$s</div>',
		esc_attr__( 'Image gallery', 'salt' ),
		esc_attr__( 'Previous images', 'salt' ),
		esc_attr__( 'Next images', 'salt' ),
		$salt_slides // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered figure HTML; escapes at the point each value is printed.
	);
} elseif ( 'masonry' === $salt_props['layout'] ) {
	// Pure CSS column flow — no JS hook by design (see docblock).
	$salt_column_classes = array(
		2 => 'columns-2',
		3 => 'columns-2 lg:columns-3',
		4 => 'columns-2 lg:columns-4',
	);
	$salt_figures        = '';
	foreach ( $salt_props['items'] as $salt_item ) {
		$salt_figures .= $salt_figure( $salt_item, 'gallery', 'mb-4 break-inside-avoid' );
	}
	printf(
		'<div class="x-gallery-masonry gap-4 %1$s">%2$s</div>',
		esc_attr( $salt_column_classes[ $salt_props['columns'] ] ),
		$salt_figures // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered figure HTML; escapes at the point each value is printed.
	);
} else {
	$salt_grid_classes = array(
		2 => 'sm:grid-cols-2',
		3 => 'sm:grid-cols-2 lg:grid-cols-3',
		4 => 'sm:grid-cols-2 lg:grid-cols-4',
	);
	$salt_figures      = '';
	foreach ( $salt_props['items'] as $salt_item ) {
		$salt_figures .= $salt_figure( $salt_item, 'gallery' );
	}
	printf(
		'<div class="x-gallery-grid grid gap-4 %1$s">%2$s</div>',
		esc_attr( $salt_grid_classes[ $salt_props['columns'] ] ),
		$salt_figures // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered figure HTML; escapes at the point each value is printed.
	);
}
