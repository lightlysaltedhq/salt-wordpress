<?php
/**
 * View: Media + Text (spec §10 #6).
 *
 * Draws the props from salt_resolve_section_media_text() (inc/sections/
 * media-text.php), inside the x-section wrapper the shared entry opens:
 * image/text rows on a md:grid-cols-2 grid, images through x-image with
 * sizes_context 'half'; a row without an image draws full-width. The flip
 * of the media side is purely visual (md:order-2 on the media cell); DOM
 * order is always media then body. No field reads, sanitising, validation
 * or logging here: a child theme overriding this file overrides the markup
 * only.
 *
 * HEADING OUTLINE RULE (A11Y01/AMBIG03): row 0's heading renders at the
 * section's heading level; every LATER row's heading renders at
 * ( 1 === level ? 2 : level ), so the outline is h1 → h2 siblings on a
 * primary section and h2 all round otherwise.
 *
 * $args:
 *   props  array{ rows: array<int, array{image_id: int, heading: string|null,
 *                 content: string|null, mediaSide: string,
 *                 cta: array{url: string, title: string, target: string}|null}> }
 *          rows is never empty; content is HTML already sanitised;
 *          mediaSide ('left'|'right') is the side the row is drawn on.
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

echo '<div class="x-media-text-rows flex flex-col gap-12">';

foreach ( $salt_props['rows'] as $salt_i => $salt_row ) {
	// HEADING OUTLINE RULE (see file docblock): row 0 at the section level,
	// later rows step to h2 when row 0 took the H1.
	$salt_row_level = 0 === $salt_i ? $salt_plan['headingLevel'] : ( 1 === $salt_plan['headingLevel'] ? 2 : $salt_plan['headingLevel'] );

	$salt_body = '';
	if ( null !== $salt_row['heading'] ) {
		$salt_body .= salt_get_component(
			'heading',
			array(
				'text'    => $salt_row['heading'],
				'level'   => $salt_row_level,
				'classes' => 'mb-4',
			)
		);
	}
	if ( null !== $salt_row['content'] ) {
		$salt_body .= '<div class="x-prose max-w-prose text-ink">' . $salt_row['content'] . '</div>';
	}
	if ( null !== $salt_row['cta'] ) {
		$salt_body .= '<div class="mt-6">' . salt_get_component(
			'button',
			array(
				'label'  => $salt_row['cta']['title'],
				'url'    => $salt_row['cta']['url'],
				'style'  => 'link-arrow',
				'target' => $salt_row['cta']['target'],
			)
		) . '</div>';
	}

	if ( $salt_row['image_id'] > 0 ) {
		$salt_media = salt_get_component(
			'image',
			array(
				'image_id'      => $salt_row['image_id'],
				'alt'           => '',
				'sizes_context' => 'half',
				'classes'       => 'w-full rounded-lg',
			)
		);
		printf(
			'<div class="x-media-text-row grid items-center gap-8 md:grid-cols-2"><div class="%1$s">%2$s</div><div class="x-media-text-body">%3$s</div></div>',
			esc_attr( 'right' === $salt_row['mediaSide'] ? 'x-media-text-media md:order-2' : 'x-media-text-media' ),
			$salt_media, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered component HTML; escapes at the point each value is printed.
			$salt_body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component HTML + resolver-sanitised (salt_sanitize_html()) rich text, escaped where each value is printed.
		);
		continue;
	}

	// Text-only row: full width, no empty media column.
	printf(
		'<div class="x-media-text-row"><div class="x-media-text-body">%s</div></div>',
		$salt_body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component HTML + resolver-sanitised (salt_sanitize_html()) rich text, escaped where each value is printed.
	);
}

echo '</div>';
