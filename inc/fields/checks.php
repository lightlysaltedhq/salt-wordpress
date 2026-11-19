<?php
/**
 * Checks on save that salt-contract asks of a generated field and ACF does
 * not make itself (rc.8 schema/field-definition.schema.json):
 *
 * - `salt.format: email` — "an email address (one @, a domain with a dot,
 *   no spaces), checked on save" (textField format);
 * - `salt.format: url` — "an absolute address (http, https, mailto or tel)"
 *   (textField format), refused on save otherwise (the founder's ruling on
 *   PR #26). It covers socials.url, externalUrl, the features and logos
 *   items' url, and the url of every link: a link's url carries the format
 *   and is shown, so submitted, only when its type is external;
 * - `salt.maxLines` — "the most lines the text may hold, checked on save"
 *   (textareaField maxLines; a location's street, two);
 * - a service's relatedServices refuses the service itself (services.json
 *   relatedServices owe).
 *
 * Classic drafts bypass publication validation. On publish ACF shows each
 * message by its field. PHP writes also check retained limits before storage;
 * nothing here compares a text length or a list size with its target.
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
 * Whether a value is an absolute address as the contract's url format
 * describes one: http or https with a host, or mailto or tel with an
 * address. A path ("/contact"), an anchor ("#pricing"), a scheme-relative
 * address ("//x.example") and any other scheme are not.
 *
 * @param string $value The value.
 * @return bool
 */
