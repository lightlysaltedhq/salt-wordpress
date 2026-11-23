<?php
/**
 * Resolver: Media + Text (salt-contract media-text, rc.8; spec §10 #6) —
 * the logic half of the section; the view is
 * template-parts/sections/media-text.php. Drawn in the contract's shared
 * wrapper (salt_section(), EP-72 M4).
 *
 * Fields (contract/fields/media-text.json): mode (single|alternating),
 * rows (image, heading, content, mediaSide, buttons) and the shared
 * settings. A row's image takes the half slot beside its words, or the
 * content slot when the row has none (contract/image-sizes.json).
 *
 * MODE RULE: in 'alternating' mode the media side flips per RENDERED row —
 * even rows left, odd rows right (0-indexed) — and each row's mediaSide is
 * IGNORED. In 'single' mode each row's own mediaSide ('left' default)
 * decides. DOM order is always media then body; the side is the row's
 * data-media-side, which the stylesheet reads. The resolver applies the
 * rule and hands the view the side each row is drawn on.
 *
 * HEADING OUTLINE RULE (markup/media-text.json headings): this section has
 * NO heading field of its own — the FIRST rendered row's heading names the
 * section (plan level, id <anchor>__heading). has_heading is true only when
 * that row has a heading, so a leading headingless row never claims the
 * page's H1. Later rows' headings rank at body base + 1.
 *
 * States (COMPL02, markup zeroState): no row with an image, heading, body
 * or button => null (renders NOTHING, no band). Empty rows are skipped +
 * logged. No JS in any state.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current media-text Flexible Content row to its view props.
 *
 * Props (contract view-props/media-text.json):
 *   rows   array<int, array{image: array|null, heading: string|null, content: string|null, mediaSide: string|null, buttons: array}>
 *          In rendered order, never empty:
 *            image      The resolved image (salt_image_contract()), or null.
 *            heading    Null when blank.
 *            content    Sanitised HTML; null when it has no words.
 *            mediaSide  left|right, the side it is drawn on (the MODE RULE
 *                       applied); null unless the row has an image and words.
 *            buttons    Up to two { label, href, style, newTab }
 *                       (salt_contract_buttons(), default style secondary).
 *   arrow  string|null The site's arrow (salt_contract_arrow()).
 *   The contract's `mode` is not a prop: it only decides `mediaSide`.
 *
 * Plan: priorityMedia, the request for the page's priority image when a
 * rendered row draws an image; the view gives it to the first such row.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>, plan: array{priorityMedia: bool}}|null Null = 0-state.
 */
function salt_resolve_section_media_text( array $context ): ?array {
	$mode = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'mode' ) : '';
	if ( ! in_array( $mode, array( 'single', 'alternating' ), true ) ) {
		if ( '' !== $mode ) {
			salt_log( sprintf( 'section media-text: invalid mode "%s" — using single.', $mode ) );
		}
		$mode = 'single';
	}

	$rows_raw = function_exists( 'get_sub_field' ) ? get_sub_field( 'rows' ) : null;
	$rows_raw = is_array( $rows_raw ) ? $rows_raw : array();
	$section  = salt_section_contract_args( 'media-text', $context );
	$band     = (string) $section['container'];

	$rows = array();
	foreach ( $rows_raw as $i => $row ) {
		if ( ! is_array( $row ) ) {
			salt_log( sprintf( 'section media-text: row %d is malformed — skipped.', (int) $i ) );
			continue;
		}

		$image_raw = $row['image'] ?? 0;
		$image_id  = is_array( $image_raw ) ? (int) ( $image_raw['ID'] ?? 0 ) : (int) $image_raw;
		$heading   = salt_nullable_text( is_string( $row['heading'] ?? null ) ? $row['heading'] : '' );
		$content   = salt_sanitize_html( is_string( $row['content'] ?? null ) ? $row['content'] : '', salt_field_allowed( 'field_salt_media_text_rows_content' ) );
		$content   = salt_rich_text_has_words( $content ) ? $content : null;
		$buttons   = salt_contract_button_props( salt_contract_buttons( $row['buttons'] ?? null, sprintf( 'section media-text: row %d', (int) $i ), 2, 'secondary' ) );
		$words     = null !== $heading || null !== $content || array() !== $buttons;

		// The half slot beside words, the content slot alone; drawn only
		// with dimensions (markup/media-text.json media).
		$image = $image_id > 0 ? salt_image_contract( $image_id, salt_image_slot_sizes( $words ? 'half' : 'content', $band ) ) : null;
		if ( null !== $image && ( $image['width'] <= 0 || $image['height'] <= 0 ) ) {
			salt_log( sprintf( 'section media-text: row %d image %d has no width or height — not rendered.', (int) $i, $image_id ) );
			$image = null;
		}

		if ( null === $image && ! $words ) {
			salt_log( sprintf( 'section media-text: row %d has no image, heading, text or button — skipped.', (int) $i ) );
			continue;
		}

		$rows[] = array(
			'image'     => $image,
			'heading'   => $heading,
			'content'   => $content,
			// Stored side, until the MODE RULE below settles the drawn one.
			'mediaSide' => 'right' === ( $row['mediaSide'] ?? '' ) ? 'right' : 'left',
			'buttons'   => $buttons,
			'words'     => $words,
		);
	}

	if ( array() === $rows ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	// MODE RULE (see file docblock): alternating flips per RENDERED row and
	// ignores mediaSide; single respects it. Only a row with an image and
	// words has a side.
	foreach ( $rows as $n => $row ) {
		if ( 'alternating' === $mode ) {
			$row['mediaSide'] = 0 === $n % 2 ? 'left' : 'right';
		}
		$rows[ $n ]['mediaSide'] = null !== $row['image'] && $row['words'] ? $row['mediaSide'] : null;
		unset( $rows[ $n ]['words'] );
	}

	return array(
		'section' => array_merge(
			$section,
			// The FIRST rendered row's heading is the section heading; without
			// one this section is a div and must not claim the page H1 (A11Y01).
			array( 'has_heading' => null !== $rows[0]['heading'] )
		),
		'props'   => array(
			'rows'  => $rows,
			'arrow' => salt_contract_arrow(),
		),
		// The band's principal image is its first row's that has one.
		'plan'    => array( 'priorityMedia' => salt_section_plan_priority( $context ) && array() !== array_filter( array_column( $rows, 'image' ) ) ),
	);
}
