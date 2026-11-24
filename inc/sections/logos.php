<?php
/**
 * Resolver: Logo / Trust Strip (salt-contract logos, rc.8; spec §10 #9) —
 * the logic half of the section; the view is template-parts/sections/
 * logos.php, drawn in the contract's shared wrapper (salt_section(),
 * EP-72 M4).
 *
 * A grid of small client/press logos, each the contract's media at the
 * thumb slot inside a frame: a plain <a> when the logo has a safe address
 * and a name (an external LINK, not a loaded asset: PRIV01 holds), a span
 * otherwise.
 *
 * "AS FEATURED IN" HOME (§18): the spec deliberately ships NO separate
 * press strip section — press/"as featured in" bands use THIS section.
 *
 * Alt text: the attachment's stored alt, else the row's name, else empty
 * (decorative).
 *
 * STATES (COMPL02, markup zeroState): heading, intro and logos all empty
 * => null (renders NOTHING, no band); broken rows (missing/unresolvable
 * attachment) are skipped + logged (DATA05). Logos never take the page's
 * priority image.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current logos Flexible Content row to its view props.
 *
 * Props (contract view-props/logos.json):
 *   heading    string|null  Contract `heading`. Null when blank.
 *   intro      string|null  Contract `intro`. Null when blank.
 *   columns    string       Contract `columns`: '2'|'3'|'4' (default '4').
 *   greyscale  bool         Contract `greyscale` (an unsaved toggle is on).
 *   items      array<int, array{image: array, name: string|null, href: string|null}>
 *                           Contract `items` (logo), may be empty. image is
 *                           salt_image_contract() at the thumb slot, its alt
 *                           the stored alt, else the name; href null unless
 *                           the address is safe and the logo has a name.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_logos( array $context ): ?array {
	$sub       = static fn( string $field ): string => function_exists( 'get_sub_field' ) && is_string( get_sub_field( $field ) ) ? (string) get_sub_field( $field ) : '';
	$heading   = salt_nullable_text( $sub( 'heading' ) );
	$intro     = salt_nullable_text( $sub( 'intro' ) );
	$columns   = in_array( $sub( 'columns' ), array( '2', '3', '4' ), true ) ? $sub( 'columns' ) : '4';
	$greyscale = function_exists( 'get_sub_field' ) ? get_sub_field( 'greyscale' ) : null;
	$greyscale = null === $greyscale ? true : (bool) $greyscale; // true_false, default 1: an unsaved toggle is on.

	$rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'items' ) : null;
	$rows = is_array( $rows ) ? $rows : array();

	// Normalise to renderable logos; broken rows skipped + logged (DATA05).
	$logos    = array();
	$unlinked = 0; // Logos with an address but no name.
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			salt_log( 'section logos: malformed logo row — skipped.' );
			continue;
		}

		$raw_image = $row['image'] ?? 0;
		$image_id  = is_array( $raw_image ) ? (int) ( $raw_image['ID'] ?? 0 ) : (int) $raw_image;
		if ( $image_id <= 0 ) {
			salt_log( 'section logos: logo row has no image — skipped (broken row).' );
			continue;
		}

		$image = salt_image_contract( $image_id, salt_image_slot_sizes( 'thumb' ) );
		if ( null === $image ) {
			continue; // Unresolvable attachment: already logged (DATA05).
		}

		$name = trim( is_string( $row['name'] ?? null ) ? $row['name'] : '' );
		$url  = esc_url_raw( trim( is_string( $row['url'] ?? null ) ? $row['url'] : '' ) );
		if ( '' === trim( $image['alt'] ) ) {
			$image['alt'] = $name;
		}
		if ( '' !== $url && '' === $name ) {
			++$unlinked;
		}

		$logos[] = array(
			'image' => $image,
			'name'  => '' !== $name ? $name : null,
			'href'  => '' !== $url && '' !== $name ? $url : null,
		);
	}

	if ( null === $heading && null === $intro && array() === $logos ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	for ( $n = 0; $n < $unlinked; $n++ ) {
		salt_log( 'section logos: linked logo has no name — rendered unlinked (accessible-name rule).' );
	}

	return array(
		'section' => array_merge(
			salt_section_contract_args( 'logos', $context ),
			// Usually heading-less: a div that never claims the page H1 (A11Y01).
			array( 'has_heading' => null !== $heading )
		),
		'props'   => array(
			'heading'   => $heading,
			'intro'     => $intro,
			'columns'   => $columns,
			'greyscale' => $greyscale,
			'items'     => $logos,
		),
	);
}
