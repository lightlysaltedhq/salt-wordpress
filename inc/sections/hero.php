<?php
/**
 * Resolver: Hero (spec §10 #1) — the logic half of the section; the view
 * is template-parts/sections/hero.php.
 *
 * Fields: variant (full-bleed|split|minimal), eyebrow, heading, subheading,
 * image (attachment ID), cta_primary + cta_secondary (ACF link arrays).
 *
 * STATES (COMPL02): heading empty AND no image => null (renders NOTHING —
 * a hero with neither is an empty band). The heading renders at the
 * wrapper-derived level: this is normally the page's primary H1
 * (has_heading true when the heading is non-empty, A11Y01).
 *
 * VARIANTS: an unknown variant degrades to full-bleed + salt_log(); the
 * minimal variant is text-only — a set image is dropped + salt_log()ged.
 * full-bleed forces the wrapper container to 'full' (the view's text
 * block re-containers itself) and, with an image, flush_top.
 *
 * DEFERRAL: spec media "image OR video" — video is absent in this build
 * (§3 locked decisions: no embeds/video). Image only.
 *
 * Malformed CTA link rows (missing url/title) are skipped + logged, never
 * a broken button. No schema, no JS.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current hero Flexible Content row to its view props.
 *
 * Props (contract view-props/hero.json; WordPress-local names in snake_case):
 *   variant        string       Contract `variant`: 'full-bleed'|'split'|'minimal'.
 *   eyebrow        string|null  Contract `eyebrow`. Null when blank.
 *   heading        string|null  Contract `heading`. Null when blank.
 *   subheading     string|null  Contract `subheading`. Null when blank.
 *   image_id       int          WordPress-local (contract `image` is a resolved
 *                               image object): a RESOLVABLE attachment ID, or
 *                               0 for none. Always 0 for the minimal variant.
 *   priorityMedia  bool         Contract `priorityMedia` (on the contract's
 *                               plan): the hero image is THE LCP image —
 *                               eager + fetchpriority=high (§8). Always true.
 *   ctas           array<int, array{label: string, url: string, style: string, target: string}>
 *                               WordPress-local (contract `buttons`): valid
 *                               links only, x-button args, primary first.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_hero( array $context ): ?array {
	$variant    = (string) get_sub_field( 'variant' );
	$eyebrow    = (string) get_sub_field( 'eyebrow' );
	$heading    = (string) get_sub_field( 'heading' );
	$subheading = (string) get_sub_field( 'subheading' );
	$image_id   = (int) get_sub_field( 'image' );

	if ( ! in_array( $variant, array( 'full-bleed', 'split', 'minimal' ), true ) ) {
		if ( '' !== $variant ) {
			salt_log( sprintf( 'section hero: unknown variant "%s" — using full-bleed.', $variant ) );
		}
		$variant = 'full-bleed';
	}

	// Validate the attachment up front: a deleted image must zero the ID so
	// an image-only hero 0-states instead of rendering a blank band (COMPL02).
	if ( $image_id > 0 && null === salt_image_data( $image_id, 'hero' ) ) {
		salt_log( sprintf( 'section hero: image %d is unresolvable — treated as absent.', $image_id ) );
		$image_id = 0;
	}

	if ( '' === trim( $heading ) && 0 === $image_id ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	// The minimal variant is text-only: a set image is ignored, visibly logged.
	if ( 'minimal' === $variant && $image_id > 0 ) {
		salt_log( sprintf( 'section hero: minimal variant ignores media — image %d not rendered.', $image_id ) );
		$image_id = 0;
		if ( '' === trim( $heading ) ) {
			return null; // Nothing left to render (COMPL02).
		}
	}

	// CTAs: ACF link arrays; malformed rows skipped + logged (never a broken button).
	$ctas = array();
	foreach ( array(
		'cta_primary'   => 'primary',
		'cta_secondary' => 'secondary',
	) as $cta_field => $cta_style ) {
		$link = get_sub_field( $cta_field );
		if ( empty( $link ) ) {
			continue; // Optional field left empty — not an error.
		}
		if ( ! is_array( $link ) || '' === trim( (string) ( $link['url'] ?? '' ) ) || '' === trim( (string) ( $link['title'] ?? '' ) ) ) {
			salt_log( sprintf( 'section hero: %s link is malformed (missing url/title) — skipped.', $cta_field ) );
			continue;
		}
		$ctas[] = array(
			'label'  => (string) $link['title'],
			'url'    => (string) $link['url'],
			'style'  => $cta_style,
			'target' => (string) ( $link['target'] ?? '' ),
		);
	}

	$wrapper = salt_section_wrapper_args( 'hero', $context );
	if ( 'full-bleed' === $variant ) {
		// Edge-to-edge image: the wrapper goes full; text re-containers itself.
		$wrapper['container'] = 'full';
		// The banner meets the header — no dead strip above the image. Text-only
		// full-bleed keeps its top padding (nothing to bleed).
		if ( $image_id > 0 ) {
			$wrapper['flush_top'] = true;
		}
	}

	return array(
		'section' => array_merge(
			$wrapper,
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'variant'       => $variant,
			'eyebrow'       => salt_nullable_text( $eyebrow ),
			'heading'       => salt_nullable_text( $heading ),
			'subheading'    => salt_nullable_text( $subheading ),
			'image_id'      => $image_id,
			'priorityMedia' => true,
			'ctas'          => $ctas,
		),
	);
}
