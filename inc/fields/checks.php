<?php
/**
 * Checks on save that salt-contract asks of a generated field and ACF does
 * not make itself (rc.7 schema/field-definition.schema.json):
 *
 * - `salt.format: email` — "an email address (one @, a domain with a dot,
 *   no spaces), checked on save" (textField format);
 * - `salt.maxLines` — "the most lines the text may hold, checked on save"
 *   (textareaField maxLines; a location's street, two);
 * - a service's relatedServices refuses the service itself (services.json
 *   relatedServices owe).
 *
 * ACF runs these with its own validation, before the post is saved, and
 * shows the message on the field. ACF itself checks required fields, text
 * and textarea maxlength, number ranges and list and gallery limits.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a value is an email address as the contract describes one: one
 * @, a domain with a dot, no spaces.
 *
 * @param string $value The value.
 * @return bool
 */
function salt_is_contract_email( string $value ): bool {
	return 1 === preg_match( '/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $value );
}

/**
 * The number of lines a text holds: CRLF and LF both end a line, and
 * line breaks after the last text start no line.
 *
 * @param string $value The value.
 * @return int
 */
function salt_line_count( string $value ): int {
	$value = rtrim( str_replace( "\r\n", "\n", $value ), "\n" );
	return '' === $value ? 0 : substr_count( $value, "\n" ) + 1;
}

/**
 * Check a generated field's contract format and line limit
 * ('acf/validate_value').
 *
 * @param bool|string         $valid Whether ACF found it valid, or its message.
 * @param mixed               $value The submitted value.
 * @param array<string,mixed> $field The field.
 * @return bool|string
 */
function salt_validate_contract_value( $valid, $value, $field ) {
	if ( true !== $valid || ! is_string( $value ) || '' === trim( $value ) || ! is_array( $field ) ) {
		return $valid;
	}
	$value = wp_unslash( $value );
	if ( 'email' === ( $field['salt']['format'] ?? null ) && ! salt_is_contract_email( trim( $value ) ) ) {
		return __( 'Enter an email address: one @, a domain with a dot, and no spaces.', 'salt' );
	}
	$max_lines = (int) ( $field['salt']['maxLines'] ?? 0 );
	if ( $max_lines > 0 && salt_line_count( $value ) > $max_lines ) {
		return sprintf(
			/* translators: %d: the most lines the field may hold. */
			_n( 'Use at most %d line.', 'Use at most %d lines.', $max_lines, 'salt' ),
			$max_lines
		);
	}
	return $valid;
}
add_filter( 'acf/validate_value', 'salt_validate_contract_value', 10, 3 );

/**
 * Refuse a service listed among its own related services
 * ('acf/validate_value/key=field_salt_collection_services_related_services').
 *
 * @param bool|string $valid Whether ACF found it valid, or its message.
 * @param mixed       $value The picked service IDs.
 * @return bool|string
 */
function salt_validate_related_services( $valid, $value ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACF verified the nonce; the ID is only compared.
	$post_id = isset( $_POST['_acf_post_id'] ) ? (int) $_POST['_acf_post_id'] : 0;
	if ( true === $valid && $post_id > 0 && is_array( $value ) && in_array( $post_id, array_map( 'intval', $value ), true ) ) {
		return __( 'A service cannot be related to itself. Remove it from the list.', 'salt' );
	}
	return $valid;
}
add_filter( 'acf/validate_value/key=field_salt_collection_services_related_services', 'salt_validate_related_services', 10, 2 );
