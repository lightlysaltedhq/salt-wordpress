<?php
/**
 * View: Tabs and tab-set (salt-contract 1.0.0). Draws props and plan only.
 * A named set of two or more panels uses native radios; otherwise stacked.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props  = $args['props'];
$salt_plan   = $args['plan'];
$salt_level  = (int) $salt_plan['headingLevel'];
$salt_anchor = (string) $salt_plan['anchor'];
$salt_tabbed = $salt_props['named'] && count( $salt_props['tabs'] ) > 1;

echo '<div class="salt-block" data-block="tabs">';
if ( null !== $salt_props['eyebrow'] ) {
	echo '<p class="salt-eyebrow">' . esc_html( (string) $salt_props['eyebrow'] ) . '</p>';
}
if ( null !== $salt_props['heading'] ) {
	printf( '<h%1$d id="%2$s">%3$s</h%1$d>', (int) $salt_level, esc_attr( (string) $salt_plan['headingId'] ), esc_html( (string) $salt_props['heading'] ) );
}
if ( null !== $salt_props['intro'] ) {
	echo '<p class="salt-intro">' . esc_html( (string) $salt_props['intro'] ) . '</p>';
}
if ( array() !== $salt_props['tabs'] ) {
	echo '<div class="salt-tabs"' . ( $salt_tabbed ? ' data-tabbed' : '' ) . '>';
	if ( $salt_tabbed ) {
		echo '<div class="salt-tabs__list" role="radiogroup" aria-label="' . esc_attr( (string) ( $salt_props['heading'] ?? $salt_props['label'] ) ) . '">';
		foreach ( $salt_props['tabs'] as $salt_i => $salt_tab ) {
			printf(
				'<div class="salt-tabs__control"><input class="salt-tabs__input salt-sr-only" type="radio" name="%1$s__tabs" id="%1$s__tab-%2$s"%3$s><label class="salt-tabs__tab" for="%1$s__tab-%2$s" id="%1$s__label-%2$s">%4$s</label></div>',
				esc_attr( $salt_anchor ),
				esc_attr( $salt_tab['id'] ),
				0 === $salt_i ? ' checked' : '',
				esc_html( $salt_tab['label'] )
			);
		}
		echo '</div>';
	}
	echo '<div class="salt-tabs__panels">';
	foreach ( $salt_props['tabs'] as $salt_tab ) {
		printf(
			'<div class="salt-tabs__panel" id="%1$s__panel-%2$s"%3$s><p class="salt-tabs__label">%4$s</p><div class="salt-rich-text">%5$s</div></div>',
			esc_attr( $salt_anchor ),
			esc_attr( $salt_tab['id'] ),
			$salt_tabbed ? ' role="group" aria-labelledby="' . esc_attr( $salt_anchor . '__label-' . $salt_tab['id'] ) . '" tabindex="0"' : '',
			esc_html( $salt_tab['label'] ),
			salt_render_rich_text_headings( $salt_tab['content'], (int) $salt_plan['bodyBaseLevel'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised by the resolver; only heading ranks change.
		);
	}
	echo '</div></div>';
}
echo '</div>';
