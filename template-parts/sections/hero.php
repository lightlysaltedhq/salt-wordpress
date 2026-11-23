<?php
/**
 * View: Hero (salt-contract markup/hero.json, rc.8; spec §10 #1).
 *
 * Draws the props from salt_resolve_section_hero() (inc/sections/hero.php)
 * as the contract's block, inside the band the shared entry opens. No
 * field reads, queries, sanitising, schema or logging here: a child theme
 * overriding this file overrides the markup only.
 *
 * Text first and media second in every variant; the side (split) and the
 * alignment (minimal) are attributes the stylesheet reads. A full-bleed
 * hero's photograph is the band's background image, drawn by the wrapper.
 *
 * $args:
 *   props  array{ variant: string, eyebrow: string|null, heading: string|null,
 *                 subheading: string|null, image: array|null, mediaSide: string,
 *                 alignment: string,
 *                 buttons: array<int, array{label: string, href: string, style: string, newTab: bool}>,
 *                 arrow: string|null }
 *   plan   array{ index: int, anchor: string, headingLevel: int, headingId: string|null, bodyBaseLevel: int, priorityMedia: bool }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props   = $args['props'];
$salt_plan    = $args['plan'];
$salt_level   = max( 1, min( 6, (int) $salt_plan['headingLevel'] ) );
$salt_id      = $salt_plan['headingId'] ?? null;
$salt_variant = (string) $salt_props['variant'];
$salt_image   = is_array( $salt_props['image'] ) && in_array( $salt_variant, array( 'split', 'stacked' ), true ) ? $salt_props['image'] : null;

$salt_root = ' data-variant="' . esc_attr( $salt_variant ) . '"';
if ( 'split' === $salt_variant && null !== $salt_image ) {
	$salt_root .= ' data-media-side="' . esc_attr( (string) $salt_props['mediaSide'] ) . '"';
}
if ( 'minimal' === $salt_variant ) {
	$salt_root .= ' data-align="' . esc_attr( (string) $salt_props['alignment'] ) . '"';
}

echo '<div class="salt-block salt-hero" data-block="hero"' . $salt_root . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
echo '<div class="salt-hero__text">';

if ( null !== $salt_props['eyebrow'] ) {
	echo '<p class="salt-eyebrow">' . esc_html( (string) $salt_props['eyebrow'] ) . '</p>';
}
if ( null !== $salt_props['heading'] ) {
	printf(
		'<h%1$d%2$s>%3$s</h%1$d>',
		(int) $salt_level,
		is_string( $salt_id ) ? ' id="' . esc_attr( $salt_id ) . '"' : '',
		esc_html( (string) $salt_props['heading'] )
	);
}
if ( null !== $salt_props['subheading'] ) {
	echo '<p class="salt-hero__subheading">' . esc_html( (string) $salt_props['subheading'] ) . '</p>';
}
if ( array() !== $salt_props['buttons'] ) {
	echo '<div class="salt-hero__actions">';
	foreach ( $salt_props['buttons'] as $salt_button ) {
		salt_component(
			'contract-button',
			array(
				'button' => $salt_button,
				'arrow'  => $salt_props['arrow'],
			)
		);
	}
	echo '</div>';
}

echo '</div>';

if ( null !== $salt_image ) {
	echo '<div class="salt-hero__media">';
	echo salt_image_contract_html( $salt_image, ! empty( $salt_plan['priorityMedia'] ), array( 'class' => 'salt-hero__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
	echo '</div>';
}

echo '</div>';
