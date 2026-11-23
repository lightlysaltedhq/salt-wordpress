<?php
/**
 * Booking — progressive booking FACADE (spec §9; PRIV01/§7), drawn as the
 * salt-contract's markup/booking.json: a salt-button link with the
 * salt-booking hook, data-style primary, data-track-control cta and the
 * provider in data-provider. The href carries the booking URL.
 *
 * When a provider + URL are configured (Site Settings → Booking) this
 * renders a button-styled PLAIN LINK to the booking page — the single
 * third-party URL allowed in facade markup, because a plain href loads
 * nothing until the visitor deliberately navigates. assets/src/js/
 * booking.js upgrades the click to the provider's inline embed ONLY when
 * marketing consent is granted; denied / no CMP simply follows the link.
 * Unconfigured → renders NOTHING (logged by salt_booking_config()).
 *
 * ARGS CONTRACT (v2 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12; v1's `classes` went with the contract markup,
 * which fixes the element's classes):
 *   label   string ''  Visible text; empty falls back to "Book a call" (i18n).
 *
 * Render via salt_component( 'booking', … ) / salt_get_component().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args = salt_require_args(
	$args ?? array(),
	array(
		'label' => array(
			'type'    => 'string',
			'default' => '',
		),
		'slots' => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'booking'
);

$salt_config = salt_booking_config();

if ( null === $salt_config ) {
	salt_log( 'booking: no booking provider configured — rendered nothing.' );
	return;
}

$salt_label = '' === $salt_args['label'] ? __( 'Book a call', 'salt' ) : $salt_args['label'];

printf(
	'<a class="salt-button salt-booking" href="%1$s" target="_blank" rel="noopener noreferrer" data-style="primary" data-track-control="cta" data-provider="%2$s">%3$s</a>',
	esc_url( $salt_config['url'] ),
	esc_attr( $salt_config['provider'] ),
	esc_html( $salt_label )
);
