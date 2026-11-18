<?php
/**
 * Resolver: Stats / Metrics (spec §10 #7) — the logic half of the section;
 * the view is template-parts/sections/stats.php.
 *
 * Fields (contract/fields/stats.json): heading (optional), intro, columns
 * (2|3|4), items [icon, value, suffix, label]; intro and icon are stored,
 * not drawn yet (M4).
 * States (COMPL02): 0 valid items => null (renders NOTHING); 1 item =>
 * valid static single-stat row; N => full grid. Items missing a number are
 * skipped and logged. No schema. No JS — the count-up animation is
 * deliberately out (INP/motion cost).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current stats Flexible Content row to its view props.
 *
 * Props (contract view-props/stats.json):
 *   heading  string|null  Contract `heading`. Null when blank.
 *   columns  string       Contract `columns`: '2'|'3'|'4' (default '3').
 *   items    array<int, array{value: string, suffix: string|null, label: string|null}>
 *                         Contract `items` (stat): `value` (from the
 *                         `value` field), `suffix`, `label`; never empty.
 *                         The contract's `icon` is stored but not
 *                         supplied yet (M4).
 *   The contract's `intro` is stored but not supplied yet either (M4).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_stats( array $context ): ?array {
	$sub = static function ( string $field ): string {
		if ( ! function_exists( 'get_sub_field' ) ) {
			return '';
		}
		$value = get_sub_field( $field );
		return is_string( $value ) ? $value : '';
	};

	$heading = $sub( 'heading' );

	$columns = $sub( 'columns' );
	if ( ! in_array( $columns, array( '2', '3', '4' ), true ) ) {
		$columns = '3';
	}

	$rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'items' ) : array();
	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	// Normalise repeater rows, skipping malformed items (logged — COMPL02).
	$items = array();
	foreach ( $rows as $i => $row ) {
		if ( ! is_array( $row ) ) {
			salt_log( sprintf( 'stats section: item %d is not a row array — skipped (broken row).', (int) $i ) );
			continue;
		}

		$value = is_string( $row['value'] ?? null ) ? trim( $row['value'] ) : '';
		if ( '' === $value ) {
			salt_log( sprintf( 'stats section: item %d has no value — skipped (broken row).', (int) $i ) );
			continue;
		}

		$items[] = array(
			'value'  => $value,
			'suffix' => salt_nullable_text( is_string( $row['suffix'] ?? null ) ? trim( $row['suffix'] ) : '' ),
			'label'  => salt_nullable_text( is_string( $row['label'] ?? null ) ? trim( $row['label'] ) : '' ),
		);
	}

	if ( array() === $items ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'stats', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'heading' => salt_nullable_text( $heading ),
			'columns' => $columns,
			'items'   => $items,
		),
	);
}
