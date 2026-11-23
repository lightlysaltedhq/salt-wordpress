<?php
/**
 * View: Process / Steps (salt-contract markup/process.json, rc.8; spec
 * §10 #14) — the contract's steps in an <ol> (numbers derive from
 * position). No JS.
 *
 * Draws the props from salt_resolve_section_process() (inc/sections/
 * process.php) as the contract's block, inside the band the shared entry
 * opens. No field reads, validation, schema or logging here: a child theme
 * overriding this file overrides the markup only.
 *
 * Step titles sit one level below the body base (section#body-heading-base).
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null, intro: string|null, layout: string,
 *                 steps: array<int, array{title: string, text: string|null, icon: string|null, image: array|null, cta: array|null}>,
 *                 priorityStep: int|null, arrow: string|null, structuredData: bool }
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

echo '<div class="salt-block" data-block="process">';

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

if ( array() !== $salt_props['steps'] ) {
	// The ordered list is the numbering AT hears; each visible number is aria-hidden.
	echo '<ol class="salt-grid salt-process" role="list" data-layout="' . esc_attr( (string) $salt_props['layout'] ) . '">';
	foreach ( array_values( $salt_props['steps'] ) as $salt_i => $salt_step ) {
		salt_component(
			'process-step',
			array(
				'number'   => (string) ( $salt_i + 1 ),
				'title'    => $salt_step['title'],
				'text'     => $salt_step['text'],
				'icon'     => $salt_step['icon'],
				'image'    => $salt_step['image'],
				'priority' => $salt_plan['priorityMedia'] && $salt_i === $salt_props['priorityStep'],
				'button'   => $salt_step['cta'],
				'arrow'    => $salt_props['arrow'],
				'level'    => min( 6, (int) ( $salt_plan['bodyBaseLevel'] ?? $salt_level ) + 1 ),
			)
		);
	}
	echo '</ol>';
}

echo '</div>';
