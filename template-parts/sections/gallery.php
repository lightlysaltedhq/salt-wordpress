<?php
/**
 * View: Gallery (salt-contract markup/gallery.json, rc.8; spec §10 #16;
 * FAIL02).
 *
 * Draws the props from salt_resolve_section_gallery() (inc/sections/
 * gallery.php) as the contract's block, inside the band the shared entry
 * opens. No field reads, image validation or logging here: a child theme
 * overriding this file overrides the markup only.
 *
 * Layouts:
 *  - grid and masonry: ul.salt-grid.salt-gallery with data-layout and
 *    data-columns; the shared stylesheet lays them out, no script.
 *  - carousel: the contract's carousel track (markup/carousel-track.json),
 *    named by the heading or the label. It scrolls without script
 *    (FAIL02); sections/carousel.js adds its controls. With no name it
 *    draws the grid (view-props `named`).
 * One image => one figure in every layout, no list or track.
 * The first image is the page's priority image when the plan grants it.
 *
 * $args:
 *   props  array{ heading: string|null, intro: string|null, layout: string, columns: string,
 *                 label: string|null, named: bool, captions: bool, images: array<int, array>,
 *                 labels: array<string,string> }
 *   plan   array{ index: int, anchor: string, headingLevel: int, headingId: string|null, bodyBaseLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props  = $args['props'];
$salt_plan   = $args['plan'];
$salt_level  = max( 1, min( 6, (int) $salt_plan['headingLevel'] ) );
$salt_id     = $salt_plan['headingId'] ?? null;
$salt_images = array_values( (array) $salt_props['images'] );

$salt_figure = static function ( array $image, bool $priority ): string {
	$html = '<figure class="salt-gallery__figure">' . salt_image_contract_html( $image, $priority, array( 'class' => 'salt-gallery__image' ) );
	if ( null !== ( $image['caption'] ?? null ) ) {
		$html .= '<figcaption class="salt-gallery__caption">' . esc_html( (string) $image['caption'] ) . '</figcaption>';
	}
	return $html . '</figure>';
};

echo '<div class="salt-block" data-block="gallery">';

if ( null !== $salt_props['heading'] ) {
	printf(
		'<h%1$d%2$s>%3$s</h%1$d>',
		(int) $salt_level,
		is_string( $salt_id ) ? ' id="' . esc_attr( $salt_id ) . '"' : '',
		esc_html( (string) $salt_props['heading'] )
	);
}
if ( null !== $salt_props['intro'] ) {
	echo '<p class="salt-intro">' . esc_html( (string) $salt_props['intro'] ) . '</p>';
}

$salt_total = count( $salt_images );
if ( 1 === $salt_total ) {
	echo $salt_figure( $salt_images[0], $salt_plan['priorityMedia'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where it was built.
} elseif ( $salt_total > 1 && 'carousel' === $salt_props['layout'] && $salt_props['named'] ) {
	$salt_slides = '';
	foreach ( $salt_images as $salt_i => $salt_image ) {
		$salt_slides .= sprintf(
			'<li class="salt-carousel__slide"><div class="salt-carousel__slide-inner" role="group" aria-roledescription="slide" aria-label="%1$s">%2$s</div></li>',
			esc_attr( salt_contract_n_of( (string) ( $salt_props['labels']['slide'] ?? '' ), $salt_i + 1, $salt_total ) ),
			$salt_figure( $salt_image, $salt_plan['priorityMedia'] && 0 === $salt_i )
		);
	}
	// The slides hold nothing focusable, so the track is the tab stop (markup/carousel-track.json).
	printf(
		'<div class="salt-carousel" role="region" aria-roledescription="carousel" aria-label="%1$s"><ul class="salt-carousel__track" role="list" tabindex="0">%2$s</ul></div>',
		esc_attr( (string) ( $salt_props['heading'] ?? $salt_props['label'] ) ),
		$salt_slides // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
	);
} elseif ( $salt_total > 1 ) {
	printf(
		'<ul class="salt-grid salt-gallery" role="list" data-layout="%1$s" data-columns="%2$s">',
		'masonry' === $salt_props['layout'] ? 'masonry' : 'grid',
		esc_attr( (string) $salt_props['columns'] )
	);
	foreach ( $salt_images as $salt_i => $salt_image ) {
		echo '<li class="salt-gallery__item">' . $salt_figure( $salt_image, $salt_plan['priorityMedia'] && 0 === $salt_i ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped where it was built.
	}
	echo '</ul>';
}

echo '</div>';
