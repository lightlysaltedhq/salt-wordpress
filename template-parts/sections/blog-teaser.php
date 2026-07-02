<?php
/**
 * Section: Blog Teaser (spec §10 #17) — CPT-backed ✦ (§10a), core 'post'.
 *
 * Fields: eyebrow, heading, columns (2|3), view_all + the shared query
 * controls (salt_query_controls_from_row() → salt_resolve_query('post'):
 * manual → controls → frozen default date DESC, 3 newest).
 *
 * STATES (COMPL02): 0 items ⇒ renders NOTHING (skip before salt_section);
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

$salt_items = salt_resolve_query( salt_query_controls_from_row(), 'post' );

// Malformed rows (no title) are skipped + logged — never a broken card.
$salt_valid = array();
foreach ( $salt_items as $salt_item ) {
	if ( '' === trim( (string) $salt_item->post_title ) ) {
		salt_log( sprintf( 'section blog-teaser: post %d has no title — skipped (malformed row).', (int) $salt_item->ID ) );
		continue;
	}
	$salt_valid[] = $salt_item;
}

if ( array() === $salt_valid ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

$salt_eyebrow  = (string) get_sub_field( 'eyebrow' );
$salt_heading  = (string) get_sub_field( 'heading' );
$salt_view_all = (bool) get_sub_field( 'view_all' );

$salt_columns = (int) get_sub_field( 'columns' );
if ( ! in_array( $salt_columns, array( 2, 3 ), true ) ) {
	if ( 0 !== $salt_columns ) {
		salt_log( sprintf( 'section blog-teaser: invalid columns "%d" — using 3.', $salt_columns ) );
	}
	$salt_columns = 3;
}

// 1-state renders a single-column static card; N caps at the chosen columns.
$salt_grid_classes = 'grid list-none gap-8 p-0';
if ( count( $salt_valid ) > 1 ) {
	$salt_grid_classes .= 2 === $salt_columns
		? ' sm:grid-cols-2'
		: ' sm:grid-cols-2 lg:grid-cols-3';
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'blog-teaser', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_valid, $salt_eyebrow, $salt_heading, $salt_view_all, $salt_grid_classes ): void {
		if ( '' !== trim( $salt_eyebrow ) || '' !== trim( $salt_heading ) ) {
			echo '<header class="x-blog-teaser-header mb-10 max-w-prose">';
			if ( '' !== trim( $salt_eyebrow ) ) {
				salt_component(
					'eyebrow',
					array(
						'text'    => $salt_eyebrow,
						'classes' => 'mb-2',
					)
				);
			}
			if ( '' !== trim( $salt_heading ) ) {
				salt_component(
					'heading',
					array(
						'text'  => $salt_heading,
						'level' => $level,
					)
				);
			}
			echo '</header>';
		}

		printf( '<ul class="x-blog-teaser-grid %s">', esc_attr( $salt_grid_classes ) );
		foreach ( $salt_valid as $salt_item ) {
			// ISO 8601 for the <time datetime> attribute; display uses the
			// site date_format. Both empty when the date fails to resolve.
			$salt_datetime = get_the_date( 'c', $salt_item );
			$salt_date     = get_the_date( '', $salt_item );

			echo '<li class="x-blog-teaser-grid-item flex">';
			salt_component(
				'card-post',
				array(
					'title'    => (string) $salt_item->post_title,
					'url'      => (string) get_the_permalink( $salt_item ),
					'date'     => is_string( $salt_date ) ? $salt_date : '',
					'datetime' => is_string( $salt_datetime ) ? $salt_datetime : '',
					'excerpt'  => (string) get_the_excerpt( $salt_item ),
					'image_id' => (int) get_post_thumbnail_id( $salt_item ),
					// Card titles step below the section heading (A11Y01).
					'level'    => $level + 1,
					'classes'  => 'w-full',
				)
			);
			echo '</li>';
		}
		echo '</ul>';

		if ( $salt_view_all ) {
			// Teaser rule (§6): no pagination — link out to the posts page.
			$salt_posts_page = (int) get_option( 'page_for_posts' );
			$salt_blog_url   = $salt_posts_page > 0 ? (string) get_permalink( $salt_posts_page ) : '';
			if ( '' === $salt_blog_url ) {
				$salt_blog_url = home_url( '/' );
			}
			echo '<div class="x-blog-teaser-view-all mt-10">';
			salt_component(
				'button',
				array(
					'label' => __( 'View all posts', 'salt' ),
					'url'   => $salt_blog_url,
					'style' => 'link-arrow',
				)
			);
			echo '</div>';
		}
	}
);
