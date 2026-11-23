<?php
/**
 * Resolver: Stats / Metrics (salt-contract stats, rc.8; spec §10 #7) — the
 * logic half of the section; the view is template-parts/sections/stats.php.
 * Drawn in the contract's shared wrapper (salt_section(), EP-72 M4).
 *
 * Fields (contract/fields/stats.json): heading (optional), intro, columns
 * (2|3|4), items [icon, value, suffix, label] and the shared settings.
 * States (COMPL02, markup zeroState): heading, intro and items all empty
 * => null (renders NOTHING). Items missing a value are skipped and logged;
 * with none left the list is not drawn. No schema. No JS — the count-up
 * animation is deliberately out (INP/motion cost).
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
 *   intro    string|null  Contract `intro`. Null when blank.
 *   columns  string       Contract `columns`: '2'|'3'|'4' (default '3').
 *   items    array<int, array{icon: string|null, value: string, suffix: string|null, label: string|null}>
 *                         Contract `items` (stat), possibly empty: `icon`
 *                         (an icon name), `value`, `suffix`, `label`.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_stats( array $context ): ?array {
	$sub  = static fn( string $field ): string => function_exists( 'get_sub_field' ) && is_string( get_sub_field( $field ) ) ? (string) get_sub_field( $field ) : '';
	$text = static fn( $value ): ?string => salt_nullable_text( is_string( $value ) ? trim( $value ) : '' );

	$heading = salt_nullable_text( $sub( 'heading' ) );
	$intro   = salt_nullable_text( $sub( 'intro' ) );

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

		$value = $text( $row['value'] ?? null );
		if ( null === $value ) {
			salt_log( sprintf( 'stats section: item %d has no value — skipped (broken row).', (int) $i ) );
			continue;
		}

		$items[] = array(
			'icon'   => $text( $row['icon'] ?? null ),
			'value'  => $value,
			'suffix' => $text( $row['suffix'] ?? null ),
			'label'  => $text( $row['label'] ?? null ),
		);
	}

	if ( null === $heading && null === $intro && array() === $items ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			salt_section_contract_args( 'stats', $context ),
			// A section with no heading is a div and must not claim the page H1 (A11Y01).
			array( 'has_heading' => null !== $heading )
		),
		'props'   => array(
			'heading' => $heading,
			'intro'   => $intro,
			'columns' => $columns,
			'items'   => $items,
		),
	);
}
