<?php
/**
 * Section: Portfolio / Work (spec §10 #15) — CPT-backed ✦ (§10a).
 *
 * Fields: eyebrow, heading, columns (2|3), view_all + the shared query
 * controls (salt_query_controls_from_row() → salt_resolve_query('work'):
 * manual → controls → frozen default date DESC, 6).
 *
 * STATES (COMPL02): 0 items ⇒ renders NOTHING (skip before salt_section);
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

if ( ! salt_module_enabled( 'work' ) ) {
	return;
}

$salt_items = salt_resolve_query( salt_query_controls_from_row(), 'work' );

// Malformed rows (no title) are skipped + logged — never a broken card.
$salt_valid = array();
foreach ( $salt_items as $salt_item ) {
	if ( '' === trim( (string) $salt_item->post_title ) ) {
		salt_log( sprintf( 'section work: work post %d has no title — skipped (malformed row).', (int) $salt_item->ID ) );
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
		salt_log( sprintf( 'section work: invalid columns "%d" — using 3.', $salt_columns ) );
	}
	$salt_columns = 3;
}

// Queue one CreativeWork node per rendered item (§15; SEO01/02/03 live in
// the graph builder — this section never prints JSON-LD itself).
foreach ( $salt_valid as $salt_item ) {
	$salt_node = array(
		'@type' => 'CreativeWork',
		'name'  => (string) $salt_item->post_title,
	);

	$salt_item_url = (string) get_the_permalink( $salt_item );
	if ( '' !== $salt_item_url ) {
		$salt_node['url'] = $salt_item_url;
	}

	$salt_client = (string) get_field( 'client', $salt_item->ID );
	if ( '' !== trim( $salt_client ) ) {
		$salt_node['about'] = array(
			'@type' => 'Organization',
			'name'  => $salt_client,
		);
	}

	salt_schema_add( $salt_node );
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
		salt_section_wrapper_args( 'work', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_valid, $salt_eyebrow, $salt_heading, $salt_view_all, $salt_grid_classes ): void {
		if ( '' !== trim( $salt_eyebrow ) || '' !== trim( $salt_heading ) ) {
			echo '<header class="x-work-header mb-10 max-w-prose">';
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

		printf( '<ul class="x-work-grid %s">', esc_attr( $salt_grid_classes ) );
		foreach ( $salt_valid as $salt_item ) {
			echo '<li class="x-work-grid-item flex">';
			salt_component(
				'card-work',
				array(
					'title'    => (string) $salt_item->post_title,
					'url'      => (string) get_the_permalink( $salt_item ),
					'client'   => (string) get_field( 'client', $salt_item->ID ),
					'summary'  => (string) get_field( 'summary', $salt_item->ID ),
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
			$salt_archive_url = get_post_type_archive_link( 'work' );
			if ( is_string( $salt_archive_url ) && '' !== $salt_archive_url ) {
				echo '<div class="x-work-view-all mt-10">';
				salt_component(
					'button',
					array(
						'label' => __( 'View all work', 'salt' ),
						'url'   => $salt_archive_url,
						'style' => 'link-arrow',
					)
				);
				echo '</div>';
			}
		}
	}
);
