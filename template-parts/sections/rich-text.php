<?php
/**
 * View: Rich Text (spec §10 #3) — the §17 exemplar section.
 *
 * Draws the props from salt_resolve_section_rich_text() (inc/sections/
 * rich-text.php), inside the x-section wrapper the shared entry opens. No
 * field reads, queries, sanitising, schema or logging here: a child theme
 * overriding this file overrides the markup only.
 *
 * $args:
 *   props  array{ heading: string|null, body: string|null }
 *          body is HTML already sanitised by salt_sanitize_html().
 *   plan   array{ index: int, headingLevel: int }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

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
if ( null !== $salt_props['body'] ) {
	printf(
		'<div class="x-prose max-w-prose text-ink">%s</div>',
		$salt_props['body'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised by the resolver through salt_sanitize_html(), constrained by salt_allowed_html() (§16a).
	);
}
