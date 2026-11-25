<?php
/**
 * Resolver: Listing (salt-contract 1.0.0). Cards and page addresses belong
 * to the current route's main query, never an editor-configured query.
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current row against the route without advancing its loop.
 * A singular page has no listing; an empty archive still has a message.
 * The native archive composition supplies its fixed heading, anchor and
 * lazy-image plan (markup/archive.json) instead of an ACF row.
 *
 * @param array<string,mixed> $context Page plan context; archive true for the native archive composition.
 * @return array|null Wrapper, view props and priority image request.
 */
function salt_resolve_section_listing( array $context ): ?array {
	global $wp_query;
	$archive = ! empty( $context['archive'] );
	if ( ! $archive && ! is_home() && ! is_archive() && ! is_search() ) {
		return null;
	}
	$sub      = static fn( string $field ): string => ! $archive && function_exists( 'get_sub_field' ) && is_string( get_sub_field( $field ) ) ? (string) get_sub_field( $field ) : '';
	$heading  = $archive ? __( 'Latest posts', 'salt' ) : salt_nullable_text( $sub( 'heading' ) );
	$settings = salt_section_contract_args( 'listing', $context );
	if ( $archive ) {
		$settings['anchor'] = 'posts';
	}
	$sizes = salt_image_slot_sizes( 'card', $settings['container'], '3' );
	$cards = array();
	foreach ( $wp_query->posts as $post ) {
		$card = salt_contract_view_card( $post, 'posts', $sizes );
		if ( null !== $card ) {
			$cards[] = $card;
		}
	}
	$page  = max( 1, (int) get_query_var( 'paged' ) );
	$total = max( 1, (int) $wp_query->max_num_pages );
	$pages = array();
	for ( $number = 1; $number <= $total; ++$number ) {
		$pages[] = array(
			'number' => $number,
			'href'   => salt_contract_url( (string) get_pagenum_link( $number ) ),
		);
	}
	return array(
		'section' => array_merge( $settings, array( 'has_heading' => null !== $heading ) ),
		'props'   => array(
			'eyebrow'    => salt_nullable_text( $sub( 'eyebrow' ) ),
			'heading'    => $heading,
			'intro'      => salt_nullable_text( $sub( 'intro' ) ),
			'page'       => $page,
			'totalPages' => $total,
			'cards'      => $cards,
			'pages'      => $pages,
			'labels'     => salt_contract_labels( 'archive' ),
			'arrow'      => salt_contract_arrow(),
		),
		'plan'    => array( 'priorityMedia' => ! $archive && salt_section_plan_priority( $context ) && is_array( $cards[0]['image'] ?? null ) ),
	);
}
