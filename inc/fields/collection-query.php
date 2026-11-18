<?php
/**
 * The editor side of a collection query whose source is a select on the
 * layout (salt-contract collection-showcase and carousel, rc.7; EP-72 M3
 * unit 8). Two things the emitted fields leave to the site
 * (contract README, "Categories"; the query's WordPress owes):
 *
 *  - ACF cannot hide one mode choice per source, so the mode field records
 *    in `salt.modeRequires` which sources by-category needs. A save with
 *    mode by-category while the source select holds another source is
 *    refused here (acf/validate_value).
 *  - With several sources, the categories picker is a multiple select of
 *    term IDs (`salt.taxonomies`, `salt.categoriesFor`) whose choices the
 *    site fills from the taxonomy of the chosen source: every listed
 *    taxonomy's terms are the field's choices (so a saved term shows and
 *    validates), and its search offers only the chosen source's
 *    (admin/collection-query.js sends the source with each search).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The source select's field key beside a query's sub-field: the query
 * group sits beside the source select on the layout, so
 * field_salt_<layout>_query_<part> is beside field_salt_<layout>_source
 * (the emitter's DATA02 keys; `salt.sourceField` is `source` on both
 * sections that have one).
 *
 * @param string $key A query sub-field's key.
 * @return string|null
 */
function salt_query_source_key( string $key ): ?string {
	return 1 === preg_match( '/^(field_salt_[a-z0-9_]+)_query_[a-z0-9_]+$/', $key, $match ) ? $match[1] . '_source' : null;
}

/**
 * The value a sibling field was submitted with, found from a field's ACF
 * input name: acf[...][<group key>][<field key>] becomes
 * acf[...][<sibling key>], one level up.
 *
 * @param string $input       The validated field's input name.
 * @param string $sibling_key The sibling's key.
 * @return mixed Null when it was not submitted.
 */
function salt_query_submitted_sibling( string $input, string $sibling_key ) {
	if ( preg_match_all( '/\[([^\]]*)\]/', $input, $parts ) < 2 ) {
		return null;
	}
	$path = $parts[1];
	array_splice( $path, -2, 2, array( $sibling_key ) );
	// ACF verified the nonce before validating; the value is only compared with a fixed list.
	$value = isset( $_POST['acf'] ) ? wp_unslash( $_POST['acf'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	foreach ( $path as $step ) {
		if ( ! is_array( $value ) || ! array_key_exists( $step, $value ) ) {
			return null;
		}
		$value = $value[ $step ];
	}
	return $value;
}

/**
 * Refuse mode by-category while the source select holds a source without
 * categories (`salt.modeRequires`, SC-010) ('acf/validate_value').
 *
 * @param bool|string         $valid Whether ACF found it valid, or its message.
 * @param mixed               $value The submitted mode.
 * @param array<string,mixed> $field The field.
 * @param string              $input The field's input name.
 * @return bool|string
 */
function salt_validate_query_mode( $valid, $value, $field, $input = '' ) {
	if ( true !== $valid || ! is_array( $field ) || ! is_array( $field['salt']['modeRequires'] ?? null ) || ! is_string( $value ) ) {
		return $valid;
	}
	$needs = $field['salt']['modeRequires'][ $value ] ?? null;
	$key   = salt_query_source_key( (string) ( $field['key'] ?? '' ) );
	if ( ! is_array( $needs ) || null === $key ) {
		return $valid;
	}
	$source = salt_query_submitted_sibling( (string) $input, $key );
	if ( ! is_string( $source ) || in_array( $source, $needs, true ) ) {
		return $valid;
	}
	return sprintf(
		/* translators: %s: the sources the mode needs, e.g. "services, case studies and posts". */
		__( 'By category works only for %s: the collections that have categories. Choose another mode, or show one of those.', 'salt' ),
		salt_query_source_list( $needs )
	);
}

/**
 * Sources as a phrase for a message: "services, case studies and posts".
 *
 * @param string[] $sources Contract source ids.
 * @return string
 */
function salt_query_source_list( array $sources ): string {
	$names = array(
		'services'     => __( 'services', 'salt' ),
		'case-studies' => __( 'case studies', 'salt' ),
		'testimonials' => __( 'testimonials', 'salt' ),
		'posts'        => __( 'posts', 'salt' ),
		'team'         => __( 'team', 'salt' ),
	);
	$list  = array_map( static fn( $source ): string => $names[ $source ] ?? (string) $source, array_values( $sources ) );
	$last  = array_pop( $list );
	return array() === $list ? (string) $last : sprintf(
		/* translators: 1: a comma-separated list, 2: its last item. */
		__( '%1$s and %2$s', 'salt' ),
		implode( ', ', $list ),
		$last
	);
}
add_filter( 'acf/validate_value', 'salt_validate_query_mode', 10, 4 );

/**
 * A categories picker's terms: every term of the given taxonomies, keyed
 * by term ID, labelled by its name.
 *
 * @param string[] $taxonomies Taxonomies.
 * @return array<string,string>
 */
function salt_query_category_choices( array $taxonomies ): array {
	$choices = array();
	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => (string) $taxonomy,
				'hide_empty' => false,
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$choices[ (string) $term->term_id ] = (string) $term->name;
		}
	}
	return $choices;
}

