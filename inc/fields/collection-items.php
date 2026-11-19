<?php
/**
 * Mixed ordered ID pickers. The sibling source determines the ID namespace.
 *
 * @package Salt
 */

defined( 'ABSPATH' ) || exit;

/** Labels for a source, optionally restricted to saved IDs, or a page of search results. */
function salt_item_choices( string $source, array $field, array $ids = array(), string $search = '', int $page = 1 ): array {
	if ( ! salt_collection_source_installed( $source ) ) {
		return array();
	}
	if ( 'team' === $source && ! empty( $field['salt']['userTick'] ) ) {
		$args = array(
			'meta_key'   => 'showOnWebsite',
			'meta_value' => '1',
			'orderby'    => 'display_name',
			'order'      => 'ASC',
			'number'     => $ids ? count( $ids ) : 20,
			'paged'      => $page,
		);
		if ( $ids ) {
			$args['include'] = $ids;
		} elseif ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'display_name' );
		}
		return wp_list_pluck( get_users( $args ), 'display_name', 'ID' );
	}
	$type = $field['salt']['postSources'][ $source ] ?? null;
	if ( ! $type ) {
		return array();
	}
	$args = array(
		'post_type'   => $type,
		'post_status' => 'publish',
		'numberposts' => $ids ? count( $ids ) : 20,
		'paged'       => $page,
		'orderby'     => 'title',
		'order'       => 'ASC',
		's'           => $search,
	);
	if ( $ids ) {
		$args['post__in'] = $ids;
	}
	return wp_list_pluck( get_posts( $args ), 'post_title', 'ID' );
}

add_filter(
	'acf/fields/select/query',
	static function ( $results, $options ) {
		$field = acf_get_field( $options['field_key'] );
		if ( ! isset( $field['salt']['postSources'] ) ) {
			return $results;
		}
		$source = sanitize_key( wp_unslash( $_POST['salt_source'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACF checks its AJAX nonce.
		$labels = salt_item_choices( $source, $field, array(), (string) $options['s'], max( 1, (int) $options['paged'] ) );
		return array(
			'results' => array_map(
				static fn( $id, $name ) => array(
					'id'   => $id,
					'text' => $name,
				),
				array_keys( $labels ),
				array_values( $labels )
			),
			'more'    => 20 === count( $labels ),
		);
	},
	10,
	2
);

/** Restore selected labels from this row's source without loading whole collections. */
add_filter(
	'acf/prepare_field',
	static function ( $field ) {
		static $sources = array();
		if ( ! is_array( $field ) ) {
			return $field;
		}
		$prefix = $field['prefix'] ?? '';
		if ( preg_match( '/^field_salt_(carousel|collection_showcase)_source$/D', $field['key'] ?? '' ) ) {
			$sources[ $prefix ] = (string) $field['value'];
		} elseif ( isset( $sources[ $prefix ] ) && 'group' === $field['type'] ) {
			foreach ( $field['sub_fields'] as &$sub ) {
				$ids = (array) ( $field['value'][ $sub['key'] ] ?? array() );
				if ( ! empty( $sub['salt']['postSources'] ) && $ids ) {
					$sub['choices'] = salt_item_choices( $sources[ $prefix ], $sub, array_map( 'absint', $ids ) );
				}
			}
		}
		return $field;
	}
);

add_filter(
	'acf/validate_value/type=select',
	static function ( $valid, $value, $field, $input ) {
		if ( true !== $valid || empty( $field['salt']['postSources'] ) || ! $value ) {
			return $valid;
		}
		$source = salt_query_submitted_sibling( $input, salt_query_source_key( $field['key'] ) );
		$ids    = array_map( 'absint', (array) $value );
		return is_string( $source ) && ! array_diff( $ids, array_keys( salt_item_choices( $source, $field, $ids ) ) ) ? true : __( 'Choose items from the selected source.', 'salt' );
	},
	10,
	4
);
