<?php
/**
 * Resolver: Feature / Benefits Grid (spec §10 #5) — the logic half of the
 * section; the view is template-parts/sections/features.php.
 *
 * Fields (contract/fields/features.json): eyebrow, heading, intro (stored,
 * not drawn yet — M4), columns (2|3|4), items [icon (the site's icon
 * select), title, text (rich text), url (an address)].
 * States (COMPL02): 0 valid items => null (renders NOTHING); 1 item =>
 * valid static single-card grid; N => full grid. Items missing a title are
 * skipped and logged — never a blank card. No schema, no JS.
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
 *   columns  string       Contract `columns`: '2'|'3'|'4' (default '3').
 *   items    array<int, array{icon: string|null, title: string, text: string|null, href: string|null}>
 *                         Contract `items` (feature), never empty: `icon`
 *                         (an icon name), `title`, `text` (rich text:
 *                         sanitised HTML, null when blank) and `href` (the
 *                         item's url, null when none).
 *   The contract's `intro` is not supplied yet (M4).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_features( array $context ): ?array {
	$sub = static function ( string $field ): string {
		if ( ! function_exists( 'get_sub_field' ) ) {
			return '';
		}
		$value = get_sub_field( $field );
		return is_string( $value ) ? $value : '';
	};

	$eyebrow = $sub( 'eyebrow' );
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
			salt_log( sprintf( 'features section: item %d is not a row array — skipped (broken row).', (int) $i ) );
			continue;
		}

		$title = is_string( $row['title'] ?? null ) ? trim( $row['title'] ) : '';
		if ( '' === $title ) {
			salt_log( sprintf( 'features section: item %d has no title — skipped (broken row).', (int) $i ) );
			continue;
		}

		$href = is_string( $row['url'] ?? null ) ? trim( $row['url'] ) : '';
		$text = is_string( $row['text'] ?? null ) ? $row['text'] : '';

		$items[] = array(
			'icon'  => salt_nullable_text( is_string( $row['icon'] ?? null ) ? trim( $row['icon'] ) : '' ),
			'title' => $title,
			// Rich text (contract `text`): sanitised HTML; null when blank.
			'text'  => '' !== trim( $text ) ? salt_sanitize_html( $text ) : null,
			'href'  => '' !== $href ? $href : null,
		);
	}

	if ( array() === $items ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'features', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'eyebrow' => salt_nullable_text( $eyebrow ),
			'heading' => salt_nullable_text( $heading ),
			'columns' => $columns,
			'items'   => $items,
		),
	);
}
