<?php
/**
 * Resolver: Feature / Benefits Grid (salt-contract features, rc.8; spec
 * §10 #5) — the logic half of the section; the view is
 * template-parts/sections/features.php. Drawn in the contract's shared
 * wrapper (salt_section(), EP-72 M4).
 *
 * Fields (contract/fields/features.json): eyebrow, heading, intro,
 * columns (2|3|4), items [icon (the site's icon select), title, text (rich
 * text), url (an address)] and the shared settings.
 * States (COMPL02, markup zeroState): eyebrow, heading, intro and items
 * all empty => null (renders NOTHING). Items missing a title are skipped
 * and logged — never a blank card; with none left the grid is not drawn.
 * No schema, no JS.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current features Flexible Content row to its view props.
 *
 * Props (contract view-props/features.json):
 *   eyebrow  string|null  Contract `eyebrow`. Null when blank.
 *   heading  string|null  Contract `heading`. Null when blank.
 *   intro    string|null  Contract `intro`. Null when blank.
 *   columns  string       Contract `columns`: '2'|'3'|'4' (default '3').
 *   items    array<int, array{icon: string|null, title: string, text: string|null, href: string|null}>
 *                         Contract `items` (feature), possibly empty: `icon`
 *                         (an icon name), `title`, `text` (rich text:
 *                         sanitised HTML, null when it has no words) and
 *                         `href` (the item's url, null when none).
 *   arrow    string|null  The site's arrow (salt_contract_arrow()).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_features( array $context ): ?array {
	$sub = static fn( string $field ): string => function_exists( 'get_sub_field' ) && is_string( get_sub_field( $field ) ) ? (string) get_sub_field( $field ) : '';

	$eyebrow = salt_nullable_text( $sub( 'eyebrow' ) );
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
			salt_log( sprintf( 'features section: item %d is not a row array — skipped (broken row).', (int) $i ) );
			continue;
		}

		$title = is_string( $row['title'] ?? null ) ? trim( $row['title'] ) : '';
		if ( '' === $title ) {
			salt_log( sprintf( 'features section: item %d has no title — skipped (broken row).', (int) $i ) );
			continue;
		}

		// An address esc_url() would empty (a disallowed scheme) is no link.
		$href = is_string( $row['url'] ?? null ) ? esc_url_raw( trim( $row['url'] ) ) : '';
		$text = salt_sanitize_html( is_string( $row['text'] ?? null ) ? $row['text'] : '', salt_field_allowed( 'field_salt_features_items_text' ) );

		$items[] = array(
			'icon'  => salt_nullable_text( is_string( $row['icon'] ?? null ) ? trim( $row['icon'] ) : '' ),
			'title' => $title,
			'text'  => salt_rich_text_has_words( $text ) ? $text : null,
			'href'  => '' !== $href ? $href : null,
		);
	}

	if ( null === $eyebrow && null === $heading && null === $intro && array() === $items ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			salt_section_contract_args( 'features', $context ),
			// A section with no heading is a div and must not claim the page H1 (A11Y01).
			array( 'has_heading' => null !== $heading )
		),
		'props'   => array(
			'eyebrow' => $eyebrow,
			'heading' => $heading,
			'intro'   => $intro,
			'columns' => $columns,
			'items'   => $items,
			'arrow'   => salt_contract_arrow(),
		),
	);
}
