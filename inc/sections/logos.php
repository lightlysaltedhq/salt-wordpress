<?php
/**
 * Resolver: Logo / Trust Strip (spec §10 #9) — the logic half of the
 * section; the view is template-parts/sections/logos.php.
 *
 * A flex row of small client/press logos rendered through x-image (the
 * single image authority) at an explicit small size (sizes_context 'card',
 * height-capped utility classes). Logos with a URL are wrapped in a plain
 * <a> — external hrefs are explicitly allowed for logo links (a LINK, not
 * a loaded asset: PRIV01 holds — nothing loads from a foreign origin
 * unless the visitor clicks).
 *
 * "AS FEATURED IN" HOME (§18): the spec deliberately ships NO separate
 * press strip section — press/"as featured in" bands use THIS section
 * (heading e.g. "As featured in", logos linking to the coverage).
 *
 * Alt text: the repeater 'name' feeds the image alt ONLY when the
 * attachment has no stored alt (x-image's precedence: an explicit
 * non-empty alt wins; an empty alt arg falls back to the stored alt).
 * With neither, the logo is decorative (alt="").
 *
 * Greyscale: the 'grayscale' utility + hover:grayscale-0 (pure CSS — no
 * JS in this section at all).
 *
 * STATES (COMPL02): 0 resolvable logos => null (renders NOTHING, never a
 * blank band); broken rows (missing/unresolvable attachment) are skipped +
 * logged (DATA05). 1 logo => the same static flex row (inherently
 * JS-free). Headings are optional and usually absent — has_heading tracks
 * the field (A11Y01).
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
 *   greyscale  bool         Contract `greyscale`.
 *   items      array<int, array{image_id: int, alt: string, href: string, link_label: string}>
 *                           Contract `items` (logo), never empty. The item
 *                           props are WordPress-local: `image_id` (the
 *                           contract's `image` is an object), `alt` (the
 *                           alt arg for x-image: the name, only when the
 *                           attachment has no alt of its own; '' otherwise),
 *                           `href` (the contract's `href`, but '' = draw the
 *                           logo unlinked), `link_label` (the link's
 *                           aria-label; '' when unlinked). The contract's
 *                           `name` is not a prop: it only feeds `alt` and
 *                           `link_label`.
 *   The contract's `intro` and `columns` are not supplied (the layout has
 *   no such fields).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_logos( array $context ): ?array {
	$heading   = function_exists( 'get_sub_field' ) ? (string) get_sub_field( 'heading' ) : '';
	$greyscale = function_exists( 'get_sub_field' ) ? (bool) get_sub_field( 'greyscale' ) : false;

	$rows = function_exists( 'get_sub_field' ) ? get_sub_field( 'logos' ) : null;
	$rows = is_array( $rows ) ? $rows : array();

	// Normalise to renderable logos; broken rows skipped + logged (DATA05).
	$logos = array();
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

		$data = salt_image_data( $image_id, 'card' );
		if ( null === $data ) {
			continue; // Unresolvable attachment: salt_image_data() already logged (DATA05).
		}

		$name = trim( (string) ( $row['name'] ?? '' ) );
		$url  = trim( (string) ( $row['url'] ?? '' ) );
		// 'name' feeds alt ONLY when the attachment alt is empty — an
		// explicit non-empty alt arg beats the stored alt in x-image.
		$alt = '' === trim( (string) $data['alt'] ) ? $name : '';

		// Pre-split behaviour kept: $alt is only ever filled from $name, so
		// this is effectively "name is not empty". Reported as a Work Item;
		// do not "fix" here.
		$linked = '' !== $url && ( '' !== $name || '' !== $alt );
		if ( ! $linked && '' !== $url ) {
			salt_log( 'section logos: linked logo has no name or alt — rendered unlinked (accessible-name rule).' );
		}

		$logos[] = array(
			'image_id'   => $image_id,
			'alt'        => $alt,
			'href'       => $linked ? $url : '', // '' = draw unlinked.
			'link_label' => $linked ? ( '' !== $name ? $name : $alt ) : '',
		);
	}

	if ( array() === $logos ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'logos', $context ),
			// Usually heading-less: never claims the page H1 without one (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'heading'   => salt_nullable_text( $heading ),
			'greyscale' => $greyscale,
			'items'     => $logos,
		),
	);
}