/**
 * Fill a several-source categories picker's choices from its taxonomies
 * ('acf/load_field/type=select').
 *
 * @param array<string,mixed> $field The field.
 * @return array<string,mixed>
 */
function salt_query_categories_field( $field ) {
	if ( is_array( $field ) && is_array( $field['salt']['taxonomies'] ?? null ) ) {
		$field['choices'] = salt_query_category_choices( $field['salt']['taxonomies'] );
	}
	return $field;
}
add_filter( 'acf/load_field/type=select', 'salt_query_categories_field' );

/**
 * A categories picker's search: the terms of the chosen source's taxonomy
 * only (admin/collection-query.js posts the source as `salt_source`)
 * ('acf/fields/select/query').
 *
 * @param array<string,mixed> $results ACF's shortcut results (empty).
 * @param array<string,mixed> $options The request: field_key, s.
 * @return array<string,mixed>
 */
function salt_query_categories_search( $results, $options ) {
	$field = function_exists( 'acf_get_field' ) ? acf_get_field( (string) ( $options['field_key'] ?? '' ) ) : null;
	if ( ! is_array( $field ) || ! is_array( $field['salt']['categoriesFor'] ?? null ) ) {
		return $results;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACF verified the field's nonce before this filter runs.
	$source   = isset( $_POST['salt_source'] ) ? sanitize_key( wp_unslash( $_POST['salt_source'] ) ) : '';
	$known    = salt_collection_sources()[ $source ] ?? null;
	$taxonomy = null !== $known && in_array( $source, $field['salt']['categoriesFor'], true ) ? $known['taxonomy'] : null;
	$search   = strtolower( (string) ( $options['s'] ?? '' ) );
	$out      = array();
	foreach ( null === $taxonomy ? array() : salt_query_category_choices( array( $taxonomy ) ) as $id => $name ) {
		if ( '' === $search || str_contains( strtolower( $name ), $search ) ) {
			$out[] = array(
				'id'   => $id,
				'text' => $name,
			);
		}
	}
	return array(
		'results' => $out,
		'limit'   => count( $out ),
	);
}
add_filter( 'acf/fields/select/query', 'salt_query_categories_search', 10, 2 );

/**
 * Load the script that sends the chosen source with a categories search
 * ('acf/input/admin_enqueue_scripts').
 *
 * @return void
 */
function salt_collection_query_enqueue(): void {
	$src = salt_asset_optional( 'admin/collection-query.js' );
	if ( null === $src ) {
		salt_log( 'salt_collection_query: no manifest entry for "admin/collection-query.js" — a categories search offers no terms. Re-run the asset build.' );
		return;
	}
	wp_enqueue_script(
		'salt-collection-query',
		$src,
		array( 'acf-input' ),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Content hash in the dist filename is the cache-buster (ARCH03).
		array( 'in_footer' => true )
	);
}
add_action( 'acf/input/admin_enqueue_scripts', 'salt_collection_query_enqueue' );
