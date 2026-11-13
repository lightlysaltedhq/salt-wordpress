<?php
/**
 * View: Hero (spec §10 #1) — inline.
 *
 * Draws the props from salt_resolve_section_hero() (inc/sections/hero.php),
 * inside the x-section wrapper the shared entry opens. No field reads,
 * queries, sanitising, schema or logging here: a child theme overriding
 * this file overrides the markup only.
 *
 * $args:
 *   props  array{ variant: string, eyebrow: string|null, heading: string|null,
 *                 subheading: string|null, image_id: int,
 *                 ctas: array<int, array{label: string, url: string, style: string, target: string}> }
 *          image_id is 0 or a resolvable attachment (always 0 for minimal).
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * IMAGE (§8): rendered ONLY via x-image with sizes_context 'hero'; the
 * plan's priorityMedia (always true for hero) makes it THE LCP image
 * (eager + fetchpriority=high).
 *
 * VARIANTS (v1, honest — layout classes only):
 * - full-bleed: edge-to-edge image above the text (the wrapper container
 *   is 'full'; the text block carries its own container classes).
 * - split: md:grid-cols-2, text beside image; without an image it draws
 *   the same static text block as minimal.
 * - minimal: text only.
 *
 * IMAGE SIZING (standardised): hero media is height-clamped + object-cover so
 * a huge upload can never blow the band out — full-bleed crops to a contained
 * banner (clamp(220px,34vh,360px)); split crops to 4/3 and rounds.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

$salt_text = static function () use ( $salt_props, $salt_plan ): void {
	if ( null !== $salt_props['eyebrow'] ) {
		salt_component(
			'eyebrow',
			array(
				'text'    => $salt_props['eyebrow'],
				'classes' => 'mb-3',
			)
		);
	}
	if ( null !== $salt_props['heading'] ) {
		salt_component(
			'heading',
			array(
				'text'  => $salt_props['heading'],
				'level' => $salt_plan['headingLevel'],
				'size'  => 'xl',
			)
		);
	}
	if ( null !== $salt_props['subheading'] ) {
		printf(
			'<p class="x-hero-subheading mt-4 max-w-prose text-lg text-ink-muted">%s</p>',
			esc_html( $salt_props['subheading'] )
		);
	}
	if ( array() !== $salt_props['ctas'] ) {
		echo '<div class="x-hero-ctas mt-8 flex flex-wrap gap-4">';
		foreach ( $salt_props['ctas'] as $salt_cta ) {
			salt_component( 'button', $salt_cta );
		}
		echo '</div>';
	}
};

$salt_image = static function ( string $classes ) use ( $salt_props, $salt_plan ): void {
	salt_component(
		'image',
		array(
			'image_id'      => $salt_props['image_id'],
			'alt'           => '', // Stored attachment alt wins (x-image precedence).
			'priority'      => $salt_plan['priorityMedia'], // THE LCP image: eager + fetchpriority=high (§8).
			'sizes_context' => 'hero',
			'classes'       => $classes,
		)
	);
};

if ( 'split' === $salt_props['variant'] && $salt_props['image_id'] > 0 ) {
	echo '<div class="x-hero x-hero-split grid gap-10 md:grid-cols-2 md:items-center">';
	echo '<div class="x-hero-text">';
	$salt_text();
	echo '</div>';
	echo '<div class="x-hero-media">';
	$salt_image( 'aspect-[4/3] w-full rounded-lg object-cover' );
	echo '</div>';
	echo '</div>';
	return;
}

if ( 'full-bleed' === $salt_props['variant'] ) {
	echo '<div class="x-hero x-hero-full-bleed">';
	if ( $salt_props['image_id'] > 0 ) {
		// Standardised banner: a fixed, contained height so a large
		// upload can never fill the viewport (a full-bleed hero is a
		// banner ABOVE the heading, not a full-screen image).
		$salt_image( 'h-[clamp(220px,34vh,360px)] w-full object-cover' );
	}
	// Container 'full' on the wrapper — the text re-containers itself.
	echo '<div class="x-hero-text mx-auto w-full max-w-5xl px-4 pt-10 sm:px-6 lg:px-8">';
	$salt_text();
	echo '</div>';
	echo '</div>';
	return;
}

// minimal — and split without an image degrades to the same static text block.
echo '<div class="x-hero x-hero-' . esc_attr( $salt_props['variant'] ) . '">';
echo '<div class="x-hero-text">';
$salt_text();
echo '</div>';
echo '</div>';
