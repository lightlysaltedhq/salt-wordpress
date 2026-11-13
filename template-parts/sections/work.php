<?php
/**
 * View: Portfolio / Work (spec §10 #15) — a static grid of x-card-work cards.
 *
 * Draws the props from salt_resolve_section_work() (inc/sections/work.php),
 * inside the x-section wrapper the shared entry opens. No field reads,
 * queries, schema or logging here: a child theme overriding this file
 * overrides the markup only.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null, columns: 2|3,
 *                 items: array<int, array{title: string, url: string, client: string,
 *                          summary: string, image_id: int}>,
 *                 view_all_url: string|null }
 *          items is never empty; image_id 0 = no image.
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

// 1-state renders a single-column static card; N caps at the chosen columns.
$salt_grid_classes = 'grid list-none gap-8 p-0';
if ( count( $salt_props['items'] ) > 1 ) {
	$salt_grid_classes .= 2 === $salt_props['columns']
		? ' sm:grid-cols-2'
		: ' sm:grid-cols-2 lg:grid-cols-3';
}

if ( null !== $salt_props['eyebrow'] || null !== $salt_props['heading'] ) {
	echo '<header class="x-work-header mb-10 max-w-prose">';
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
				'text'  => $salt_props['heading'],
				'level' => $salt_plan['headingLevel'],
			)
		);
	}
	echo '</header>';
}

printf( '<ul class="x-work-grid %s">', esc_attr( $salt_grid_classes ) );
foreach ( $salt_props['items'] as $salt_item ) {
	echo '<li class="x-work-grid-item flex">';
	salt_component(
		'card-work',
		array(
			'title'    => $salt_item['title'],
			'url'      => $salt_item['url'],
			'client'   => $salt_item['client'],
			'summary'  => $salt_item['summary'],
			'image_id' => $salt_item['image_id'],
			// Card titles step below the section heading (A11Y01).
			'level'    => $salt_plan['headingLevel'] + 1,
			'classes'  => 'w-full',
		)
	);
	echo '</li>';
}
echo '</ul>';

if ( null !== $salt_props['view_all_url'] ) {
	echo '<div class="x-work-view-all mt-10">';
	salt_component(
		'button',
		array(
			'label' => __( 'View all work', 'salt' ),
			'url'   => $salt_props['view_all_url'],
			'style' => 'link-arrow',
		)
	);
	echo '</div>';
}