function salt_is_contract_url( string $value ): bool {
	if ( preg_match( '/\s/', $value ) ) {
		return false;
	}
	return 1 === preg_match( '#^(?:https?://[^/?\#\s]+|(?:mailto|tel):\S)#i', $value );
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
	if ( 'url' === ( $field['salt']['format'] ?? null ) && ! salt_is_contract_url( trim( $value ) ) ) {
		return __( 'Enter a full address that starts with http://, https://, mailto: or tel:. A path such as /contact or an anchor such as #pricing is not accepted; link to a page on this site with an internal link instead.', 'salt' );
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
 * Original emitted definitions by key. ACF adds defaults (notably a number's
 * step) which are not contract limits. Never apply these rules to other groups.
 *
 * @return array
 */
function salt_limit_definitions(): array {
	static $definitions = null;
	if ( null === $definitions ) {
		$definitions = array();
		$index       = static function ( array $fields ) use ( &$index, &$definitions ): void {
			foreach ( $fields as $field ) {
				$definitions[ $field['key'] ] = $field;
				$index( $field['sub_fields'] ?? array() );
				foreach ( $field['layouts'] ?? array() as $layout ) {
					$index( $layout['sub_fields'] );
				}
			}
		};
		$index( salt_generated_sections_group()['fields'] ?? array() );
		foreach ( salt_generated_collection_groups() as $group ) {
			$index( $group['fields'] );
		}
	}
	return $definitions;
}

/**
 * Check only a retained limit, or (on publication) a time or numeric step.
 * A target is never consulted. Blank optional numbers are valid; zero is a number.
 *
 * @param array $field   Emitted definition.
 * @param mixed $value   Unformatted value.
 * @param bool  $publish Also check formats.
 * @return bool|string
 */
function salt_check_limit( array $field, $value, bool $publish ) {
	$type = $field['type'];
	if ( false === $value ) {
		$value = null; // ACF's unformatted read of an empty optional field.
	}
	if ( 'number' === $type && null !== $value && '' !== $value ) {
		if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) || $value < $field['min'] || $value > $field['max'] ) {
			/* translators: 1: lowest allowed number, 2: highest allowed number. */
			return sprintf( __( 'Enter a number from %1$s to %2$s.', 'salt' ), $field['min'], $field['max'] );
		}
		if ( $publish && isset( $field['step'] ) && 0.0 !== fmod( (float) $value - $field['min'], (float) $field['step'] ) ) {
			/* translators: %s: number step, for example 0.5. */
			return sprintf( __( 'Use increments of %s.', 'salt' ), $field['step'] );
		}
	}
	if ( in_array( $type, array( 'gallery', 'repeater' ), true ) ) {
		$list = is_array( $value ) ? $value : array();
		unset( $list['acfcloneindex'] );
		if ( isset( $field['min'] ) && count( $list ) < $field['min'] ) {
			/* translators: %d: smallest permitted item count. */
			return sprintf( _n( 'Add at least %d item.', 'Add at least %d items.', $field['min'], 'salt' ), $field['min'] );
		}
		if ( isset( $field['max'] ) && count( $list ) > $field['max'] ) {
			/* translators: %d: largest permitted item count. */
			return sprintf( __( 'Use at most %d items.', 'salt' ), $field['max'] );
		}
	}
	if ( $publish && 'time_picker' === $type && '' !== $value && null !== $value && ( ! is_string( $value ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d(?::00)?$/D', $value ) ) ) {
		return __( 'Enter a time in 24-hour HH:MM format.', 'salt' );
	}
	if ( ! empty( $field['salt']['maxLines'] ) ) {
		return salt_validate_contract_value( true, $value, $field );
	}
	return true;
}

/**
 * Add missing publish checks to ACF's normal validation.
 *
 * @param bool|string $valid Existing result.
 * @param mixed       $value Submitted value.
 * @param array       $field ACF field.
 * @return bool|string
 */
function salt_validate_published_limit( $valid, $value, $field ) {
	$definition = salt_limit_definitions()[ $field['key'] ] ?? null;
	if ( true === $valid && 'time_picker' === ( $definition['type'] ?? '' ) && is_string( $value ) && strlen( $value ) > 5 ) {
		// H:i:00 is a valid internal ACF representation, but the editor asks for HH:MM.
		return __( 'Enter a time in 24-hour HH:MM format.', 'salt' );
	}
	return true === $valid && $definition ? salt_check_limit( $definition, $value, true ) : $valid;
}
add_filter( 'acf/validate_value', 'salt_validate_published_limit', 10, 3 );

/**
 * Preflight the whole submitted tree BEFORE ACF writes the first sub-field:
 * ACF does not return a nested write's error to the importer. Both key and
 * name are supported by update_field(). Hidden clone rows are not values.
 *
 * @param array $field   Generated field.
 * @param mixed $value   Raw submitted value.
 * @param bool  $publish Check formats as well as hard limits.
 * @return true|WP_Error
 */
function salt_preflight_limits( array $field, $value, bool $publish = false ) {
	$message = salt_check_limit( $field, $value, $publish );
	if ( true === $message && $publish && ! empty( $field['salt']['format'] ) ) {
		$message = salt_validate_contract_value( true, $value, $field );
	}
	if ( true !== $message ) {
		return new WP_Error( 'salt_field_limit', $field['label'] . ': ' . $message );
	}
	if ( ! is_array( $value ) ) {
		return true;
	}
	unset( $value['acfcloneindex'] );
	$rows = 'group' === $field['type'] ? array( $value ) : $value;
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$children = $field['sub_fields'] ?? array();
		if ( 'flexible_content' === $field['type'] ) {
			$layouts  = array_column( $field['layouts'], 'sub_fields', 'name' );
			$children = $layouts[ $row['acf_fc_layout'] ?? '' ] ?? array();
		}
		foreach ( $children as $child ) {
			$key = array_key_exists( $child['key'], $row ) ? $child['key'] : $child['name'];
			if ( array_key_exists( $key, $row ) ) {
				if ( $publish && isset( $child['salt']['sourceField'] ) && is_array( $row[ $key ] ) ) {
					$source     = $row[ $child['salt']['sourceField'] ] ?? $row[ salt_query_source_key( $child['key'] . '_mode' ) ] ?? null;
					$query      = $row[ $key ];
					$mode       = $query['mode'] ?? $query[ $child['key'] . '_mode' ] ?? null;
					$categories = $query['categories'] ?? $query[ $child['key'] . '_categories' ] ?? array();
					$message    = salt_check_topic_source( $source, $mode, $categories );
					if ( true !== $message ) {
						return new WP_Error( 'salt_topic_source', $message );
					}
				}
				$result = salt_preflight_limits( $child, $row[ $key ], $publish );
				if ( true !== $result ) {
					return $result;
				}
			}
		}
	}
	return true;
}

/**
 * Refuse an outside ACF write with a message (even to an existing draft).
 * ACF clears its submitted-values flag for PHP API calls, even within a
 * save_post hook. Its row helpers ignore a pre_update_value return value and
 * return success; an exception reaches those callers as well as update_field().
 *
 * @param mixed $previous Previous short circuit, or null.
 * @param mixed $value    Raw value.
 * @param mixed $post_id  Object being saved.
 * @param array $field    ACF field.
 * @return mixed
 * @throws DomainException When the proposed value breaks a contract rule.
 */
function salt_check_outside_write( $previous, $value, $post_id, $field ) {
	if ( null !== $previous || ( doing_action( 'acf/save_post' ) && acf_get_data( 'acf_saving_submitted_values' ) ) ) {
		return $previous;
	}
	$definition = salt_limit_definitions()[ $field['key'] ] ?? null;
	if ( $definition ) {
		$result = salt_preflight_limits( $definition, $value, is_numeric( $post_id ) && 'publish' === get_post_status( $post_id ) );
		if ( is_wp_error( $result ) ) {
			throw new DomainException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- A PHP caller receives plain text, not HTML.
		}
	}
	return null;
}
add_filter( 'acf/pre_update_value', 'salt_check_outside_write', 10, 4 );

/**
 * Native status changes carry no ACF payload: validate what will go live.
 *
 * @param int $id Post ID.
 * @return true|WP_Error
 */
function salt_check_stored_publication( int $id ) {
	$type = get_post_type( $id );
	if ( ! function_exists( 'get_field' ) ) {
		return true;
	}
	$groups = 'page' === $type ? array( salt_generated_sections_group() ) : salt_generated_collection_groups();
	foreach ( $groups as $group ) {
		if ( ! $group || salt_collection_group_post_type( $group ) !== $type ) {
			continue;
		}
		foreach ( $group['fields'] as $field ) {
			if ( ! metadata_exists( 'post', $id, $field['name'] ) && ! isset( $field['min'] ) ) {
				continue;
			}
			$result = salt_preflight_limits( $field, get_field( $field['key'], $id, false ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
	}
	return true;
}

/**
 * The core post/page REST routes do not expose these ACF groups, but can
 * publish an existing draft. Check its stored layout before changing status.
 *
 * @param stdClass|WP_Error $prepared Prepared post.
 * @param WP_REST_Request   $request  Core REST request.
 * @return stdClass|WP_Error
 */
function salt_check_rest_publication( $prepared, $request ) {
	if ( is_wp_error( $prepared ) || empty( $request['id'] ) || ! in_array( $prepared->post_status ?? null, array( 'publish', 'future' ), true ) ) {
		return $prepared;
	}
	$result = salt_check_stored_publication( (int) $request['id'] );
	if ( is_wp_error( $result ) ) {
		$result->add_data( array( 'status' => 400 ) );
		return $result;
	}
	return $prepared;
}
add_filter( 'rest_pre_insert_page', 'salt_check_rest_publication', 10, 2 );
add_filter( 'rest_pre_insert_post', 'salt_check_rest_publication', 10, 2 );

/**
 * Quick Edit and PHP imports can publish a draft without opening its form.
 * The full editor instead validates the incoming values (which may correct
 * an invalid draft). Only skip this check for the same post's verified form.
 *
 * @param int   $id   Existing post.
 * @param array $data Core's new data.
 * @throws DomainException For a PHP caller attempting an invalid transition.
 */
function salt_check_native_publication( int $id, array $data ): void {
	if ( ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) || get_post_status( $id ) === $data['post_status'] ) {
		return;
	}
	if ( isset( $_POST['_acf_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_acf_nonce'] ) ), 'post' ) && isset( $_POST['_acf_post_id'] ) && absint( wp_unslash( $_POST['_acf_post_id'] ) ) === $id ) {
		return;
	}
	$result = salt_check_stored_publication( $id );
	if ( is_wp_error( $result ) ) {
		if ( is_admin() && ! defined( 'WP_CLI' ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', 400 );
		}
		throw new DomainException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text for the importer.
	}
}
add_action( 'pre_post_update', 'salt_check_native_publication', 10, 2 );

/**
 * Keep even an invalid draft time editable rather than letting the date
 * picker silently reformat it. ACF still reads valid stored times as H:i.
 *
 * @param array $field ACF field for rendering.
 * @return array
 */
function salt_edit_contract_time( array $field ): array {
	if ( isset( salt_limit_definitions()[ $field['key'] ] ) ) {
		$field['type']        = 'text';
		$field['prepend']     = '';
		$field['append']      = '';
		$field['placeholder'] = 'HH:MM';
		$field['value']       = preg_replace( '/^(\d\d:\d\d):00$/D', '$1', (string) $field['value'] );
	}
	return $field;
}
add_filter( 'acf/prepare_field/type=time_picker', 'salt_edit_contract_time' );

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
