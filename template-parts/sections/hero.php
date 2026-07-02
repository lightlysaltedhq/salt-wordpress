<?php
/**
 * Section: Hero (spec §10 #1) — inline.
 *
 * Fields: variant (full-bleed|split|minimal), eyebrow, heading, subheading,
 * image (attachment ID), cta_primary + cta_secondary (ACF link arrays).
 *
 * STATES (COMPL02): heading empty AND no image ⇒ renders NOTHING (skip
 * before salt_section — a hero with neither is an empty band). The heading
 * renders at the wrapper-derived level: this is normally the page's
 * primary H1 (has_heading true when the heading is non-empty, A11Y01).
 *
 * IMAGE (§8): rendered ONLY via x-image with priority => true (eager +
 * fetchpriority=high — THE LCP image) and sizes_context 'hero'.
 *
 * VARIANTS (v1, honest — layout classes only):
 * - full-bleed: edge-to-edge image above the text (container forced 'full';
 *   the text block carries its own container classes).
 * - split: md:grid-cols-2, text beside image.
 * - minimal: text only — a set image is ignored + salt_log()ged.
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

$salt_variant    = (string) get_sub_field( 'variant' );
$salt_eyebrow    = (string) get_sub_field( 'eyebrow' );
$salt_heading    = (string) get_sub_field( 'heading' );
$salt_subheading = (string) get_sub_field( 'subheading' );
$salt_image_id   = (int) get_sub_field( 'image' );

if ( ! in_array( $salt_variant, array( 'full-bleed', 'split', 'minimal' ), true ) ) {
	if ( '' !== $salt_variant ) {
		salt_log( sprintf( 'section hero: unknown variant "%s" — using full-bleed.', $salt_variant ) );
	}
	$salt_variant = 'full-bleed';
}

if ( '' === trim( $salt_heading ) && 0 === $salt_image_id ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

// The minimal variant is text-only: a set image is ignored, visibly logged.
if ( 'minimal' === $salt_variant && $salt_image_id > 0 ) {
	salt_log( sprintf( 'section hero: minimal variant ignores media — image %d not rendered.', $salt_image_id ) );
	$salt_image_id = 0;
	if ( '' === trim( $salt_heading ) ) {
		return; // Nothing left to render (COMPL02).
	}
}

// CTAs: ACF link arrays; malformed rows skipped + logged (never a broken button).
$salt_ctas = array();
foreach ( array(
	'cta_primary'   => 'primary',
	'cta_secondary' => 'secondary',
) as $salt_cta_field => $salt_cta_style ) {
	$salt_link = get_sub_field( $salt_cta_field );
	if ( empty( $salt_link ) ) {
		continue; // Optional field left empty — not an error.
	}
	if ( ! is_array( $salt_link ) || '' === trim( (string) ( $salt_link['url'] ?? '' ) ) || '' === trim( (string) ( $salt_link['title'] ?? '' ) ) ) {
		salt_log( sprintf( 'section hero: %s link is malformed (missing url/title) — skipped.', $salt_cta_field ) );
		continue;
	}
	$salt_ctas[] = array(
		'label'  => (string) $salt_link['title'],
		'url'    => (string) $salt_link['url'],
		'style'  => $salt_cta_style,
		'target' => (string) ( $salt_link['target'] ?? '' ),
	);
}

$salt_wrapper = salt_section_wrapper_args( 'hero', $args ?? array() );
if ( 'full-bleed' === $salt_variant ) {
	// Edge-to-edge image: the wrapper goes full; text re-containers itself.
	$salt_wrapper['container'] = 'full';
}

salt_section(
	array_merge(
		$salt_wrapper,
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_variant, $salt_eyebrow, $salt_heading, $salt_subheading, $salt_image_id, $salt_ctas ): void {
		$salt_text = static function () use ( $level, $salt_eyebrow, $salt_heading, $salt_subheading, $salt_ctas ): void {
			if ( '' !== trim( $salt_eyebrow ) ) {
				salt_component(
					'eyebrow',
					array(
						'text'    => $salt_eyebrow,
						'classes' => 'mb-3',
					)
				);
			}
			if ( '' !== trim( $salt_heading ) ) {
				salt_component(
					'heading',
					array(
						'text'  => $salt_heading,
						'level' => $level,
						'size'  => 'xl',
					)
				);
			}
			if ( '' !== trim( $salt_subheading ) ) {
				printf(
					'<p class="x-hero-subheading mt-4 max-w-prose text-lg text-ink-muted">%s</p>',
					esc_html( $salt_subheading )
				);
			}
			if ( array() !== $salt_ctas ) {
				echo '<div class="x-hero-ctas mt-8 flex flex-wrap gap-4">';
				foreach ( $salt_ctas as $salt_cta ) {
					salt_component( 'button', $salt_cta );
				}
				echo '</div>';
			}
		};

		$salt_image = static function ( string $classes ) use ( $salt_image_id ): void {
			salt_component(
				'image',
				array(
					'image_id'      => $salt_image_id,
					'alt'           => '', // Stored attachment alt wins (x-image precedence).
					'priority'      => true, // THE LCP image: eager + fetchpriority=high (§8).
					'sizes_context' => 'hero',
					'classes'       => $classes,
				)
			);
		};

		if ( 'split' === $salt_variant && $salt_image_id > 0 ) {
			echo '<div class="x-hero x-hero-split grid gap-10 md:grid-cols-2 md:items-center">';
			echo '<div class="x-hero-text">';
			$salt_text();
			echo '</div>';
			echo '<div class="x-hero-media">';
			$salt_image( 'w-full' );
			echo '</div>';
			echo '</div>';
			return;
		}

		if ( 'full-bleed' === $salt_variant ) {
			echo '<div class="x-hero x-hero-full-bleed">';
			if ( $salt_image_id > 0 ) {
				$salt_image( 'w-full' );
			}
			// Container 'full' on the wrapper — the text re-containers itself.
			echo '<div class="x-hero-text mx-auto w-full max-w-5xl px-4 pt-10 sm:px-6 lg:px-8">';
			$salt_text();
			echo '</div>';
			echo '</div>';
			return;
		}

		// minimal — and split without an image degrades to the same static text block.
		echo '<div class="x-hero x-hero-' . esc_attr( $salt_variant ) . '">';
		echo '<div class="x-hero-text">';
		$salt_text();
		echo '</div>';
		echo '</div>';
	}
);
