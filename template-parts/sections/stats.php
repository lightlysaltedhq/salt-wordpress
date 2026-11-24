<?php
/**
 * View: Stats / Metrics (salt-contract markup/stats.json and stat.json,
 * rc.8; spec §10 #7).
 *
 * Draws the props from salt_resolve_section_stats() (inc/sections/stats.php)
 * as the contract's block, inside the band the shared entry opens: heading,
 * intro, then ul.salt-grid of li.salt-stat. No field reads, validation or
 * logging here: a child theme overriding this file overrides the markup
 * only.
 *
 * $args:
 *   props  array{ heading: string|null, intro: string|null, columns: string,
 *                 items: array<int, array{icon: string|null, value: string, suffix: string|null, label: string|null}> }
 *          columns is '2'|'3'|'4'.
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

echo '<div class="salt-block" data-block="stats">';

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
	echo '<ul class="salt-grid" role="list" data-columns="' . esc_attr( (string) $salt_props['columns'] ) . '">';
	foreach ( $salt_props['items'] as $salt_item ) {
		echo '<li class="salt-stat">';
		if ( null !== $salt_item['icon'] ) {
			echo salt_contract_icon( (string) $salt_item['icon'], 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-bundled artwork, attributes escaped.
		}
		echo '<p class="salt-stat__value">' . esc_html( (string) $salt_item['value'] );
		if ( null !== $salt_item['suffix'] ) {
			echo '<span class="salt-stat__suffix">' . esc_html( (string) $salt_item['suffix'] ) . '</span>';
		}
		echo '</p>';
		if ( null !== $salt_item['label'] ) {
			echo '<p class="salt-stat__label">' . esc_html( (string) $salt_item['label'] ) . '</p>';
		}
		echo '</li>';
	}
	echo '</ul>';
}

echo '</div>';
