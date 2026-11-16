<?php
/**
 * View: Logo / Trust Strip (spec §10 #9).
 *
 * Draws the props from salt_resolve_section_logos() (inc/sections/logos.php),
 * inside the x-section wrapper the shared entry opens: a flex row of logos
 * through x-image (the single image authority), each wrapped in a plain <a>
 * when it has an href. No field reads, validation or logging here: a child
 * theme overriding this file overrides the markup only.
 *
 * $args:
 *   props  array{ heading: string|null, greyscale: bool,
 *                 items: array<int, array{image_id: int, alt: string, href: string, link_label: string}> }
 *          items is never empty; href '' means draw the logo unlinked.
 *   plan   array{ index: int, headingLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];

if ( null !== $salt_props['heading'] ) {
	salt_component(
		'heading',
		array(
			'text'    => $salt_props['heading'],
			'level'   => $salt_plan['headingLevel'],
			'size'    => 'sm',
			'classes' => 'mb-6 text-ink-muted',
		)
	);
}

// Explicit small rendering: height-capped, width auto — a logo
// never renders at its intrinsic (potentially huge) size.
$salt_image_classes = salt_classnames(
	array(
		'h-10 md:h-12 w-auto object-contain',
		$salt_props['greyscale'] ? 'grayscale transition hover:grayscale-0' : '',
	)
);

echo '<ul class="x-logos flex flex-wrap items-center justify-center gap-x-10 gap-y-6" data-section-component="logos">';

foreach ( $salt_props['items'] as $salt_logo ) {
	$salt_img = salt_get_component(
		'image',
		array(
			'image_id'      => $salt_logo['image_id'],
			'alt'           => $salt_logo['alt'],
			'sizes_context' => 'card',
			'classes'       => $salt_image_classes,
		)
	);

	echo '<li class="x-logos-item flex items-center">';
	if ( '' !== $salt_logo['href'] ) {
		// External logo links are allowed (plain <a>). The accessible name
		// comes from the label; the resolver leaves a nameless link unlinked.
		printf(
			'<a href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( $salt_logo['href'] ),
			esc_attr( $salt_logo['link_label'] ),
			$salt_img // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- x-image escapes internally.
		);
	} else {
		echo $salt_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- x-image escapes internally.
	}
	echo '</li>';
}

echo '</ul>';
