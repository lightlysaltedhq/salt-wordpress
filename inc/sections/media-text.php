<?php
/**
 * Resolver: Media + Text (spec §10 #6) — the logic half of the section;
 * the view is template-parts/sections/media-text.php.
 *
 * Inline repeater of image/text rows on a md:grid-cols-2 grid; images render
 * through x-image with sizes_context 'half'. Text-only rows (no image) render
 * full-width — never an empty media column.
 *
 * MODE RULE: in 'alternating' mode the media side flips automatically per
 * RENDERED row — even rows media-left, odd rows media-right (0-indexed) —
 * and each row's mediaSide sub-field is IGNORED. In 'single' mode each
 * row's own mediaSide ('left' default) decides the column order. The flip
 * is purely visual (md:order-2 on the media cell); DOM order is always
 * media then body, so the reading order never changes. The resolver applies
 * the rule and hands the view the side each row is drawn on.
 *
 * HEADING OUTLINE RULE (A11Y01/AMBIG03): this section has NO heading field
 * of its own — the FIRST rendered row's heading acts as the section heading.
 * has_heading is true only when that first row has a non-empty heading, so
 * a leading headingless row can never consume the page's H1 claim. In the
 * view: row 0's heading renders at the section's heading level (H1 only when
 * the wrapper designates this section primary AND the claim succeeds); every
 * LATER row's heading renders at ( 1 === level ? 2 : level ), so the outline
 * is h1 → h2 siblings on a primary section and h2 all round otherwise —
 * never a skipped level, never multiple H1s.
 *
 * States (COMPL02): 0 usable rows => null (renders NOTHING, no section
 * band). A row is usable when it has an image, a heading or content;
 * malformed/empty rows are skipped + logged. No JS in any state.
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
 *   rows  array<int, array{image_id: int, heading: string|null, content: string|null, mediaSide: string, cta: array{url: string, title: string, target: string}|null}>
 *         Contract `rows` (row), in rendered order, never empty:
 *           image_id   WordPress-local (the contract's `image` is an
 *                      object): a resolvable attachment ID, 0 for none.
 *           heading    Contract `heading`. Null when blank; trimmed.
 *           content    Contract `content` (rich text). Sanitised HTML
 *                      (salt_sanitize_html()); null when blank.
 *           mediaSide  Contract `mediaSide`: 'left'|'right'. The EFFECTIVE
 *                      side: the MODE RULE is already applied.
 *           cta        WordPress-local (the contract has `buttons`, a list
 *                      of button): url/title/target of the row's one link,
 *                      or null (absent or malformed).
 *   The contract's `mode` is not a prop: it only decides `mediaSide`.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
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

	// Normalise rows; broken/malformed rows are skipped + logged (DATA05 spirit).
	$rows = array();
	foreach ( $rows_raw as $i => $row ) {
		if ( ! is_array( $row ) ) {
			salt_log( sprintf( 'section media-text: row %d is malformed — skipped.', (int) $i ) );
			continue;
		}

		$image_raw = $row['image'] ?? 0;
		$image_id  = is_array( $image_raw ) ? (int) ( $image_raw['ID'] ?? 0 ) : (int) $image_raw;
		$heading   = trim( (string) ( $row['heading'] ?? '' ) );
		$content   = trim( (string) ( $row['content'] ?? '' ) );

		// A deleted attachment must not count as row content (COMPL02).
		if ( $image_id > 0 && null === salt_image_data( $image_id, 'half' ) ) {
			salt_log( sprintf( 'section media-text: row %d image %d is unresolvable — treated as absent.', (int) $i, $image_id ) );
			$image_id = 0;
		}

		if ( 0 >= $image_id && '' === $heading && '' === $content ) {
			salt_log( sprintf( 'section media-text: row %d has no image, heading or content — skipped.', (int) $i ) );
			continue;
		}

		$cta       = null;
		$cta_raw   = $row['cta'] ?? null;
		$cta_url   = is_array( $cta_raw ) ? trim( (string) ( $cta_raw['url'] ?? '' ) ) : '';
		$cta_title = is_array( $cta_raw ) ? trim( (string) ( $cta_raw['title'] ?? '' ) ) : '';
		if ( is_array( $cta_raw ) && array() !== $cta_raw ) {
			if ( '' !== $cta_url && '' !== $cta_title ) {
				$cta = array(
					'url'    => $cta_url,
					'title'  => $cta_title,
					'target' => '_blank' === ( $cta_raw['target'] ?? '' ) ? '_blank' : '',
				);
			} else {
				salt_log( sprintf( 'section media-text: row %d has a malformed CTA link (url and title required) — CTA skipped.', (int) $i ) );
			}
		}

		$rows[] = array(
			'image_id'  => $image_id,
			'heading'   => '' !== $heading ? $heading : null,
			'content'   => '' !== $content ? salt_sanitize_html( $content ) : null,
			// Stored side, until the MODE RULE below settles the effective one.
			'mediaSide' => 'right' === ( $row['mediaSide'] ?? '' ) ? 'right' : 'left',
			'cta'       => $cta,
		);
	}

	if ( array() === $rows ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	// MODE RULE (see file docblock): alternating flips per RENDERED row and
	// ignores mediaSide; single respects it.
	if ( 'alternating' === $mode ) {
		foreach ( array_keys( $rows ) as $n ) {
			$rows[ $n ]['mediaSide'] = 0 === $n % 2 ? 'left' : 'right';
		}
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'media-text', $context ),
			// The FIRST rendered row's heading is the section heading; without
			// one this section must not claim the page H1 (A11Y01).
			array( 'has_heading' => null !== $rows[0]['heading'] )
		),
		'props'   => array(
			'rows' => $rows,
		),
	);
}
