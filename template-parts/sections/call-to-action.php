<?php
/**
 * View: Call to Action (salt-contract markup/call-to-action.json, rc.8;
 * spec §10 #10).
 *
 * Draws the props from salt_resolve_section_call_to_action()
 * (inc/sections/call-to-action.php) as the contract's block, inside the
 * band the shared entry opens. No field reads, validation, logging or
 * defaults here: a child theme overriding this file overrides the markup
 * only.
 *
 * $args:
 *   props  array{ heading: string|null, body: string|null (sanitised HTML),
 *                 buttons: array<int, array{label: string, href: string, style: string, newTab: bool}>,
 *                 arrow: string|null }
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

echo '<div class="salt-block" data-block="call-to-action" data-align="centre">';

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
if ( array() !== $salt_props['buttons'] ) {
	echo '<div class="salt-cta__actions">';
	foreach ( $salt_props['buttons'] as $salt_button ) {
		salt_component(
			'contract-button',
			array(
				'button' => $salt_button,
				'arrow'  => $salt_props['arrow'],
			)
		);
	}
	echo '</div>';
}

echo '</div>';
