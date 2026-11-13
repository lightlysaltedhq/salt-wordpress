<?php
/**
 * Resolver: Scroll Carousel (spec §10 #18) — the logic half of the
 * section; the view is template-parts/sections/scroll-carousel.php.
 *
 * Fields: heading, eyebrow, card_type (generic|testimonial), cards [image,
 * title, text, role]. States (COMPL02): 0 valid cards => null (renders
 * NOTHING, no band); 1 card => one static card; N => the snap track.
 * Cards with neither title nor text are skipped and logged. No schema.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current scroll_carousel Flexible Content row to its view props.
 *
 * WordPress-local section: the contract folds this layout into carousel
 * (view-props/carousel.json); that fold is a later item, so the props keep
 * WordPress-local snake_case names, taking a contract name only where it
 * is identical in meaning.
 *
 * Props:
 *   eyebrow    string|null  Contract name `eyebrow` (identical meaning). Null when blank.
 *   heading    string|null  Contract name `heading` (identical meaning). Null
 *                           when blank. Also names the track (aria-label).
 *   card_type  string       WordPress-local: 'generic'|'testimonial'
 *                           (invalid or blank => 'generic').
 *   cards      array<int, array{image_id: int, title: string, text: string, role: string}>
 *                           WordPress-local (named after the field), in row
 *                           order; never empty. title/text/role are trimmed,
 *                           '' for none (title or text is non-empty).
 *                           image_id is the stored ID as an int, 0 for none,
 *                           and is NOT validated here: before the split the
 *                           section passed it to x-image unchecked and x-image
 *                           decided (and logged) — kept as is (rule 2).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_scroll_carousel( array $context ): ?array {
	$sub = static function ( string $field ): string {
		if ( ! function_exists( 'get_sub_field' ) ) {
			return '';
		}
		$value = get_sub_field( $field );
		return is_string( $value ) ? $value : '';
	};

	$heading = $sub( 'heading' );
	$eyebrow = $sub( 'eyebrow' );

	$card_type = $sub( 'card_type' );
	if ( ! in_array( $card_type, array( 'generic', 'testimonial' ), true ) ) {
		$card_type = 'generic';
	}

	$rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'cards' ) : array();
	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	// Normalise repeater rows, skipping malformed items (logged — COMPL02).
	$items = array();
	foreach ( $rows as $i => $row ) {
		if ( ! is_array( $row ) ) {
			salt_log( sprintf( 'scroll-carousel section: card %d is not a row array — skipped (broken row).', (int) $i ) );
			continue;
		}

		$title = is_string( $row['title'] ?? null ) ? trim( $row['title'] ) : '';
		$text  = is_string( $row['text'] ?? null ) ? trim( $row['text'] ) : '';
		if ( '' === $title && '' === $text ) {
			salt_log( sprintf( 'scroll-carousel section: card %d has no title or text — skipped (broken row).', (int) $i ) );
			continue;
		}

		$image_raw = $row['image'] ?? 0;
		if ( is_array( $image_raw ) ) {
			$image_raw = $image_raw['ID'] ?? ( $image_raw['id'] ?? 0 );
		}

		$items[] = array(
			'image_id' => is_numeric( $image_raw ) ? (int) $image_raw : 0,
			'title'    => $title,
			'text'     => $text,
			'role'     => is_string( $row['role'] ?? null ) ? trim( $row['role'] ) : '',
		);
	}

	if ( array() === $items ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'scroll-carousel', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'eyebrow'   => salt_nullable_text( $eyebrow ),
			'heading'   => salt_nullable_text( $heading ),
			'card_type' => $card_type,
			'cards'     => $items,
		),
	);
}
