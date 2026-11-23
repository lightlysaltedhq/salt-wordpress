<?php
/**
 * Resolver: Hero (salt-contract hero, rc.8; spec §10 #1) — the logic half
 * of the section; the view is template-parts/sections/hero.php. Drawn in
 * the contract's shared wrapper (salt_section(), EP-72 M4).
 *
 * Fields (contract/fields/hero.json): variant (full-bleed|split|stacked|
 * minimal), eyebrow, heading, subheading, image (split and stacked),
 * mediaSide (split), alignment (minimal), buttons (a list of up to two
 * { link, style }) and the shared settings. A full-bleed hero's photograph
 * is the section's background image (settings.backgroundImage), which the
 * wrapper draws behind the words.
 *
 * STATES (COMPL02, markup zeroState): eyebrow, heading, subheading, image
 * and buttons all empty => null (renders NOTHING, no band). A full-bleed
 * hero's image is its renderable background; a minimal hero has none.
 *
 * VARIANTS: an unknown variant degrades to full-bleed + salt_log(); the
 * minimal variant is text-only: a set image is dropped + salt_log()ged.
 *
 * DEFERRAL: spec media "image OR video" — video is absent in this build
 * (§3 locked decisions: no embeds/video). Image only.
 *
 * Button rows that make no usable link (salt_contract_buttons()) are
 * skipped + logged, never a broken button. No schema, no JS.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current hero Flexible Content row to its view props.
 *
 * Props (contract view-props/hero.json):
 *   variant     string       full-bleed|split|stacked|minimal.
 *   eyebrow     string|null  Null when blank.
 *   heading     string|null  Null when blank.
 *   subheading  string|null  Null when blank.
 *   image       array|null   The resolved image (salt_image_contract(): the
 *                            half slot for split, the content slot for
 *                            stacked, at the band's width); null for
 *                            full-bleed and minimal, or when none resolves.
 *   mediaSide   string       left|right.
 *   alignment   string       left|centre.
 *   buttons     array<int, array{label: string, href: string, style: string, newTab: bool}>
 *                            Valid links only, in row order.
 *   arrow       string|null  The site's arrow (salt_contract_arrow()).
 *
 * Plan: priorityMedia, the request for the page's priority image when the
 * hero draws its own image (a full-bleed background is the wrapper's).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>, plan: array{priorityMedia: bool}}|null Null = 0-state.
 */
function salt_resolve_section_hero( array $context ): ?array {
	$sub        = static fn( string $field ): string => is_string( get_sub_field( $field ) ) ? (string) get_sub_field( $field ) : '';
	$variant    = $sub( 'variant' );
	$eyebrow    = salt_nullable_text( $sub( 'eyebrow' ) );
	$heading    = salt_nullable_text( $sub( 'heading' ) );
	$subheading = salt_nullable_text( $sub( 'subheading' ) );

	if ( ! in_array( $variant, array( 'full-bleed', 'split', 'stacked', 'minimal' ), true ) ) {
		if ( '' !== $variant ) {
			salt_log( sprintf( 'section hero: unknown variant "%s" — using full-bleed.', $variant ) );
		}
		$variant = 'full-bleed';
	}

	$section  = salt_section_contract_args( 'hero', $context );
	$image_id = (int) get_sub_field( 'image' );
	$image    = null;
	if ( 'minimal' === $variant && $image_id > 0 ) {
		salt_log( sprintf( 'section hero: minimal variant ignores media — image %d not rendered.', $image_id ) );
	} elseif ( in_array( $variant, array( 'split', 'stacked' ), true ) && $image_id > 0 ) {
		$image = salt_image_contract( $image_id, salt_image_slot_sizes( 'split' === $variant ? 'half' : 'content', (string) $section['container'] ) );
		if ( null !== $image && ( $image['width'] <= 0 || $image['height'] <= 0 ) ) {
			$image = null; // Drawn only with dimensions (markup/hero.json media).
		}
	}

	$buttons = salt_contract_button_props( salt_contract_buttons( get_sub_field( 'buttons' ), 'section hero', 2 ) );

	// A full-bleed hero's photograph is the band's background image.
	$background = 'full-bleed' === $variant && null !== $section['background_image'];
	if ( null === $eyebrow && null === $heading && null === $subheading && null === $image && ! $background && array() === $buttons ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	return array(
		'section' => array_merge(
			$section,
			// A section with no heading is a div and must not claim the page H1 (A11Y01).
			array( 'has_heading' => null !== $heading )
		),
		'props'   => array(
			'variant'    => $variant,
			'eyebrow'    => $eyebrow,
			'heading'    => $heading,
			'subheading' => $subheading,
			'image'      => $image,
			'mediaSide'  => 'right' === $sub( 'mediaSide' ) ? 'right' : 'left',
			'alignment'  => in_array( $sub( 'alignment' ), array( 'centre', 'center' ), true ) ? 'centre' : 'left',
			'buttons'    => $buttons,
			'arrow'      => salt_contract_arrow(),
		),
		'plan'    => array( 'priorityMedia' => salt_section_plan_priority( $context ) && null !== $image ),
	);
}
