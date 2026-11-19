<?php
/**
 * The public contact and social links shared by team cards and profiles.
 *
 * @package Salt
 */

defined( 'ABSPATH' ) || exit;

$salt_email = $args['email'] ?? '';
$salt_tel   = $args['phoneHref'] ?? '';
if ( '' !== $salt_email || '' !== $salt_tel ) {
	echo '<ul class="salt-showcase__contact" role="list">';
	if ( '' !== $salt_email ) {
		echo '<li><a class="salt-showcase__contact-link" href="' . esc_url( 'mailto:' . $salt_email ) . '">' . esc_html( $salt_email ) . '</a></li>';
	}
	if ( '' !== $salt_tel ) {
		echo '<li><a class="salt-showcase__contact-link" href="' . esc_url( $salt_tel ) . '">' . esc_html( $args['phone'] ) . '</a></li>';
	}
	echo '</ul>';
}
if ( ! empty( $args['profiles'] ) ) {
	echo '<ul class="salt-showcase__socials" role="list" aria-label="' . esc_attr( $args['social_label'] ) . '">';
	foreach ( $args['profiles'] as $salt_profile ) {
		$salt_glyph = null !== $salt_profile['network'] ? salt_contract_icon( (string) $salt_profile['network'], 'sm', (string) $salt_profile['name'] ) : '';
		echo '<li><a class="salt-showcase__social-link" href="' . esc_url( $salt_profile['href'] ) . '">';
		echo '' !== $salt_glyph ? $salt_glyph : '<span>' . esc_html( $salt_profile['name'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme-bundled artwork, attributes escaped.
		echo '</a></li>';
	}
	echo '</ul>';
}
