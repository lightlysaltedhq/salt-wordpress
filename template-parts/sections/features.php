<?php
/**
 * View: Feature / Benefits Grid (salt-contract markup/features.json, rc.8;
 * spec §10 #5).
 *
 * Draws the props from salt_resolve_section_features() (inc/sections/
 * features.php) as the contract's block, inside the band the shared entry
 * opens: eyebrow, heading, intro, then ul.salt-grid of generic cards (icon,
 * title linked when the item has a url, rich-text body). No field reads,
 * validation or logging here: a child theme overriding this file overrides
 * the markup only.
 *
 * $args:
 *   props  array{ eyebrow: string|null, heading: string|null, intro: string|null,
 *                 columns: string,
 *                 items: array<int, array{icon: string|null, title: string, text: string|null (sanitised HTML), href: string|null}>,
 *                 arrow: string|null }
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
// Card titles rank one below the body base (section#body-heading-base).
$salt_card_level = min( 6, (int) ( $salt_plan['bodyBaseLevel'] ?? ( null === $salt_props['heading'] ? 1 : $salt_level ) ) + 1 );
$salt_arrow      = null !== $salt_props['arrow'] ? '<span class="salt-arrow" aria-hidden="true">&#x2060;' . esc_html( (string) $salt_props['arrow'] ) . '</span>' : '';

echo '<div class="salt-block" data-block="features">';

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
if ( null !== $salt_props['intro'] ) {
	echo '<p class="salt-intro">' . esc_html( (string) $salt_props['intro'] ) . '</p>';
}

if ( array() !== $salt_props['items'] ) {
	echo '<ul class="salt-grid" role="list" data-columns="' . esc_attr( (string) $salt_props['columns'] ) . '">';
	foreach ( $salt_props['items'] as $salt_item ) {
		echo '<li><div class="salt-card">';
		if ( null !== $salt_item['icon'] ) {
			echo salt_contract_icon( (string) $salt_item['icon'], 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-bundled artwork, attributes escaped.
		}
		$salt_title = esc_html( (string) $salt_item['title'] );
		if ( null !== $salt_item['href'] ) {
			$salt_title = '<a class="salt-card__link" href="' . esc_url( (string) $salt_item['href'] ) . '">' . $salt_title . $salt_arrow . '</a>';
		}
		printf( '<h%1$d>%2$s</h%1$d>', (int) $salt_card_level, $salt_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		if ( null !== $salt_item['text'] ) {
			echo '<div class="salt-rich-text">' . $salt_item['text'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already sanitised by the resolver.
		}
		echo '</div></li>';
	}
	echo '</ul>';
}

echo '</div>';
