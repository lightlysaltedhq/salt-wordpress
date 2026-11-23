<?php
/**
 * View: Logo / Trust Strip (salt-contract markup/logos.json, rc.8; spec §10 #9).
 *
 * Draws the props from salt_resolve_section_logos() (inc/sections/logos.php)
 * as the contract's block, inside the band the shared entry opens: a grid
 * of logos, each in a frame (a plain <a> when it has an href, a span
 * otherwise). No field reads, validation or logging here: a child theme
 * overriding this file overrides the markup only.
 *
 * $args:
 *   props  array{ heading: string|null, intro: string|null, columns: string, greyscale: bool,
 *                 items: array<int, array{image: array, name: string|null, href: string|null}> }
 *   plan   array{ index: int, anchor: string, headingLevel: int, headingId: string|null, bodyBaseLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props = $args['props'];
$salt_plan  = $args['plan'];
$salt_level = max( 1, min( 6, (int) $salt_plan['headingLevel'] ) );
$salt_id    = $salt_plan['headingId'] ?? null;

echo '<div class="salt-block" data-block="logos">';

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

if ( array() !== $salt_props['items'] ) {
	printf(
		'<ul class="salt-grid salt-logos" role="list" data-columns="%1$s" data-greyscale="%2$s">',
		esc_attr( (string) $salt_props['columns'] ),
		$salt_props['greyscale'] ? 'true' : 'false'
	);
	foreach ( $salt_props['items'] as $salt_logo ) {
		// Logos never take the page's priority image (markup/logos.json).
		$salt_img = salt_image_contract_html( $salt_logo['image'], false, array( 'class' => 'salt-logos__image' ) );
		echo '<li class="salt-logos__item">';
		if ( null !== $salt_logo['href'] ) {
			printf(
				'<a class="salt-logos__frame" href="%1$s" aria-label="%2$s">%3$s</a>',
				esc_url( (string) $salt_logo['href'] ),
				esc_attr( (string) $salt_logo['name'] ),
				$salt_img // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
			);
		} else {
			echo '<span class="salt-logos__frame">' . $salt_img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
		}
		echo '</li>';
	}
	echo '</ul>';
}

echo '</div>';
