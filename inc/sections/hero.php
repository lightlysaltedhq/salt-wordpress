<?php
/**
 * Resolver: Hero (spec §10 #1) — the logic half of the section; the view
 * is template-parts/sections/hero.php.
 *
 * Fields (contract/fields/hero.json): variant (full-bleed|split|stacked|
 * minimal), eyebrow, heading, subheading, image (split and stacked),
 * buttons (a list of up to two { link, style }). A full-bleed hero's image
 * is the section's background image (settings.backgroundImage.image); its
 * fit, position and scrim are not drawn yet (unit 9). mediaSide and
 * alignment are stored but not drawn yet (M4).
 *
 * STATES (COMPL02): heading empty AND no image => null (renders NOTHING —
 * a hero with neither is an empty band). The heading renders at the
 * wrapper-derived level: this is normally the page's primary H1
 * (has_heading true when the heading is non-empty, A11Y01).
 *
 * VARIANTS: an unknown variant degrades to full-bleed + salt_log(); the
 * minimal variant is text-only — a set image is dropped + salt_log()ged.
 * stacked is drawn as split until the view learns it (M4).
 * full-bleed forces the wrapper container to 'full' (the view's text
 * block re-containers itself) and, with an image, flush_top.
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
 * Props (contract view-props/hero.json; WordPress-local names in snake_case):
 *   variant        string       Contract `variant`: 'full-bleed'|'split'|'minimal'.
 *   eyebrow        string|null  Contract `eyebrow`. Null when blank.
 *   heading        string|null  Contract `heading`. Null when blank.
 *   subheading     string|null  Contract `subheading`. Null when blank.
 *   image_id       int          WordPress-local (contract `image` is a resolved
 *                               image object): a RESOLVABLE attachment ID, or
 *                               0 for none. Always 0 for the minimal variant.
 *   ctas           array<int, array{label: string, url: string, style: string, target: string}>
 *                               WordPress-local (contract `buttons`): valid
 *                               links only, x-button args, in row order.
 *
 * Plan (returned under `plan`, merged into the view's $args['plan']):
 *   priorityMedia  true         Contract plan `priorityMedia`: the hero image
 *                               is THE LCP image — eager + fetchpriority=high
 *                               (§8). True only when it draws one (SC-026).
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>, plan: array{priorityMedia: bool}}|null Null = 0-state.
 */
function salt_resolve_section_hero( array $context ): ?array {
	$variant    = (string) get_sub_field( 'variant' );
	$eyebrow    = (string) get_sub_field( 'eyebrow' );
	$heading    = (string) get_sub_field( 'heading' );
	$subheading = (string) get_sub_field( 'subheading' );

	if ( ! in_array( $variant, array( 'full-bleed', 'split', 'stacked', 'minimal' ), true ) ) {
		if ( '' !== $variant ) {
			salt_log( sprintf( 'section hero: unknown variant "%s" — using full-bleed.', $variant ) );
		}
		$variant = 'full-bleed';
	}
	if ( 'stacked' === $variant ) {
		$variant = 'split'; // Not drawn yet: the nearest layout WordPress has (M4).
	}

	// Split and stacked carry their own image; full-bleed draws the section's
	// background image; minimal has none (contract/fields/hero.json).
	if ( 'full-bleed' === $variant ) {
		$background = salt_section_settings()['backgroundImage'] ?? null;
		$image_id   = is_array( $background ) ? (int) ( $background['image'] ?? 0 ) : 0;
	} else {
		$image_id = (int) get_sub_field( 'image' );
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

	$ctas = salt_contract_buttons( get_sub_field( 'buttons' ), 'section hero', 2 );

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
			'variant'    => $variant,
			'eyebrow'    => salt_nullable_text( $eyebrow ),
			'heading'    => salt_nullable_text( $heading ),
			'subheading' => salt_nullable_text( $subheading ),
			'image_id'   => $image_id,
			'ctas'       => $ctas,
		),
		'plan'    => array( 'priorityMedia' => $image_id > 0 ),
	);
}
