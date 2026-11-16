<?php
/**
 * View: Stats / Metrics (spec §10 #7) — inline repeater of x-stat.
 *
 * Draws the props from salt_resolve_section_stats() (inc/sections/stats.php),
 * inside the x-section wrapper the shared entry opens. No field reads,
 * validation or logging here: a child theme overriding this file overrides
 * the markup only.
 *
 * $args:
 *   props  array{ heading: string|null, columns: string,
 *                 items: array<int, array{value: string, suffix: string|null, label: string|null}> }
 *          columns is '2'|'3'|'4'; items is never empty.
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
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

$salt_column_classes = array(
	'2' => 'sm:grid-cols-2',
	'3' => 'sm:grid-cols-3',
	'4' => 'sm:grid-cols-2 lg:grid-cols-4',
);

printf( '<div class="x-stats-grid grid gap-8 %s">', esc_attr( $salt_column_classes[ $salt_props['columns'] ] ) );
foreach ( $salt_props['items'] as $salt_item ) {
	salt_component(
		'stat',
		array(
			'number' => $salt_item['value'],
			'suffix' => $salt_item['suffix'] ?? '',
			'label'  => $salt_item['label'] ?? '',
		)
	);
}
echo '</div>';
