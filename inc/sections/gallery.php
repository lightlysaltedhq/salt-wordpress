<?php
/**
 * Resolver: Gallery (spec §10 #16; FAIL02) — the logic half of the
 * section; the view is template-parts/sections/gallery.php.
 *
 * Fields (contract/fields/gallery.json): layout (grid|masonry|carousel;
 * invalid => grid, logged), columns ('2'|'3'|'4'; invalid => 3, logged),
 * captions (toggle, default on), images (gallery). heading, intro and
 * label are stored but not drawn yet (M4).
 * Images are normalised to resolvable attachment IDs; broken/malformed
 * entries are skipped + logged (DATA05). Captions come from the attachment
 * caption (wp_get_attachment_caption) when the captions toggle is on.
 *
 * States (COMPL02): 0 resolvable images => null (renders NOTHING, no
 * band). 1 image => the view draws ONE static full-width figure in every
 * layout; N => the chosen layout.
 *
 * Headings (A11Y01): the layout has no heading field — has_heading is
 * always false, so a gallery never claims the page H1.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current gallery Flexible Content row to its view props.
 *
 * Props (contract view-props/gallery.json; WordPress-local names in snake_case):
 *   layout    string  Contract `layout`: 'grid'|'masonry'|'carousel'
 *                     (validated; invalid or blank => 'grid').
 *   columns   int     Contract `columns`, as an int 2|3|4 (the contract's
 *                     enum is the strings "2"|"3"|"4"); invalid or blank => 3.
 *   captions  bool    Contract `captions`: the toggle. Already applied to
 *                     each item's caption; a view draws nothing for it.
 *   items     array<int, array{image_id: int, caption: string}>
 *                     WordPress-local (contract `images` is a list of
 *                     resolved image objects): RESOLVABLE attachment IDs in
 *                     field order, never empty; caption is the trimmed
 *                     attachment caption, '' for none (or captions off).
 *
 * Naming gaps against the contract: `heading`, `intro`, `label` and `named`
 * are stored but have no logic yet (M4) (no heading field: the carousel is
 * drawn with a fixed aria-label, where the contract draws an unnamed set as
 * a grid); `images` is `items` (attachment IDs, not resolved images).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_gallery( array $context ): ?array {
	$layout = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'layout' ) : '';
	if ( ! in_array( $layout, array( 'grid', 'masonry', 'carousel' ), true ) ) {
		if ( '' !== $layout ) {
			salt_log( sprintf( 'section gallery: invalid layout "%s" — using grid.', $layout ) );
		}
		$layout = 'grid';
	}

	$columns = function_exists( 'get_sub_field' ) ? (int) get_sub_field( 'columns' ) : 0;
	if ( ! in_array( $columns, array( 2, 3, 4 ), true ) ) {
		if ( 0 !== $columns ) {
			salt_log( sprintf( 'section gallery: invalid columns "%d" — using 3.', $columns ) );
		}
		$columns = 3;
	}

	$captions = function_exists( 'get_sub_field' ) ? (bool) get_sub_field( 'captions' ) : false;

	$ids_raw = function_exists( 'get_sub_field' ) ? get_sub_field( 'images' ) : null;
	$ids_raw = is_array( $ids_raw ) ? $ids_raw : array();

	// Normalise to resolvable attachment IDs; broken entries skipped + logged.
	$items = array();
	foreach ( $ids_raw as $raw ) {
		$id = is_array( $raw ) ? (int) ( $raw['ID'] ?? 0 ) : (int) $raw;
		if ( $id <= 0 ) {
			salt_log( 'section gallery: malformed image entry — skipped.' );
			continue;
		}
		if ( null === salt_image_data( $id, 'gallery' ) ) {
			continue; // Unresolvable attachment: salt_image_data() already logged (DATA05).
		}

		$caption = '';
		if ( $captions && function_exists( 'wp_get_attachment_caption' ) ) {
			$caption = trim( (string) wp_get_attachment_caption( $id ) );
		}

		$items[] = array(
			'image_id' => $id,
			'caption'  => $caption,
		);
	}

	if ( array() === $items ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'gallery', $context ),
			// No heading field: never claims the page H1 (A11Y01).
			array( 'has_heading' => false )
		),
		'props'   => array(
			'layout'   => $layout,
			'columns'  => $columns,
			'captions' => $captions,
			'items'    => $items,
		),
	);
}
