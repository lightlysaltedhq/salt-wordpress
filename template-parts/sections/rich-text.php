<?php
/**
 * View: Rich Text (salt-contract markup/rich-text.json, rc.8; spec §10 #3)
 * — the §17 exemplar section and the first drawn inside the contract's
 * shared wrapper (EP-72 M4).
 *
 * Draws the props from salt_resolve_section_rich_text() (inc/sections/
 * rich-text.php) as the contract's block, inside the band the shared entry
 * opens. No field reads, queries, sanitising, schema or logging here: a
 * child theme overriding this file overrides the markup only.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null, body: string|null, alignment: string }
 *          body is HTML already sanitised by salt_sanitize_html().
 *   plan   array{ index: int, anchor: string, headingLevel: int, headingId: string|null, bodyBaseLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];
$salt_level = max( 1, min( 6, (int) $salt_plan['headingLevel'] ) );
$salt_id    = $salt_plan['headingId'] ?? null;

echo '<div class="salt-block" data-block="rich-text" data-align="' . esc_attr( (string) $salt_props['alignment'] ) . '">';

if ( null !== $salt_props['eyebrow'] ) {
	echo '<p class="salt-eyebrow">' . esc_html( (string) $salt_props['eyebrow'] ) . '</p>';
}
if ( null !== $salt_props['heading'] ) {
	printf(
		'<h%1$d%2$s>%3$s</h%1$d>',
		(int) $salt_level,
		is_string( $salt_id ) ? ' id="' . esc_attr( $salt_id ) . '"' : '',
		esc_html( (string) $salt_props['heading'] )
	);
}
if ( null !== $salt_props['body'] ) {
	printf(
		'<div class="salt-rich-text">%s</div>',
		salt_render_rich_text_headings( (string) $salt_props['body'], (int) ( $salt_plan['bodyBaseLevel'] ?? ( null === $salt_props['heading'] ? 1 : $salt_level ) ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already sanitised by the resolver; only heading ranks change.
	);
}

echo '</div>';
