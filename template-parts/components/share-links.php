<?php
/**
 * Share links, as the contract draws them (salt-contract rc.8
 * markup/share-links.json, SC-006): a group named by labels.group, an
 * optional visible heading (a paragraph, never a heading), text links to
 * each service's share intent in the same tab, and the copy-link control,
 * drawn not ready (data-ready="false") for a script to wire. Nothing when
 * there is neither a service nor the copy control. A view: it draws its
 * args and nothing else.
 *
 * ARGS:
 *   heading  string|null The visible heading, or null for none.
 *   services array       Rows of { service, href } (salt_share_links()), in order.
 *   copy     bool        Offer the copy-link control.
 *   labels   array       labels.group, labels.copy, labels.services.<service>.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args     = $args ?? array();
$salt_services = (array) ( $salt_args['services'] ?? array() );
$salt_copy     = ! empty( $salt_args['copy'] );
$salt_labels   = (array) ( $salt_args['labels'] ?? array() );
$salt_names    = (array) ( $salt_labels['services'] ?? array() );
if ( array() === $salt_services && ! $salt_copy ) {
	return;
}

echo '<div class="salt-share" role="group" aria-label="' . esc_attr( (string) ( $salt_labels['group'] ?? '' ) ) . '">';
if ( null !== ( $salt_args['heading'] ?? null ) && '' !== trim( (string) $salt_args['heading'] ) ) {
	echo '<p class="salt-share__heading">' . esc_html( (string) $salt_args['heading'] ) . '</p>';
}
if ( array() !== $salt_services ) {
	echo '<ul class="salt-share__list" role="list">';
	foreach ( $salt_services as $salt_service ) {
		printf(
			'<li><a class="salt-share__link" href="%1$s" data-share="%2$s">%3$s</a></li>',
			esc_url( (string) $salt_service['href'], array( 'http', 'https', 'mailto' ) ),
			esc_attr( (string) $salt_service['service'] ),
			esc_html( (string) ( $salt_names[ $salt_service['service'] ] ?? $salt_service['service'] ) )
		);
	}
	echo '</ul>';
}
if ( $salt_copy ) {
	echo '<span class="salt-copy-link"><button class="salt-copy-link__button" type="button" data-ready="false">' . esc_html( (string) ( $salt_labels['copy'] ?? '' ) ) . '</button><span class="salt-copy-link__status" role="status" aria-live="polite"></span></span>';
}
echo '</div>';
