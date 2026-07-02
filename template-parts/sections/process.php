<?php
/**
 * Section: Process / Steps (spec §10 #14) — inline repeater of
 * x-process-step inside an <ol> (numbers derive from position).
 *
 * Fields: heading, eyebrow, layout (timeline|cards), steps [title, text,
 * icon], schema_toggle. States (COMPL02): 0 valid steps => renders NOTHING
 * (skip before the wrapper); 1 step => valid static single-step list; N =>
 * timeline (vertical, connector border) or cards grid. Steps missing a
 * title are skipped and logged. Step titles render at $level + 1 (A11Y01).
 *
 * Schema (§15 — AEO not SEO): OPT-IN via schema_toggle (default OFF). When
 * on, ONE HowTo node is queued via salt_schema_add(): name = the section
 * heading (omitted when empty), step = HowToStep array [position, name,
 * text] built from the SAME rows the section renders. No JS.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_sub = static function ( string $field ): string {
	if ( ! function_exists( 'get_sub_field' ) ) {
		return '';
	}
	$value = get_sub_field( $field );
	return is_string( $value ) ? $value : '';
};

$salt_heading = $salt_sub( 'heading' );
$salt_eyebrow = $salt_sub( 'eyebrow' );

$salt_layout = $salt_sub( 'layout' );
if ( ! in_array( $salt_layout, array( 'timeline', 'cards' ), true ) ) {
	$salt_layout = 'timeline';
}

$salt_rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'steps' ) : array();
if ( ! is_array( $salt_rows ) ) {
	$salt_rows = array();
}

// Normalise repeater rows, skipping malformed items (logged — COMPL02).
$salt_items = array();
foreach ( $salt_rows as $salt_i => $salt_row ) {
	if ( ! is_array( $salt_row ) ) {
		salt_log( sprintf( 'process section: step %d is not a row array — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	$salt_title = is_string( $salt_row['title'] ?? null ) ? trim( $salt_row['title'] ) : '';
	if ( '' === $salt_title ) {
		salt_log( sprintf( 'process section: step %d has no title — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	$salt_items[] = array(
		'title' => $salt_title,
		'text'  => is_string( $salt_row['text'] ?? null ) ? trim( $salt_row['text'] ) : '',
		'icon'  => is_string( $salt_row['icon'] ?? null ) ? trim( $salt_row['icon'] ) : '',
	);
}

if ( array() === $salt_items ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

// HowTo schema — OPT-IN, default off (AEO not SEO, §15).
$salt_schema_on = function_exists( 'get_sub_field' ) ? (bool) get_sub_field( 'schema_toggle' ) : false;
if ( $salt_schema_on ) {
	$salt_step_nodes = array();
	foreach ( $salt_items as $salt_position => $salt_item ) {
		$salt_step_nodes[] = array(
			'@type'    => 'HowToStep',
			'position' => $salt_position + 1,
			'name'     => $salt_item['title'],
			'text'     => '' !== $salt_item['text'] ? $salt_item['text'] : $salt_item['title'],
		);
	}

	$salt_node = array(
		'@type' => 'HowTo',
		'step'  => $salt_step_nodes,
	);
	if ( '' !== trim( $salt_heading ) ) {
		$salt_node['name'] = $salt_heading;
	}

	salt_schema_add( $salt_node );
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'process', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_heading, $salt_eyebrow, $salt_layout, $salt_items ): void {
		if ( '' !== trim( $salt_eyebrow ) ) {
			salt_component(
				'eyebrow',
				array(
					'text'    => $salt_eyebrow,
					'classes' => 'mb-2',
				)
			);
		}
		if ( '' !== trim( $salt_heading ) ) {
			salt_component(
				'heading',
				array(
					'text'    => $salt_heading,
					'level'   => $level,
					'classes' => 'mb-6',
				)
			);
		}

		// Step titles step one level below the section heading (A11Y01).
		$step_level = min( 6, $level + 1 );

		$list_classes = 'cards' === $salt_layout
			? 'x-process-cards grid list-none gap-6 sm:grid-cols-2 lg:grid-cols-3'
			: 'x-process-timeline relative flex list-none flex-col gap-8 border-s border-border ps-8';

		$item_classes = 'cards' === $salt_layout
			? 'x-process-item h-full rounded-lg border border-border bg-surface p-6'
			: 'x-process-item relative';

		// Ordered list: the semantic numbering AT hears (visible numbers are
		// aria-hidden position markers inside x-process-step).
		printf( '<ol class="x-process-list %s">', esc_attr( $list_classes ) );
		foreach ( $salt_items as $i => $item ) {
			printf( '<li class="%s">', esc_attr( $item_classes ) );
			salt_component(
				'process-step',
				array(
					'number' => (string) ( $i + 1 ),
					'title'  => $item['title'],
					'text'   => $item['text'],
					'icon'   => $item['icon'],
					'level'  => $step_level,
				)
			);
			echo '</li>';
		}
		echo '</ol>';
	}
);
