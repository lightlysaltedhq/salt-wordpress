<?php
/**
 * One button in link form, as the contract draws a section's call to
 * action (salt-contract markup/button.json, rc.8): a.salt-button with
 * data-style and data-track-control="cta"; a new tab adds target and rel.
 * With the site's arrow, the label and the arrow sit together in
 * span.salt-button__label, so the arrow wraps with the last word. Drawn
 * by the hero, call-to-action and media-text views (EP-72 M4). A view: it
 * draws its args and nothing else.
 *
 * ARGS:
 *   button array        The contract's button props { label, href, style,
 *                       newTab } (view-props/_shared.json button;
 *                       salt_contract_button_props()).
 *   arrow  string|null  The site's arrow (salt_contract_arrow()), or null for none.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_button = (array) ( $args['button'] ?? array() );
$salt_href   = esc_url( (string) ( $salt_button['href'] ?? '' ) );
// A link form button needs an address: one esc_url() empties (a disallowed
// scheme) draws nothing (markup/button.json zeroState).
if ( '' === $salt_href ) {
	return;
}
$salt_arrow = $args['arrow'] ?? null;
$salt_label = esc_html( (string) ( $salt_button['label'] ?? '' ) );
if ( null !== $salt_arrow ) {
	$salt_label = '<span class="salt-button__label">' . $salt_label . '<span class="salt-arrow" aria-hidden="true">&#x2060;' . esc_html( (string) $salt_arrow ) . '</span></span>';
}

printf(
	'<a class="salt-button" href="%1$s" data-style="%2$s" data-track-control="cta"%3$s>%4$s</a>',
	$salt_href, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	esc_attr( (string) ( $salt_button['style'] ?? 'primary' ) ),
	! empty( $salt_button['newTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '',
	$salt_label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
);
