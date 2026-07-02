<?php
/**
 * Section: Services (spec §10 #4) — CPT-backed service cards.
 *
 * Query funnel (§10a): salt_resolve_query( salt_query_controls_from_row(), 'service' ).
 * DATA04: the default resolution is menu_order ASC, ALL published services —
 * exact parity with the legacy services_overview hardcoded query.
 * Teaser rule (§6): never paginates — editors bound the count via the query
 * controls; the optional "view all" link points at the public /services/ archive.
 *
 * States (COMPL02): 0 valid items => renders NOTHING (skip before the wrapper);
 * 1 item => valid static single-card layout (no JS anywhere in this section);
 * N => grid / list / featured. Malformed items (empty title) are skipped and
 * logged. Schema: one Service node per rendered service via salt_schema_add().
 *
 * @package Salt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! salt_module_enabled( 'service' ) ) {
	return; // §10b layer 2: module off => skip render entirely.
}

$salt_sub = static function ( string $field ): string {
	if ( ! function_exists( 'get_sub_field' ) ) {
		return '';
	}
	$value = get_sub_field( $field );
	return is_string( $value ) ? $value : '';
};

$salt_layout = $salt_sub( 'layout' );
if ( ! in_array( $salt_layout, array( 'grid', 'list', 'featured' ), true ) ) {
	$salt_layout = 'grid';
}

$salt_columns = $salt_sub( 'columns' );
if ( ! in_array( $salt_columns, array( '2', '3', '4' ), true ) ) {
	$salt_columns = '3';
}

$salt_eyebrow  = $salt_sub( 'eyebrow' );
$salt_heading  = $salt_sub( 'heading' );
$salt_view_all = function_exists( 'get_sub_field' ) ? (bool) get_sub_field( 'view_all' ) : false;

$salt_items = salt_resolve_query( salt_query_controls_from_row(), 'service' );

// Normalise to card data, skipping malformed rows (logged — never a blank card).
$salt_cards = array();
foreach ( $salt_items as $salt_item ) {
	$salt_title = trim( (string) $salt_item->post_title );
	if ( '' === $salt_title ) {
		salt_log( sprintf( 'services section: service %d has an empty title — skipped (broken row).', (int) $salt_item->ID ) );
		continue;
	}

	$salt_summary = get_field( 'summary', $salt_item->ID );
	$salt_icon    = get_field( 'icon', $salt_item->ID );

	$salt_cards[] = array(
		'title'   => $salt_title,
		'url'     => (string) get_the_permalink( $salt_item->ID ),
		'summary' => is_string( $salt_summary ) ? $salt_summary : '',
		'icon'    => is_string( $salt_icon ) ? $salt_icon : '',
	);
}

if ( array() === $salt_cards ) {
	return; // 0-state: skip the section entirely (COMPL02).
}

// One Service node per rendered service (§15) — assembled by the graph builder.
foreach ( $salt_cards as $salt_card ) {
	$salt_node = array(
		'@type' => 'Service',
		'name'  => $salt_card['title'],
	);
	if ( '' !== $salt_card['url'] ) {
		$salt_node['url'] = $salt_card['url'];
	}
	if ( '' !== $salt_card['summary'] ) {
		$salt_node['description'] = $salt_card['summary'];
	}
	salt_schema_add( $salt_node );
}

salt_section(
	array_merge(
		salt_section_wrapper_args( 'services', $args ?? array() ),
		// A section with no heading must not claim the page H1 (A11Y01).
		array( 'has_heading' => '' !== trim( $salt_heading ) )
	),
	static function ( int $level ) use ( $salt_layout, $salt_columns, $salt_eyebrow, $salt_heading, $salt_view_all, $salt_cards ): void {
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
					'text'    => $salt_heading,
					'level'   => $level,
					'classes' => 'mb-6',
				)
			);
		}

		// Card titles step one level below the section heading (A11Y01).
		$card_level = min( 6, $level + 1 );

		$card = static function ( array $data, string $classes = '' ) use ( $card_level ): void {
			salt_component(
				'card-service',
				array(
					'title'   => $data['title'],
					'url'     => $data['url'],
					'summary' => $data['summary'],
					'icon'    => $data['icon'],
					'level'   => $card_level,
					'classes' => $classes,
				)
			);
		};

		$column_classes = array(
			'2' => 'sm:grid-cols-2',
			'3' => 'sm:grid-cols-2 lg:grid-cols-3',
			'4' => 'sm:grid-cols-2 lg:grid-cols-4',
		);

		if ( 'list' === $salt_layout ) {
			echo '<div class="x-services-list grid gap-6">';
			foreach ( $salt_cards as $data ) {
				$card( $data );
			}
			echo '</div>';
		} elseif ( 'featured' === $salt_layout ) {
			// First service leads; the remainder (if any) fall into the grid —
			// a single item is a valid static featured card (COMPL02).
			echo '<div class="x-services-featured grid gap-6">';
			$card( $salt_cards[0], 'x-card-service-featured bg-brand-tint' );
			$rest = array_slice( $salt_cards, 1 );
			if ( array() !== $rest ) {
				printf( '<div class="grid gap-6 %s">', esc_attr( $column_classes[ $salt_columns ] ) );
				foreach ( $rest as $data ) {
					$card( $data );
				}
				echo '</div>';
			}
			echo '</div>';
		} else {
			printf( '<div class="x-services-grid grid gap-6 %s">', esc_attr( $column_classes[ $salt_columns ] ) );
			foreach ( $salt_cards as $data ) {
				$card( $data );
			}
			echo '</div>';
		}

		if ( $salt_view_all ) {
			$archive_url = get_post_type_archive_link( 'service' );
			if ( is_string( $archive_url ) && '' !== $archive_url ) {
				echo '<div class="mt-8">';
				salt_component(
					'button',
					array(
						'label'   => __( 'View all services', 'salt' ),
						'url'     => $archive_url,
						'style'   => 'link-arrow',
						'classes' => 'px-0',
					)
				);
				echo '</div>';
			}
		}
	}
);
