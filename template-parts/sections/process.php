<?php
/**
 * View: Process / Steps (spec §10 #14) — x-process-step inside an <ol>
 * (numbers derive from position). No JS.
 *
 * Draws the props from salt_resolve_section_process() (inc/sections/
 * process.php), inside the x-section wrapper the shared entry opens. No
 * field reads, validation, schema or logging here: a child theme
 * overriding this file overrides the markup only.
 *
 * Layouts: timeline (vertical, connector border) or cards grid. Step
 * titles render one level below the section heading (A11Y01).
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null, layout: string,
 *                 steps: array<int, array{title: string, text: string, icon: string}>,
 *                 structuredData: bool }
 *          steps is never empty.
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

if ( null !== $salt_props['eyebrow'] ) {
	salt_component(
		'eyebrow',
		array(
			'text'    => $salt_props['eyebrow'],
			'classes' => 'mb-2',
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

// Step titles step one level below the section heading (A11Y01).
$salt_step_level = min( 6, $salt_plan['headingLevel'] + 1 );

$salt_list_classes = 'cards' === $salt_props['layout']
	? 'x-process-cards grid list-none gap-6 sm:grid-cols-2 lg:grid-cols-3'
	: 'x-process-timeline relative flex list-none flex-col gap-8 border-s border-border ps-8';

$salt_item_classes = 'cards' === $salt_props['layout']
	? 'x-process-item h-full rounded-lg border border-border bg-surface p-6'
	: 'x-process-item relative';

// Ordered list: the semantic numbering AT hears (visible numbers are
// aria-hidden position markers inside x-process-step).
printf( '<ol class="x-process-list %s">', esc_attr( $salt_list_classes ) );
foreach ( $salt_props['steps'] as $salt_i => $salt_step ) {
	printf( '<li class="%s">', esc_attr( $salt_item_classes ) );
	salt_component(
		'process-step',
		array(
			'number' => (string) ( $salt_i + 1 ),
			'title'  => $salt_step['title'],
			'text'   => $salt_step['text'],
			'icon'   => $salt_step['icon'],
			'level'  => $salt_step_level,
		)
	);
	echo '</li>';
}
echo '</ol>';
