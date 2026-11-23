<?php
/**
 * The author box, as the contract draws it (salt-contract rc.8
 * markup/author-box.json): an aside named by labels.author, the author's
 * photo (thumb slot, lazy) when they have one, then the "Written by" label,
 * the name (linked when the author has an archive), the role and the bio.
 * Not drawn without a name. A view: it draws its args and nothing else.
 *
 * ARGS:
 *   name   string      The author's name (required).
 *   href   string      The author's archive, or '' for none.
 *   role   string      The role, or ''.
 *   bio    string      The bio, or ''.
 *   photo  array|null  The photo as a contract image (salt_image_contract(),
 *                      thumb slot), or null.
 *   labels array       labels.author, labels.writtenBy.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$salt_args   = $args ?? array();
$salt_name   = trim( (string) ( $salt_args['name'] ?? '' ) );
$salt_labels = (array) ( $salt_args['labels'] ?? array() );
if ( '' === $salt_name ) {
	return;
}
$salt_href = (string) ( $salt_args['href'] ?? '' );
$salt_role = trim( (string) ( $salt_args['role'] ?? '' ) );
$salt_bio  = trim( (string) ( $salt_args['bio'] ?? '' ) );

echo '<aside class="salt-author-box" aria-label="' . esc_attr( (string) ( $salt_labels['author'] ?? '' ) ) . '">';
if ( is_array( $salt_args['photo'] ?? null ) ) {
	echo '<div class="salt-author-box__media">' . salt_image_contract_html( $salt_args['photo'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute by attribute.
}
echo '<div class="salt-author-box__body">';
echo '<p class="salt-author-box__label">' . esc_html( (string) ( $salt_labels['writtenBy'] ?? '' ) ) . '</p>';
echo '<p class="salt-author-box__name">' . ( '' !== $salt_href ? '<a href="' . esc_url( $salt_href ) . '">' . esc_html( $salt_name ) . '</a>' : esc_html( $salt_name ) ) . '</p>';
if ( '' !== $salt_role ) {
	echo '<p class="salt-author-box__role">' . esc_html( $salt_role ) . '</p>';
}
if ( '' !== $salt_bio ) {
	echo '<p class="salt-author-box__bio">' . esc_html( $salt_bio ) . '</p>';
}
echo '</div></aside>';
