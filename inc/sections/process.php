<?php
/**
 * Resolver: Process / Steps (spec §10 #14) — the logic half of the
 * section; the view is template-parts/sections/process.php.
 *
 * Fields: heading, eyebrow, layout (timeline|cards), steps [title, text,
 * icon], structuredData. States (COMPL02): 0 valid steps => null (renders
 * NOTHING, no band); 1 step => valid static single-step list; N =>
 * timeline or cards grid. Steps missing a title are skipped and logged.
 *
 * Schema (§15 — AEO not SEO): OPT-IN via structuredData (default OFF). When
 * on, ONE HowTo node is queued via salt_schema_add(): name = the section
 * heading (omitted when empty), step = HowToStep array [position, name,
 * text] built from the SAME rows the section renders.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current process Flexible Content row to its view props.
 *
 * Props (contract view-props/process.json; WordPress-local names in snake_case):
 *   eyebrow         string|null  Contract `eyebrow`. Null when blank.
 *   heading         string|null  Contract `heading`. Null when blank.
 *   layout          string       Contract `layout`: 'timeline'|'cards'
 *                                (invalid or blank => 'timeline').
 *   steps           array<int, array{title: string, text: string, icon: string}>
 *                                Contract `steps` (step), in row order; never
 *                                empty. title is trimmed and never empty;
 *                                text and icon are trimmed, '' for none (the
 *                                contract's are nullable) — passed to
 *                                x-process-step as they are.
 *   structuredData  bool         Contract `structuredData`, from the
 *                                structuredData field. The HowTo node is
 *                                already queued; a view draws nothing for it.
 *
 * Naming gaps against the contract: `intro` has no field; a step has no
 * `image` or `cta`; step `text`/`icon` are '' rather than null.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_process( array $context ): ?array {
	$sub = static function ( string $field ): string {
		if ( ! function_exists( 'get_sub_field' ) ) {
			return '';
		}
		$value = get_sub_field( $field );
		return is_string( $value ) ? $value : '';
	};

	$heading = $sub( 'heading' );
	$eyebrow = $sub( 'eyebrow' );

	$layout = $sub( 'layout' );
	if ( ! in_array( $layout, array( 'timeline', 'cards' ), true ) ) {
		$layout = 'timeline';
	}

	$rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'steps' ) : array();
	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	// Normalise repeater rows, skipping malformed items (logged — COMPL02).
	$items = array();
	foreach ( $rows as $i => $row ) {
		if ( ! is_array( $row ) ) {
			salt_log( sprintf( 'process section: step %d is not a row array — skipped (broken row).', (int) $i ) );
			continue;
		}

		$title = is_string( $row['title'] ?? null ) ? trim( $row['title'] ) : '';
		if ( '' === $title ) {
			salt_log( sprintf( 'process section: step %d has no title — skipped (broken row).', (int) $i ) );
			continue;
		}

		$items[] = array(
			'title' => $title,
			'text'  => is_string( $row['text'] ?? null ) ? trim( $row['text'] ) : '',
			'icon'  => is_string( $row['icon'] ?? null ) ? trim( $row['icon'] ) : '',
		);
	}

	if ( array() === $items ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	// HowTo schema — OPT-IN, default off (AEO not SEO, §15).
	$schema_on = function_exists( 'get_sub_field' ) ? (bool) get_sub_field( 'structuredData' ) : false;
	if ( $schema_on ) {
		$step_nodes = array();
		foreach ( $items as $position => $item ) {
			$step_nodes[] = array(
				'@type'    => 'HowToStep',
				'position' => $position + 1,
				'name'     => $item['title'],
				'text'     => '' !== $item['text'] ? $item['text'] : $item['title'],
			);
		}

		$node = array(
			'@type' => 'HowTo',
			'step'  => $step_nodes,
		);
		if ( '' !== trim( $heading ) ) {
			$node['name'] = $heading;
		}

		salt_schema_add( $node );
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'process', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'eyebrow'        => salt_nullable_text( $eyebrow ),
			'heading'        => salt_nullable_text( $heading ),
			'layout'         => $layout,
			'steps'          => $items,
			'structuredData' => $schema_on,
		),
	);
}
