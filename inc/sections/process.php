<?php
/**
 * Resolver: Process / Steps (salt-contract process, rc.8; spec §10 #14) —
 * the logic half of the section; the view is template-parts/sections/
 * process.php, drawn in the contract's shared wrapper (salt_section(),
 * EP-72 M4).
 *
 * Fields: eyebrow, heading, intro, layout (timeline|cards), steps [icon,
 * image, title, text, cta], structuredData. States (COMPL02, markup
 * zeroState): eyebrow, heading, intro and steps all empty => null (renders
 * NOTHING, no band); a heading with no steps draws the heading alone;
 * 1 step => a one-step list; N => timeline or cards. Steps missing a title
 * are skipped and logged.
 *
 * Schema (§15 — AEO not SEO): OPT-IN via structuredData (default OFF). When
 * on and there are steps, ONE HowTo node is queued via salt_schema_add():
 * name = the section heading (omitted when empty), step = HowToStep array
 * [position, name, text] built from the SAME rows the section renders.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current process Flexible Content row to its view props.
 *
 * Props (contract view-props/process.json):
 *   eyebrow         string|null  Contract `eyebrow`. Null when blank.
 *   heading         string|null  Contract `heading`. Null when blank.
 *   intro           string|null  Contract `intro`. Null when blank.
 *   layout          string       Contract `layout`: 'timeline'|'cards'
 *                                (invalid or blank => 'timeline').
 *   steps           array<int, array{title: string, text: string|null, icon: string|null,
 *                                    image: array|null, cta: array|null}>
 *                                Contract `steps` (step), in row order; may be
 *                                empty. title trimmed, never empty; text and
 *                                icon trimmed, null for none; image
 *                                salt_image_contract() at the layout's slot
 *                                (timeline: content; cards: card, three
 *                                columns), null when unset or unresolved; cta
 *                                the button props (salt_contract_button_props(),
 *                                style secondary), null unless the link resolves.
 *   priorityStep    int|null     WordPress-local: the step whose image is the
 *                                section's principal image (timeline: step 0;
 *                                cards: the first with an image), null for none.
 *                                It is drawn as the priority image only when
 *                                the plan grants it.
 *   arrow           string|null  The site's arrow (salt_contract_arrow()).
 *   structuredData  bool         Contract `structuredData`. The HowTo node is
 *                                already queued; a view draws nothing for it.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>, plan: array{priorityMedia: bool}}|null Null = 0-state.
 */
function salt_resolve_section_process( array $context ): ?array {
	$sub = static function ( string $field ): string {
		if ( ! function_exists( 'get_sub_field' ) ) {
			return '';
		}
		$value = get_sub_field( $field );
		return is_string( $value ) ? $value : '';
	};

	$heading = salt_nullable_text( $sub( 'heading' ) );
	$eyebrow = salt_nullable_text( $sub( 'eyebrow' ) );
	$intro   = salt_nullable_text( $sub( 'intro' ) );
	$section = salt_section_contract_args( 'process', $context );

	$layout = $sub( 'layout' );
	if ( ! in_array( $layout, array( 'timeline', 'cards' ), true ) ) {
		$layout = 'timeline';
	}

	$rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'steps' ) : array();
	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	$sizes = 'cards' === $layout ? salt_image_slot_sizes( 'card', $section['container'], '3' ) : salt_image_slot_sizes( 'content', $section['container'] );

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

		$text = is_string( $row['text'] ?? null ) ? trim( $row['text'] ) : '';
		$icon = is_string( $row['icon'] ?? null ) ? trim( $row['icon'] ) : '';
		$link = salt_contract_link( $row['cta'] ?? null );

		$items[] = array(
			'title' => $title,
			'text'  => '' !== $text ? $text : null,
			'icon'  => '' !== $icon ? $icon : null,
			'image' => salt_image_contract( (int) ( $row['image'] ?? 0 ), $sizes ),
			'cta'   => null === $link ? null : salt_contract_button_props(
				array(
					array_merge( $link, array( 'style' => 'secondary' ) ),
				)
			)[0],
		);
	}

	if ( null === $eyebrow && null === $heading && null === $intro && array() === $items ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	// The section's principal image (markup/process.json priorityMedia):
	// timeline, step 0's; cards, the first step with one.
	$priority_step = null;
	foreach ( $items as $i => $item ) {
		if ( null !== $item['image'] ) {
			$priority_step = $i;
			break;
		}
		if ( 'timeline' === $layout ) {
			break;
		}
	}

	// HowTo schema — OPT-IN, default off (AEO not SEO, §15).
	$schema_on = function_exists( 'get_sub_field' ) ? (bool) get_sub_field( 'structuredData' ) : false;
	if ( $schema_on && array() !== $items ) {
		$step_nodes = array();
		foreach ( $items as $position => $item ) {
			$step_nodes[] = array(
				'@type'    => 'HowToStep',
				'position' => $position + 1,
				'name'     => $item['title'],
				'text'     => $item['text'] ?? $item['title'],
			);
		}

		$node = array(
			'@type' => 'HowTo',
			'step'  => $step_nodes,
		);
		if ( null !== $heading ) {
			$node['name'] = $heading;
		}

		salt_schema_add( $node );
	}

	return array(
		'section' => array_merge(
			$section,
			// A section with no heading is a div and must not claim the page H1 (A11Y01).
			array( 'has_heading' => null !== $heading )
		),
		'props'   => array(
			'eyebrow'        => $eyebrow,
			'heading'        => $heading,
			'intro'          => $intro,
			'layout'         => $layout,
			'steps'          => $items,
			'priorityStep'   => $priority_step,
			'arrow'          => salt_contract_arrow(),
			'structuredData' => $schema_on,
		),
		'plan'    => array( 'priorityMedia' => salt_section_plan_priority( $context ) && null === $section['background_image'] && null !== $priority_step ),
	);
}
