<?php
/**
 * x-booking — progressive booking FACADE (spec §9; PRIV01/§7).
 *
 * When a provider + URL are configured (Site Settings → Booking) this
 * renders a button-styled PLAIN LINK to the booking page — the single
 * third-party URL allowed in facade markup, because a plain href loads
 * nothing until the visitor deliberately navigates. assets/src/js/
 * booking.js upgrades the click to the provider's inline embed ONLY when
 * marketing consent is granted; denied / no CMP simply follows the link.
 * Unconfigured → renders NOTHING (logged by salt_booking_config()).
 *
 * ARGS CONTRACT (v1 — changing this signature or the markup structure is a
 * MAJOR semver bump, §4/§12):
 *   label   string ''  Visible text; empty falls back to "Book a call" (i18n).
 *   classes string ''  Extra utility classes appended verbatim.
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
		'label'   => array(
			'type'    => 'string',
			'default' => '',
		),
		'classes' => array(
			'type'    => 'string',
			'default' => '',
		),
		'slots'   => array(
			'type'    => 'array',
			'default' => array(),
		),
	),
	'booking'
);

$salt_config = salt_booking_config();

if ( null === $salt_config ) {
	salt_log( 'x-booking: no booking provider configured — rendered nothing.' );
	return;
}

$salt_label = '' === $salt_args['label'] ? __( 'Book a call', 'salt' ) : $salt_args['label'];

$salt_classes = salt_classnames(
	array(
		'x-booking',
		'x-button x-button-primary',
		'inline-flex items-center justify-center gap-2 rounded-md font-medium',
		'bg-brand text-on-brand hover:bg-brand-strong',
		'px-5 py-2.5',
		$salt_args['classes'],
	)
);
?>
<a
	class="<?php echo esc_attr( $salt_classes ); ?>"
	href="<?php echo esc_url( $salt_config['url'] ); ?>"
	target="_blank"
	rel="noopener noreferrer"
	data-salt-booking
	data-provider="<?php echo esc_attr( $salt_config['provider'] ); ?>"
	data-url="<?php echo esc_attr( $salt_config['url'] ); ?>"
><?php echo esc_html( $salt_label ); ?></a>
