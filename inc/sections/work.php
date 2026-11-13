<?php
/**
 * Resolver: Portfolio / Work (spec §10 #15) — the logic half of the
 * section; the view is template-parts/sections/work.php. CPT-backed ✦ (§10a).
 *
 * Fields: eyebrow, heading, columns (2|3), view_all + the shared query
 * controls (salt_query_controls_from_row() → salt_resolve_query('work'):
 * manual → controls → frozen default date DESC, 6).
 *
 * STATES (COMPL02): module off or 0 items ⇒ null (renders NOTHING);
 * 1 item ⇒ valid static single-column card, no slider/JS; N ⇒ static grid.
 * This section ships zero JavaScript at any count. Items with an empty
 * title are malformed — skipped + salt_log()ged, never a broken card.
 *
 * TEASER RULE (§6): never paginates — a bounded count (query control,
 * default 6, max 24) plus an optional "View all" link to the public
 * /work/ archive.
 *
 * DECISION (v1): the §10 'filterable' flag is NOT implemented — client-side
 * filtering is JS complexity with no no-JS story; taxonomy filtering
 * already exists via the shared query controls (editors scope the grid by
 * work_type), and the public archive covers browsing. Revisit post-v1.
 *
 * Schema: one CreativeWork node per rendered item via salt_schema_add()
 * (name, url; about ⇒ client when set). Never an ad-hoc script tag (SEO03).
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current work Flexible Content row to its view props.
 *
 * Props. The contract folds this layout into collection-showcase
 * (view-props/collection-showcase.json) in a LATER work item; until then
 * only the identical-meaning props carry the contract's names:
 *   eyebrow       string|null  Contract `eyebrow`. Null when blank.
 *   heading       string|null  Contract `heading`. Null when blank.
 *   columns       int          Contract `columns` by name, but an int 2|3
 *                              here (the contract's enum is the strings
 *                              '2'|'3'|'4'); invalid => 3. The view derives
 *                              the grid classes from it and the item count.
 *   items         array<int, array{title: string, url: string, client: string, summary: string, image_id: int}>
 *                              WordPress-local (contract `cards`): the
 *                              work items in query order; never empty.
 *                              title is the stored post title, untrimmed;
 *                              image_id is the featured image's attachment
 *                              ID, 0 for none.
 *   view_all_url  string|null  WordPress-local (contract `viewAll`, a link):
 *                              the /work/ archive URL when "view all" is on
 *                              and the archive exists; null otherwise.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_work( array $context ): ?array {
	if ( ! salt_module_enabled( 'work' ) ) {
		return null;
	}

	$posts = salt_resolve_query( salt_query_controls_from_row(), 'work' );

	// Malformed rows (no title) are skipped + logged — never a broken card.
	$valid = array();
	foreach ( $posts as $post_item ) {
		if ( '' === trim( (string) $post_item->post_title ) ) {
			salt_log( sprintf( 'section work: work post %d has no title — skipped (malformed row).', (int) $post_item->ID ) );
			continue;
		}
		$valid[] = $post_item;
	}

	if ( array() === $valid ) {
		return null; // 0-state: skip the section entirely (COMPL02).
	}

	$eyebrow  = (string) get_sub_field( 'eyebrow' );
	$heading  = (string) get_sub_field( 'heading' );
	$view_all = (bool) get_sub_field( 'view_all' );

	$columns = (int) get_sub_field( 'columns' );
	if ( ! in_array( $columns, array( 2, 3 ), true ) ) {
		if ( 0 !== $columns ) {
			salt_log( sprintf( 'section work: invalid columns "%d" — using 3.', $columns ) );
		}
		$columns = 3;
	}

	// One CreativeWork node per rendered item (§15; SEO01/02/03 live in the
	// graph builder — this section never prints JSON-LD itself), and the
	// card data the view draws.
	$items = array();
	foreach ( $valid as $post_item ) {
		$node = array(
			'@type' => 'CreativeWork',
			'name'  => (string) $post_item->post_title,
		);

		$item_url = (string) get_the_permalink( $post_item );
		if ( '' !== $item_url ) {
			$node['url'] = $item_url;
		}

		$client = (string) get_field( 'client', $post_item->ID );
		if ( '' !== trim( $client ) ) {
			$node['about'] = array(
				'@type' => 'Organization',
				'name'  => $client,
			);
		}

		salt_schema_add( $node );

		$items[] = array(
			'title'    => (string) $post_item->post_title,
			'url'      => $item_url,
			'client'   => $client,
			'summary'  => (string) get_field( 'summary', $post_item->ID ),
			'image_id' => (int) get_post_thumbnail_id( $post_item ),
		);
	}

	// The "view all" link: resolved only when the option is on, as before.
	$view_all_url = null;
	if ( $view_all ) {
		$archive_url = get_post_type_archive_link( 'work' );
		if ( is_string( $archive_url ) && '' !== $archive_url ) {
			$view_all_url = $archive_url;
		}
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'work', $context ),
			// A section with no heading must not claim the page H1 (A11Y01).
			array( 'has_heading' => '' !== trim( $heading ) )
		),
		'props'   => array(
			'eyebrow'      => salt_nullable_text( $eyebrow ),
			'heading'      => salt_nullable_text( $heading ),
			'columns'      => $columns,
			'items'        => $items,
			'view_all_url' => $view_all_url,
		),
	);
}
