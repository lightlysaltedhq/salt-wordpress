<?php
/**
 * Resolver: Blog Teaser (spec §10 #17) — the logic half of the section;
 * the view is template-parts/sections/blog-teaser.php. CPT-backed ✦ (§10a),
 * core 'post'.
 *
 * Fields: eyebrow, heading, columns (2|3), view_all + the shared query
 * controls (salt_query_controls_from_row() → salt_resolve_query('post'):
 * manual → controls → frozen default date DESC, 3 newest).
 *
 * STATES (COMPL02): 0 items ⇒ null (renders NOTHING);
 * 1 item ⇒ valid static single-column card, no slider/JS; N ⇒ static grid.
 * This section ships zero JavaScript at any count. Items with an empty
 * title are malformed — skipped + salt_log()ged, never a broken card.
 *
 * TEASER RULE (§6): never paginates — a bounded count (query control,
 * default 3, max 24) plus an optional "View all" link to the posts page
 * (get_option('page_for_posts'), falling back to home_url('/')).
 *
 * Core 'post' is always available — no module guard applies (§10b).
 * Schema: none (§10 table '—').
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current blog_teaser Flexible Content row to its view props.
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
 *   items         array<int, array{title: string, url: string, date: string, datetime: string, excerpt: string, image_id: int}>
 *                              WordPress-local (contract `cards`): the
 *                              posts in query order; never empty. title is
 *                              the stored post title, untrimmed; date is
 *                              display-formatted and datetime ISO 8601
 *                              (both '' when the date fails to resolve);
 *                              image_id is the featured image's attachment
 *                              ID, 0 for none.
 *   view_all_url  string|null  WordPress-local (contract `viewAll`, a link):
 *                              the posts page URL (else the home URL) when
 *                              "view all" is on; null when it is off.
 *
 * @param array<string,mixed> $context Loop context: index, is_primary.
 * @return array{section: array<string,mixed>, props: array<string,mixed>}|null Null = 0-state.
 */
function salt_resolve_section_blog_teaser( array $context ): ?array {
	$posts = salt_resolve_query( salt_query_controls_from_row(), 'post' );

	// Malformed rows (no title) are skipped + logged — never a broken card.
	$valid = array();
	foreach ( $posts as $post_item ) {
		if ( '' === trim( (string) $post_item->post_title ) ) {
			salt_log( sprintf( 'section blog-teaser: post %d has no title — skipped (malformed row).', (int) $post_item->ID ) );
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
			salt_log( sprintf( 'section blog-teaser: invalid columns "%d" — using 3.', $columns ) );
		}
		$columns = 3;
	}

	$items = array();
	foreach ( $valid as $post_item ) {
		// ISO 8601 for the <time datetime> attribute; display uses the
		// site date_format. Both empty when the date fails to resolve.
		$datetime = get_the_date( 'c', $post_item );
		$date     = get_the_date( '', $post_item );

		$items[] = array(
			'title'    => (string) $post_item->post_title,
			'url'      => (string) get_the_permalink( $post_item ),
			'date'     => is_string( $date ) ? $date : '',
			'datetime' => is_string( $datetime ) ? $datetime : '',
			'excerpt'  => (string) get_the_excerpt( $post_item ),
			'image_id' => (int) get_post_thumbnail_id( $post_item ),
		);
	}

	$view_all_url = null;
	if ( $view_all ) {
		// Teaser rule (§6): no pagination — link out to the posts page.
		$posts_page   = (int) get_option( 'page_for_posts' );
		$view_all_url = $posts_page > 0 ? (string) get_permalink( $posts_page ) : '';
		if ( '' === $view_all_url ) {
			$view_all_url = home_url( '/' );
		}
	}

	return array(
		'section' => array_merge(
			salt_section_wrapper_args( 'blog-teaser', $context ),
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
