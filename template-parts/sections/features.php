<?php
/**
 * View: Feature / Benefits Grid (spec §10 #5) — inline repeater.
 *
 * Draws the props from salt_resolve_section_features() (inc/sections/
 * features.php), inside the x-section wrapper the shared entry opens. No
 * field reads, validation or logging here: a child theme overriding this
 * file overrides the markup only.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null, columns: string,
 *                 items: array<int, array{icon: string|null, title: string, text: string|null (sanitised HTML), href: string|null}> }
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

// Card titles step one level below the section heading (A11Y01).
$salt_card_level = min( 6, $salt_plan['headingLevel'] + 1 );

$salt_column_classes = array(
	'2' => 'sm:grid-cols-2',
	'3' => 'sm:grid-cols-2 lg:grid-cols-3',
	'4' => 'sm:grid-cols-2 lg:grid-cols-4',
);

printf( '<div class="x-features-grid grid gap-6 %s">', esc_attr( $salt_column_classes[ $salt_props['columns'] ] ) );
foreach ( $salt_props['items'] as $salt_item ) {
	$salt_icon_html = '';
	if ( null !== $salt_item['icon'] ) {
		$salt_icon_html = salt_get_component(
			'icon',
			array(
				'name' => $salt_item['icon'],
				'size' => 'lg',
			)
		);
	}

	echo salt_get_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component output escapes at the point each value is printed.
		'card',
		array(
			'title'     => $salt_item['title'],
			'url'       => $salt_item['href'] ?? '',
			'level'     => $salt_card_level,
			'body_html' => $salt_item['text'] ?? '',
			'media'     => '' !== $salt_icon_html
				? '<div class="x-card-feature-icon px-6 pt-6 text-brand">' . $salt_icon_html . '</div>'
				: '',
			'classes'   => 'x-card-feature',
		)
	);
}
echo '</div>';
