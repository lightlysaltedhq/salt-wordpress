<?php
/**
 * Resolver: Services (spec §10 #4) — the logic half of the section; the
 * view is template-parts/sections/services.php. CPT-backed service cards.
 *
 * Query funnel (§10a): salt_resolve_query( salt_query_controls_from_row(), 'service' ).
 * DATA04: the default resolution is menu_order ASC, ALL published services —
 * exact parity with the legacy services_overview hardcoded query.
 * Teaser rule (§6): never paginates — editors bound the count via the query
 * controls; the optional "view all" link points at the public /services/ archive.
 *
 * States (COMPL02): module off or 0 valid items => null (renders NOTHING);
 * 1 item => valid static single-card layout (no JS anywhere in this section);
 * N => grid / list / featured. Malformed items (empty title) are skipped and
 * logged. Schema: one Service node per rendered service via salt_schema_add().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current services Flexible Content row to its view props.
 *
 * Props. The contract folds this layout into collection-showcase
 * (view-props/collection-showcase.json) in a LATER work item; until then
 * only the identical-meaning props carry the contract's names:
 *   eyebrow       string|null  Contract `eyebrow`. Null when blank.
 *   heading       string|null  Contract `heading`. Null when blank.
 *   layout        string       Contract `layout` (this section uses grid |
 *                              list | featured; defaults to grid).
 *   columns       string       Contract `columns` ('2'|'3'|'4'; defaults to '3').
 *   items         array<int, array{title: string, url: string, summary: string, icon: string}>
 *                              WordPress-local (contract `cards`): the
 *                              service cards in query order; never empty.
 *   view_all_url  string|null  WordPress-local (contract `viewAll`, a link):
 *                              the /services/ archive URL when "view all" is
 *                              on and the archive exists; null otherwise.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_services( array $context ): ?array {
	if ( ! salt_module_enabled( 'service' ) ) {
		return null; // §10b layer 2: module off => skip render entirely.
	}

	$sub = static function ( string $field ): string {
		if ( ! function_exists( 'get_sub_field' ) ) {
			return '';
		}
		$value = get_sub_field( $field );
		return is_string( $value ) ? $value : '';
	};

	$layout = $sub( 'layout' );
	if ( ! in_array( $layout, array( 'grid', 'list', 'featured' ), true ) ) {
		$layout = 'grid';
	}

	$columns = $sub( 'columns' );
	if ( ! in_array( $columns, array( '2', '3', '4' ), true ) ) {
		$columns = '3';
	}

	$eyebrow  = $sub( 'eyebrow' );
	$heading  = $sub( 'heading' );
	$view_all = function_exists( 'get_sub_field' ) ? (bool) get_sub_field( 'view_all' ) : false;

	$posts = salt_resolve_query( salt_query_controls_from_row(), 'service' );

	// Normalise to card data, skipping malformed rows (logged — never a blank card).
	$cards = array();
	foreach ( $posts as $post_item ) {
		$title = trim( (string) $post_item->post_title );
		if ( '' === $title ) {
			salt_log( sprintf( 'services section: service %d has an empty title — skipped (broken row).', (int) $post_item->ID ) );
			continue;
		}

		$summary = get_field( 'summary', $post_item->ID );
		$icon    = get_field( 'icon', $post_item->ID );

		$cards[] = array(
			'title'   => $title,
			'url'     => (string) get_the_permalink( $post_item->ID ),
			'summary' => is_string( $summary ) ? $summary : '',
			'icon'    => is_string( $icon ) ? $icon : '',
		);
	}

	if ( array() === $cards ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	// One Service node per rendered service (§15) — assembled by the graph builder.
	foreach ( $cards as $card ) {
		$node = array(
			'@type' => 'Service',
			'name'  => $card['title'],
		);
		if ( '' !== $card['url'] ) {
			// Stable entity @id (SEO01): identical services dedupe across
			// sections and merge with Review.itemReviewed references.
			$node['@id'] = $card['url'] . '#service';
			$node['url'] = $card['url'];
		}
		if ( '' !== $card['summary'] ) {
			$node['description'] = $card['summary'];
		}
		salt_schema_add( $node );
	}

	// The "view all" link: resolved only when the option is on, as before.
	$view_all_url = null;
	if ( $view_all ) {
		$archive_url = get_post_type_archive_link( 'service' );
		if ( is_string( $archive_url ) && '' !== $archive_url ) {
			$view_all_url = $archive_url;
		}
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'services', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'eyebrow'      => salt_nullable_text( $eyebrow ),
			'heading'      => salt_nullable_text( $heading ),
			'layout'       => $layout,
			'columns'      => $columns,
			'items'        => $cards,
			'view_all_url' => $view_all_url,
		),
	);
}
