<?php
/**
 * Section: Gallery (spec §10 #16; FAIL02).
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
 *
 * FIGURE RULE: there is NO x-figure component — this section renders
 * <figure>/<figcaption> directly, with x-image (the single image authority)
 * inside the figure. Captions come from the attachment caption
 * (wp_get_attachment_caption) when the 'captions' toggle is on; empty
 * captions render no <figcaption>.
 *
 * States (COMPL02): 0 resolvable images => renders NOTHING (no section
 * band); broken/malformed image entries are skipped + logged (DATA05).
 * 1 image => ONE static full-width figure in EVERY layout — no track, no
 * grid, no [data-salt-carousel], so the JS module never initialises on a
 * single image. N => the chosen layout.
 *
 * Headings (A11Y01): the layout has no heading field — has_heading is
 * always false, so a gallery never claims the page H1 (the page-level
 * fallback supplies one when a gallery leads the page).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_layout = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'layout' ) : '';
if ( ! in_array( $salt_layout, array( 'grid', 'masonry', 'carousel' ), true ) ) {
	if ( '' !== $salt_layout ) {
		salt_log( sprintf( 'section gallery: invalid layout "%s" — using grid.', $salt_layout ) );
	}
	$salt_layout = 'grid';
}

$salt_columns = function_exists( 'get_sub_field' ) ? (int) get_sub_field( 'columns' ) : 0;
if ( ! in_array( $salt_columns, array( 2, 3, 4 ), true ) ) {
	if ( 0 !== $salt_columns ) {
		salt_log( sprintf( 'section gallery: invalid columns "%d" — using 3.', $salt_columns ) );
	}
	$salt_columns = 3;
}

$salt_captions = function_exists( 'get_sub_field' ) ? (bool) get_sub_field( 'captions' ) : false;

$salt_ids_raw = function_exists( 'get_sub_field' ) ? get_sub_field( 'images' ) : null;
$salt_ids_raw = is_array( $salt_ids_raw ) ? $salt_ids_raw : array();

// Normalise to resolvable attachment IDs; broken entries skipped + logged.
$salt_items = array();
foreach ( $salt_ids_raw as $salt_raw ) {
	$salt_id = is_array( $salt_raw ) ? (int) ( $salt_raw['ID'] ?? 0 ) : (int) $salt_raw;
	if ( $salt_id <= 0 ) {
		salt_log( 'section gallery: malformed image entry — skipped.' );
		continue;
	}
	if ( null === salt_image_data( $salt_id, 'gallery' ) ) {
		continue; // Unresolvable attachment: salt_image_data() already logged (DATA05).
	}

	$salt_caption = '';
	if ( $salt_captions && function_exists( 'wp_get_attachment_caption' ) ) {
		$salt_caption = trim( (string) wp_get_attachment_caption( $salt_id ) );
	}

	$salt_items[] = array(
		'id'      => $salt_id,
		'caption' => $salt_caption,
	);
}

if ( array() === $salt_items ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

/*
 * Build one <figure> (x-figure does not exist — rendered directly here,
 * x-image inside; see FIGURE RULE in the file docblock).
 */
$salt_figure = static function ( array $item, string $sizes_context, string $classes = '' ): string {
	$html  = '<figure class="' . esc_attr( salt_classnames( array( 'x-gallery-item', $classes ) ) ) . '">';
	$html .= salt_get_component(
		'image',
		array(
			'image_id'      => $item['id'],
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

salt_section(
	array_merge(
		salt_section_wrapper_args( 'gallery', $args ?? array() ),
		// No heading field: never claims the page H1 (A11Y01).
		array( 'has_heading' => false )
	),
	static function ( int $level ) use ( $salt_items, $salt_layout, $salt_columns, $salt_figure ): void {
		unset( $level ); // No headings in a gallery (see file docblock).

		if ( 1 === count( $salt_items ) ) {
			// 1-state: one static figure in EVERY layout — no track, no
			// grid, no JS trigger attribute (COMPL02/FAIL02).
			printf(
				'<div class="x-gallery-single">%s</div>',
				$salt_figure( $salt_items[0], 'full' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered figure HTML; escapes at the point each value is printed.
			);
			return;
		}

		if ( 'carousel' === $salt_layout ) {
			// No-JS base (FAIL02): a keyboard-focusable CSS scroll-snap
			// track. gallery.js keys off data-salt-carousel and injects its
			// prev/next buttons — none exist in this markup.
			$salt_slides = '';
			foreach ( $salt_items as $salt_item ) {
				$salt_slides .= '<div class="x-gallery-slide w-4/5 shrink-0 snap-start sm:w-3/5 lg:w-2/5">' . $salt_figure( $salt_item, 'gallery' ) . '</div>';
			}
			printf(
				'<div class="x-gallery-track flex snap-x snap-mandatory gap-4 overflow-x-auto pb-4" data-salt-carousel role="region" tabindex="0" aria-label="%1$s" data-salt-prev-label="%2$s" data-salt-next-label="%3$s">%4$s</div>',
				esc_attr__( 'Image gallery', 'salt' ),
				esc_attr__( 'Previous images', 'salt' ),
				esc_attr__( 'Next images', 'salt' ),
				$salt_slides // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered figure HTML; escapes at the point each value is printed.
			);
			return;
		}

		if ( 'masonry' === $salt_layout ) {
			// Pure CSS column flow — no JS hook by design (see docblock).
			$salt_column_classes = array(
				2 => 'columns-2',
				3 => 'columns-2 lg:columns-3',
				4 => 'columns-2 lg:columns-4',
			);
			$salt_figures        = '';
			foreach ( $salt_items as $salt_item ) {
				$salt_figures .= $salt_figure( $salt_item, 'gallery', 'mb-4 break-inside-avoid' );
			}
			printf(
				'<div class="x-gallery-masonry gap-4 %1$s">%2$s</div>',
				esc_attr( $salt_column_classes[ $salt_columns ] ),
				$salt_figures // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered figure HTML; escapes at the point each value is printed.
			);
			return;
		}

		$salt_grid_classes = array(
			2 => 'sm:grid-cols-2',
			3 => 'sm:grid-cols-2 lg:grid-cols-3',
			4 => 'sm:grid-cols-2 lg:grid-cols-4',
		);
		$salt_figures      = '';
		foreach ( $salt_items as $salt_item ) {
			$salt_figures .= $salt_figure( $salt_item, 'gallery' );
		}
		printf(
			'<div class="x-gallery-grid grid gap-4 %1$s">%2$s</div>',
			esc_attr( $salt_grid_classes[ $salt_columns ] ),
			$salt_figures // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered figure HTML; escapes at the point each value is printed.
		);
	}
);
