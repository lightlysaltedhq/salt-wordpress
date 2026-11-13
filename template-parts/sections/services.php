<?php
/**
 * View: Services (spec §10 #4) — CPT-backed service cards.
 *
 * Draws the props from salt_resolve_section_services() (inc/sections/
 * services.php), inside the x-section wrapper the shared entry opens. No
 * field reads, queries, schema or logging here: a child theme overriding
 * this file overrides the markup only.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null,
 *                 layout: 'grid'|'list'|'featured', columns: '2'|'3'|'4',
 *                 items: array<int, array{title: string, url: string, summary: string, icon: string}>,
 *                 view_all_url: string|null }
 *          items is never empty.
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

$salt_card = static function ( array $data, string $classes = '' ) use ( $salt_card_level ): void {
	salt_component(
		'card-service',
		array(
			'title'   => $data['title'],
			'url'     => $data['url'],
			'summary' => $data['summary'],
			'icon'    => $data['icon'],
			'level'   => $salt_card_level,
			'classes' => $classes,
		)
	);
};

$salt_column_classes = array(
	'2' => 'sm:grid-cols-2',
	'3' => 'sm:grid-cols-2 lg:grid-cols-3',
	'4' => 'sm:grid-cols-2 lg:grid-cols-4',
);

if ( 'list' === $salt_props['layout'] ) {
	echo '<div class="x-services-list grid gap-6">';
	foreach ( $salt_props['items'] as $salt_data ) {
		$salt_card( $salt_data );
	}
	echo '</div>';
} elseif ( 'featured' === $salt_props['layout'] ) {
	// First service leads; the remainder (if any) fall into the grid —
	// a single item is a valid static featured card (COMPL02).
	echo '<div class="x-services-featured grid gap-6">';
	$salt_card( $salt_props['items'][0], 'x-card-service-featured bg-brand-tint' );
	$salt_rest = array_slice( $salt_props['items'], 1 );
	if ( array() !== $salt_rest ) {
		printf( '<div class="grid gap-6 %s">', esc_attr( $salt_column_classes[ $salt_props['columns'] ] ) );
		foreach ( $salt_rest as $salt_data ) {
			$salt_card( $salt_data );
		}
		echo '</div>';
	}
	echo '</div>';
} else {
	printf( '<div class="x-services-grid grid gap-6 %s">', esc_attr( $salt_column_classes[ $salt_props['columns'] ] ) );
	foreach ( $salt_props['items'] as $salt_data ) {
		$salt_card( $salt_data );
	}
	echo '</div>';
}

if ( null !== $salt_props['view_all_url'] ) {
	echo '<div class="mt-8">';
	salt_component(
		'button',
		array(
			'label'   => __( 'View all services', 'salt' ),
			'url'     => $salt_props['view_all_url'],
			'style'   => 'link-arrow',
			'classes' => 'px-0',
		)
	);
	echo '</div>';
}
