<?php
/**
 * View: Carousel (salt-contract markup/carousel.json and
 * markup/carousel-track.json, rc.7; EP-72 M3 unit 8). Draws the props
 * salt_resolve_section_carousel() returns and nothing else, inside the
 * contract's wrapper.
 *
 * One card is drawn alone; two or more with a name (heading or label) are
 * the carousel track, a scroll-snapping list whose previous and next
 * controls assets/src/js/sections/carousel.js mounts; two or more with no
 * name are a plain ul.salt-grid.
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
// Card titles at body base + 1: one below the heading, h2 with none
// (section#body-heading-base).
$salt_body   = null !== $salt_heading ? min( 6, $salt_level + 1 ) : 2;
$salt_cards  = array_values( (array) ( $salt_props['cards'] ?? array() ) );
$salt_labels = (array) ( $salt_props['labels'] ?? array() );
$salt_style  = (string) ( $salt_props['cardStyle'] ?? 'generic' );

/**
 * One card's markup. The first card's image is the priority image when the
 * plan grants it, never a quotation's photo.
 */
$salt_card_html = static function ( array $card, int $i ) use ( $salt_body, $salt_labels, $salt_style, $salt_plan, $salt_props ): string {
	return salt_get_component(
		'contract-card',
		array(
			'card'     => $card,
			'style'    => $salt_style,
			'level'    => $salt_body,
			'priority' => 'quote' !== $salt_style && 0 === $i && ! empty( $salt_plan['priorityMedia'] ),
			'labels'   => $salt_labels,
			'arrow'    => $salt_props['arrow'] ?? null,
		)
	);
};

echo '<div class="salt-block" data-block="carousel" data-source="' . esc_attr( (string) $salt_props['source'] ) . '">';

if ( null !== ( $salt_props['eyebrow'] ?? null ) ) {
	echo '<p class="salt-eyebrow">' . esc_html( (string) $salt_props['eyebrow'] ) . '</p>';
}
if ( null !== $salt_heading ) {
	printf( '<h%1$d id="%2$s">%3$s</h%1$d>', (int) $salt_level, esc_attr( (string) $salt_plan['anchor'] . '__heading' ), esc_html( (string) $salt_heading ) );
}
if ( null !== ( $salt_props['intro'] ?? null ) ) {
	echo '<p class="salt-intro">' . esc_html( (string) $salt_props['intro'] ) . '</p>';
}

$salt_total = count( $salt_cards );
if ( 1 === $salt_total ) {
	echo $salt_card_html( $salt_cards[0], 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The card component escapes.
} elseif ( empty( $salt_props['named'] ) ) {
	echo '<ul class="salt-grid" role="list">';
	foreach ( $salt_cards as $salt_i => $salt_card ) {
		echo '<li>' . $salt_card_html( $salt_card, $salt_i ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The card component escapes.
	}
	echo '</ul>';
} else {
	$salt_slides = '';
	foreach ( $salt_cards as $salt_i => $salt_card ) {
		$salt_slides .= sprintf(
			'<li class="salt-carousel__slide"><div class="salt-carousel__slide-inner" role="group" aria-roledescription="slide" aria-label="%1$s">%2$s</div></li>',
			esc_attr( salt_contract_n_of( (string) ( $salt_labels['slide'] ?? '' ), $salt_i + 1, $salt_total ) ),
			$salt_card_html( $salt_card, $salt_i )
		);
	}
	// The track is a tab stop when no slide holds anything focusable
	// (markup/carousel-track.json), so it can be scrolled from the keyboard.
	$salt_focusable = 1 === preg_match( '/<(a|button|input|select|textarea|summary)\b|tabindex=/i', $salt_slides );
	printf(
		'<div class="salt-carousel" role="region" aria-roledescription="carousel" aria-label="%1$s"><ul class="salt-carousel__track" role="list"%2$s>%3$s</ul></div>',
		esc_attr( (string) ( $salt_heading ?? $salt_props['label'] ) ),
		$salt_focusable ? '' : ' tabindex="0"',
		$salt_slides // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
	);
}

echo '</div>';
