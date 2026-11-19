<?php
/**
 * View: Collection showcase (salt-contract markup/collection-showcase.json,
 * rc.7; EP-72 M3 unit 8). Draws the props salt_resolve_section_collection_showcase()
 * returns and nothing else, inside the contract's wrapper.
 *
 * Layouts: grid, list and featured are ul.salt-grid.salt-showcase of cards
 * (featured marks its lead item); accordion is one details per entry,
 * named showcase-<plan.index>, its panel the quote; index is a list of
 * links, grouped by category when the props carry groups.
 *
 * $args = array{ props: array, plan: array{ index: int, anchor: string, headingLevel: int, priorityMedia: bool } }
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_props   = (array) ( $args['props'] ?? array() );
$salt_plan    = (array) ( $args['plan'] ?? array() );
$salt_level   = max( 1, min( 6, (int) ( $salt_plan['headingLevel'] ?? 2 ) ) );
$salt_heading = $salt_props['heading'] ?? null;
// The body's heading base: one below the section's heading, or the first
// body level (h2) when the section draws none (section#body-heading-base).
$salt_body    = null !== $salt_heading ? min( 6, $salt_level + 1 ) : 2;
$salt_layout  = (string) $salt_props['layout'];
$salt_source  = (string) $salt_props['source'];
$salt_labels  = (array) ( $salt_props['labels'] ?? array() );
$salt_arrow   = $salt_props['arrow'] ?? null;
$salt_arrow_h = null !== $salt_arrow ? '<span class="salt-arrow" aria-hidden="true">&#x2060;' . esc_html( (string) $salt_arrow ) . '</span>' : '';

echo '<div class="salt-block" data-block="collection-showcase" data-source="' . esc_attr( $salt_source ) . '">';

if ( null !== ( $salt_props['eyebrow'] ?? null ) ) {
	echo '<p class="salt-eyebrow">' . esc_html( (string) $salt_props['eyebrow'] ) . '</p>';
}
if ( null !== $salt_heading ) {
	printf( '<h%1$d id="%2$s">%3$s</h%1$d>', (int) $salt_level, esc_attr( (string) $salt_plan['anchor'] . '__heading' ), esc_html( (string) $salt_heading ) );
}
if ( null !== ( $salt_props['intro'] ?? null ) ) {
	echo '<p class="salt-intro">' . esc_html( (string) $salt_props['intro'] ) . '</p>';
}

$salt_index_list = static function ( array $cards ): void {
	echo '<ul class="salt-showcase__index" role="list">';
	foreach ( $cards as $salt_card ) {
		echo '<li><a class="salt-showcase__link" href="' . esc_url( (string) $salt_card['href'] ) . '">' . esc_html( (string) $salt_card['title'] ) . '</a></li>';
	}
	echo '</ul>';
};

if ( 'index' === $salt_layout ) {
	if ( is_array( $salt_props['groups'] ?? null ) ) {
		foreach ( $salt_props['groups'] as $salt_group ) {
			if ( null === $salt_group['title'] ) {
				$salt_index_list( (array) $salt_group['cards'] );
				continue;
			}
			echo '<div class="salt-showcase__group">';
			printf( '<h%1$d>%2$s</h%1$d>', (int) $salt_body, esc_html( (string) $salt_group['title'] ) );
			$salt_index_list( (array) $salt_group['cards'] );
			echo '</div>';
		}
	} else {
		$salt_index_list( (array) $salt_props['cards'] );
	}
} elseif ( 'accordion' === $salt_layout ) {
	echo '<div class="salt-accordion">';
	foreach ( (array) $salt_props['cards'] as $salt_card ) {
		echo '<details class="salt-accordion__item" name="' . esc_attr( 'showcase-' . (int) ( $salt_plan['index'] ?? 0 ) ) . '">';
		echo '<summary class="salt-accordion__summary">' . esc_html( (string) $salt_card['title'] ) . '</summary>';
		echo '<div class="salt-accordion__content">';
		salt_component(
			'contract-card',
			array(
				'card'   => $salt_card,
				'style'  => 'panel',
				'quote'  => 'testimonials' === $salt_source,
				'labels' => $salt_labels,
			)
		);
		echo '</div></details>';
	}
	echo '</div>';
} else {
	printf(
		'<ul class="salt-grid salt-showcase" role="list" data-layout="%1$s"%2$s>',
		esc_attr( $salt_layout ),
		'list' !== $salt_layout ? ' data-columns="' . esc_attr( (string) $salt_props['columns'] ) . '"' : ''
	);
	foreach ( array_values( (array) $salt_props['cards'] ) as $salt_i => $salt_card ) {
		$salt_lead = 'featured' === $salt_layout && 0 === $salt_i;
		echo $salt_lead ? '<li data-featured="true">' : '<li>';
		salt_component(
			'contract-card',
			array(
				'card'     => $salt_card,
				'style'    => 'testimonials' === $salt_source ? 'quote' : 'generic',
				'level'    => $salt_body,
				'priority' => ! empty( $salt_plan['priorityMedia'] ) && 0 === $salt_i,
				'results'  => $salt_lead ? (array) ( $salt_props['results'] ?? array() ) : array(),
				'labels'   => $salt_labels,
				'arrow'    => $salt_arrow,
			)
		);
		echo '</li>';
	}
	echo '</ul>';
}

if ( is_array( $salt_props['viewAll'] ?? null ) ) {
	$salt_view_all = $salt_props['viewAll'];
	printf(
		'<p class="salt-showcase__view-all"><a class="salt-button" href="%1$s"%2$s data-style="link" data-track-control="cta"><span class="salt-button__label">%3$s%4$s</span></a></p>',
		esc_url( (string) $salt_view_all['href'] ),
		! empty( $salt_view_all['newTab'] ) ? ' target="_blank" rel="noopener"' : '',
		esc_html( (string) $salt_view_all['label'] ),
		$salt_arrow_h // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	);
}

echo '</div>';
