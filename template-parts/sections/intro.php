<?php
/**
 * View: Intro / Lead (spec §10 #2) — inline.
 *
 * Draws the props from salt_resolve_section_intro() (inc/sections/intro.php),
 * inside the x-section wrapper the shared entry opens. No field reads,
 * sanitising, validation or logging here: a child theme overriding this
 * file overrides the markup only.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null,
 *                 content: string|null, align: string }
 *          content is HTML already sanitised by salt_sanitize_html();
 *          align is 'left'|'center'.
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

// Narrow reading measure; centred variant centres measure + text.
$salt_classes = 'x-intro max-w-prose';
if ( 'center' === $salt_props['align'] ) {
	$salt_classes .= ' mx-auto text-center';
}

printf( '<div class="%s">', esc_attr( $salt_classes ) );
if ( null !== $salt_props['eyebrow'] ) {
	salt_component(
		'eyebrow',
		array(
			'text'    => $salt_props['eyebrow'],
			'classes' => 'mb-3',
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
if ( null !== $salt_props['content'] ) {
	printf(
		'<div class="x-prose text-lg text-ink">%s</div>',
		$salt_props['content'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised by the resolver through salt_sanitize_html(), constrained by salt_allowed_html() (§16a).
	);
}
echo '</div>';
