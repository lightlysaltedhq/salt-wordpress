<?php
/**
 * Section: Media + Text (spec §10 #6).
 *
 * Inline repeater of image/text rows on a md:grid-cols-2 grid; images render
 * through x-image with sizes_context 'half'. Text-only rows (no image) render
 * full-width — never an empty media column.
 *
 * MODE RULE: in 'alternating' mode the media side flips automatically per
 * RENDERED row — even rows media-left, odd rows media-right (0-indexed) —
 * and each row's media_side sub-field is IGNORED. In 'single' mode each
 * row's own media_side ('left' default) decides the column order. The flip
 * is purely visual (md:order-2 on the media cell); DOM order is always
 * media then body, so the reading order never changes.
 *
 * HEADING OUTLINE RULE (A11Y01/AMBIG03): this section has NO heading field
 * of its own — the FIRST rendered row's heading acts as the section heading.
 * has_heading is true only when that first row has a non-empty heading, so
 * a leading headingless row can never consume the page's H1 claim. Inside
 * the callback: row 0's heading renders at $level (H1 only when the wrapper
 * designates this section primary AND the claim succeeds); every LATER
 * row's heading renders at ( 1 === $level ? 2 : $level ), so the outline is
 * h1 → h2 siblings on a primary section and h2 all round otherwise — never
 * a skipped level, never multiple H1s.
 *
 * States (COMPL02): 0 usable rows => renders NOTHING (no section band).
 * A row is usable when it has an image, a heading or content; malformed/
 * empty rows are skipped + logged. No JS in any state.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_mode = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'mode' ) : '';
if ( ! in_array( $salt_mode, array( 'single', 'alternating' ), true ) ) {
	if ( '' !== $salt_mode ) {
		salt_log( sprintf( 'section media-text: invalid mode "%s" — using single.', $salt_mode ) );
	}
	$salt_mode = 'single';
}

$salt_rows_raw = function_exists( 'get_sub_field' ) ? get_sub_field( 'rows' ) : null;
$salt_rows_raw = is_array( $salt_rows_raw ) ? $salt_rows_raw : array();

// Normalise rows; broken/malformed rows are skipped + logged (DATA05 spirit).
$salt_items = array();
foreach ( $salt_rows_raw as $salt_i => $salt_row ) {
	if ( ! is_array( $salt_row ) ) {
		salt_log( sprintf( 'section media-text: row %d is malformed — skipped.', (int) $salt_i ) );
		continue;
	}

	$salt_image_raw = $salt_row['image'] ?? 0;
	$salt_image_id  = is_array( $salt_image_raw ) ? (int) ( $salt_image_raw['ID'] ?? 0 ) : (int) $salt_image_raw;
	$salt_heading   = trim( (string) ( $salt_row['heading'] ?? '' ) );
	$salt_content   = trim( (string) ( $salt_row['content'] ?? '' ) );

	if ( 0 >= $salt_image_id && '' === $salt_heading && '' === $salt_content ) {
		salt_log( sprintf( 'section media-text: row %d has no image, heading or content — skipped.', (int) $salt_i ) );
		continue;
	}

	$salt_cta       = null;
	$salt_cta_raw   = $salt_row['cta'] ?? null;
	$salt_cta_url   = is_array( $salt_cta_raw ) ? trim( (string) ( $salt_cta_raw['url'] ?? '' ) ) : '';
	$salt_cta_title = is_array( $salt_cta_raw ) ? trim( (string) ( $salt_cta_raw['title'] ?? '' ) ) : '';
	if ( is_array( $salt_cta_raw ) && array() !== $salt_cta_raw ) {
		if ( '' !== $salt_cta_url && '' !== $salt_cta_title ) {
			$salt_cta = array(
				'url'    => $salt_cta_url,
				'title'  => $salt_cta_title,
				'target' => '_blank' === ( $salt_cta_raw['target'] ?? '' ) ? '_blank' : '',
			);
		} else {
			salt_log( sprintf( 'section media-text: row %d has a malformed CTA link (url and title required) — CTA skipped.', (int) $salt_i ) );
		}
	}

	$salt_items[] = array(
		'image_id' => $salt_image_id,
		'heading'  => $salt_heading,
		'content'  => $salt_content,
		'side'     => 'right' === ( $salt_row['media_side'] ?? '' ) ? 'right' : 'left',
		'cta'      => $salt_cta,
	);
}

if ( array() === $salt_items ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'media-text', $args ?? array() ),
		// The FIRST rendered row's heading is the section heading; without
		// one this section must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== $salt_items[0]['heading'] )
	),
	static function ( int $level ) use ( $salt_items, $salt_mode ): void {
		echo '<div class="x-media-text-rows flex flex-col gap-12">';

		foreach ( $salt_items as $salt_i => $salt_item ) {
			// HEADING OUTLINE RULE (see file docblock): row 0 at $level,
			// later rows step to h2 when row 0 took the H1.
			$salt_row_level = 0 === $salt_i ? $level : ( 1 === $level ? 2 : $level );

			// MODE RULE (see file docblock): alternating flips per row and
			// ignores media_side; single respects it.
			$salt_side = 'alternating' === $salt_mode
				? ( 0 === $salt_i % 2 ? 'left' : 'right' )
				: $salt_item['side'];

			$salt_body = '';
			if ( '' !== $salt_item['heading'] ) {
				$salt_body .= salt_get_component(
					'heading',
					array(
						'text'    => $salt_item['heading'],
						'level'   => $salt_row_level,
						'classes' => 'mb-4',
					)
				);
			}
			if ( '' !== $salt_item['content'] ) {
				$salt_body .= '<div class="x-prose max-w-prose text-ink">' . salt_sanitize_html( $salt_item['content'] ) . '</div>';
			}
			if ( null !== $salt_item['cta'] ) {
				$salt_body .= '<div class="mt-6">' . salt_get_component(
					'button',
					array(
						'label'  => $salt_item['cta']['title'],
						'url'    => $salt_item['cta']['url'],
						'style'  => 'link-arrow',
						'target' => $salt_item['cta']['target'],
					)
				) . '</div>';
			}

			if ( $salt_item['image_id'] > 0 ) {
				$salt_media = salt_get_component(
					'image',
					array(
						'image_id'      => $salt_item['image_id'],
						'alt'           => '',
						'sizes_context' => 'half',
						'classes'       => 'w-full rounded-lg',
					)
				);
				printf(
					'<div class="x-media-text-row grid items-center gap-8 md:grid-cols-2"><div class="%1$s">%2$s</div><div class="x-media-text-body">%3$s</div></div>',
					esc_attr( 'right' === $salt_side ? 'x-media-text-media md:order-2' : 'x-media-text-media' ),
					$salt_media, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered component HTML; escapes at the point each value is printed.
					$salt_body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component HTML + salt_sanitize_html()-constrained WYSIWYG, escaped where each value is printed.
				);
				continue;
			}

			// Text-only row: full width, no empty media column.
			printf(
				'<div class="x-media-text-row"><div class="x-media-text-body">%s</div></div>',
				$salt_body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component HTML + salt_sanitize_html()-constrained WYSIWYG, escaped where each value is printed.
			);
		}

		echo '</div>';
	}
);
