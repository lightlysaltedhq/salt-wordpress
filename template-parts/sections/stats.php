<?php
/**
 * Section: Stats / Metrics (spec §10 #7) — inline repeater of x-stat.
 *
 * Fields: heading (optional), columns (2|3|4), items [number, suffix, label].
 * States (COMPL02): 0 valid items => renders NOTHING (skip before the
 * wrapper); 1 item => valid static single-stat row; N => full grid. Items
 * missing a number are skipped and logged. No schema. No JS — the count-up
 * animation is deliberately out (INP/motion cost).
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

$salt_columns = $salt_sub( 'columns' );
if ( ! in_array( $salt_columns, array( '2', '3', '4' ), true ) ) {
	$salt_columns = '3';
}

$salt_rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'items' ) : array();
if ( ! is_array( $salt_rows ) ) {
	$salt_rows = array();
}

// Normalise repeater rows, skipping malformed items (logged — COMPL02).
$salt_items = array();
foreach ( $salt_rows as $salt_i => $salt_row ) {
	if ( ! is_array( $salt_row ) ) {
		salt_log( sprintf( 'stats section: item %d is not a row array — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	$salt_number = is_string( $salt_row['number'] ?? null ) ? trim( $salt_row['number'] ) : '';
	if ( '' === $salt_number ) {
		salt_log( sprintf( 'stats section: item %d has no number — skipped (broken row).', (int) $salt_i ) );
		continue;
	}

	$salt_items[] = array(
		'number' => $salt_number,
		'suffix' => is_string( $salt_row['suffix'] ?? null ) ? trim( $salt_row['suffix'] ) : '',
		'label'  => is_string( $salt_row['label'] ?? null ) ? trim( $salt_row['label'] ) : '',
	);
}

if ( array() === $salt_items ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'stats', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_heading, $salt_columns, $salt_items ): void {
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

		$column_classes = array(
			'2' => 'sm:grid-cols-2',
			'3' => 'sm:grid-cols-3',
			'4' => 'sm:grid-cols-2 lg:grid-cols-4',
		);

		printf( '<div class="x-stats-grid grid gap-8 %s">', esc_attr( $column_classes[ $salt_columns ] ) );
		foreach ( $salt_items as $item ) {
			salt_component(
				'stat',
				array(
					'number' => $item['number'],
					'suffix' => $item['suffix'],
					'label'  => $item['label'],
				)
			);
		}
		echo '</div>';
	}
);
