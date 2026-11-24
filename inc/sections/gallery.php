<?php
/**
 * Resolver: Gallery (salt-contract gallery, rc.8; spec §10 #16; FAIL02) —
 * the logic half of the section; the view is template-parts/sections/
 * gallery.php, drawn in the contract's shared wrapper (salt_section(),
 * EP-72 M4).
 *
 * Fields (contract/fields/gallery.json): heading, intro, layout
 * (grid|masonry|carousel; invalid => grid, logged), columns ('2'|'3'|'4';
 * invalid => 3, logged), label (names an unheaded carousel; never shown),
 * captions (toggle, default on), images (gallery). Images resolve to the
 * contract's media at their slot (one image: content; a carousel slide:
 * card, unbanded; grid or masonry: card at the columns); broken entries
 * are skipped + logged (DATA05). Captions come from the media library when
 * the captions toggle is on.
 *
 * States (COMPL02, markup zeroState): heading, intro and images all empty
 * => null (renders NOTHING, no band); a heading-only gallery draws. 1 image
 * => one figure in every layout; N => the chosen layout, and a carousel
 * with neither a heading nor a label draws the grid (view-props `named`).
 * The first image is the section's principal image.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current gallery Flexible Content row to its view props.
 *
 * Props (contract view-props/gallery.json):
 *   heading   string|null  Contract `heading`. Null when blank.
 *   intro     string|null  Contract `intro`. Null when blank.
 *   layout    string       Contract `layout`: 'grid'|'masonry'|'carousel'.
 *   columns   string       Contract `columns`: '2'|'3'|'4'.
 *   label     string|null  Contract `label`. Null when blank.
 *   named     bool         Contract `named`: a heading or a label is set.
 *   captions  bool         Contract `captions`. Already applied: an image's
 *                          caption is null when they are off.
 *   images    array<int, array>  Contract `images`: salt_image_contract()
 *                          per resolvable image, in field order; may be empty.
 *   labels    array<string,string> salt_contract_labels() (labels.slide).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>, plan: array{priorityMedia: bool}}|null Null = 0-state.
 */
function salt_resolve_section_gallery( array $context ): ?array {
	$sub     = static fn( string $field ): string => function_exists( 'get_sub_field' ) && is_scalar( get_sub_field( $field ) ) ? (string) get_sub_field( $field ) : '';
	$heading = salt_nullable_text( $sub( 'heading' ) );
	$intro   = salt_nullable_text( $sub( 'intro' ) );
	$label   = salt_nullable_text( $sub( 'label' ) );
	$section = salt_section_contract_args( 'gallery', $context );

	$layout = $sub( 'layout' );
	if ( ! in_array( $layout, array( 'grid', 'masonry', 'carousel' ), true ) ) {
		if ( '' !== $layout ) {
			salt_log( sprintf( 'section gallery: invalid layout "%s" — using grid.', $layout ) );
		}
		$layout = 'grid';
	}

	$columns = $sub( 'columns' );
	if ( ! in_array( $columns, array( '2', '3', '4' ), true ) ) {
		if ( '' !== $columns ) {
			salt_log( sprintf( 'section gallery: invalid columns "%s" — using 3.', $columns ) );
		}
		$columns = '3';
	}

	// true_false, default 1: an unsaved toggle is on.
	$captions = function_exists( 'get_sub_field' ) ? get_sub_field( 'captions' ) : null;
	$captions = null === $captions ? true : (bool) $captions;
	$named    = null !== $heading || null !== $label;

	$ids_raw = function_exists( 'get_sub_field' ) ? get_sub_field( 'images' ) : null;
	$ids_raw = is_array( $ids_raw ) ? $ids_raw : array();

	$ids = array();
	foreach ( $ids_raw as $raw ) {
		$id = is_array( $raw ) ? (int) ( $raw['ID'] ?? 0 ) : (int) $raw;
		if ( $id <= 0 ) {
			salt_log( 'section gallery: malformed image entry — skipped.' );
			continue;
		}
		$ids[] = $id;
	}

	// The slot each image is drawn in (contract/image-sizes.json): a slide
	// is a card whatever the band, a grid or masonry cell a card at the
	// columns, and a lone image fills the content box. An unresolvable
	// attachment is skipped (logged by salt_image_contract(), DATA05), so
	// a set that falls to one image is drawn again at the content slot.
	$sizes = 'carousel' === $layout && $named ? salt_image_slot_sizes( 'card' ) : salt_image_slot_sizes( 'card', $section['container'], $columns );
	$found = array(); // Pairs of [ attachment ID, image ], in order; an ID may repeat.
	foreach ( $ids as $id ) {
		$image = 1 === count( $ids ) ? null : salt_image_contract( $id, $sizes );
		if ( 1 === count( $ids ) || null !== $image ) {
			$found[] = array( $id, $image );
		}
	}
	if ( 1 === count( $found ) ) {
		$found = array( array( $found[0][0], salt_image_contract( $found[0][0], salt_image_slot_sizes( 'content', $section['container'] ) ) ) );
	}
	$images = array_values( array_filter( array_column( $found, 1 ) ) );
	// A caption is drawn trimmed, and only when captions are on.
	$images = array_map(
		static fn( array $image ): array => array_merge( $image, array( 'caption' => $captions && null !== $image['caption'] ? salt_nullable_text( trim( (string) $image['caption'] ) ) : null ) ),
		$images
	);

	if ( null === $heading && null === $intro && array() === $images ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			$section,
			// A section with no heading is a div and must not claim the page H1 (A11Y01).
			array( 'has_heading' => null !== $heading )
		),
		'props'   => array(
			'heading'  => $heading,
			'intro'    => $intro,
			'layout'   => $layout,
			'columns'  => $columns,
			'label'    => $label,
			'named'    => $named,
			'captions' => $captions,
			'images'   => $images,
			'labels'   => salt_contract_labels(),
		),
		'plan'    => array( 'priorityMedia' => salt_section_plan_priority( $context ) && null === $section['background_image'] && array() !== $images ),
	);
}
