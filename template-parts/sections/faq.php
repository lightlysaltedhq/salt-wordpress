<?php
/**
 * View: FAQ (salt-contract markup/faq.json, rc.8; spec §10 #13) — zero JS.
 *
 * Draws the props from salt_resolve_section_faq() (inc/sections/faq.php)
 * as the contract's block, inside the band the shared entry opens: each
 * item is a native <details> of the contract's accordion
 * (markup/accordion.json), its answer in div.salt-rich-text, none open.
 * No field reads, queries, sanitising, schema or logging here: a child
 * theme overriding this file overrides the markup only.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null, intro: string|null,
 *                 items: array<int, array{id: string, question: string, answer: string}>,
 *                 structuredData: bool }
 *          items is never empty; answer is HTML already sanitised.
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
// One exclusive-open group per band (faq-<plan index>), on every details.
$salt_group = 'faq-' . (int) $salt_plan['index'];

echo '<div class="salt-block" data-block="faq">';

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
if ( null !== $salt_props['intro'] ) {
	echo '<p class="salt-intro">' . esc_html( (string) $salt_props['intro'] ) . '</p>';
}

echo '<div class="salt-accordion">';
foreach ( $salt_props['items'] as $salt_item ) {
	echo '<details class="salt-accordion__item" name="' . esc_attr( $salt_group ) . '">';
	echo '<summary class="salt-accordion__summary">' . esc_html( (string) $salt_item['question'] ) . '</summary>';
	printf(
		'<div class="salt-accordion__content"><div class="salt-rich-text">%s</div></div>',
		salt_render_rich_text_headings( (string) $salt_item['answer'], (int) ( $salt_plan['bodyBaseLevel'] ?? $salt_level ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already sanitised by the resolver; only heading ranks change.
	);
	echo '</details>';
}
echo '</div>';

echo '</div>';
